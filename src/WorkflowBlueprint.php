<?php

namespace Codewiser\Workflow;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Workflow blueprint.
 */
abstract class WorkflowBlueprint implements CastsAttributes
{
    /**
     * Array of available Model Workflow steps. First one is initial.
     *
     * @return array<string,State>
     * @example [new, review, published, correcting]
     */
    abstract public function states(): array;

    /**
     * Array of allowed transitions between states.
     *
     * @return array<array,Transition>
     * @example [[new, review], [review, published], [review, correcting], [correcting, review]]
     */
    abstract public function transitions(): array;

    private static array $engines = [];

    /**
     * Get State Machine Engine for the Blueprint.
     *
     * @param Model $model
     * @param string $attribute
     * @return StateMachineEngine
     */
    public static function engine(Model $model, string $attribute): StateMachineEngine
    {
        $key = $model::class . ':' . $attribute;

        if (!isset(self::$engines[$key])) {
            self::$engines[$key] = new StateMachineEngine(new static, $model);
        }

        return self::$engines[$key];
    }

    /**
     * Transform the attribute from the underlying model values.
     *
     * @param Model $model
     * @param string $key
     * @param string|int|null $value
     * @param array $attributes
     * @return State|null
     */
    public function get($model, string $key, $value, array $attributes): ?State
    {
        return $value ? self::engine($model, $key)->states()->one($value) : null;
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param Model $model
     * @param string $key
     * @param State|BackedEnum|string|int|null $value
     * @param array $attributes
     * @return string|int|null
     */
    public function set($model, string $key, $value, array $attributes): int|string|null
    {
        return State::scalar($value);
    }
}
