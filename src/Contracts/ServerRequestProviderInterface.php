<?php

declare(strict_types=1);

namespace Hydra\Http\Contracts;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Builds the incoming server request from the current environment.
 *
 * This is deliberately separate from PSR-17's ServerRequestFactoryInterface
 * (which builds a request from an explicit method + URI). The concrete
 * implementation — e.g. an adapter over nyholm's ServerRequestCreator — lives
 * in the application so the http layer stays free of any PSR-7 implementation.
 */
interface ServerRequestProviderInterface
{
    public function fromGlobals(): ServerRequestInterface;
}
