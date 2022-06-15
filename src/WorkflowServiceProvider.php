<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Commands\ValidateCommand;
use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Listeners\TransitionListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if (config('services.workflow.history')) {
            Event::listen(ModelInitialized::class, [TransitionListener::class, 'handleModelInitialized']);
            Event::listen(ModelTransited::class, [TransitionListener::class, 'handleModelTransited']);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ValidateCommand::class
            ]);
        }
    }
}