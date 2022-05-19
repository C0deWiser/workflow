<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasStateMachineEngine;
use Codewiser\Workflow\Traits\HasWorkflow;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Transition between states in State Machine.
 */
class Transition implements Arrayable
{
    use HasAttributes, HasStateMachineEngine;

    protected ?string $caption = null;
    protected Collection $prerequisites;
    protected Collection $callbacks;
    protected array $rules = [];
    protected mixed $authorization = null;
    protected array $context = [];

    /**
     * Instantiate new transition.
     */
    public static function make(string|int $source, string|int $target): static
    {
        return new static($source, $target);
    }

    public function __construct(
        public string|int $source,
        public string|int $target
    )
    {
        $this->prerequisites = new Collection();
        $this->callbacks = new Collection();
    }

    /**
     * Authorize transition using policy ability (or closure).
     */
    public function authorizedBy(string|callable $ability): static
    {
        $this->authorization = $ability;

        return $this;
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
     * Callback(s) will run after transition is done.
     */
    public function after(callable $callback): static
    {
        $this->callbacks->push($callback);

        return $this;
    }

    /**
     * Set Transition caption.
     */
    public function as(string $caption): static
    {
        if ($caption)
            $this->caption = $caption;

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
        return [
            'caption' => $this->caption(),
            'source' => $this->source,
            'target' => $this->target,
            'problems' => $this->problems(),
            'requires' => array_keys($this->rules)
        ] + $this->additional();
    }

    /**
     * Get transition caption trans string.
     */
    public function caption(): string
    {
        $fallback = Str::snake(class_basename($this->engine->blueprint())) . ".transitions.{$this->source}.{$this->target}";

        return $this->caption ?? $fallback;
    }

    /**
     * Source state.
     */
    public function source(): State
    {
        return $this->engine->states()->one($this->source);
    }

    /**
     * Target state.
     */
    public function target(): State
    {
        return $this->engine->states()->one($this->target);
    }

    /**
     * Ability to authorize.
     */
    public function authorization(): string|callable|null
    {
        return $this->authorization;
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
    public function problems(): array
    {
        return $this->prerequisites()
            ->map(function ($condition) {
                try {
                    call_user_func($condition, $this->engine->model());
                } catch (TransitionFatalException $e) {
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
    public function validationRules(): array
    {
        return $this->rules;
    }

    /**
     * Examine transition preconditions.
     *
     * @throws TransitionFatalException|TransitionRecoverableException
     */
    public function validate(): static
    {
        foreach ($this->prerequisites() as $condition) {
            call_user_func($condition, $this->engine->model());
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
}
