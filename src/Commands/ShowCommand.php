<?php

namespace Codewiser\Workflow\Commands;

use Codewiser\Workflow\BlueprintValidator;
use Codewiser\Workflow\Commands\Traits\ClassDiscover;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateCollection;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\TransitionCollection;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ShowCommand extends Command
{
    use ClassDiscover;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:show {--class=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display workflow scheme';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $class = $this->option('class');
        $className = $this->classDiscover($this->option('class'));

        if (!$className) {
            $this->error("$class Not Found");
            return;
        }

        $this->info($className);
        $blueprint = new $className();

        if (!($blueprint instanceof WorkflowBlueprint)) {
            $this->warn("$class Not a WorkflowBlueprint instance");
            return;
        }

        $transitions = TransitionCollection::make($blueprint->transitions());
        $states = StateCollection::make($blueprint->states());

        $states
            ->each(function (State $state) use ($states, $transitions) {
                $this->info($state->caption());

                $transitions->from($state->value)
                    ->each(function (Transition $transition) use ($states) {
                        $target = $states->one($transition->target)->caption();
                        $this->warn("\t{$transition->caption}->{$target}");
                    });
            });
    }
}
