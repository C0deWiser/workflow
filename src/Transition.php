<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasCallbacks;
use Codewiser\Workflow\Traits\HasCaption;
use Codewiser\Workflow\Traits\HasPrerequisites;
use Codewiser\Workflow\Traits\HasStateMachineEngine;
use Codewiser\Workflow\Traits\HasValidationRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Transition between states in State Machine.
 */
class Transition implements Arrayable, Injectable
{
    use HasAttributes, HasStateMachineEngine, HasCaption, HasCallbacks, HasValidationRules, HasPrerequisites;

    /**
     * Source state.
     *
     * @var \BackedEnum|string|int
     */
    public $source;

    /**
     * Target state.
     *
     * @var \BackedEnum|string|int
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
     * @var null|boolean|string|callable
     */
    protected $authorization = null;

    protected ?Charge $charge = null;

    /**
     * Transit context.
     *
     * @var array
     */
    protected array $context = [];

    /**
     * Instantiate new transition.
     *
     * @param \BackedEnum|string|int $source
     * @param \BackedEnum|string|int $target
     * @return static
     */
    public static function make($source, $target): Transition
    {
        return new static($source, $target);
    }

    /**
     * @param \BackedEnum|string|int $source
     * @param \BackedEnum|string|int $target
     */
    public function __construct($source, $target)
    {
        $this->source = $source;
        $this->target = $target;
    }

    /**
     * Authorize transition using policy ability (or closure).
     *
     * @param false|string|callable $ability
     */
    public function authorizedBy($ability): self
    {
        $this->authorization = $ability;

        return $this;
    }

    /**
     * Hide transition from humans, so only robots can move it.
     */
    public function hidden(): self
    {
        $this->authorizedBy(false);

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
            ? ['rules' => $this->validationRules(true) + $this->target()->validationRules(true)]
            : [];

        $issues = $this->issues() ? ['issues' => $this->issues()] : [];

        $charge = $this->charge ? [
            'charge' => [
                'progress' => $this->charge->charging($this),
                'allow' => $this->charge->mayCharge($this),
                'history' => $this->charge->history($this),
            ]
        ] : [];

        return [
                'name' => $this->caption(),
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
        return $this->caption ?? Value::name($this->source) . " - " . Value::name($this->target);
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
     * @return false|string|callable|null
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

        if (!is_null($ability = $this->authorization())) {
            if (is_bool($ability)) {
                $allowed = $ability;
            } elseif (is_string($ability)) {
                $allowed = Gate::allows($ability, $this->engine()->model);
            } elseif (is_callable($ability)) {
                try {
                    $allowed = call_user_func($ability, $this->engine()->model);
                } catch (AuthorizationException $exception) {
                    $allowed = false;
                }
            }
        }

        return $allowed === false ? null : $this;
    }

    /**
     * Get list of problems with the transition.
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
            ->toArray();
    }

    /**
     * Get or set and validate transition additional context.
     *
     * @param array|string|null $context
     * @return mixed
     * @throws ValidationException
     *
     */
    public function context($context = null)
    {
        if (is_array($context)) {

            if ($rules = $this->validationRules() + $this->target()->validationRules()) {
                $context = validator($context, $rules)->validate();
            }

            $this->context = $context;
        }

        if (is_string($context)) {
            return Arr::get($this->context, $context);
        }

        return $this->context;
    }

    /**
     * Run this transition, passing optional context. Returns Model for you to save it.
     *
     * @param array $context
     * @return Model
     */
    public function transit(array $context = []): Model
    {
        return $this->engine()->transit($this->target, $context);
    }
}
