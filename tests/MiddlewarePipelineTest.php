<?php

declare(strict_types=1);

namespace zRoute\Tests;

use PHPUnit\Framework\TestCase;
use zRoute\Contracts\MiddlewareInterface;
use zRoute\MiddlewarePipeline;
use zRoute\Request;

/**
 * Unit tests for the MiddlewarePipeline class.
 */
class MiddlewarePipelineTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers — anonymous middleware factories
    // -----------------------------------------------------------------------

    /**
     * Build a middleware class name whose handle() appends a marker to a shared
     * log and then calls $next.
     *
     * @param  string[] $log Shared reference for execution-order tracking.
     * @param  string   $id  Marker appended before and after calling $next.
     * @return class-string<MiddlewareInterface>
     */
    private function makeMiddlewareClass(array &$log, string $id): string
    {
        // We need a concrete named class (not an anonymous class) because the
        // pipeline instantiates middleware by class name string.
        $className = 'TestMiddleware_' . md5($id . uniqid('', true));

        // Capture $log by reference in the eval'd class via a static registry.
        MiddlewarePipelineTest::$logRegistry[$className] = &$log;
        MiddlewarePipelineTest::$idRegistry[$className]  = $id;

        eval(
            'class ' . $className . ' implements \zRoute\Contracts\MiddlewareInterface {
                public function handle(\zRoute\Request $request, callable $next): mixed {
                    $log =& \zRoute\Tests\MiddlewarePipelineTest::$logRegistry[self::class];
                    $id  = \zRoute\Tests\MiddlewarePipelineTest::$idRegistry[self::class];
                    $log[] = $id . "-before";
                    $result = $next($request);
                    $log[] = $id . "-after";
                    return $result;
                }
            }'
        );

        return $className;
    }

    /** @var array<string, array<int, string>> */
    public static array $logRegistry = [];

    /** @var array<string, string> */
    public static array $idRegistry  = [];

    private function makeRequest(): Request
    {
        return new Request('GET', '/');
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function testEmptyPipelineCallsDestinationDirectly(): void
    {
        $pipeline = new MiddlewarePipeline([]);
        $result   = $pipeline->run($this->makeRequest(), static fn($r) => 'done');

        $this->assertSame('done', $result);
    }

    public function testSingleMiddlewareWrapsDestination(): void
    {
        $log = [];
        $mw  = $this->makeMiddlewareClass($log, 'mw');

        $pipeline = new MiddlewarePipeline([$mw]);
        $result   = $pipeline->run($this->makeRequest(), static fn($r) => 'done');

        $this->assertSame('done', $result);
        $this->assertSame(['mw-before', 'mw-after'], $log);
    }

    public function testMultipleMiddlewareExecuteInOrder(): void
    {
        $log = [];
        $mw1 = $this->makeMiddlewareClass($log, 'first');
        $mw2 = $this->makeMiddlewareClass($log, 'second');

        $pipeline = new MiddlewarePipeline([$mw1, $mw2]);
        $pipeline->run($this->makeRequest(), static fn($r) => 'done');

        // Outer (first) wraps inner (second): first-before, second-before, second-after, first-after
        $this->assertSame(
            ['first-before', 'second-before', 'second-after', 'first-after'],
            $log,
        );
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $log = [];
        $mw1 = $this->makeMiddlewareClass($log, 'first');

        // A middleware that never calls $next.
        $shortCircuitClass = 'ShortCircuit_' . md5(uniqid('', true));
        eval(
            'class ' . $shortCircuitClass . ' implements \zRoute\Contracts\MiddlewareInterface {
                public function handle(\zRoute\Request $request, callable $next): mixed {
                    return "short-circuited";
                }
            }'
        );

        $pipeline = new MiddlewarePipeline([$mw1, $shortCircuitClass]);
        $result   = $pipeline->run($this->makeRequest(), static fn($r) => 'destination');

        $this->assertSame('short-circuited', $result);
        // mw1 is the OUTER layer; shortCircuit is the INNER layer.
        // mw1 calls $next(), which invokes shortCircuit.  ShortCircuit returns
        // 'short-circuited' without calling destination — but it still returns
        // normally to mw1.  mw1 therefore receives the value from $next() and
        // continues to execute its after-logic ('first-after').
        // Only 'destination' is bypassed, not the outer middleware's tail.
        $this->assertSame(['first-before', 'first-after'], $log);
    }

    public function testMiddlewareReceivesAndCanModifyRequest(): void
    {
        $received = null;

        $modifyClass = 'ModifyMiddleware_' . md5(uniqid('', true));
        eval(
            'class ' . $modifyClass . ' implements \zRoute\Contracts\MiddlewareInterface {
                public function handle(\zRoute\Request $request, callable $next): mixed {
                    $modified = $request->withPathParams(["injected" => "yes"]);
                    return $next($modified);
                }
            }'
        );

        $pipeline = new MiddlewarePipeline([$modifyClass]);
        $pipeline->run($this->makeRequest(), function (Request $req) use (&$received): string {
            $received = $req->getPathParams();
            return 'ok';
        });

        $this->assertSame(['injected' => 'yes'], $received);
    }

    public function testPipelineReturnValueIsPropagated(): void
    {
        $log = [];
        $mw  = $this->makeMiddlewareClass($log, 'x');

        $pipeline = new MiddlewarePipeline([$mw]);
        $result   = $pipeline->run($this->makeRequest(), static fn($r) => 42);

        $this->assertSame(42, $result);
    }
}
