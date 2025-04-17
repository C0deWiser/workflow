<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Commands\ShowCommand;
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
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations')
        ], 'workflow-migrations');

        Event::listen(ModelInitialized::class, [TransitionListener::class, 'handleModelInitialized']);
        Event::listen(ModelTransited::class, [TransitionListener::class, 'handleModelTransited']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ValidateCommand::class,
                ShowCommand::class,
            ]);
        }
    }
}
