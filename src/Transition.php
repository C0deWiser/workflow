<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasCaption;
use Codewiser\Workflow\Traits\HasEloquentEvents;
use Codewiser\Workflow\Traits\HasPrerequisites;
use Codewiser\Workflow\Traits\HasStateMachineEngine;
use Codewiser\Workflow\Traits\HasValidationRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Config\Repository as ContextRepository;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Transition between states in State Machine.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @template TType of \BackedEnum
 */
class Transition implements Arrayable, Injectable
{
    use HasAttributes, HasStateMachineEngine, HasCaption, HasEloquentEvents, HasValidationRules, HasPrerequisites;

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

    public static function make(\BackedEnum $source, \BackedEnum $target): static
    {
        return new static($source, $target);
    }

    /**
     * @param  TType  $source  Source state.
     * @param  TType  $target  Target state.
     */
    public function __construct(public \BackedEnum $source, public \BackedEnum $target)
    {
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
     * @param  callable(Model, Transition): bool|string  $authorization  Ability name or callable.
     */
    public function authorizedBy(callable|string $authorization): static
    {
        $this->authorization = $authorization;

        return $this;
    }

    /**
     * Hide transition from humans, so only robots can move it.
     */
    public function hidden(): static
    {
        $this->authorization = fn() => false;

        return $this;
    }

    /**
     * Examine transition preconditions.
     *
     * @throws TransitionFatalException|TransitionRecoverableException
     */
    public function validate(): static
    {
        $this->prerequisites()
            ->merge($this->target()->prerequisites())
            ->each(
                fn(callable $condition) => call_user_func($condition, $this->engine->model)
            );

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
                'source' => $this->source->value,
                'target' => $this->target->value,
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
        return $this->resolveCaption($this->engine->model) ?? $this->target()->caption();
    }

    public function additional(): array
    {
        return $this->resolveAttributes($this->engine->model);
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
     */
    public function route(\BackedEnum $source, \BackedEnum $target): bool
    {
        return $this->source()->is($source) && $this->target()->is($target);
    }

    /**
     * Transition required to be charged to fire.
     */
    public function chargeable(Charge $charge): static
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
     * @return null|string|callable(Model, Transition): bool
     */
    public function authorization(): callable|string|null
    {
        return $this->authorization;
    }

    /**
     * Check if transition authorized.
     */
    public function authorized(): ?static
    {
        $allowed = null;

        if ($authorization = $this->authorization()) {
            if (is_string($authorization)) {
                $allowed = Gate::allows($authorization, [$this->engine()->model, $this]);
            } elseif (is_callable($authorization)) {
                try {
                    $allowed = call_user_func($authorization, $this->engine()->model, $this);
                } catch (AuthorizationException) {
                    $allowed = false;
                }
            }
        }

        return $allowed === false ? null : $this;
    }

    /**
     * Get a list of problems with the transition.
     *
     * @return array<int, string>
     */
    public function issues(): array
    {
        return $this->prerequisites()
            ->merge($this->target()->prerequisites())
            ->map(function (callable $condition) {
                try {
                    call_user_func($condition, $this->engine->model);
                } catch (TransitionFatalException) {
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
    public function transit(array $context = []): Model
    {
        return $this->engine()->transit($this->target, $context);
    }
}
