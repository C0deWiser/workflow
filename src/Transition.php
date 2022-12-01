<?php

namespace Codewiser\Workflow;

use BackedEnum;
use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasCaption;
use Codewiser\Workflow\Traits\HasStateMachineEngine;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Transition between states in State Machine.
 */
class Transition implements Arrayable, Injectable
{
    use HasAttributes, HasStateMachineEngine, HasCaption;

    protected Collection $prerequisites;
    protected Collection $callbacks;
    protected array $rules = [];
    protected mixed $authorization = null;
    protected array $context = [];
    protected array $listeners = [];

    /**
     * Instantiate new transition.
     */
    public static function make(BackedEnum|string|int $source, BackedEnum|string|int $target): static
    {
        return new static($source, $target);
    }

    public function __construct(
        public BackedEnum|string|int $source,
        public BackedEnum|string|int $target
    )
    {
        $this->prerequisites = new Collection();
        $this->callbacks = new Collection();
    }

    /**
     * Authorize transition using policy ability (or closure).
     */
    public function authorizedBy(false|string|callable $ability): static
    {
        $this->authorization = $ability;

        return $this;
    }

    /**
     * Listen for Eloquent `saved`, `created` and `updated` events.
     */
    public function listenTo(string $event, ?callable $listener): static
    {
        $this->listeners[$event] = $listener;

        return $this;
    }

    /**
     * Get event listener if it was defined.
     */
    public function listener(string $event): mixed
    {
        return $this->listeners[$event] ?? null;
    }

    /**
     * Add prerequisite to the transition.
     */
    public function before(callable $prerequisite): static
    {
        $this->prerequisites->push($prerequisite);

        return $this;
    }

    /**
     * Hide transition from humans, so only robots can move it.
     */
    public function hidden(): static
    {
        $this->authorizedBy(false);

        return $this;
    }

    /**
     * Callback(s) will run after transition is done.
     */
    public function after(callable $callback): static
    {
        $this->callbacks->push($callback);

        return $this;
    }

    /**
     * Add requirement(s) to transition payload.
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function toArray(): array
    {
        $rules = $this->validationRules() ? ['rules' => $this->validationRules(true)] : [];
        $issues = $this->issues() ? ['issues' => $this->issues()] : [];

        return [
                'name' => $this->caption(),
                'source' => $this->source->value,
                'target' => $this->target->value,
            ]
            + $rules
            + $issues
            + $this->additional();
    }

    /**
     * Get transition caption trans string.
     */
    public function caption(): string
    {
        return $this->caption ?? "{$this->source->name} - {$this->target->name}";
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
     * Ability to authorize.
     */
    public function authorization(): false|string|callable|null
    {
        return $this->authorization;
    }

    /**
     * Check if transition authorized.
     */
    public function authorized(): bool
    {
        if ($ability = $this->authorization()) {
            if (is_bool($ability)) {
                return $ability;
            } elseif (is_string($ability)) {
                return Gate::allows($ability, $this->engine()->model);
            } elseif (is_callable($ability)) {
                return call_user_func($ability, $this->engine()->model);
            }
        }
        return true;
    }

    /**
     * Get registered preconditions.
     *
     * @return Collection<callable>
     */
    public function prerequisites(): Collection
    {
        return $this->prerequisites;
    }

    /**
     * Get registered transition callbacks.
     *
     * @return Collection<callable>
     */
    public function callbacks(): Collection
    {
        return $this->callbacks;
    }

    /**
     * Get list of problems with the transition.
     *
     * @return array<string>
     */
    public function issues(): array
    {
        return $this->prerequisites()
            ->map(function ($condition) {
                try {
                    call_user_func($condition, $this->engine->model);
                } catch (TransitionFatalException) {
                    // Skip
                } catch (TransitionRecoverableException $e) {
                    // Collect only recoverable messages
                    return $e->getMessage();
                }
                return '';
            })
            ->filter()
            ->toArray();
    }

    /**
     * Get attributes, that must be provided into transit() method.
     */
    public function validationRules($explode = false): array
    {
        $rules = $this->rules;

        if ($explode) {
            foreach ($rules as $attribute => $rule) {
                if (is_string($rule)) {
                    $rules[$attribute] = explode('|', $rule);
                }
            }
        }

        return $rules;
    }

    /**
     * Examine transition preconditions.
     *
     * @throws TransitionFatalException|TransitionRecoverableException
     */
    public function validate(): static
    {
        foreach ($this->prerequisites() as $condition) {
            call_user_func($condition, $this->engine->model);
        }
        return $this;
    }

    /**
     * Get or set and validate transition additional context.
     *
     * @throws ValidationException
     */
    public function context(mixed $context = null): mixed
    {
        if (is_array($context)) {

            if ($rules = $this->validationRules()) {
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
     * Run this transition.
     */
    public function run()
    {
        $this->engine()->transit($this->target);
    }
}
