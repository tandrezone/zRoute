<?php

declare(strict_types=1);

namespace zRoute\Tests;

use PHPUnit\Framework\TestCase;
use zRoute\Router;

/**
 * Integration tests for the Router class.
 */
class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    // -----------------------------------------------------------------------
    // Static routes
    // -----------------------------------------------------------------------

    public function testStaticGetRoute(): void
    {
        $this->router->get('/about', static fn($p) => 'about page');

        $this->assertSame('about page', $this->router->dispatch('GET', '/about'));
    }

    public function testRootRoute(): void
    {
        $this->router->get('/', static fn($p) => 'home');

        $this->assertSame('home', $this->router->dispatch('GET', '/'));
    }

    public function testMultipleStaticRoutes(): void
    {
        $this->router
            ->get('/foo', static fn($p) => 'foo')
            ->get('/bar', static fn($p) => 'bar');

        $this->assertSame('foo', $this->router->dispatch('GET', '/foo'));
        $this->assertSame('bar', $this->router->dispatch('GET', '/bar'));
    }

    // -----------------------------------------------------------------------
    // Dynamic routes
    // -----------------------------------------------------------------------

    public function testDynamicRoute(): void
    {
        $this->router->get('/users/$id', static fn($p) => 'user:' . $p['id']);

        $this->assertSame('user:42', $this->router->dispatch('GET', '/users/42'));
    }

    public function testDynamicRouteWithHyphenatedParamName(): void
    {
        $this->router->get(
            '/products/$product-slug',
            static fn($p) => 'product:' . $p['product-slug'],
        );

        $result = $this->router->dispatch('GET', '/products/my-awesome-widget');
        $this->assertSame('product:my-awesome-widget', $result);
    }

    public function testMultipleDynamicParams(): void
    {
        $this->router->get(
            '/users/$userId/posts/$postId',
            static fn($p) => $p['userId'] . '/' . $p['postId'],
        );

        $this->assertSame('7/99', $this->router->dispatch('GET', '/users/7/posts/99'));
    }

    public function testDynamicAndStaticRoutesCoexist(): void
    {
        $this->router
            ->get('/products/featured', static fn($p) => 'featured')
            ->get('/products/$product-slug', static fn($p) => 'product:' . $p['product-slug']);

        // The static route must win when the path matches it exactly.
        $this->assertSame('featured', $this->router->dispatch('GET', '/products/featured'));
        $this->assertSame('product:some-other', $this->router->dispatch('GET', '/products/some-other'));
    }

    // -----------------------------------------------------------------------
    // HTTP methods
    // -----------------------------------------------------------------------

    public function testPostRoute(): void
    {
        $this->router->post('/users', static fn($p) => 'created');

        $this->assertSame('created', $this->router->dispatch('POST', '/users'));
    }

    public function testPutRoute(): void
    {
        $this->router->put('/users/$id', static fn($p) => 'updated:' . $p['id']);

        $this->assertSame('updated:5', $this->router->dispatch('PUT', '/users/5'));
    }

    public function testPatchRoute(): void
    {
        $this->router->patch('/users/$id', static fn($p) => 'patched:' . $p['id']);

        $this->assertSame('patched:3', $this->router->dispatch('PATCH', '/users/3'));
    }

    public function testDeleteRoute(): void
    {
        $this->router->delete('/users/$id', static fn($p) => 'deleted:' . $p['id']);

        $this->assertSame('deleted:9', $this->router->dispatch('DELETE', '/users/9'));
    }

    public function testMethodIsCaseInsensitive(): void
    {
        $this->router->get('/ping', static fn($p) => 'pong');

        $this->assertSame('pong', $this->router->dispatch('get', '/ping'));
    }

    public function testAnyMethodRoute(): void
    {
        $this->router->any('/ping', static fn($p) => 'pong');

        $this->assertSame('pong', $this->router->dispatch('GET', '/ping'));
        $this->assertSame('pong', $this->router->dispatch('POST', '/ping'));
        $this->assertSame('pong', $this->router->dispatch('DELETE', '/ping'));
    }

    // -----------------------------------------------------------------------
    // Method chaining
    // -----------------------------------------------------------------------

    public function testFluentInterface(): void
    {
        $result = $this->router
            ->get('/a', static fn($p) => 'a')
            ->post('/b', static fn($p) => 'b')
            ->put('/c', static fn($p) => 'c')
            ->delete('/d', static fn($p) => 'd');

        $this->assertInstanceOf(Router::class, $result);
        $this->assertCount(4, $this->router->getRoutes());
    }

    // -----------------------------------------------------------------------
    // 404 Not Found
    // -----------------------------------------------------------------------

    public function testNotFoundHandlerIsCalled(): void
    {
        $this->router->notFound(static fn($path) => '404:' . $path);

        $this->assertSame('404:/unknown', $this->router->dispatch('GET', '/unknown'));
    }

    public function testDispatchReturnsNullWhenNoHandlerAndNoMatch(): void
    {
        $this->assertNull($this->router->dispatch('GET', '/unknown'));
    }

    // -----------------------------------------------------------------------
    // 405 Method Not Allowed
    // -----------------------------------------------------------------------

    public function testMethodNotAllowedHandlerIsCalled(): void
    {
        $this->router->get('/users', static fn($p) => 'list');
        $this->router->methodNotAllowed(static fn($method, $path) => '405:' . $method . ':' . $path);

        $this->assertSame('405:POST:/users', $this->router->dispatch('POST', '/users'));
    }

    public function testMethodNotAllowedReturnsNullWhenNoHandler(): void
    {
        $this->router->get('/users', static fn($p) => 'list');

        $this->assertNull($this->router->dispatch('POST', '/users'));
    }

    // -----------------------------------------------------------------------
    // Path normalisation
    // -----------------------------------------------------------------------

    public function testTrailingSlashIsIgnored(): void
    {
        $this->router->get('/about', static fn($p) => 'about');

        $this->assertSame('about', $this->router->dispatch('GET', '/about/'));
    }

    public function testQueryStringIsStripped(): void
    {
        $this->router->get('/search', static fn($p) => 'search');

        $this->assertSame('search', $this->router->dispatch('GET', '/search?q=hello'));
    }

    public function testLeadingSlashIsNormalised(): void
    {
        $this->router->get('/hello', static fn($p) => 'hello');

        $this->assertSame('hello', $this->router->dispatch('GET', 'hello'));
    }

    // -----------------------------------------------------------------------
    // Security: path traversal prevention
    // -----------------------------------------------------------------------

    public function testPathTraversalIsResolved(): void
    {
        $this->router->get('/admin', static fn($p) => 'admin');

        // /products/../admin should resolve to /admin and NOT match /admin
        // (because the attacker expects to reach /admin by traversing up
        //  from /products). The router resolves the segments, so the
        //  normalised path IS /admin — and thus it should match.
        //  What must NOT happen is a bypass that skips the route entirely.
        $result = $this->router->dispatch('GET', '/products/../admin');
        $this->assertSame('admin', $result);
    }

    public function testPathTraversalDoesNotEscapeRoot(): void
    {
        $this->router->get('/', static fn($p) => 'home');

        $this->assertSame('home', $this->router->dispatch('GET', '/../../../'));
    }

    public function testDotSegmentsAreResolved(): void
    {
        $this->router->get('/about', static fn($p) => 'about');

        $this->assertSame('about', $this->router->dispatch('GET', '/./about/.'));
    }

    // -----------------------------------------------------------------------
    // Handler receives correct params
    // -----------------------------------------------------------------------

    public function testHandlerReceivesEmptyParamsForStaticRoute(): void
    {
        $received = null;
        $this->router->get('/static', function (array $params) use (&$received) {
            $received = $params;
        });

        $this->router->dispatch('GET', '/static');
        $this->assertSame([], $received);
    }

    public function testHandlerReceivesExtractedParams(): void
    {
        $received = null;
        $this->router->get('/items/$category/$id', function (array $params) use (&$received) {
            $received = $params;
        });

        $this->router->dispatch('GET', '/items/books/123');
        $this->assertSame(['category' => 'books', 'id' => '123'], $received);
    }
}
