<?php

namespace Codewiser\Workflow\Console;

use Codewiser\Workflow\StateMachine;
use Codewiser\Workflow\StateMachineResolver;
use Codewiser\Workflow\Transition;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shows the current state and transitions of a model workflow.
 */
class ShowWorkflowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:show
        {model : Model reference: a morph alias or a class name, with an id as `article#1`}
        {--as= : Inspect as this Authenticatable, e.g. `user#1`}
        {--attr= : Show only the workflow, bound to this attribute}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the current state and transitions of a model';

    /**
     * Execute the console command.
     */
    public function handle(StateMachineResolver $resolver): int
    {
        [$reference, $id] = $this->parseReference(
            (string) $this->argument('model'),
            'model',
            '`workflow:show %s#1`'
        );

        if ($reference === null) {
            return static::FAILURE;
        }

        if (($class = $this->resolveClass($reference, 'model')) === null) {
            return static::FAILURE;
        }

        if (($model = $this->retrieve($class, $reference, $id, 'model')) === null) {
            return static::FAILURE;
        }

        // Authenticatable, to inspect the workflow from its point of view
        $actor = null;
        $actorReference = null;

        if ($this->option('as')) {
            if (($authenticated = $this->authenticatable()) === null) {
                return static::FAILURE;
            }

            [$actor, $actorReference] = $authenticated;
        }

        $workflows = $resolver->collect($model);

        if ($workflows->isEmpty()) {
            $this->warn(sprintf('%s has no workflows.', $class));

            return static::FAILURE;
        }

        $attributes = $workflows->pluck('attribute');

        if ($attribute = $this->option('attr')) {
            $workflows = $workflows->filter(
                fn(StateMachine $engine) => $engine->attribute === $attribute
            );

            if ($workflows->isEmpty()) {
                $this->error(sprintf(
                    'Model has no workflow [%s]. Available: %s',
                    $attribute,
                    $attributes->implode(', ')
                ));

                return static::FAILURE;
            }
        }

        $this->renderSubject($model, $class, $reference, 'Model');

        if ($actor) {
            $this->renderSubject($actor, get_class($actor), $actorReference, 'As');
        }

        foreach ($workflows as $engine) {
            $this->renderWorkflow($engine);
        }

        return static::SUCCESS;
    }

    /**
     * Reference and id, as given to the command.
     *
     * Accepts `user#1` notation.
     *
     * @return array{0: null|string, 1: null|string}
     */
    protected function parseReference(string $input, string $subject, string $hint): array
    {
        [$reference, $id] = array_pad(explode('#', trim($input), 2), 2, '');

        $reference = trim($reference);
        $id = trim($id);

        if ($reference === '') {
            $this->error(sprintf('%s reference is missing.', ucfirst($subject)));

            return [null, null];
        }

        if ($id === '') {
            $this->error(sprintf(
                '%s id is missing. Try %s.',
                ucfirst($subject),
                sprintf($hint, $reference)
            ));

            return [null, null];
        }

        return [$reference, $id];
    }

    /**
     * Model class, resolved from a class name or a morph map alias.
     */
    protected function resolveClass(string $reference, string $subject): ?string
    {
        $class = ltrim($reference, '\\');

        if (is_a($class, Model::class, true)) {
            return $class;
        }

        if (class_exists($class)) {
            $this->error(sprintf('[%s] is not an Eloquent model.', $reference));

            return null;
        }

        if ($morphed = Relation::getMorphedModel($reference)) {
            return $morphed;
        }

        $aliases = array_keys(Relation::morphMap() ?: []);

        $this->error(sprintf('Unable to resolve %s [%s].', $subject, $reference));

        if ($aliases) {
            $this->line('Morph map: '.implode(', ', $aliases));
        }

        $this->line('Pass a class name (e.g. `App\Models\User#1`) or declare a morph map (Relation::morphMap([...])).');

        return null;
    }

    /**
     * The model itself, trashed models included.
     *
     * @param  class-string<Model>  $class
     */
    protected function retrieve(string $class, string $reference, string $id, string $subject): ?Model
    {
        $query = $class::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($class))) {
            $query->withTrashed();
        }

        $model = $query->find($id);

        if (! $model) {
            $this->error(sprintf('%s [%s#%s] was not found.', ucfirst($subject), $reference, $id));
        }

        return $model;
    }

    /**
     * The Authenticatable, chosen with `--as`, authenticated for the run.
     *
     * @return array{0: Model, 1: string}|null
     */
    protected function authenticatable(): ?array
    {
        $as = (string) $this->option('as');

        [$reference, $id] = $this->parseReference($as, 'authenticatable', '`--as=%s#1`');

        if ($reference === null) {
            return null;
        }

        if (! $this->laravel->bound(Factory::class)) {
            $this->error(sprintf('Unable to authenticate: [%s] is not bound in the container.', Factory::class));

            return null;
        }

        if (($class = $this->resolveClass($reference, 'authenticatable')) === null) {
            return null;
        }

        if (($actor = $this->retrieve($class, $reference, $id, 'authenticatable')) === null) {
            return null;
        }

        if (! $actor instanceof Authenticatable) {
            $this->error(sprintf('[%s] is not Authenticatable.', $class));

            return null;
        }

        // Make this user current, so `auth()->user()` in callbacks returns it
        $this->laravel->make(Factory::class)->guard()->setUser($actor);

        return [$actor, $reference];
    }

    protected function renderSubject(Model $model, string $class, string $reference, string $label): void
    {
        $line = sprintf('%s: %s #%s', $label, $class, $model->getKey());

        if ($reference !== $class) {
            $line .= sprintf(' (as %s)', $reference);
        }

        if (method_exists($model, 'trashed') && $model->trashed()) {
            $line .= ' [trashed]';
        }

        $this->line($line);
    }

    protected function renderWorkflow(StateMachine $engine): void
    {
        $state = $engine->state();

        $this->line('');
        $this->line(sprintf('Workflow [%s]', $engine->attribute));

        if (! $state) {
            $this->line('  State is not initialized.');

            return;
        }

        $value = $state->enum->value;
        $caption = $state->caption();

        $this->line(sprintf(
            '  State: %s',
            $caption === $value ? $value : sprintf('%s (%s)', $value, $caption)
        ));

        $transitions = $engine->getTransitionListing()->from($state->enum);

        if ($transitions->isEmpty()) {
            $this->line('  No transitions from this state.');

            return;
        }

        // Targets of transitions a user is allowed to run
        $allowed = $transitions
            ->authorized()
            ->map(fn(Transition $transition) => $transition->target->value)
            ->all();

        $charged = $transitions->contains(
            fn(Transition $transition) => $transition->charger($engine) !== null
        );

        $headers = ['Target', 'Caption', 'Status', 'Issues'];

        if ($charged) {
            $headers[] = 'Charge';
        }

        $rows = $transitions->map(function (Transition $transition) use ($charged, $allowed, $engine) {
            $issues = $transition->issues();

            if ($transition->isForbidden()) {
                $status = 'forbidden';
            } elseif (! in_array($transition->target->value, $allowed, true)) {
                $status = 'unauthorized';
            } elseif ($issues) {
                $status = 'blocked';
            } else {
                $status = 'available';
            }

            $row = [
                $transition->target->value,
                $transition->caption(),
                $status,
                implode(' ', $issues),
            ];

            if ($charged) {
                $row[] = $this->charge($transition, $engine);
            }

            return $row;
        })->all();

        $this->table($headers, $rows);
    }

    /**
     * Charging level of a transition, in percents.
     */
    protected function charge(Transition $transition, StateMachine $engine): string
    {
        if (! $charger = $transition->charger($engine)) {
            return '';
        }

        $level = (int) round($charger->chargingLevel($transition) * 100);

        return min(100, max(0, $level)).'%';
    }
}
