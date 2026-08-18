<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\Traits\HasEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Initiates State Machine, watches for changes, fires Event.
 */
class WorkflowObserver
{
    use HasEngine;

    /**
     * @var callable(object): void
     */
    protected $eventDispatcher;

    public function __construct()
    {
        $this->setEventDispatcher(fn(object $event) => event($event));
    }

    protected function dispatchEvent(object $event): void
    {
        call_user_func($this->eventDispatcher, $event);
    }

    /**
     * For testing purposes.
     *
     * @internal
     */
    public function setEventDispatcher(callable $dispatcher): void
    {
        $this->eventDispatcher = $dispatcher;
    }

    public function creating(Model $model): bool
    {
        return StateMachine::collect($model)
            ->reject(function (StateMachine $engine) use ($model) {

                $this->inject($engine);

                $state = $this->nowCreating();

                // Set initial state
                $model->setAttribute($engine->attribute, $state->enum);

                // Context for Events
                $context = new Context($state, $this->engine->userdata());

                // Run state callbacks
                if ($engine->state()->invoke($model, $context, 'saving') === false) {
                    return false;
                }

                return true;
            })
            // Empty means there are no failures
            ->isEmpty();
    }

    public function created(Model $model): void
    {
        StateMachine::collect($model)
            ->each(function (StateMachine $engine) use ($model) {

                $this->inject($engine);

                $state = $this->wasCreated();

                // Context for Events
                $context = new Context($state, $this->engine->userdata());

                // Fire event
                $this->dispatchEvent(new ModelInitialized($engine, $context));

                // Run state callbacks
                $engine->state()->invoke($model, $context, 'saved');
            });
    }

    public function updating(Model $model): bool
    {
        // If one transition is invalid, all update is invalid
        return StateMachine::collect($model)
            // Rejecting successful validations
            ->reject(function (StateMachine $engine) use ($model) {

                $this->inject($engine);

                if ($transition = $this->nowTransiting()) {

                    if ($transition->isForbidden()) {
                        throw new TransitionException('Transition is forbidden.');
                    }

                    if ($transition->issues()) {
                        throw new TransitionException('Transition doesnt meet conditions to run.');
                    }

                    // Context for Events
                    $context = new Context($transition, $this->engine->userdata());

                    // Transition callbacks
                    if ($transition->invoke($model, $context, 'saving') === false) {
                        return false;
                    }
                    // State callbacks
                    if ($transition->target()->invoke($model, $context, 'saving') === false) {
                        return false;
                    }
                }

                return true;
            })
            // Empty means there are no failures
            ->isEmpty();
    }

    public function updated(Model $model): void
    {
        StateMachine::collect($model)
            ->each(function (StateMachine $engine) use ($model) {

                $this->inject($engine);

                if ($transition = $this->wasTransited()) {

                    // Context for Events
                    $context = new Context($transition, $this->engine->userdata());

                    // For Event Listener
                    $this->dispatchEvent(new ModelTransited($engine, $context));

                    // Transition callbacks
                    $transition->invoke($model, $context, 'saved');
                    // State callbacks
                    $transition->target()->invoke($model, $context, 'saved');
                }
            });
    }

    protected function nowCreating(): ?State
    {
        return $this->engine->state() ?? $this->engine->getStateListing()->initial();
    }

    protected function wasCreated(): ?State
    {
        $state = $this->engine->state();

        // State must exist
        if (! $state) {
            throw new ItemNotFoundException('Initial state not found');
        }

        return $state;
    }

    /**
     * Get a transition, that is now running, but not saved yet.
     */
    protected function nowTransiting(): ?Transition
    {
        $model = $this->engine->model;
        $attribute = $this->engine->attribute;

        if ($model->isDirty($attribute) &&
            ($source = $model->getOriginal($attribute)) &&
            ($target = $model->getAttribute($attribute)) &&
            $source != $target) {

            return $this->engine->getTransitionListing()
                ->from($source)
                ->to($target)
                // Transition must exist
                ->sole();
        }

        return null;
    }

    /**
     * Get a transition that was just saved.
     */
    protected function wasTransited(): ?Transition
    {
        $model = $this->engine->model;
        $attribute = $this->engine->attribute;

        if ($model->wasChanged($attribute) &&
            ($source = $model->getOriginal($attribute)) &&
            ($target = $model->getAttribute($attribute)) &&
            $source != $target) {

            return $this->engine->getTransitionListing()
                ->from($source)
                ->to($target)
                // Transition must exist
                ->sole();
        }

        return null;
    }
}
