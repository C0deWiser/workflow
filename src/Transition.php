<?php

namespace Codewiser\Workflow;

use Closure;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Traits\HasWorkflow;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Transition between states in State Machine
 * @package Codewiser\Workflow
 *
 */
class Transition implements Arrayable
{
    protected string $source;
    protected string $target;
    protected ?string $caption = null;

    /**
     * @var Model|HasWorkflow|null
     */
    protected ?Model $model;
    protected ?string $attribute;
    protected Collection $prerequisites;
    protected Collection $callbacks;
    protected Collection $attributes;
    /**
     * @var string|\Closure|null
     */
    protected $authorization;
    /**
     * Transition additional context.
     *
     * @var array
     */
    protected array $context = [];

    /**
     * Instantiate new transition.
     *
     * @param $source
     * @param $target
     * @return static
     */
    public static function define($source, $target): self
    {
        return new static($source, $target);
    }

    public function __construct(string $source, string $target)
    {
        $this->source = $source;
        $this->target = $target;
        $this->authorization = null;
        $this->prerequisites = new Collection();
        $this->attributes = new Collection();
        $this->callbacks = new Collection();
    }

    /**
     * Vivify transition with model context.
     *
     * @param Model $model
     * @param string $attribute
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
     *
     * @param string|\Closure $ability
     * @return $this
     */
    public function authorizedBy($ability): self
    {
        $this->authorization = $ability;
        return $this;
    }

    /**
     * Add prerequisite to the transition.
     *
     * @param Closure $prerequisite
     * @return $this
     */
    public function before(Closure $prerequisite): self
    {
        $this->prerequisites->push($prerequisite);
        return $this;
    }

    /**
     * Callback(s) will run after transition is done.
     *
     * @param Closure $callback
     * @return $this
     */
    public function after(Closure $callback): self
    {
        $this->callbacks->push($callback);
        return $this;
    }

    /**
     * Set Transition caption.
     *
     * @param string $caption
     * @return $this
     */
    public function as(string $caption): self
    {
        if ($caption)
            $this->caption = $caption;
        return $this;
    }

    /**
     * Add requirement(s) to transition payload.
     *
     * @param string|string[] $attributes
     * @return $this
     */
    public function requires($attributes): self
    {
        $this->attributes->merge((array)$attributes);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'caption' => $this->caption(),
            'source' => $this->source(),
            'target' => $this->target(),
            'problems' => $this->problems(),
            'requires' => $this->attributes->toArray()
        ];
    }

    /**
     * Get transition caption trans string.
     *
     * @return string
     */
    public function caption(): string
    {
        return $this->caption ??
            __(Str::snake(class_basename($this->workflow()->blueprint())) . ".transitions.{$this->source()}.{$this->target()}");
    }

    /**
     * Source state.
     *
     * @return string
     */
    public function source(): string
    {
        return $this->source;
    }

    /**
     * Target state.
     *
     * @return string
     */
    public function target(): string
    {
        return $this->target;
    }

    /**
     * Ability to authorize.
     *
     * @return string|\Closure|null
     */
    public function authorization()
    {
        return $this->authorization;
    }

    /**
     * Get registered preconditions.
     *
     * @return Collection|Closure[]
     */
    public function prerequisites(): Collection
    {
        return $this->prerequisites;
    }

    /**
     * Get registered transition callbacks.
     *
     * @return Collection|Closure[]
     */
    public function callbacks(): Collection
    {
        return $this->callbacks;
    }

    /**
     * Get list of problems with the transition.
     *
     * @return array|string[]
     */
    public function problems(): array
    {
        return $this->prerequisites()
            ->map(function (\Closure $condition) {
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
     *
     * @return Collection
     */
    public function requirements(): Collection
    {
        return $this->attributes;
    }

    /**
     * Parent context of this transition.
     *
     * @return StateMachineEngine|null
     */
    protected function workflow(): ?StateMachineEngine
    {
        return $this->model->workflow($this->attribute);
    }

    /**
     * Examine transition preconditions.
     *
     * @return Transition
     * @throws TransitionFatalException
     * @throws TransitionRecoverableException
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
     * @param array|null $context
     * @return array
     * @throws ValidationException
     */
    public function context(array $context = null): array
    {
        if (is_array($context)) {

            $rules = $this->requirements()
                ->mapWithKeys(function (string $attribute) {
                    return [$attribute => 'required|string'];
                })
                ->toArray();

            if ($rules) {
                $context = Validator::validate($context, $rules);
            }

            $this->context = $context;
        }

        return $this->context;
    }
}
