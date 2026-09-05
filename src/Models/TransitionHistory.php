<?php

namespace Codewiser\Workflow\Models;

use Codewiser\Workflow\Context;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\StateMachineResolver;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\WorkflowBlueprint;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @template TModel of Model
 * @template TType of \BackedEnum
 *
 * @property integer $id
 * @property string $blueprint Attrinbute name (or Blueprint class, legacy).
 * @property string|null $source
 * @property string $target
 * @property array|null $context
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Authenticatable|null $performer
 * @property TModel $transitionable
 */
class TransitionHistory extends Model
{
    use Prunable;

    protected $table = 'transition_history';

    protected $casts = [
        'context' => 'array'
    ];

    protected ?StateMachine $engine = null;

    /**
     * Model, storing transition history records, when overridden
     * with TransitionHistory::useModel().
     *
     * @var null|class-string<static>
     */
    protected static ?string $model = null;

    /**
     * Set the model, storing the transition history. It must extend
     * TransitionHistory.
     *
     * @param  class-string<static>  $model
     */
    public static function useModel(string $model): void
    {
        static::$model = $model;
    }

    /**
     * Model, that stores the transition history records.
     * It may be replaced with an extended model with
     * TransitionHistory::useModel(), e.g. from the AppServiceProvider.
     *
     * @return class-string<static>
     */
    public static function model(): string
    {
        $model = static::$model ?? static::class;

        return is_a($model, static::class, true) ? $model : static::class;
    }

    protected static function booted(): void
    {
        static::addGlobalScope('latest', fn(Builder $builder) => $builder->latest());
    }

    public function performer(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function transitionable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function blueprint(): ?WorkflowBlueprint
    {
        return $this->engine()?->blueprint;
    }

    protected function engine(): ?StateMachine
    {
        if (! $this->engine) {
            $resolver = function_exists('app')
                ? app(StateMachineResolver::class)
                : new StateMachineResolver();

            $this->engine = $resolver->restore($this->transitionable, $this->blueprint);
        }

        return $this->engine;
    }

    protected function state(string|int|\BackedEnum $value): ?State
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        return $this->engine()?->getStateListing()->first(fn(State $state) => $state->enum->value === $value);
    }

    /**
     * Restore object for the source state.
     *
     * @return null|State<TType>
     */
    public function source(): ?State
    {
        if ($source = $this->source) {
            return $this->state($source);
        }

        return null;
    }

    /**
     * Restore object for the target state.
     *
     * @return null|State<TType>
     */
    public function target(): ?State
    {
        return $this->state($this->target);
    }

    /**
     * Restore object for the transition.
     *
     * @return null|Transition<TModel, TType>
     */
    public function transition(): ?Transition
    {
        $engine = $this->engine();
        $source = $this->source();
        $target = $this->target();

        if ($engine && $source && $target) {
            try {
                return $engine->getTransitionListing()
                    ->from($source->enum)
                    ->to($target->enum)
                    ->sole();

            } catch (Exception) {
                //
            }
        }

        return null;
    }

    /**
     * Restore context with userdata.
     */
    public function context(): ?Context
    {
        $ctx = $this->transition() ?? $this->target();

        if ($ctx) {
            return new Context($ctx, $this->context ?? []);
        }

        return null;
    }

    public function prunable(): Builder
    {
        return static::query()->doesntHave('transitionable');
    }
}
