<?php

declare(strict_types=1);

namespace Hydra\Http\Tests\Unit;

use Hydra\Http\Contracts\EmitterInterface;
use Hydra\Http\Contracts\ServerRequestProviderInterface;
use Hydra\Http\HttpKernel;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Records the response it was asked to emit. */
final class CapturingEmitter implements EmitterInterface
{
    public ?ResponseInterface $emitted = null;

    public function emit(ResponseInterface $response): void
    {
        $this->emitted = $response;
    }
}

final class HttpKernelTest extends TestCase
{
    public function testHandleCapturesRequestRunsHandlerAndEmitsResponse(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $requests = $this->createStub(ServerRequestProviderInterface::class);
        $requests->method('fromGlobals')->willReturn($request);

        // The application handler must receive exactly the captured request.
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $emitter = new CapturingEmitter;

        (new HttpKernel($requests, $handler, $emitter))->handle();

        $this->assertSame($response, $emitter->emitted, 'the handler response is what gets emitted');
    }
}
