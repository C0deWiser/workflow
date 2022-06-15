<?php

namespace Codewiser\Workflow\Commands;

use Codewiser\Workflow\BlueprintValidator;
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

        $validator = new BlueprintValidator($blueprint);

        $this->table(['Value', 'Caption', 'Additional', 'Error'], $validator->states());

        $this->table(['Source', 'Target', 'Caption', 'Issues', 'Auth', 'Context', 'Additional', 'Errors'], $validator->transitions());

        if ($validator->valid) {
            $this->info("Blueprint {$class} is valid");
        } else {
            $this->error("Blueprint {$class} is invalid");
        }

        return self::SUCCESS;
    }

}
