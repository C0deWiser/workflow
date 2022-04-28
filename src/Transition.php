<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasWorkflow;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Transition between states in State Machine.
 */
class Transition implements Arrayable
{
    use HasAttributes;

    protected ?string $caption = null;

    /**
     * @var Model|HasWorkflow|null
     */
    protected ?Model $model;
    protected ?string $attribute;
    protected Collection $prerequisites;
    protected Collection $callbacks;
    protected array $rules = [];
    /**
     * @var string|callable|null
     */
    protected $authorization = null;
    /**
     * Transition additional context.
     */
    protected array $context = [];

    /**
     * Instantiate new transition.
     */
    public static function define(string $source, string $target): static
    {
        return new static($source, $target);
    }

    public function __construct(protected string $source, protected string $target)
    {
        $this->prerequisites = new Collection();
        $this->callbacks = new Collection();
    }

    /**
     * Vivify transition with model context.
     */
    public function inject(Model $model, string $attribute)
    {
        $this->model = $model;
        $this->attribute = $attribute;
    }

    public function model(): Model
    {
        return $this->model;
    }

    /**
     * Authorize transition using policy ability (or closure).
     */
    public function authorizedBy(string|callable $ability): self
    {
        $this->authorization = $ability;
        return $this;
    }

    /**
     * Add prerequisite to the transition.
     */
    public function before(callable $prerequisite): self
    {
        $this->prerequisites->push($prerequisite);
        return $this;
    }

    /**
     * Callback(s) will run after transition is done.
     */
    public function after(callable $callback): self
    {
        $this->callbacks->push($callback);
        return $this;
    }

    /**
     * Set Transition caption.
     */
    public function as(string $caption): self
    {
        if ($caption)
            $this->caption = $caption;
        return $this;
    }

    /**
     * Add requirement(s) to transition payload.
     */
    public function rules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'caption' => $this->caption(),
            'source' => $this->source(),
            'target' => $this->target(),
            'problems' => $this->problems(),
            'requires' => array_keys($this->rules)
        ] + $this->additional();
    }

    /**
     * Get transition caption trans string.
     */
    public function caption(): string
    {
        $fallback = Str::snake(class_basename($this->workflow()->blueprint())) . ".transitions.{$this->source()}.{$this->target()}";
        return $this->caption ?? $fallback;
    }

    /**
     * Source state.
     */
    public function source(): string
    {
        return $this->source;
    }

    /**
     * Target state.
     */
    public function target(): string
    {
        return $this->target;
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
                    call_user_func($condition, $this->model);
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
     * Parent context of this transition.
     */
    protected function workflow(): ?StateMachineEngine
    {
        return $this->model->workflow($this->attribute);
    }

    /**
     * Examine transition preconditions.
     *
     * @throws TransitionFatalException|TransitionRecoverableException
     */
    public function validate(): self
    {
        foreach ($this->prerequisites() as $condition) {
            call_user_func($condition, $this->model);
        }
        return $this;
    }

    /**
     * Get or set and validate transition additional context.
     *
     * @throws ValidationException
     */
    public function context(array $context = null): array
    {
        if (is_array($context)) {

            if ($rules = $this->validationRules()) {
                $context = validator($context, $rules)->validate();
            }

            $this->context = $context;
        }

        return $this->context;
    }
}
