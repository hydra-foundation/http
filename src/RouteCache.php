<?php

declare(strict_types=1);

namespace Hydra\Http;

/**
 * Reads and writes the compiled route cache: the plain array produced by
 * RouteScanner::scan(), written as a PHP file that `return`s it alongside a
 * fingerprint of the controllers list it was compiled from.
 *
 * A PHP file rather than a serialized blob, so opcache keeps the compiled form
 * resident and loading the cache is a near-free require — not an unserialize on
 * every request. The scanner's output is all arrays and class-strings (no
 * closures, no objects), so var_export round-trips it exactly; RouteScannerTest
 * guards that the shape stays this plain.
 *
 * The fingerprint guards against a stale artifact: when the app's controllers
 * list changes (a controller added, removed, or reordered) after `route:cache`
 * was run, load() reports a miss instead of serving routes for a list that no
 * longer exists, and the caller's cold-cache fallback re-scans. A cache file
 * from before the fingerprint existed is treated the same way — stale, not
 * broken. Note the fingerprint covers the LIST, not the controllers' contents:
 * editing #[Route] attributes on an already-listed controller still requires
 * re-running `route:cache` (the deploy contract, unchanged).
 *
 * Population is the caller's policy (see the kernel's Router binding): load(),
 * and on a miss scan once and store(). This class owns only the file mechanics,
 * not whether or when to cache. Rebuilding after a route change is just deleting
 * the file — the next request repopulates it.
 */
final class RouteCache
{
    /**
     * @param list<class-string> $controllers the list the cache is compiled
     *                                        from; its fingerprint is embedded
     *                                        on store() and checked on load()
     */
    public function __construct(
        private readonly string $path,
        private readonly array $controllers,
    ) {}

    /**
     * The cached route definitions, or null when no cache file exists yet, the
     * file predates the fingerprint format, or the fingerprint does not match
     * the current controllers list (a stale artifact from a different deploy).
     *
     * @return list<array{method: string, path: string, handler: array{0: class-string, 1: string}, middleware: list<class-string>}>|null
     */
    public function load(): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }

        $cached = require $this->path;

        if (
            !is_array($cached)
            || !isset($cached['controllers'], $cached['routes'])
            || $cached['controllers'] !== $this->fingerprint()
        ) {
            return null;
        }

        return $cached['routes'];
    }

    /**
     * Compile the route definitions to the cache file, fingerprinted with the
     * controllers list they were scanned from. The parent directory is created
     * if missing, and the write is atomic — written to a temp file in the same
     * directory then renamed over the target — so a concurrent request can
     * never read a half-written cache.
     *
     * @param list<array{method: string, path: string, handler: array{0: class-string, 1: string}, middleware: list<class-string>}> $routes
     */
    public function store(array $routes): void
    {
        $dir = dirname($this->path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $artifact = [
            'controllers' => $this->fingerprint(),
            'routes' => $routes,
        ];

        $contents = '<?php' . PHP_EOL . PHP_EOL
            . 'return ' . var_export($artifact, true) . ';' . PHP_EOL;

        $tmp = tempnam($dir, 'routes-');
        file_put_contents($tmp, $contents);
        rename($tmp, $this->path);

        // Drop any previously-compiled version opcache may be holding for this
        // path so a rebuild (delete + repopulate) takes effect on the next
        // request rather than waiting for opcache to notice the new mtime.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->path, true);
        }
    }

    /**
     * Remove the cache file if it exists, returning whether anything was
     * deleted. A no-op (false) when the cache was already cold — clearing an
     * absent cache is success, not an error.
     */
    public function clear(): bool
    {
        if (!is_file($this->path)) {
            return false;
        }

        unlink($this->path);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->path, true);
        }

        return true;
    }

    /**
     * The controllers-list fingerprint embedded in the artifact. Order-sensitive
     * on purpose: reordering the list reorders the scan output, which changes
     * route matching precedence — a different list is a different cache.
     */
    private function fingerprint(): string
    {
        return sha1(implode("\n", $this->controllers));
    }
}
