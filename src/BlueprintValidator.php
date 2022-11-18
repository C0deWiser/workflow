<?php

namespace Codewiser\Workflow;

use BackedEnum;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\MultipleItemsFoundException;

class BlueprintValidator
{
    public StateCollection $states;
    public TransitionCollection $transitions;
    public bool $valid = true;

    public function __construct(public WorkflowBlueprint $blueprint)
    {
        $this->states = StateCollection::make($blueprint->states());

        $this->transitions = TransitionCollection::make($blueprint->transitions());
    }

    public function transitions(): array
    {
        return $this->transitions
            ->map(function (Transition $transition) {
                $row = [
                    'source' => $transition->source instanceof BackedEnum ? $transition->source->value : $transition->source,
                    'target' => $transition->target instanceof BackedEnum ? $transition->target->value : $transition->target,
                    'caption' => $transition->caption(),
                    'prerequisites' => $transition->prerequisites()->isEmpty() ? 'No' : 'Yes',
                    'authorization' => is_null($transition->authorization()) ? 'No' : 'Yes',
                    'rules' => json_encode($transition->validationRules(true)),
                    'additional' => json_encode($transition->additional()),
                    'errors' => []
                ];

                try {
                    $this->states->one($transition->source);
                } catch (ItemNotFoundException) {
                    $row['errors'][] = 'Source Not Found';
                    $this->valid = false;
                }
                try {
                    $this->states->one($transition->target);
                } catch (ItemNotFoundException) {
                    $row['errors'][] = 'Target Not Found';
                    $this->valid = false;
                }

                $row['errors'] = implode(', ', $row['errors']);

                return $row;
            })
            ->toArray();
    }

    public function states(): array
    {
        return $this->states
            ->map(function (State $state) {
                $row = [
                    'value' => $state->state instanceof BackedEnum ? $state->state->value : $state->state,
                    'caption' => $state->caption(),
                    'additional' => json_encode($state->additional()),
                    'error' => null
                ];

                try {
                    $this->states->one($state->state);
                } catch (MultipleItemsFoundException) {
                    $row['error'] = "State {$row['value']} defined few times.";
                    $this->valid = false;
                }

                return $row;
            })
            ->toArray();
    }
}
