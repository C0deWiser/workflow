<?php

namespace Codewiser\Workflow\Models;

use Codewiser\Workflow\State;
use Codewiser\Workflow\StateCollection;
use Codewiser\Workflow\Transition;
use Codewiser\Workflow\TransitionCollection;
use Codewiser\Workflow\WorkflowBlueprint;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property integer $id
 * @property string $blueprint
 * @property string|null $source
 * @property string $target
 * @property array|null $context
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Authenticatable|null $performer
 * @property-read Model $transitionable
 */
class TransitionHistory extends Model
{
    protected $table = 'transition_history';

    protected $casts = [
        'context' => 'array'
    ];

    protected static function booted()
    {
        static::addGlobalScope('latest', function (Builder $builder) {
            $builder->latest();
        });
    }

    public function performer(): MorphTo
    {
        return $this->morphTo();
    }

    public function transitionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function blueprint(): WorkflowBlueprint
    {
        $class = $this->blueprint;

        return new $class;
    }

    public function source(): ?State
    {
        if ($source = $this->source) {
            try {
                return StateCollection::make($this->blueprint()->states())
                    ->one($source);
            } catch (Exception) {

            }
        }

        return null;
    }

    public function target(): ?State
    {
        try {
            return StateCollection::make($this->blueprint()->states())
                ->one($this->target);
        } catch (Exception) {
            return null;
        }
    }

    public function transition(): ?Transition
    {
        if (($source = $this->source()) && ($target = $this->target())) {
            try {

                $transition = TransitionCollection::make($this->blueprint()->transitions())
                    ->from($source->value)
                    ->to($target->value)
                    ->sole();

                if ($context = $this->context) {
                    $transition->context($context);
                }

                return $transition;

            } catch (Exception) {

            }
        }

        return null;
    }
}
