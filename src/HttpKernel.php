<?php

declare(strict_types=1);

namespace Hydra\Http;

use Hydra\Core\Contracts\KernelInterface;
use Hydra\Http\Contracts\EmitterInterface;
use Hydra\Http\Contracts\ServerRequestProviderInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Drives the HTTP request lifecycle.
 *
 * The kernel is pure glue: capture the incoming request, hand it to the
 * application handler, and emit the response. The handler is whatever the
 * application assembled — typically a Pipeline wrapping the Router — so the
 * kernel itself knows nothing about middleware or routing.
 */
final class HttpKernel implements KernelInterface
{
    public function __construct(
        private readonly ServerRequestProviderInterface $requests,
        private readonly RequestHandlerInterface $handler,
        private readonly EmitterInterface $emitter,
    ) {}

    public function handle(): void
    {
        $this->emitter->emit(
            $this->handler->handle($this->requests->fromGlobals())
        );
    }

    public function terminate(): void
    {
    }
}
