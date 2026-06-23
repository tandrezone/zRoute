<?php

declare(strict_types=1);

namespace zRoute;

/**
 * Immutable representation of an incoming HTTP request.
 *
 * Carries the HTTP method, URI, parsed parameters from all sources (path,
 * query-string, request body, headers), and exposes a unified accessor for
 * looking up a value regardless of its origin.
 *
 * Use Request::fromGlobals() to create an instance from PHP's superglobals,
 * or construct one manually for testing / CLI use.
 */
class Request
{
    /**
     * @param string               $method      HTTP method (always uppercased).
     * @param string               $uri         Raw request URI (may contain query string).
     * @param array<string,mixed>  $queryParams Parsed query-string parameters ($_GET).
     * @param array<string,mixed>  $bodyParams  Parsed request body ($_POST or decoded JSON).
     * @param array<string,mixed>  $pathParams  URI dynamic segments resolved by the router.
     * @param array<string,string> $headers     HTTP request headers.
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $queryParams = [],
        private readonly array $bodyParams  = [],
        private readonly array $pathParams  = [],
        private readonly array $headers     = [],
    ) {}

    // -----------------------------------------------------------------------
    // Factory
    // -----------------------------------------------------------------------

    /**
     * Build a Request from PHP superglobals and the raw input stream.
     *
     * JSON bodies (Content-Type: application/json) are decoded automatically.
     */
    public static function fromGlobals(): static
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';

        $queryParams = $_GET ?? [];

        // Prefer decoded JSON body over form-encoded body.
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (str_contains(strtolower($contentType), 'application/json')) {
            $raw     = (string) file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            $bodyParams = is_array($decoded) ? $decoded : [];
        } else {
            $bodyParams = $_POST ?? [];
        }

        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];

        return new static($method, $uri, $queryParams, $bodyParams, [], $headers);
    }

    // -----------------------------------------------------------------------
    // Immutable "wither" helpers
    // -----------------------------------------------------------------------

    /**
     * Return a copy of this request with the given path parameters merged in.
     */
    public function withPathParams(array $pathParams): static
    {
        return new static(
            $this->method,
            $this->uri,
            $this->queryParams,
            $this->bodyParams,
            $pathParams,
            $this->headers,
        );
    }

    /**
     * Return a copy of this request with the body parameters replaced.
     */
    public function withBodyParams(array $bodyParams): static
    {
        return new static(
            $this->method,
            $this->uri,
            $this->queryParams,
            $bodyParams,
            $this->pathParams,
            $this->headers,
        );
    }

    // -----------------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------------

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    /** @return array<string,mixed> */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /** @return array<string,mixed> */
    public function getBodyParams(): array
    {
        return $this->bodyParams;
    }

    /** @return array<string,mixed> */
    public function getPathParams(): array
    {
        return $this->pathParams;
    }

    /** @return array<string,string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Retrieve a single header value (case-insensitive key lookup).
     */
    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Look up a parameter value, searching path → query → body in that order.
     */
    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->pathParams[$key] ?? $this->queryParams[$key] ?? $this->bodyParams[$key] ?? $default;
    }

    /**
     * Return all parameters merged: body is the base layer, query overrides,
     * and path params take highest precedence.
     *
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return array_merge($this->bodyParams, $this->queryParams, $this->pathParams);
    }
}
