<?php

declare(strict_types=1);

namespace zRoute\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use zRoute\Contracts\MiddlewareInterface;
use zRoute\Exceptions\ValidationException;
use zRoute\Request;
use zRoute\RouteLoader;
use zRoute\Router;

/**
 * Integration tests for RouteLoader — verifies that array-based route
 * definitions are correctly parsed, validated, and dispatched through the
 * middleware pipeline to the controller callback.
 */
class RouteLoaderTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        // Seed superglobals so Request::fromGlobals() inside handlers works.
        $_GET    = [];
        $_POST   = [];
        $_SERVER = array_merge($_SERVER, ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
    }

    // -----------------------------------------------------------------------
    // Basic loading
    // -----------------------------------------------------------------------

    public function testStaticRouteIsRegistered(): void
    {
        $this->router->loadFromArray([
            [
                'name'       => 'test.static',
                'method'     => 'GET',
                'path'       => '/hello',
                'callback'   => static fn(Request $req) => 'hello',
                'middleware' => [],
                'parameters' => [],
            ],
        ]);

        $this->assertSame('hello', $this->router->dispatch('GET', '/hello'));
    }

    public function testDynamicCurlyBraceRouteExtractsParam(): void
    {
        $this->router->loadFromArray([
            [
                'name'       => 'test.show',
                'method'     => 'GET',
                'path'       => '/resources/{id}',
                'callback'   => static fn(Request $req) => 'id=' . $req->getParam('id'),
                'middleware' => [],
                'parameters' => [
                    'id' => ['type' => 'integer', 'source' => 'path', 'regex' => '[0-9]+'],
                ],
            ],
        ]);

        $this->assertSame('id=7', $this->router->dispatch('GET', '/resources/7'));
    }

    public function testCallbackAsArrayPairIsInstantiatedAndInvoked(): void
    {
        $controllerClass = $this->makeControllerClass('hello-from-class');

        $this->router->loadFromArray([
            [
                'name'       => 'test.class',
                'method'     => 'GET',
                'path'       => '/class-route',
                'callback'   => [$controllerClass, 'handle'],
                'middleware' => [],
                'parameters' => [],
            ],
        ]);

        $this->assertSame('hello-from-class', $this->router->dispatch('GET', '/class-route'));
    }

    // -----------------------------------------------------------------------
    // Parameter validation
    // -----------------------------------------------------------------------

    public function testDefaultValueIsApplied(): void
    {
        $received = null;

        $this->router->loadFromArray([
            [
                'method'     => 'GET',
                'path'       => '/paged',
                'callback'   => function (Request $req) use (&$received) {
                    $received = $req->getParam('page');
                    return 'ok';
                },
                'middleware' => [],
                'parameters' => [
                    'page' => ['type' => 'integer', 'required' => false, 'default' => 1],
                ],
            ],
        ]);

        $_GET = [];
        $this->router->dispatch('GET', '/paged');
        $this->assertSame(1, $received);
    }

    public function testQueryParamIsValidatedAndCast(): void
    {
        $received = null;

        $this->router->loadFromArray([
            [
                'method'     => 'GET',
                'path'       => '/search',
                'callback'   => function (Request $req) use (&$received) {
                    $received = $req->getParam('per_page');
                    return 'ok';
                },
                'middleware' => [],
                'parameters' => [
                    'per_page' => ['type' => 'integer', 'required' => false, 'default' => 15],
                ],
            ],
        ]);

        $_GET = ['per_page' => '30'];
        $this->router->dispatch('GET', '/search');
        $this->assertSame(30, $received);
    }

    // -----------------------------------------------------------------------
    // Middleware pipeline
    // -----------------------------------------------------------------------

    public function testMiddlewareIsExecutedBeforeHandler(): void
    {
        $log = [];

        $mwClass = $this->makeLoggingMiddlewareClass($log, 'auth');

        $this->router->loadFromArray([
            [
                'method'     => 'GET',
                'path'       => '/protected',
                'callback'   => function (Request $req) use (&$log) {
                    $log[] = 'handler';
                    return 'protected';
                },
                'middleware' => [$mwClass],
                'parameters' => [],
            ],
        ]);

        $this->router->dispatch('GET', '/protected');

        $this->assertSame(['auth-before', 'handler', 'auth-after'], $log);
    }

    public function testMultipleMiddlewareRunInDeclarationOrder(): void
    {
        $log  = [];
        $mw1  = $this->makeLoggingMiddlewareClass($log, 'first');
        $mw2  = $this->makeLoggingMiddlewareClass($log, 'second');

        $this->router->loadFromArray([
            [
                'method'     => 'POST',
                'path'       => '/submit',
                'callback'   => function (Request $req) use (&$log) {
                    $log[] = 'handler';
                    return 'submitted';
                },
                'middleware' => [$mw1, $mw2],
                'parameters' => [],
            ],
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->router->dispatch('POST', '/submit');

        $this->assertSame(['first-before', 'second-before', 'handler', 'second-after', 'first-after'], $log);
    }

    // -----------------------------------------------------------------------
    // Config-driven path resolution
    // -----------------------------------------------------------------------

    public function testConfigPathIsResolvedFromConfigArray(): void
    {
        $this->router->loadFromArray(
            [
                [
                    'method'     => 'GET',
                    'path'       => 'config[app.home_route]',
                    'callback'   => static fn(Request $req) => 'home',
                    'middleware' => [],
                    'parameters' => [],
                ],
            ],
            ['app' => ['home_route' => '/dashboard']],
        );

        $this->assertSame('home', $this->router->dispatch('GET', '/dashboard'));
    }

    public function testMissingConfigKeyThrows(): void
    {
        $this->expectException(RuntimeException::class);

        $this->router->loadFromArray(
            [
                [
                    'method'     => 'GET',
                    'path'       => 'config[missing.key]',
                    'callback'   => static fn(Request $req) => 'x',
                    'middleware' => [],
                    'parameters' => [],
                ],
            ],
            [],
        );
    }

    // -----------------------------------------------------------------------
    // Named routes
    // -----------------------------------------------------------------------

    public function testNamedRouteIsRegisteredInRegistry(): void
    {
        $this->router->loadFromArray([
            [
                'name'       => 'user.show',
                'method'     => 'GET',
                'path'       => '/users/{id}',
                'callback'   => static fn(Request $req) => 'user',
                'middleware' => [],
                'parameters' => [],
            ],
        ]);

        $this->assertNotNull($this->router->getNamedRoute('user.show'));
        $this->assertSame('user.show', $this->router->getNamedRoute('user.show')?->getName());
    }

    public function testUnknownNamedRouteReturnsNull(): void
    {
        $this->assertNull($this->router->getNamedRoute('does.not.exist'));
    }

    // -----------------------------------------------------------------------
    // Routes without callback are skipped
    // -----------------------------------------------------------------------

    public function testDefinitionWithoutCallbackIsSkipped(): void
    {
        $this->router->loadFromArray([
            [
                'name'    => 'stub',
                'method'  => 'GET',
                'path'    => '/stub',
                'purpose' => 'No callback, should be skipped.',
                // 'callback' intentionally absent
            ],
        ]);

        $this->assertNull($this->router->dispatch('GET', '/stub'));
    }

    // -----------------------------------------------------------------------
    // loadFromFile
    // -----------------------------------------------------------------------

    public function testLoadFromFileThrowsForNonArrayReturn(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'zroute_test_') . '.php';
        file_put_contents($tmpFile, '<?php return "not an array";');

        try {
            $this->expectException(\InvalidArgumentException::class);
            (new RouteLoader())->loadFromFile($this->router, $tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testLoadFromFileRegistersRoutes(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'zroute_test_') . '.php';
        file_put_contents($tmpFile, '<?php return [
            [
                "name"       => "file.route",
                "method"     => "GET",
                "path"       => "/from-file",
                "callback"   => static fn(\zRoute\Request $r) => "from-file",
                "middleware" => [],
                "parameters" => [],
            ],
        ];');

        try {
            $this->router->loadFromFile($tmpFile);
            $this->assertSame('from-file', $this->router->dispatch('GET', '/from-file'));
        } finally {
            @unlink($tmpFile);
        }
    }

    // -----------------------------------------------------------------------
    // Internal test helpers
    // -----------------------------------------------------------------------

    /** @return class-string */
    private function makeControllerClass(string $returnValue): string
    {
        $className = 'TestController_' . md5($returnValue . uniqid('', true));
        RouteLoaderTest::$controllerValues[$className] = $returnValue;

        eval(
            'class ' . $className . ' {
                public function handle(\zRoute\Request $req): mixed {
                    return \zRoute\Tests\RouteLoaderTest::$controllerValues[self::class];
                }
            }'
        );

        return $className;
    }

    /** @return class-string<MiddlewareInterface> */
    private function makeLoggingMiddlewareClass(array &$log, string $id): string
    {
        $className = 'LogMiddleware_' . md5($id . uniqid('', true));
        RouteLoaderTest::$loaderLogRegistry[$className] = &$log;
        RouteLoaderTest::$loaderIdRegistry[$className]  = $id;

        eval(
            'class ' . $className . ' implements \zRoute\Contracts\MiddlewareInterface {
                public function handle(\zRoute\Request $request, callable $next): mixed {
                    $log =& \zRoute\Tests\RouteLoaderTest::$loaderLogRegistry[self::class];
                    $id  = \zRoute\Tests\RouteLoaderTest::$loaderIdRegistry[self::class];
                    $log[] = $id . "-before";
                    $result = $next($request);
                    $log[] = $id . "-after";
                    return $result;
                }
            }'
        );

        return $className;
    }

    /** @var array<string, string> */
    public static array $controllerValues    = [];

    /** @var array<string, array<int, string>> */
    public static array $loaderLogRegistry   = [];

    /** @var array<string, string> */
    public static array $loaderIdRegistry    = [];
}
