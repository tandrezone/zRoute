<?php

declare(strict_types=1);

namespace zRoute;

/**
 * Represents a single registered route.
 *
 * Patterns support dynamic segments using the $paramName syntax, where
 * paramName may contain letters, digits, underscores, and hyphens.
 *
 * Examples:
 *   /about                           (static)
 *   /products/$product-slug          (one dynamic segment)
 *   /users/$userId/posts/$postId     (two dynamic segments)
 */
class Route
{
    /** @var string[] */
    private array $paramNames = [];

    private string $regex;

    private string $method;

    /**
     * @param string   $method  HTTP method (will be uppercased automatically)
     * @param string   $pattern URL pattern with optional $param placeholders
     * @param callable $handler Handler called with an array of matched params
     */
    public function __construct(
        string $method,
        private readonly string $pattern,
        private readonly mixed $handler,
    ) {
        $this->method = strtoupper($method);
        $this->parsePattern();
    }

    // -----------------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------------

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    /** @return string[] */
    public function getParamNames(): array
    {
        return $this->paramNames;
    }

    public function getRegex(): string
    {
        return $this->regex;
    }

    // -----------------------------------------------------------------------
    // Matching
    // -----------------------------------------------------------------------

    /**
     * Match against the given HTTP method and URL path.
     *
     * @return array<string, string>|null  Extracted params, or null on mismatch
     */
    public function matches(string $method, string $path): ?array
    {
        if ($this->method !== strtoupper($method)) {
            return null;
        }

        return $this->matchPath($path);
    }

    /**
     * Match against a URL path only (HTTP method is ignored).
     *
     * @return array<string, string>|null  Extracted params, or null on mismatch
     */
    public function matchPath(string $path): ?array
    {
        if (!preg_match($this->regex, $path, $regexMatches)) {
            return null;
        }

        $params = [];
        foreach ($this->paramNames as $index => $name) {
            $params[$name] = $regexMatches[$index + 1];
        }

        return $params;
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Parse the URL pattern to extract parameter names and compile the regex.
     */
    private function parsePattern(): void
    {
        // Collect parameter names in declaration order.
        preg_match_all('/\$([a-zA-Z][a-zA-Z0-9_-]*)/', $this->pattern, $matches);
        $this->paramNames = $matches[1];

        // Build the matching regex by splitting on $param tokens so that
        // static segments can be safely preg_quote'd.
        $parts = preg_split(
            '/(\$[a-zA-Z][a-zA-Z0-9_-]*)/',
            $this->pattern,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        );

        $regexParts = [];
        foreach ($parts as $part) {
            if (preg_match('/^\$[a-zA-Z][a-zA-Z0-9_-]*$/', $part)) {
                // Dynamic segment — matches any non-slash characters
                $regexParts[] = '([^/]+)';
            } else {
                $regexParts[] = preg_quote($part, '#');
            }
        }

        $this->regex = '#^' . implode('', $regexParts) . '$#';
    }
}
