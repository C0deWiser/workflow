<?php

namespace Codewiser\Workflow\Commands;

use Codewiser\Workflow\BlueprintValidator;
use Codewiser\Workflow\WorkflowBlueprint;
use Illuminate\Console\Command;

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
        $className = class_basename($class);

        if (!class_exists($class)) {
            $this->error("$className Not Found");
            return self::INVALID;
        }

        $blueprint = new $class();

        if (!($blueprint instanceof WorkflowBlueprint)) {
            $this->warn("$className Not a WorkflowBlueprint instance");
            return self::INVALID;
        }

        $validator = new BlueprintValidator($blueprint);

        $this->table(['Value', 'Caption', 'Additional', 'Error'], $validator->states());

        $this->table(['Source', 'Target', 'Caption', 'Issues', 'Auth', 'Context', 'Additional', 'Errors'], $validator->transitions());

        if ($validator->valid) {
            $this->info("Blueprint $className is valid");
            return self::SUCCESS;
        } else {
            $this->error("Blueprint $className is invalid");
            return self::FAILURE;
        }
    }

}
