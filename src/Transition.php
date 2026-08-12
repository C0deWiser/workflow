<?php

namespace Codewiser\Workflow;

use Codewiser\Workflow\Contracts\Injectable;
use Codewiser\Workflow\Traits\HasAttributes;
use Codewiser\Workflow\Traits\HasCaption;
use Codewiser\Workflow\Traits\HasCharge;
use Codewiser\Workflow\Traits\HasConditions;
use Codewiser\Workflow\Traits\HasDeadEnd;
use Codewiser\Workflow\Traits\HasEloquentEvents;
use Codewiser\Workflow\Traits\HasEngine;
use Codewiser\Workflow\Traits\HasContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Config\Repository as ContextRepository;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Transition between states in State Machine.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @template TType of \BackedEnum
 */
class Transition implements Arrayable, Injectable
{
    use HasAttributes {
        additional as protected selfAdditional;
    }
    use HasCaption {
        caption as protected selfCaption;
    }
    use HasDeadEnd {
        isForbidden as protected selfIsForbidden;
    }
    use HasConditions {
        issues as protected selfIssues;
    }
    use HasContext {
        validationRules as protected selfValidationRules;
    }
    use HasEngine, HasEloquentEvents, HasCharge;

    public static function make(\BackedEnum $source, \BackedEnum $target): static
    {
        return new static($source, $target);
    }

    /**
     * @param  TType  $source  Source state.
     * @param  TType  $target  Target state.
     */
    public function __construct(public \BackedEnum $source, public \BackedEnum $target)
    {
        $this->context = new ContextRepository;
    }

    public function __serialize(): array
    {
        return [
            'source'  => $this->source,
            'target'  => $this->target,
            'engine'  => serialize($this->engine),
            'context' => serialize($this->context),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->source = $data['source'];
        $this->target = $data['target'];
        $this->engine = unserialize($data['engine']);
        $this->context = unserialize($data['context']);
    }

    public function toArray(): array
    {
        $data = [
            'name'   => $this->caption(),
            'source' => $this->source->value,
            'target' => $this->target->value,

            ...$this->additional()
        ];

        if ($rules = $this->validationRules()) {
            $data['rules'] = $rules;
        }

        if ($issues = $this->issues()) {
            $data['issues'] = $issues;
        }

        if ($charge = $this->charge($this->engine)) {
            $data['charge'] = [
                'progress' => $charge->charging($this),
                'allow'    => $charge->mayCharge($this),
                'history'  => $charge->history($this),
            ];
        }

        return $data;
    }

    /**
     * Get transition caption trans string.
     */
    public function caption(): string
    {
        return $this->selfCaption() ?? $this->target()->caption();
    }

    public function additional(): array
    {
        return $this->selfAdditional() + $this->target()->additional();
    }

    public function isForbidden(): bool
    {
        return $this->selfIsForbidden() || $this->target()->isForbidden();
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
     * Get a list of problems with a transition.
     *
     * @return array<int, string>
     */
    public function issues(): array
    {
        return array_merge(
            $this->selfIssues(),
            $this->target()->issues()
        );
    }

    public function validationRules($explode = false): array
    {
        return $this->mergeRules($this->target()->validationRules());
    }

    /**
     * Run this transition, passing optional context. Returns Model for you to save it.
     *
     * @param  array  $context
     *
     * @return TModel
     */
    public function transit(array $context = []): Model
    {
        return $this->engine()->transit($this->target, $context);
    }

    /**
     * Authorize transition.
     *
     * @throws AuthorizationException
     */
    public function authorize(): static
    {
        $arguments = [$this->engine->model, $this];

        $callback = $this->engine->blueprint->authorization();

        if (is_callable($callback)) {

            $allowed = call_user_func_array($callback, $arguments);

            if ($allowed instanceof Response && $allowed->denied()) {
                throw new AuthorizationException();
            }

            if (is_bool($allowed) && $allowed === false) {
                throw new AuthorizationException();
            }
        } elseif (is_string($callback)) {
            Gate::authorize($callback, $arguments);
        }

        return $this;
    }
}
