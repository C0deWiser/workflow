<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasCallbacks;
use Codewiser\Workflow\Traits\HasCaption;
use Codewiser\Workflow\Traits\HasFootprint;
use Codewiser\Workflow\Traits\HasPrerequisites;
use Codewiser\Workflow\Traits\HasStateMachineEngine;
use Codewiser\Workflow\Traits\HasValidationRules;
use Illuminate\Config\Repository as ContextRepository;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Transition between states in State Machine.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @template TType
 */
class Transition implements Arrayable, Injectable
{
    use HasAttributes, HasStateMachineEngine, HasCaption, HasCallbacks, HasValidationRules, HasPrerequisites, HasFootprint;

    /**
     * Source state.
     *
     * @var TType
     */
    public $source;

    /**
     * Target state.
     *
     * @var TType
     */
    public $target;

    /**
     * Instructions to authorize transit.
     *
     * null — without authorization
     * false — denies transit
     * string — invoke policy ability
     * callable — will be invoked for authorization
     *
     * @var null|string|callable
     */
    protected $authorization = null;

    protected ?Charge $charge = null;

    /**
     * Instantiate new transition.
     *
     * @param  TType  $source
     * @param  TType  $target
     *
     * @return static
     */
    public static function make($source, $target): Transition
    {
        return new static($source, $target);
    }

    /**
     * @param  TType  $source
     * @param  TType  $target
     */
    public function __construct($source, $target)
    {
        $this->source = $source;
        $this->target = $target;
        $this->context = new ContextRepository;
    }

    public function __serialize(): array
    {
        return [
            'source'  => $this->source,
            'target'  => $this->target,
            'engine'  => serialize($this->engine),
            'context' => serialize($this->context),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->source = $data['source'];
        $this->target = $data['target'];
        $this->engine = unserialize($data['engine']);
        $this->context = unserialize($data['context']);
    }

    /**
     * Authorize transition using policy ability (or closure).
     *
     * @param  callable|string|null  $ability
     * @param  callable|null  $callback
     */
    public function authorizedBy($ability = null, callable $callback = null): self
    {
        $this->authorization = $ability ?? $callback;

        return $this;
    }

    /**
     * Hide transition from humans, so only robots can move it.
     */
    public function hidden(): self
    {
        $this->authorization = fn() => false;

        return $this;
    }

    /**
     * Examine transition preconditions.
     *
     * @throws TransitionFatalException|TransitionRecoverableException
     */
    public function validate(): Transition
    {
        $this->prerequisites()
            ->merge($this->target()->prerequisites())
            ->each(function ($condition) {
                call_user_func($condition, $this->engine->model);
            });

        return $this;
    }

    public function toArray(): array
    {
        $rules = ($this->validationRules() || $this->target()->validationRules())
            ? ['rules' => $this->mergeRules($this->target()->validationRules())]
            : [];

        $issues = $this->issues() ? ['issues' => $this->issues()] : [];

        $charge = $this->charge ? [
            'charge' => [
                'progress' => $this->charge->charging($this),
                'allow'    => $this->charge->mayCharge($this),
                'history'  => $this->charge->history($this),
            ]
        ] : [];

        return [
                'name'   => $this->caption(),
                'source' => Value::scalar($this->source),
                'target' => Value::scalar($this->target),
            ]
            + $rules
            + $issues
            + $charge
            + $this->additional()
            // In general, target additional is enough for a transition
            + $this->target()->additional();
    }

    /**
     * Get transition caption trans string.
     */
    public function caption(): string
    {
        return $this->resolveCaption($this->engine()->model) ?? $this->target()->caption();
    }

    public function chronicle(?Model $performer): ?string
    {
        if (is_callable($this->footprint)) {
            $chronicle = call_user_func($this->footprint, $this->engine()->model, $performer);
        }

        return $chronicle ?? $this->target()->chronicle($performer);
    }

    /**
     * Source state.
     */
    public function source(): State
    {
        return $this->engine->getStateListing()->one($this->source);
    }

    /**
     * Target state.
     */
    public function target(): State
    {
        return $this->engine->getStateListing()->one($this->target);
    }

    /**
     * Check the transition route.
     *
     * @param  TType  $source
     * @param  TType  $target
     *
     * @return bool
     */
    public function route($source, $target): bool
    {
        return $this->source()->is($source) && $this->target()->is($target);
    }

    /**
     * Transition required to be charged to fire.
     */
    public function chargeable(Charge $charge): self
    {
        $this->charge = $charge;

        return $this;
    }

    /**
     * Get transition charge.
     */
    public function charge(): ?Charge
    {
        return $this->charge;
    }

    /**
     * Ability to authorize.
     *
     * @return string|callable|null
     */
    public function authorization()
    {
        return $this->authorization;
    }

    /**
     * Check if transition authorized.
     */
    public function authorized(): ?self
    {
        $allowed = null;

        if ($ability = $this->authorization()) {
            if (is_string($ability)) {
                $allowed = Gate::allows($ability, [$this->engine()->model, $this]);
            } elseif (is_callable($ability)) {
                $allowed = call_user_func($ability, $this->engine()->model, $this);
            }
        }

        return $allowed === false ? null : $this;
    }

    /**
     * Get a list of problems with the transition.
     *
     * @return array<string>
     */
    public function issues(): array
    {
        return $this->prerequisites()
            ->merge($this->target()->prerequisites())
            ->map(function ($condition) {
                try {
                    call_user_func($condition, $this->engine->model);
                } catch (TransitionFatalException $exception) {
                    // Skip
                } catch (TransitionRecoverableException $exception) {
                    // Collect only recoverable messages
                    return $exception->getMessage();
                }
                return '';
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Get or set (and validate) transition additional context.
     *
     * @throws ValidationException
     */
    public function context(array $context = null): ContextRepository
    {
        if (is_array($context)) {

            $rules = $this->mergeRules($this->target()->validationRules());

            if ($rules) {
                $this->context = new ContextRepository(
                    validator($context, $rules)->validate()
                );
            }
        }

        return $this->context;
    }

    /**
     * Run this transition, passing optional context. Returns Model for you to save it.
     *
     * @param  array  $context
     *
     * @return TModel
     */
    public function transit(array $context = [])
    {
        return $this->engine()->transit($this->target, $context);
    }
}
