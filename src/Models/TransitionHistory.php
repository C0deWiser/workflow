<?php

namespace Codewiser\Workflow\Models;

use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachine;
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

    protected static function booted(): void
    {
        static::addGlobalScope('latest', fn(Builder $builder) => $builder->latest());
    }

    public function performer(): MorphTo
    {
        return $this->morphTo();
    }

    public function transitionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function blueprint(): ?WorkflowBlueprint
    {
        return $this->engine()?->blueprint;
    }

    protected function engine(): ?StateMachine
    {
        if (! $this->engine) {
            $this->engine = StateMachine::restore($this->transitionable, $this->blueprint);
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
                $transition = $engine->getTransitionListing()
                    ->from($source->enum)
                    ->to($target->enum)
                    ->sole();

                if ($context = $this->context) {
                    $transition->context($context);
                }

                return $transition;

            } catch (Exception) {
                //
            }
        }

        return null;
    }

    public function prunable(): Builder
    {
        return static::query()->doesntHave('transitionable');
    }
}
