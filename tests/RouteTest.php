<?php

declare(strict_types=1);

namespace zRoute\Tests;

use PHPUnit\Framework\TestCase;
use zRoute\Route;

/**
 * Unit tests for the Route class.
 */
class RouteTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Construction / accessors
    // -----------------------------------------------------------------------

    public function testGetters(): void
    {
        $handler = static fn(array $p) => 'ok';
        $route   = new Route('GET', 'about.show', '/about', $handler);

        $this->assertSame('GET', $route->getMethod());
        $this->assertSame('about.show', $route->getName());
        $this->assertSame('/about', $route->getPattern());
        $this->assertSame($handler, $route->getHandler());
        $this->assertSame([], $route->getParamNames());
    }

    public function testMethodIsUppercased(): void
    {
        $route = new Route('get', 'path.show', '/path', static fn($p) => null);
        $this->assertSame('GET', $route->getMethod());
    }

    public function testParamNamesExtracted(): void
    {
        $route = new Route('GET', 'users.posts.show', '/users/$userId/posts/$postId', static fn($p) => null);
        $this->assertSame(['userId', 'postId'], $route->getParamNames());
    }

    public function testParamNameWithHyphen(): void
    {
        $route = new Route('GET', 'products.show', '/products/$product-slug', static fn($p) => null);
        $this->assertSame(['product-slug'], $route->getParamNames());
    }

    // -----------------------------------------------------------------------
    // Static route matching
    // -----------------------------------------------------------------------

    public function testStaticRouteMatches(): void
    {
        $route = new Route('GET', 'about.show', '/about', static fn($p) => null);
        $params = $route->matches('GET', '/about');

        $this->assertIsArray($params);
        $this->assertEmpty($params);
    }

    public function testStaticRouteDoesNotMatchDifferentPath(): void
    {
        $route = new Route('GET', 'about.show', '/about', static fn($p) => null);
        $this->assertNull($route->matches('GET', '/contact'));
    }

    public function testStaticRouteDoesNotMatchPrefix(): void
    {
        $route = new Route('GET', 'about.show', '/about', static fn($p) => null);
        $this->assertNull($route->matches('GET', '/about/more'));
    }

    // -----------------------------------------------------------------------
    // Dynamic route matching
    // -----------------------------------------------------------------------

    public function testSingleDynamicParam(): void
    {
        $route  = new Route('GET', 'users.show', '/users/$id', static fn($p) => null);
        $params = $route->matches('GET', '/users/42');

        $this->assertSame(['id' => '42'], $params);
    }

    public function testDynamicParamWithHyphen(): void
    {
        $route  = new Route('GET', 'products.show', '/products/$product-slug', static fn($p) => null);
        $params = $route->matches('GET', '/products/my-awesome-widget');

        $this->assertSame(['product-slug' => 'my-awesome-widget'], $params);
    }

    public function testMultipleDynamicParams(): void
    {
        $route  = new Route('GET', 'users.posts.show', '/users/$userId/posts/$postId', static fn($p) => null);
        $params = $route->matches('GET', '/users/7/posts/99');

        $this->assertSame(['userId' => '7', 'postId' => '99'], $params);
    }

    public function testDynamicSegmentDoesNotCrossSlash(): void
    {
        $route = new Route('GET', 'users.show', '/users/$id', static fn($p) => null);

        // The dynamic segment must not consume path separators.
        $this->assertNull($route->matches('GET', '/users/7/extra'));
    }

    // -----------------------------------------------------------------------
    // Method matching
    // -----------------------------------------------------------------------

    public function testMethodMismatchReturnsNull(): void
    {
        $route = new Route('POST', 'users.create', '/users', static fn($p) => null);
        $this->assertNull($route->matches('GET', '/users'));
    }

    public function testMatchPathIgnoresMethod(): void
    {
        $route  = new Route('POST', 'users.create', '/users', static fn($p) => null);
        $params = $route->matchPath('/users');

        $this->assertIsArray($params);
        $this->assertEmpty($params);
    }

    // -----------------------------------------------------------------------
    // Regex exposed for debugging
    // -----------------------------------------------------------------------

    public function testRegexIsNonEmpty(): void
    {
        $route = new Route('GET', 'foo.show', '/foo/$bar', static fn($p) => null);
        $this->assertNotEmpty($route->getRegex());
    }

    public function testStaticSpecialCharsAreEscaped(): void
    {
        // Dots in static segments should be treated as literals.
        $route = new Route('GET', 'api.status', '/api/v1.0/status', static fn($p) => null);

        $this->assertNotNull($route->matches('GET', '/api/v1.0/status'));
        $this->assertNull($route->matches('GET', '/api/v1X0/status'));
    }
}
