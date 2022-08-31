<?php

namespace Dakujem\Latter;

use Latte\Engine;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Render runtime context object.
 * Immutable.
 */
final class Runtime
{
    private Response $response;

    /**
     * Latte engine.
     */
    private ?Engine $engine;

    /**
     * Render parameters.
     */
    private array $params;

    /**
     * Render target (template/routine name used when render was called).
     */
    private ?string $target;

    /**
     * Variable runtime arguments.
     */
    private array $more;

    /**
     * @param Response $response
     * @param Engine|null $engine
     * @param array $params
     * @param string $target
     * @param mixed ...$more
     */
    function __construct(
        Response $response,
        string   $target,
        array    $params = [],
        ?Engine  $engine = null,
                 ...$more
    )
    {
        $this->response = $response;
        $this->target = $target;
        $this->params = $params;
        $this->engine = $engine;
        $this->more = $more;
    }

    /**
     * Static factory.
     *
     * @param Response $response
     * @param string $target
     * @param array $params
     * @param Engine|null $engine
     * @param mixed ...$more
     * @return static
     */
    static function i(
        Response $response,
        string   $target,
        array    $params = [],
        ?Engine  $engine = null,
                 ...$more
    ): self
    {
        return new static(
            $response,
            $target,
            $params,
            $engine,
            ...$more
        );
    }

    function withParams(array $params): self
    {
        return new static(
            $this->response,
            $this->target,
            $params,
            $this->engine,
            ...$this->more
        );
    }

    function withParam(string $name, $value): self
    {
        return new static(
            $this->response,
            $this->target,
            array_merge($this->params, [$name => $value]),
            $this->engine,
            ...$this->more
        );
    }

    function withTarget(string $target): self
    {
        return new static(
            $this->response,
            $target,
            $this->params,
            $this->engine,
            ...$this->more
        );
    }

    function getResponse(): Response
    {
        return $this->response;
    }

    function getEngine(): ?Engine
    {
        return $this->engine;
    }

    function getParams(): array
    {
        return $this->params;
    }

    function getTarget(): string
    {
        return $this->target;
    }

    function getMore(): array
    {
        return $this->more;
    }
}
