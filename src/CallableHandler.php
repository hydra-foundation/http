<?php

declare(strict_types=1);

namespace Hydra\Http;

use Hydra\Http\Contracts\ArgumentResolverInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adapts a callable into a PSR-15 request handler.
 *
 * Rather than calling the target with a fixed (ServerRequestInterface), it asks
 * an {@see ArgumentResolverInterface} to build the arguments from the target's
 * own signature and the route's matched parameters. A controller can therefore
 * declare just what it needs:
 *   fn(int $id): ResponseInterface
 *   fn(ServerRequestInterface $request, string $slug): ResponseInterface
 *
 * This is the seam the router uses to turn a route's closure or controller into
 * the innermost handler of the pipeline.
 */
final class CallableHandler implements RequestHandlerInterface
{
    /** @var callable */
    private $handler;

    /** @param array<string, string> $routeParams matched placeholders for this request */
    public function __construct(
        callable $handler,
        private readonly ArgumentResolverInterface $arguments,
        private readonly array $routeParams = [],
    ) {
        $this->handler = $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->handler)(...$this->arguments->resolve($this->handler, $request, $this->routeParams));
    }
}
