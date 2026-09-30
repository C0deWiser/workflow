<?php


namespace Codewiser\Workflow;

use Codewiser\Workflow\Events\ModelInitialized;
use Codewiser\Workflow\Events\ModelTransited;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\Traits\HasEngine;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Initiates State Machine, watches for changes, fires events, validates user data.
 */
class WorkflowObserver
{
    use HasEngine;

    public function __construct(
        protected Dispatcher $events,
        protected Factory $validators,
        protected StateMachineResolver $resolver
    ) {
        //
    }

    /**
     * Validate user data against contextual validation rules.
     * Returns only the data, that passed validation.
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    protected function validatedUserdata(Transition|State $contextual): array
    {
        $validation = $contextual->validation() ?? new Validation([]);

        return $this->validators
            ->make($this->engine->userdata(), $validation->rules, $validation->messages, $validation->attributes)
            ->validated();
    }

    public function creating(Model $model): bool
    {
        return $this->resolver->collect($model)
            ->reject(function (StateMachine $engine) use ($model) {

                $this->inject($engine);

                $state = $this->nowCreating();

                // Set initial state
                $model->setAttribute($engine->attribute, $state->enum);

                // Context for Events
                $context = new Context($state, $this->validatedUserdata($state));

                // Run state callbacks
                if ($engine->state()->invoke($model, $context, 'saving') === false) {
                    return false;
                }

                // Keep (possibly modified) context for 'created' event
                $engine->keepUserdata($context->data()->all());

                return true;
            })
            // Empty means there are no failures
            ->isEmpty();
    }

    public function created(Model $model): void
    {
        $this->resolver->collect($model)
            ->each(function (StateMachine $engine) use ($model) {

                $this->inject($engine);

                $state = $this->wasCreated();

                // Context for Events (validated on creating, may be modified by saving callbacks)
                $context = new Context($state, $this->engine->userdata());

                // Fire event
                $this->events->dispatch(new ModelInitialized($engine, $context));

                // Run state callbacks
                $engine->state()->invoke($model, $context, 'saved');
            });
    }

    public function updating(Model $model): bool
    {
        // If one transition is invalid, all update is invalid
        return $this->resolver->collect($model)
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
                    $context = new Context(
                        $transition,
                        $this->validatedUserdata($transition),
                        $this->engine->redirectedState()
                    );

                    // Transition callbacks
                    if ($transition->invoke($model, $context, 'saving') === false) {
                        return false;
                    }
                    // State callbacks, of the state the model lands in
                    if ($context->landedIn()->invoke($model, $context, 'saving') === false) {
                        return false;
                    }

                    // Keep (possibly modified) context for 'updated' event
                    $engine->keepUserdata($context->data()->all());
                }

                return true;
            })
            // Empty means there are no failures
            ->isEmpty();
    }

    public function updated(Model $model): void
    {
        $this->resolver->collect($model)
            ->each(function (StateMachine $engine) use ($model) {

                $this->inject($engine);

                if ($transition = $this->wasTransited()) {

                    // Context for Events (validated on updating, may be modified by saving callbacks)
                    $context = new Context(
                        $transition,
                        $this->engine->userdata(),
                        $this->engine->redirectedState()
                    );

                    // For Event Listener
                    $this->events->dispatch(new ModelTransited($engine, $context));

                    // Transition callbacks
                    $transition->invoke($model, $context, 'saved');
                    // State callbacks, of the state the model landed in
                    $context->landedIn()->invoke($model, $context, 'saved');
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
        return $this->changedTransition(
            fn() => $this->engine->model->isDirty($this->engine->attribute),
            fn() => $this->engine->model->getOriginal($this->engine->attribute)
        );
    }

    /**
     * Get a transition that was just saved.
     */
    protected function wasTransited(): ?Transition
    {
        return $this->changedTransition(
            fn() => $this->engine->model->wasChanged($this->engine->attribute),
            fn() => $this->engine->model->getOriginal($this->engine->attribute)
        );
    }

    /**
     * Resolve the transition the model attribute was changed by.
     *
     * A chargeable transition may have fired to a redirected state, while staying
     * the running transition. It is returned as is, so its rules, callbacks and
     * history stay attached to it.
     */
    protected function changedTransition(Closure $changed, Closure $source): ?Transition
    {
        $model = $this->engine->model;
        $attribute = $this->engine->attribute;

        if ($changed() &&
            ($from = $source()) &&
            ($to = $model->getAttribute($attribute)) &&
            $from != $to) {

            // Redirected transition keeps running as itself
            if ($redirected = $this->engine->redirectedTransition()) {
                return $redirected;
            }

            return $this->engine->getTransitionListing()
                ->from($from)
                ->to($to)
                // Transition must exist
                ->sole();
        }

        return null;
    }
}
