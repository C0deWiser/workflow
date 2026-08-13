<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\Traits\HasEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ItemNotFoundException;

/**
 * Initiates State Machine, watches for changes, fires Event.
 */
class WorkflowObserver
{
    use HasEngine;

    public function creating(Model $model): bool
    {
        return StateMachine::collect($model)
            ->reject(function (StateMachine $engine) use ($model) {

                $this->inject($engine);

                $state = $this->nowCreating();

                // Set initial state
                $model->setAttribute($engine->attribute, $state->enum);

                // Context for Events
                $context = new Context($state, $engine->getActor());

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
                $context = new Context($state, $engine->getActor());

                // Fire event
                event(new ModelInitialized($engine, $context));

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
                    $context = new Context($transition, $engine->getActor());

                    // For Transition Observer
                    if (method_exists($model, 'fireTransitionEvent')) {
                        if ($model->fireTransitionEvent('transiting', true, $engine, $context) === false) {
                            return false;
                        }
                    }

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
                    $context = new Context($transition, $engine->getActor());

                    // For Transition Observer
                    if (method_exists($model, 'fireTransitionEvent')) {
                        $model->fresh()->fireTransitionEvent('transited', false, $engine, $context);
                    }

                    // For Event Listener
                    event(new ModelTransited($engine, $context));

                    // Transition callbacks
                    $transition->invoke($model, $context, 'saved');
                    // State callbacks
                    $transition->target()->invoke($model, $context, 'saved');
                }
            });
    }

    protected function nowCreating(): ?State
    {
        if ($engine = $this->engine) {

            $state = $engine->state() ?? $engine->getStateListing()->initial();

            // Pass context to state for validation. May throw an Exception
            $state->context($this->restoreContext());

            return $state;
        }

        return null;
    }

    protected function wasCreated(): ?State
    {
        if ($engine = $this->engine) {

            $state = $engine->state();

            // State must exist
            if (! $state) {
                throw new ItemNotFoundException('Initial state not found');
            }

            // Pass context to state, so it will be accessible in events.
            $state->context($this->restoreContext());

            return $state;
        }

        return null;
    }

    /**
     * Get a transition, that is now running, but not saved yet.
     */
    protected function nowTransiting(): ?Transition
    {
        if ($engine = $this->engine) {
            $model = $engine->model;
            $attribute = $engine->attribute;

            if ($model->isDirty($attribute) &&
                ($source = $model->getOriginal($attribute)) &&
                ($target = $model->getAttribute($attribute)) &&
                $source != $target) {

                $transition = $engine->getTransitionListing()
                    ->from($source)
                    ->to($target)
                    // Transition must exist
                    ->sole();

                // Pass context to transition for validation. May throw an Exception
                $transition->context($this->restoreContext());

                return $transition;
            }
        }

        return null;
    }

    /**
     * Get a transition that was just saved.
     */
    protected function wasTransited(): ?Transition
    {
        if ($engine = $this->engine) {
            $model = $engine->model;
            $attribute = $engine->attribute;

            if ($model->wasChanged($attribute) &&
                ($source = $model->getOriginal($attribute)) &&
                ($target = $model->getAttribute($attribute)) &&
                $source != $target) {

                $transition = $engine->getTransitionListing()
                    ->from($source)
                    ->to($target)
                    // Transition must exist
                    ->sole();

                // Pass context to transition, so it will be accessible in events.
                $transition->context($this->restoreContext());

                return $transition;
            }
        }

        return null;
    }

    protected function restoreContext(): array
    {
        if ($engine = $this->engine) {
            return StateMachine::$context[$engine->attribute] ?? [];
        }

        return [];
    }
}
