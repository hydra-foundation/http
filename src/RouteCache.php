<?php

declare(strict_types=1);

namespace Hydra\Http;

/**
 * Reads and writes the compiled route cache: the plain array produced by
 * RouteScanner::scan(), written as a PHP file that `return`s it.
 *
 * A PHP file rather than a serialized blob, so opcache keeps the compiled form
 * resident and loading the cache is a near-free require — not an unserialize on
 * every request. The scanner's output is all arrays and class-strings (no
 * closures, no objects), so var_export round-trips it exactly; RouteScannerTest
 * guards that the shape stays this plain.
 *
 * Population is the caller's policy (see the app's Router binding): load(), and
 * on a miss scan once and store(). This class owns only the file mechanics, not
 * whether or when to cache. Rebuilding after a route change is just deleting the
 * file — the next request repopulates it.
 */
final class RouteCache
{
    public function __construct(private readonly string $path) {}

    /**
     * The cached route definitions, or null when no cache file exists yet.
     *
     * @return list<array{method: string, path: string, handler: array{0: class-string, 1: string}, middleware: list<class-string>}>|null
     */
    public function load(): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }

        return require $this->path;
    }

    /**
     * Compile the route definitions to the cache file. The parent directory is
     * created if missing, and the write is atomic — written to a temp file in
     * the same directory then renamed over the target — so a concurrent request
     * can never read a half-written cache.
     *
     * @param list<array{method: string, path: string, handler: array{0: class-string, 1: string}, middleware: list<class-string>}> $routes
     */
    public function store(array $routes): void
    {
        $dir = dirname($this->path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $contents = '<?php' . PHP_EOL . PHP_EOL
            . 'return ' . var_export($routes, true) . ';' . PHP_EOL;

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
}
