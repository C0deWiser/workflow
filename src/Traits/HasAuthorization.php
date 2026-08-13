<?php

namespace Codewiser\Workflow\Traits;

use Codewiser\Workflow\Context;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

trait HasAuthorization
{
    /**
     * Instructions to authorize transit.
     *
     * null — default authorization
     * string — invoke policy ability
     * callable — will be invoked for authorization (may throw AuthorizationException)
     *
     * @var null|string|callable(Model, Context): (bool|Response)
     */
    protected $authorization = null;

    /**
     * Authorize transition using this.
     *
     * @param  null|string|callable(Model, Context): (bool|Response)  $authorization Ability or callable.
     */
    public function authorizedBy(callable|string|null $authorization): static
    {
        $this->authorization = $authorization;

        return $this;
    }
}