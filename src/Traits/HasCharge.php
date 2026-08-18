<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Charger;
use Codewiser\Workflow\StateMachine;

trait HasCharge
{
    protected ?Charger $charge = null;

    /**
     * Transition required to be charged to fire.
     */
    public function chargeable(Charger $charge): static
    {
        $this->charge = $charge;

        return $this;
    }

    /**
     * Get transition charge.
     *
     * @internal
     */
    public function charge(StateMachine $machine): ?Charger
    {
        return $this->charge?->inject($machine);
    }
}