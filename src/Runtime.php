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
     * @var View
     */
    private $view;

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
     * @param View        $view
     * @param Engine|null $engine
     * @param array       $params
     * @param string      $target
     * @param mixed       ...$more
     */
    function __construct(
//        View $view,
        Response $response,
        string $target,
        array $params = [],
        Engine $engine = null,
        ...$more
    )
    {
//        $this->view = $view;
        $this->response = $response;
        $this->target = $target;
        $this->params = $params;
        $this->engine = $engine;
        $this->more = $more;
    }


    /**
     * Static factory.
     *
     * @param View        $view
     * @param Response    $response
     * @param string      $target
     * @param array       $params
     * @param Engine|null $engine
     * @param mixed       ...$more
     * @return static
     */
    static function i(
//        View $view,
        Response $response,
        string $target,
        array $params = [],
        Engine $engine = null,
        ...$more
    ): self
    {
        return new static(
//            $view,
            $response,
            $target,
            $params,
            $engine,
            ...$more
        );
    }

//
//    /**
//     * Render the target into a response body using the internal View instance.
//     *
//     * @param string|null   $target
//     * @param array|null    $params
//     * @param Engine|null   $engine
//     * @param Response|null $response
//     * @return Response
//     */
//    function toResponse(
//        string $target = null,
//        array $params = null,
//        Engine $engine = null,
//        Response $response = null
//    ): Response
//    {
//        return $this->getView()->respond(
//            $response ?? $this->getResponse(),
//            $engine ?? $this->getEngine(),
//            $target ?? $this->getTarget(),
//            $params ?? $this->getParams()
//        );
//    }


    function withParams(array $params): self
    {
        return new static(
//            $this->view,
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
//            $this->view,
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
//
//
//    /**
//     * @return View
//     */
//    function getView(): View
//    {
//        return $this->view;
//    }


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