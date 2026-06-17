<?php

declare(strict_types=1);

namespace Hydra\Http\Tests\Unit;

use Hydra\Http\RouteCache;
use PHPUnit\Framework\TestCase;

final class RouteCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hydra-routecache-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }

        // The cache may live a level down (the missing-directory test), so clean
        // recursively rather than assuming a flat directory.
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->dir);
    }

    /** A representative scan result: plain arrays, class-strings, empty and populated middleware. */
    private function sampleRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/posts',
                'handler' => ['App\\Controllers\\BlogController', 'index'],
                'middleware' => [],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/users',
                'handler' => ['App\\Controllers\\AdminController', 'store'],
                'middleware' => ['App\\Middleware\\Authenticate', 'App\\Middleware\\RequireAdmin'],
            ],
        ];
    }

    public function testLoadReturnsNullWhenNoCacheFileExists(): void
    {
        $cache = new RouteCache($this->dir . '/routes.php');

        $this->assertNull($cache->load());
    }

    public function testStoreThenLoadRoundTripsTheRoutesExactly(): void
    {
        $cache = new RouteCache($this->dir . '/routes.php');
        $routes = $this->sampleRoutes();

        $cache->store($routes);

        // Same values AND same order/keys — the Router iterates in order, so a
        // reordered cache would silently change matching precedence.
        $this->assertSame($routes, $cache->load());
    }

    public function testStoreCreatesMissingParentDirectories(): void
    {
        $path = $this->dir . '/bootstrap/cache/routes.php';
        $cache = new RouteCache($path);

        $cache->store($this->sampleRoutes());

        $this->assertFileExists($path);
        $this->assertSame($this->sampleRoutes(), $cache->load());
    }

    public function testStoredFileIsPlainPhpThatReturnsTheRoutes(): void
    {
        $path = $this->dir . '/routes.php';
        (new RouteCache($path))->store($this->sampleRoutes());

        // The artifact is a require-able PHP file (opcache-friendly), not a blob.
        $this->assertStringStartsWith('<?php', file_get_contents($path));
        $this->assertSame($this->sampleRoutes(), require $path);
    }

    public function testClearRemovesTheCacheAndReportsIt(): void
    {
        $path = $this->dir . '/routes.php';
        $cache = new RouteCache($path);
        $cache->store($this->sampleRoutes());

        $this->assertTrue($cache->clear());
        $this->assertFileDoesNotExist($path);
        $this->assertNull($cache->load());
    }

    public function testClearOnAColdCacheIsANoOp(): void
    {
        // Clearing an absent cache is success, not an error — it just had
        // nothing to remove.
        $this->assertFalse((new RouteCache($this->dir . '/routes.php'))->clear());
    }

    public function testStoreOverwritesAnExistingCache(): void
    {
        $cache = new RouteCache($this->dir . '/routes.php');
        $cache->store($this->sampleRoutes());

        $rebuilt = [[
            'method' => 'GET',
            'path' => '/health',
            'handler' => ['App\\Controllers\\HomeController', 'health'],
            'middleware' => [],
        ]];
        $cache->store($rebuilt);

        // Rebuilding (delete + repopulate is the deploy story; here just a
        // second store) fully replaces the previous artifact.
        $this->assertSame($rebuilt, $cache->load());
    }
}
