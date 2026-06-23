<?php

declare(strict_types=1);

namespace zRoute;

/**
 * Represents a single registered route.
 *
 * Patterns support dynamic segments using either the legacy $paramName syntax
 * or the curly-brace {paramName} syntax.  Both forms may be mixed in the same
 * pattern, and paramName may contain letters, digits, underscores, and hyphens.
 *
 * An optional $paramRegexMap may supply per-parameter regex constraints; this
 * is used by RouteLoader when the route definition specifies a 'regex' rule.
 *
 * Examples:
 *   /about                                   (static)
 *   /products/$product-slug                  ($-syntax dynamic segment)
 *   /users/{id}                              ({}-syntax dynamic segment)
 *   /users/{id}/posts/{postId}               (two dynamic segments)
 *   /resources/{id}  + ['id' => '[0-9]+']    (numeric-only constraint)
 */
class Route
{
    /** @var string[] */
    private array $paramNames = [];

    private string $regex;

    private string $method;

    /** @var string Optional logical name for this route (e.g. 'user.show'). */
    private string $name = '';

    /**
     * @param string               $method         HTTP method (uppercased automatically).
     * @param string               $pattern        URL pattern with optional param placeholders.
     * @param callable             $handler        Handler called with an array of matched params.
     * @param array<string,string> $paramRegexMap  Optional per-param regex overrides.
     */
    public function __construct(
        string $method,
        private readonly string $pattern,
        private readonly mixed $handler,
        private readonly array $paramRegexMap = [],
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
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
     *
     * Supported placeholder syntaxes (may be mixed in one pattern):
     *   $paramName   – legacy dollar-prefix style
     *   {paramName}  – curly-brace style (from route definition arrays)
     *
     * Per-param regex overrides in $paramRegexMap take precedence over the
     * default catch-all ([^/]+) for that segment.
     */
    private function parsePattern(): void
    {
        // Combined token pattern — matches either {name} or $name.
        $tokenRe = '/(\{[a-zA-Z][a-zA-Z0-9_-]*\}|\$[a-zA-Z][a-zA-Z0-9_-]*)/';

        // Collect parameter names (in order).
        preg_match_all($tokenRe, $this->pattern, $allMatches);
        $this->paramNames = array_map(
            static function (string $token): string {
                // Strip leading '$' or surrounding '{' '}'.
                return trim($token, '${}');
            },
            $allMatches[1],
        );

        // Build matching regex by splitting on token boundaries so that static
        // segments can be safely preg_quote'd.
        $parts = preg_split($tokenRe, $this->pattern, -1, PREG_SPLIT_DELIM_CAPTURE);

        $regexParts = [];
        foreach ($parts as $part) {
            if (preg_match('/^\{([a-zA-Z][a-zA-Z0-9_-]*)\}$/', $part, $m)) {
                // {paramName} — use custom regex if supplied, else any non-slash chars.
                $paramRegex   = $this->paramRegexMap[$m[1]] ?? '[^/]+';
                $regexParts[] = '(' . $paramRegex . ')';
            } elseif (preg_match('/^\$([a-zA-Z][a-zA-Z0-9_-]*)$/', $part)) {
                // $paramName — always any non-slash chars (legacy behaviour).
                $regexParts[] = '([^/]+)';
            } else {
                $regexParts[] = preg_quote($part, '#');
            }
        }

        $this->regex = '#^' . implode('', $regexParts) . '$#';
    }
}
