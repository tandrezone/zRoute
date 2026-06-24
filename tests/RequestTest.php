<?php

declare(strict_types=1);

namespace zRoute\Tests;

use PHPUnit\Framework\TestCase;
use zRoute\Request;

/**
 * Unit tests for the Request value object.
 */
class RequestTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Construction / accessors
    // -----------------------------------------------------------------------

    public function testGettersReturnConstructorValues(): void
    {
        $req = new Request('POST', '/items', ['q' => 'search'], ['name' => 'foo'], ['id' => '1'], ['Accept' => 'application/json']);

        $this->assertSame('POST', $req->getMethod());
        $this->assertSame('/items', $req->getUri());
        $this->assertSame(['q' => 'search'], $req->getQueryParams());
        $this->assertSame(['name' => 'foo'], $req->getBodyParams());
        $this->assertSame(['id' => '1'], $req->getPathParams());
        $this->assertSame(['Accept' => 'application/json'], $req->getHeaders());
    }

    public function testDefaultsToEmptyArrays(): void
    {
        $req = new Request('GET', '/');

        $this->assertSame([], $req->getQueryParams());
        $this->assertSame([], $req->getBodyParams());
        $this->assertSame([], $req->getPathParams());
        $this->assertSame([], $req->getHeaders());
    }

    // -----------------------------------------------------------------------
    // withPathParams — immutability
    // -----------------------------------------------------------------------

    public function testWithPathParamsReturnsNewInstance(): void
    {
        $original = new Request('GET', '/');
        $updated  = $original->withPathParams(['id' => '42']);

        $this->assertNotSame($original, $updated);
        $this->assertSame([], $original->getPathParams());
        $this->assertSame(['id' => '42'], $updated->getPathParams());
    }

    public function testWithPathParamsPreservesOtherFields(): void
    {
        $original = new Request('POST', '/path', ['q' => '1'], ['body' => 'v']);
        $updated  = $original->withPathParams(['x' => '5']);

        $this->assertSame('POST', $updated->getMethod());
        $this->assertSame('/path', $updated->getUri());
        $this->assertSame(['q' => '1'], $updated->getQueryParams());
        $this->assertSame(['body' => 'v'], $updated->getBodyParams());
    }

    // -----------------------------------------------------------------------
    // withBodyParams — immutability
    // -----------------------------------------------------------------------

    public function testWithBodyParamsReturnsNewInstance(): void
    {
        $original = new Request('POST', '/');
        $updated  = $original->withBodyParams(['key' => 'value']);

        $this->assertNotSame($original, $updated);
        $this->assertSame([], $original->getBodyParams());
        $this->assertSame(['key' => 'value'], $updated->getBodyParams());
    }

    // -----------------------------------------------------------------------
    // getHeader — case-insensitive lookup
    // -----------------------------------------------------------------------

    public function testGetHeaderIsCaseInsensitive(): void
    {
        $req = new Request('GET', '/', [], [], [], ['Authorization' => '******']);

        $this->assertSame('******', $req->getHeader('authorization'));
        $this->assertSame('******', $req->getHeader('AUTHORIZATION'));
        $this->assertSame('******', $req->getHeader('Authorization'));
    }

    public function testGetHeaderReturnsNullForMissingHeader(): void
    {
        $req = new Request('GET', '/');
        $this->assertNull($req->getHeader('X-Custom'));
    }

    // -----------------------------------------------------------------------
    // getParam — priority order: path > query > body
    // -----------------------------------------------------------------------

    public function testGetParamPriority(): void
    {
        $req = new Request(
            'GET',
            '/',
            ['key' => 'query'],
            ['key' => 'body'],
            ['key' => 'path'],
        );

        // Path params win.
        $this->assertSame('path', $req->getParam('key'));
    }

    public function testGetParamFallsBackToQuery(): void
    {
        $req = new Request('GET', '/', ['key' => 'query'], ['key' => 'body']);
        $this->assertSame('query', $req->getParam('key'));
    }

    public function testGetParamFallsBackToBody(): void
    {
        $req = new Request('GET', '/', [], ['key' => 'body']);
        $this->assertSame('body', $req->getParam('key'));
    }

    public function testGetParamReturnsDefaultWhenMissing(): void
    {
        $req = new Request('GET', '/');
        $this->assertSame('fallback', $req->getParam('missing', 'fallback'));
    }

    // -----------------------------------------------------------------------
    // all() — merged view
    // -----------------------------------------------------------------------

    public function testAllMergesAllParams(): void
    {
        $req = new Request('POST', '/', ['q' => 'query'], ['b' => 'body'], ['p' => 'path']);
        $all = $req->all();

        $this->assertSame('query', $all['q']);
        $this->assertSame('body', $all['b']);
        $this->assertSame('path', $all['p']);
    }

    public function testPathParamsOverrideQueryInAll(): void
    {
        $req = new Request('GET', '/', ['id' => 'query'], [], ['id' => 'path']);
        $this->assertSame('path', $req->all()['id']);
    }
}
