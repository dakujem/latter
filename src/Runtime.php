<?php

namespace Dakujem\Latter;

use Latte\Engine;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Render runtime context object.
 */
final class Runtime
{

    /**
     * @var Response
     */
    private $response;

    /**
     * Latte engine.
     *
     * @var Engine
     */
    private $engine = null;

    /**
     * Render parameters.
     *
     * @var array
     */
    private $params = [];

    /**
     * Render target (template/routine name used when render was called).
     *
     * @var string
     */
    private $target = null;

    /**
     * Variable runtime arguments.
     *
     * @var array
     */
    private $more = [];


    /**
     * @param Response    $response
     * @param Engine|null $engine
     * @param array       $params
     * @param string      $target
     * @param mixed       ...$more
     */
    function __construct(
        Response $response,
        string $target,
        array $params = [],
        Engine $engine = null,
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
     * @param Response    $response
     * @param string      $target
     * @param array       $params
     * @param Engine|null $engine
     * @param mixed       ...$more
     * @return static
     */
    static function i(
        Response $response,
        string $target,
        array $params = [],
        Engine $engine = null,
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


    /**
     * @return Response
     */
    function getResponse(): Response
    {
        return $this->response;
    }


    /**
     * @return Engine|null
     */
    function getEngine(): ?Engine
    {
        return $this->engine;
    }


    /**
     * @return array
     */
    function getParams(): array
    {
        return $this->params;
    }


    /**
     * @return string
     */
    function getTarget(): string
    {
        return $this->target;
    }


    /**
     * @return array
     */
    function getMore(): array
    {
        return $this->more;
    }


}