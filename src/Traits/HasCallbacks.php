<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\State;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait HasCallbacks
{
    /**
     * Callable collection, that would be invoked after event.
     *
     * @var array
     */
    protected $callbacks = [];

    /**
     * Get registered transition callbacks.
     *
     * @return Collection<callable>
     */
    public function callbacks(): Collection
    {
        return collect($this->callbacks);
    }

    /**
     * Callback(s) will run after transition is done or state is reached.
     */
    public function after(callable $callback): self
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * Run callbacks.
     *
     * @return void
     */
    public function invoke(Model $model, ?State $previous, array $context = [])
    {
        $this->callbacks()
            ->each(function (callable $callback) use ($model, $previous, $context) {

                $params = [];
                $reflection = new \ReflectionFunction($callback);

                foreach ($reflection->getParameters() as $parameter) {
                    if ($parameter->getPosition() == 0) {
                        // Assume first parameter as Model
                        $params[] = $model->fresh();
                    } elseif ($parameter->getName() == 'previous') {
                        $params[] = $previous;
                    } elseif ($parameter->getName() == 'context') {
                        $params[] = $context;
                    }
                }

                $reflection->invokeArgs($params);
            });
    }
}
