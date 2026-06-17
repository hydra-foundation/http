<?php

declare(strict_types=1);

namespace Hydra\Http\Attributes;

use Attribute;

/**
 * Declares a route on a controller method:
 *
 *   #[Route('/users/{id}', methods: ['GET'])]
 *   public function show(int $id): ResponseInterface
 *
 * Middleware can be attached per-route as class-strings; the Router resolves
 * them through the container and wraps the handler in a nested pipeline:
 *
 *   #[Route('/admin', middleware: [RequireAuth::class])]
 *
 * Repeatable, so one method can answer several paths/verbs. The RouteScanner
 * reads these and registers them through the Router's fluent API.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /** @var list<string> */
    public readonly array $methods;

    /**
     * @param list<string>|string $methods
     * @param list<class-string>  $middleware  PSR-15 middleware, outermost first
     */
    public function __construct(
        public readonly string $path,
        array|string $methods = ['GET'],
        public readonly array $middleware = [],
    ) {
        $this->methods = array_map(strtoupper(...), (array) $methods);
    }
}
