<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Charge;
use Codewiser\Workflow\State;
use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\Transition;

trait HasCharge
{
    protected ?Charge $charge = null;

    /**
     * Transition required to be charged to fire.
     */
    public function chargeable(Charge $charge): static
    {
        $this->charge = $charge;

        return $this;
    }

    /**
     * Get transition charge.
     */
    public function charge(StateMachine $machine): ?Charge
    {
        return $this->charge?->inject($machine);
    }
}