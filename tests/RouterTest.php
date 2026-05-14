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

    private function routeName(string $suffix): string
    {
        return $this->name() . '.' . $suffix;
    }

    // -----------------------------------------------------------------------
    // Static routes
    // -----------------------------------------------------------------------

    public function testStaticGetRoute(): void
    {
        $this->router->get($this->routeName('about'), '/about', static fn($p) => 'about page');

        $this->assertSame('about page', $this->router->dispatchRequest('GET', '/about'));
    }

    public function testRootRoute(): void
    {
        $this->router->get($this->routeName('home'), '/', static fn($p) => 'home');

        $this->assertSame('home', $this->router->dispatchRequest('GET', '/'));
    }

    public function testMultipleStaticRoutes(): void
    {
        $this->router
            ->get($this->routeName('foo'), '/foo', static fn($p) => 'foo')
            ->get($this->routeName('bar'), '/bar', static fn($p) => 'bar');

        $this->assertSame('foo', $this->router->dispatchRequest('GET', '/foo'));
        $this->assertSame('bar', $this->router->dispatchRequest('GET', '/bar'));
    }

    // -----------------------------------------------------------------------
    // Dynamic routes
    // -----------------------------------------------------------------------

    public function testDynamicRoute(): void
    {
        $this->router->get($this->routeName('users.show'), '/users/$id', static fn($p) => 'user:' . $p['id']);

        $this->assertSame('user:42', $this->router->dispatchRequest('GET', '/users/42'));
    }

    public function testDynamicRouteWithHyphenatedParamName(): void
    {
        $this->router->get(
            $this->routeName('products.show'),
            '/products/$product-slug',
            static fn($p) => 'product:' . $p['product-slug'],
        );

        $result = $this->router->dispatchRequest('GET', '/products/my-awesome-widget');
        $this->assertSame('product:my-awesome-widget', $result);
    }

    public function testMultipleDynamicParams(): void
    {
        $this->router->get(
            $this->routeName('users.posts.show'),
            '/users/$userId/posts/$postId',
            static fn($p) => $p['userId'] . '/' . $p['postId'],
        );

        $this->assertSame('7/99', $this->router->dispatchRequest('GET', '/users/7/posts/99'));
    }

    public function testDynamicAndStaticRoutesCoexist(): void
    {
        $this->router
            ->get($this->routeName('products.featured'), '/products/featured', static fn($p) => 'featured')
            ->get($this->routeName('products.show'), '/products/$product-slug', static fn($p) => 'product:' . $p['product-slug']);

        // The static route must win when the path matches it exactly.
        $this->assertSame('featured', $this->router->dispatchRequest('GET', '/products/featured'));
        $this->assertSame('product:some-other', $this->router->dispatchRequest('GET', '/products/some-other'));
    }

    // -----------------------------------------------------------------------
    // HTTP methods
    // -----------------------------------------------------------------------

    public function testPostRoute(): void
    {
        $this->router->post($this->routeName('users.create'), '/users', static fn($p) => 'created');

        $this->assertSame('created', $this->router->dispatchRequest('POST', '/users'));
    }

    public function testPutRoute(): void
    {
        $this->router->put($this->routeName('users.update'), '/users/$id', static fn($p) => 'updated:' . $p['id']);

        $this->assertSame('updated:5', $this->router->dispatchRequest('PUT', '/users/5'));
    }

    public function testPatchRoute(): void
    {
        $this->router->patch($this->routeName('users.patch'), '/users/$id', static fn($p) => 'patched:' . $p['id']);

        $this->assertSame('patched:3', $this->router->dispatchRequest('PATCH', '/users/3'));
    }

    public function testDeleteRoute(): void
    {
        $this->router->delete($this->routeName('users.delete'), '/users/$id', static fn($p) => 'deleted:' . $p['id']);

        $this->assertSame('deleted:9', $this->router->dispatchRequest('DELETE', '/users/9'));
    }

    public function testMethodIsCaseInsensitive(): void
    {
        $this->router->get($this->routeName('ping'), '/ping', static fn($p) => 'pong');

        $this->assertSame('pong', $this->router->dispatchRequest('get', '/ping'));
    }

    public function testAnyMethodRoute(): void
    {
        $this->router->any($this->routeName('ping'), '/ping', static fn($p) => 'pong');

        $this->assertSame('pong', $this->router->dispatchRequest('GET', '/ping'));
        $this->assertSame('pong', $this->router->dispatchRequest('POST', '/ping'));
        $this->assertSame('pong', $this->router->dispatchRequest('DELETE', '/ping'));
    }

    // -----------------------------------------------------------------------
    // Method chaining
    // -----------------------------------------------------------------------

    public function testFluentInterface(): void
    {
        $result = $this->router
            ->get($this->routeName('a'), '/a', static fn($p) => 'a')
            ->post($this->routeName('b'), '/b', static fn($p) => 'b')
            ->put($this->routeName('c'), '/c', static fn($p) => 'c')
            ->delete($this->routeName('d'), '/d', static fn($p) => 'd');

        $this->assertInstanceOf(Router::class, $result);
        $this->assertCount(4, $this->router->getRoutes());
    }

    // -----------------------------------------------------------------------
    // 404 Not Found
    // -----------------------------------------------------------------------

    public function testNotFoundHandlerIsCalled(): void
    {
        $this->router->notFound(static fn($path) => '404:' . $path);

        $this->assertSame('404:/unknown', $this->router->dispatchRequest('GET', '/unknown'));
    }

    public function testDispatchReturnsNullWhenNoHandlerAndNoMatch(): void
    {
        $this->assertNull($this->router->dispatchRequest('GET', '/unknown'));
    }

    // -----------------------------------------------------------------------
    // 405 Method Not Allowed
    // -----------------------------------------------------------------------

    public function testMethodNotAllowedHandlerIsCalled(): void
    {
        $this->router->get($this->routeName('users.list'), '/users', static fn($p) => 'list');
        $this->router->methodNotAllowed(static fn($method, $path) => '405:' . $method . ':' . $path);

        $this->assertSame('405:POST:/users', $this->router->dispatchRequest('POST', '/users'));
    }

    public function testMethodNotAllowedReturnsNullWhenNoHandler(): void
    {
        $this->router->get($this->routeName('users.list'), '/users', static fn($p) => 'list');

        $this->assertNull($this->router->dispatchRequest('POST', '/users'));
    }

    // -----------------------------------------------------------------------
    // Path normalisation
    // -----------------------------------------------------------------------

    public function testTrailingSlashIsIgnored(): void
    {
        $this->router->get($this->routeName('about'), '/about', static fn($p) => 'about');

        $this->assertSame('about', $this->router->dispatchRequest('GET', '/about/'));
    }

    public function testQueryStringIsStripped(): void
    {
        $this->router->get($this->routeName('search'), '/search', static fn($p) => 'search');

        $this->assertSame('search', $this->router->dispatchRequest('GET', '/search?q=hello'));
    }

    public function testLeadingSlashIsNormalised(): void
    {
        $this->router->get($this->routeName('hello'), '/hello', static fn($p) => 'hello');

        $this->assertSame('hello', $this->router->dispatchRequest('GET', 'hello'));
    }

    // -----------------------------------------------------------------------
    // Security: path traversal prevention
    // -----------------------------------------------------------------------

    public function testPathTraversalIsResolved(): void
    {
        $this->router->get($this->routeName('admin'), '/admin', static fn($p) => 'admin');

        // /products/../admin should resolve to /admin and NOT match /admin
        // (because the attacker expects to reach /admin by traversing up
        //  from /products). The router resolves the segments, so the
        //  normalised path IS /admin — and thus it should match.
        //  What must NOT happen is a bypass that skips the route entirely.
        $result = $this->router->dispatchRequest('GET', '/products/../admin');
        $this->assertSame('admin', $result);
    }

    public function testPathTraversalDoesNotEscapeRoot(): void
    {
        $this->router->get($this->routeName('home'), '/', static fn($p) => 'home');

        $this->assertSame('home', $this->router->dispatchRequest('GET', '/../../../'));
    }

    public function testDotSegmentsAreResolved(): void
    {
        $this->router->get($this->routeName('about'), '/about', static fn($p) => 'about');

        $this->assertSame('about', $this->router->dispatchRequest('GET', '/./about/.'));
    }

    // -----------------------------------------------------------------------
    // Handler receives correct params
    // -----------------------------------------------------------------------

    public function testHandlerReceivesEmptyParamsForStaticRoute(): void
    {
        $received = null;
        $this->router->get($this->routeName('static'), '/static', function (array $params) use (&$received) {
            $received = $params;
        });

        $this->router->dispatchRequest('GET', '/static');
        $this->assertSame([], $received);
    }

    public function testHandlerReceivesExtractedParams(): void
    {
        $received = null;
        $this->router->get($this->routeName('items.show'), '/items/$category/$id', function (array $params) use (&$received) {
            $received = $params;
        });

        $this->router->dispatchRequest('GET', '/items/books/123');
        $this->assertSame(['category' => 'books', 'id' => '123'], $received);
    }

    public function testDispatchByRouteNameIsStatic(): void
    {
        $this->router->get($this->routeName('about'), '/about', static fn($p) => 'about:' . ($p['locale'] ?? 'en'));

        $this->assertSame('about:fr', Router::dispatch($this->routeName('about'), ['locale' => 'fr']));
    }

    public function testDispatchByRouteNameReturnsNullForMissingRoute(): void
    {
        $this->assertNull(Router::dispatch($this->routeName('missing')));
    }

    public function testRouteNameCannotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->router->get('  ', '/about', static fn($p) => 'about');
    }
}
