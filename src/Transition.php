<?php

namespace Codewiser\Workflow;

use Closure;
use Codewiser\Workflow\Exceptions\TransitionException;
use Codewiser\Workflow\Exceptions\TransitionFatalException;
use Codewiser\Workflow\Exceptions\TransitionRecoverableException;
use Codewiser\Workflow\Traits\Workflow;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Transition between states in State Machine
 * @package Codewiser\Workflow
 *
 */
class Transition implements Arrayable
{
    protected string $source;
    protected string $target;
    /**
     * @var Model|Workflow
     */
    protected Model $model;
    protected string $attribute;
    protected Collection $conditions;
    protected Collection $callbacks;
    protected Collection $attributes;
    protected ?string $ability;

    /**
     * Instantiate new transition.
     *
     * @param $source
     * @param $target
     * @return static
     */
    public static function define($source, $target): Transition
    {
        return new static($source, $target);
    }

    public function __construct(string $source, string $target)
    {
        $this->source = $source;
        $this->target = $target;
        $this->ability = null;
        $this->conditions = new Collection();
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

    /**
     * Authorize transition using policy ability.
     * 
     * @param string $ability
     * @return $this
     */
    public function authorize($ability)
    {
        $this->ability = $ability;
        return $this;
    }

    /**
     * Add condition to the transition.
     *
     * @param Closure $condition
     * @return $this
     */
    public function condition(Closure $condition)
    {
        $this->conditions->push($condition);
        return $this;
    }

    /**
     * Callback(s) will run after transition is done.
     *
     * @param Closure $callback
     * @return $this
     */
    public function callback(Closure $callback)
    {
        $this->callbacks->push($callback);
        return $this;
    }

    /**
     * Add requirement(s) to transition payload.
     *
     * @param string|string[] $attributes
     * @return $this
     */
    public function requires($attributes)
    {
        if (is_string($attributes)) {
            $this->attributes->push($attributes);
        }
        if (is_array($attributes)) {
            $this->attributes->merge($attributes);
        }

        return $this;
    }

    public function toArray()
    {
        return [
            'caption' => $this->getCaption(),
            'source' => $this->getSource(),
            'target' => $this->getTarget(),
            'problems' => $this->getProblems(),
            'requires' => $this->attributes->toArray()
        ];
    }

    /**
     * Get human readable transition caption.
     *
     * @param bool $pastPerfect get caption for completed transition
     * @return array|Translator|string|null
     */
    public function getCaption($pastPerfect = false)
    {
        return trans(Str::snake(class_basename($this->workflow()->getBlueprint())) . "." . ($pastPerfect ? 'transited' : 'transitions') . ".{$this->getSource()}.{$this->getTarget()}");
    }

    /**
     * Source state.
     *
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Target state.
     *
     * @return string
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * Ability to authorize.
     * 
     * @return string
     */
    public function getAbility(): ?string
    {
        return $this->ability;
    }

    /**
     * Get registered preconditions.
     *
     * @return Collection|Closure[]
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * Get registered transition callbacks.
     *
     * @return Collection|Closure[]
     */
    public function getCallbacks()
    {
        return $this->callbacks;
    }

    /**
     * Get list of problems with the transition.
     *
     * @return array|string[]
     */
    public function getProblems(): array
    {
        return $this->getConditions()
            ->filter(function(\Closure $condition) {
                try {
                    call_user_func($condition, $this->model);
                } catch (TransitionFatalException $e) {
                    
                } catch (TransitionRecoverableException $e) {
                    // Left only recoverable problems
                    return true;
                }
                return false;
            })
            ->map(function(\Closure $condition) {
                try {
                    call_user_func($condition, $this->model);
                } catch (TransitionRecoverableException $e) {
                    return $e->getMessage();
                }
            })
            ->toArray();
    }

    /**
     * Get attributes, that must be provided into transit() method.
     *
     * @return Collection
     */
    public function getRequirements(): Collection
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
     * @throws TransitionRecoverableException
     * @throws TransitionFatalException
     */
    public function validate()
    {
        foreach ($this->getConditions() as $condition) {
            call_user_func($condition, $this->model);
        }
    }
}
