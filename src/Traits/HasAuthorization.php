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
     * callable — will be invoked for authorization (may throw AuthorizationException)
     *
     * @var null|callable(Model, Context): (bool|Response)
     */
    protected $authorization = null;

    /**
     * Authorize transition using this.
     *
     * @param  null|callable(Model, Context): (bool|Response)  $authorization May throw AuthorizationException.
     */
    public function authorizedBy(?callable $authorization): static
    {
        $this->authorization = $authorization;

        return $this;
    }
}