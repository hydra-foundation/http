# Hydra HTTP

The request lifecycle: a PSR-15 middleware pipeline wrapping a router, typed
HTTP errors, and the glue that turns a controller's return value into an emitted
PSR-7 response. Depends only on PSR-7/-15/-17 interfaces; the concrete request,
response, and factory implementations are the application's to bind.

## Lifecycle

`HttpKernel` is pure glue: capture the request, hand it to the application
handler — typically a `Pipeline` wrapping the `Router` — and emit the response.
The kernel knows nothing about middleware or routing itself.

`Pipeline` runs the request inward through each middleware to the innermost
handler; the response unwinds back outward. The chain is rebuilt from an
immutable middleware list on every dispatch, so one instance serves many
requests.

## Routing

Routes are declared with `#[Route]` attributes on controller methods:

```php
#[Route('/users/{id}', methods: ['GET'])]
public function show(int $id): ResponseInterface

#[Route('/admin', middleware: [AuthenticateMiddleware::class])]
public function index(): ResponseInterface
```

The attribute is repeatable. `RouteScanner` reflects these into a plain,
serializable list (no closures); `Router::loadRoutes()` applies them. Per-route
middleware class-strings are resolved through the container into a nested
pipeline.

Because that list is closure-free, `RouteCache` compiles it to a PHP file that
`return`s the array — loaded with a `require`, so opcache keeps the compiled
routes resident instead of reflecting on every request:

```php
$cache = new RouteCache('/path/to/bootstrap/cache/routes.php');
$routes = $cache->load();           // null on a cold cache

if ($routes === null) {
    $routes = (new RouteScanner)->scan($controllers);
    $cache->store($routes);         // atomic write, creates the dir
}

$router->loadRoutes($routes);
```

`RouteCache` owns only the file mechanics — load, atomic store, opcache
invalidation. *Whether* to cache is the caller's policy: the app skeleton gates
this on a `ROUTE_CACHE` flag (off in dev so route edits take effect immediately),
and rebuilding after a route change is just deleting the file.

A class-level `#[RouteGroup]` shares a path prefix and/or middleware across all
of a controller's routes:

```php
#[RouteGroup('/admin', middleware: [AuthenticateMiddleware::class])]
final class AdminController
{
    #[Route('/')]       // → /admin,        middleware: [AuthenticateMiddleware]
    #[Route('/users')]  // → /admin/users,  middleware: [AuthenticateMiddleware]
}
```

Grouping is purely a scan-time concern: `RouteScanner` folds the prefix and
middleware into each route, so `Router`, `Route`, and `#[Route]` never learn
about groups and the output stays a flat, cacheable list. The prefix is
canonicalized (a group root `/` collapses to the bare prefix), group middleware
runs outermost, and a repeated `#[RouteGroup]` on one class fails loud at scan
time.

## Argument resolution

`ArgumentResolver` reflects the target's signature and fills each parameter:
the request (by type-hint, any name), a `{placeholder}` route value coerced to
the declared scalar type, a default/null, or — failing all of those — a
`LogicException`, because the signature asked for something routing can't
supply. A placeholder value that doesn't fit its declared type is a non-match
(→ 404), not a coercion.

## Errors

Any layer signals failure by throwing an `HttpException` (which carries its own
status and headers). `ErrorHandlerMiddleware` is the single authority that turns
errors into responses: a mapped `HttpException` becomes its status; any other
`Throwable` becomes a 500, so a controller bug never leaks a raw fatal. Faults
(5xx) are forwarded to a PSR-3 logger under the `exception` context key;
expected 4xx client errors are not. The logger defaults to a `NullLogger`, so
logging is opt-in and the catch path needs no null checks.

This package ships no `ServiceProvider`; the application wires the kernel,
pipeline, router, and PSR-7/-17 implementations at its composition root.
