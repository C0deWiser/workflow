<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Charger;
use Codewiser\Workflow\StateMachine;

trait HasCharge
{
    protected ?Charger $charger = null;

    /**
     * Transition requires to be charged to fire.
     */
    public function chargeable(Charger $charger): static
    {
        $this->charger = $charger;

        return $this;
    }

    /**
     * Get transition charger.
     *
     * @internal
     */
    public function charger(StateMachine $machine): ?Charger
    {
        return $this->charger?->inject($machine);
    }
}