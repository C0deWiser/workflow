<?php

namespace Codewiser\Workflow\Commands;

use Codewiser\Workflow\State;
use Codewiser\Workflow\StateCollection;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\TransitionCollection;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Console\Command;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;

class ValidateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:blueprint {--class=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate workflow blueprint.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $class = $this->option('class');

        if (!class_exists($class)) {
            $this->error("{$class} Not Found");
            return self::INVALID;
        }

        $blueprint = new $class();

        if (!($blueprint instanceof WorkflowBlueprint)) {
            $this->warn("{$class} Not a WorkflowBlueprint instance");
            return self::INVALID;
        }

        $this->validate($blueprint);

        return self::SUCCESS;
    }

    protected function validate(WorkflowBlueprint $blueprint)
    {
        $states = StateCollection::make($blueprint->states());

        $this->table(['Value', 'Caption', 'Additional', 'Error'], $this->validateStates($states));

        $transitions = TransitionCollection::make($blueprint->transitions());

        $this->table(['Source', 'Target', 'Caption', 'Issues', 'Auth', 'Rules', 'Additional', 'Errors'], $this->validateTransitions($transitions, $states));
    }

    protected function validateTransitions(TransitionCollection $transitions, StateCollection $states): array
    {
        return $transitions
            ->map(function (Transition $transition) use ($states) {
                $row = [
                    'source' => State::scalar($transition->source()),
                    'target' => State::scalar($transition->target()),
                    'caption' => $transition->caption(),
                    'prerequisites' => !is_null($transition->prerequisites()),
                    'authorization' => !is_null($transition->authorization()),
                    'rules' => $transition->validationRules(true),
                    'additional' => json_encode($transition->additional()),
                    'errors' => []
                ];

                try {
                    $states->one($transition->source);
                } catch (ItemNotFoundException) {
                    $row['errors'][] = 'Source Not Found';
                }
                try {
                    $states->one($transition->target);
                } catch (ItemNotFoundException) {
                    $row['errors'][] = 'Target Not Found';
                }

                $row['errors'] = implode(', ', $row['errors']);

                return $row;
            })
            ->toArray();
    }

    protected function validateStates(StateCollection $states): array
    {
        return $states
            ->map(function (State $state) use ($states) {
                $row = [
                    'value' => State::scalar($state->value),
                    'caption' => $state->caption(),
                    'additional' => json_encode($state->additional()),
                    'error' => null
                ];

                try {
                    $states->one($state);
                } catch (MultipleItemsFoundException) {
                    $row['error'] = "State {$row['value']} defined few times.";
                }

                return $row;
            })
            ->toArray();
    }
}
