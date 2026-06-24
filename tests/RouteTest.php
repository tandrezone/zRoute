<?php

declare(strict_types=1);

namespace zRoute\Tests;

use PHPUnit\Framework\TestCase;
use zRoute\Route;

/**
 * Unit tests for the Route class, covering both the legacy $param syntax
 * and the new {param} curly-brace syntax introduced for array-based routes.
 */
class RouteTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Construction / accessors
    // -----------------------------------------------------------------------

    public function testGetters(): void
    {
        $handler = static fn(array $p) => 'ok';
        $route   = new Route('GET', '/about', $handler);

        $this->assertSame('GET', $route->getMethod());
        $this->assertSame('/about', $route->getPattern());
        $this->assertSame($handler, $route->getHandler());
        $this->assertSame([], $route->getParamNames());
    }

    public function testMethodIsUppercased(): void
    {
        $route = new Route('get', '/path', static fn($p) => null);
        $this->assertSame('GET', $route->getMethod());
    }

    public function testParamNamesExtracted(): void
    {
        $route = new Route('GET', '/users/$userId/posts/$postId', static fn($p) => null);
        $this->assertSame(['userId', 'postId'], $route->getParamNames());
    }

    public function testParamNameWithHyphen(): void
    {
        $route = new Route('GET', '/products/$product-slug', static fn($p) => null);
        $this->assertSame(['product-slug'], $route->getParamNames());
    }

    // -----------------------------------------------------------------------
    // Name field
    // -----------------------------------------------------------------------

    public function testDefaultNameIsEmpty(): void
    {
        $route = new Route('GET', '/path', static fn($p) => null);
        $this->assertSame('', $route->getName());
    }

    public function testSetNameReturnsSelf(): void
    {
        $route = new Route('GET', '/path', static fn($p) => null);
        $this->assertSame($route, $route->setName('my.route'));
        $this->assertSame('my.route', $route->getName());
    }

    // -----------------------------------------------------------------------
    // Legacy $param static route matching
    // -----------------------------------------------------------------------

    public function testStaticRouteMatches(): void
    {
        $route  = new Route('GET', '/about', static fn($p) => null);
        $params = $route->matches('GET', '/about');

        $this->assertIsArray($params);
        $this->assertEmpty($params);
    }

    public function testStaticRouteDoesNotMatchDifferentPath(): void
    {
        $route = new Route('GET', '/about', static fn($p) => null);
        $this->assertNull($route->matches('GET', '/contact'));
    }

    public function testStaticRouteDoesNotMatchPrefix(): void
    {
        $route = new Route('GET', '/about', static fn($p) => null);
        $this->assertNull($route->matches('GET', '/about/more'));
    }

    // -----------------------------------------------------------------------
    // Legacy $param dynamic matching
    // -----------------------------------------------------------------------

    public function testSingleDynamicParam(): void
    {
        $route  = new Route('GET', '/users/$id', static fn($p) => null);
        $params = $route->matches('GET', '/users/42');

        $this->assertSame(['id' => '42'], $params);
    }

    public function testDynamicParamWithHyphen(): void
    {
        $route  = new Route('GET', '/products/$product-slug', static fn($p) => null);
        $params = $route->matches('GET', '/products/my-awesome-widget');

        $this->assertSame(['product-slug' => 'my-awesome-widget'], $params);
    }

    public function testMultipleDynamicParams(): void
    {
        $route  = new Route('GET', '/users/$userId/posts/$postId', static fn($p) => null);
        $params = $route->matches('GET', '/users/7/posts/99');

        $this->assertSame(['userId' => '7', 'postId' => '99'], $params);
    }

    public function testDynamicSegmentDoesNotCrossSlash(): void
    {
        $route = new Route('GET', '/users/$id', static fn($p) => null);
        $this->assertNull($route->matches('GET', '/users/7/extra'));
    }

    // -----------------------------------------------------------------------
    // New {param} curly-brace syntax
    // -----------------------------------------------------------------------

    public function testCurlyBraceSyntaxExtractsParamName(): void
    {
        $route = new Route('GET', '/resources/{id}', static fn($p) => null);
        $this->assertSame(['id'], $route->getParamNames());
    }

    public function testCurlyBraceSyntaxMatchesDynamicSegment(): void
    {
        $route  = new Route('GET', '/resources/{id}', static fn($p) => null);
        $params = $route->matches('GET', '/resources/42');

        $this->assertSame(['id' => '42'], $params);
    }

    public function testCurlyBraceMultipleParams(): void
    {
        $route  = new Route('GET', '/users/{userId}/posts/{postId}', static fn($p) => null);
        $params = $route->matches('GET', '/users/7/posts/99');

        $this->assertSame(['userId' => '7', 'postId' => '99'], $params);
    }

    public function testCurlyBraceDoesNotCrossSlash(): void
    {
        $route = new Route('GET', '/resources/{id}', static fn($p) => null);
        $this->assertNull($route->matches('GET', '/resources/1/extra'));
    }

    // -----------------------------------------------------------------------
    // Per-param regex constraints (curly-brace only)
    // -----------------------------------------------------------------------

    public function testCustomRegexAllowsMatchingValue(): void
    {
        $route  = new Route('GET', '/resources/{id}', static fn($p) => null, ['id' => '[0-9]+']);
        $params = $route->matches('GET', '/resources/123');

        $this->assertSame(['id' => '123'], $params);
    }

    public function testCustomRegexRejectsNonMatchingValue(): void
    {
        $route = new Route('GET', '/resources/{id}', static fn($p) => null, ['id' => '[0-9]+']);
        $this->assertNull($route->matches('GET', '/resources/abc'));
    }

    // -----------------------------------------------------------------------
    // Mixed syntax
    // -----------------------------------------------------------------------

    public function testMixedSyntaxInOnePattern(): void
    {
        $route  = new Route('GET', '/a/$x/b/{y}', static fn($p) => null);
        $params = $route->matches('GET', '/a/hello/b/world');

        $this->assertSame(['x' => 'hello', 'y' => 'world'], $params);
    }

    // -----------------------------------------------------------------------
    // Method matching
    // -----------------------------------------------------------------------

    public function testMethodMismatchReturnsNull(): void
    {
        $route = new Route('POST', '/users', static fn($p) => null);
        $this->assertNull($route->matches('GET', '/users'));
    }

    public function testMatchPathIgnoresMethod(): void
    {
        $route  = new Route('POST', '/users', static fn($p) => null);
        $params = $route->matchPath('/users');

        $this->assertIsArray($params);
        $this->assertEmpty($params);
    }

    // -----------------------------------------------------------------------
    // Regex exposed for debugging
    // -----------------------------------------------------------------------

    public function testRegexIsNonEmpty(): void
    {
        $route = new Route('GET', '/foo/$bar', static fn($p) => null);
        $this->assertNotEmpty($route->getRegex());
    }

    public function testStaticSpecialCharsAreEscaped(): void
    {
        // Dots in static segments should be treated as literals.
        $route = new Route('GET', '/api/v1.0/status', static fn($p) => null);

        $this->assertNotNull($route->matches('GET', '/api/v1.0/status'));
        $this->assertNull($route->matches('GET', '/api/v1X0/status'));
    }
}
