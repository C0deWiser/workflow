<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Models\TransitionHistory;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin Model
 *
 * @property-read Collection|TransitionHistory[] $transitions
 * @property-read null|TransitionHistory $latest_transition
 */
trait HasTransitionHistory
{
    public function transitions(): MorphMany
    {
        return $this->morphMany(TransitionHistory::class, 'transitionable');
    }

    public function latest_transition(): MorphOne
    {
        return $this
            ->morphOne(TransitionHistory::class, 'transitionable')
            ->latestOfMany();
    }

    public function loadLatestTransition(?\Closure $performer = null, ?\Closure $transitionable = null): self
    {
        return $this->load($this->getLatestTransitionConstraining($performer, $transitionable));
    }

    public function scopeWithLatestTransition(Builder $builder, ?\Closure $performer = null, ?\Closure $transitionable = null): void
    {
        $builder->with($this->getLatestTransitionConstraining($performer, $transitionable));
    }

    protected function getLatestTransitionConstraining(?\Closure $performer = null, ?\Closure $transitionable = null): array
    {
        return [
            'latest_transition' => [
                'performer'      => $performer ?? fn(MorphTo $builder) => $builder,
                'transitionable' => $transitionable ?? fn(MorphTo $builder) => $builder
            ]
        ];
    }
}
