<?php

declare(strict_types=1);

namespace Hydra\Http\Attributes;

use Attribute;

/**
 * Declares a group on a controller class: a shared path prefix and/or shared
 * middleware applied to every #[Route] on the class's methods.
 *
 *   #[RouteGroup('/admin', middleware: [AuthenticateMiddleware::class])]
 *   final class AdminController
 *   {
 *       #[Route('/')]       // → /admin,        middleware: [Authenticate]
 *       #[Route('/users')]  // → /admin/users,  middleware: [Authenticate]
 *   }
 *
 * Grouping is purely a scan-time concern: the RouteScanner folds the prefix and
 * middleware into each method's route, so the Router and Route value object
 * never learn about groups. The group's middleware runs outermost, before any
 * the method declares for itself.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class RouteGroup
{
    /**
     * @param string             $prefix      prepended to each method route's path
     * @param list<class-string> $middleware  PSR-15 middleware, outermost first
     */
    public function __construct(
        public readonly string $prefix = '',
        public readonly array $middleware = [],
    ) {}
}
