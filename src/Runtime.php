<?php


namespace Dakujem\Latter;


use Latte\Engine;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Render runtime context object.
 */
class Runtime
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
     * @param Response $response
     * @param View $view
     * @param Engine|null $engine
     * @param array $params
     * @param string $target
     * @param $more
     */
    function __construct(
        View $view,
        Response $response,
        string $target,
        array $params = [],
        Engine $engine = null,
        ...$more
    ) {
        $this->view = $view;
        $this->response = $response;
        $this->target = $target;
        $this->params = $params;
        $this->engine = $engine;
        $this->more = $more;
    }


    static function i(
        View $view,
        Response $response,
        string $target,
        array $params = [],
        Engine $engine = null,
        ...$more
    ): self {
        return new static(
            $view,
            $response,
            $target,
            $params,
            $engine,
            ...$more
        );
    }


    function toResponse(
        string $target = null,
        array $params = null,
        Engine $engine = null,
        Response $response = null
    ): Response {
        return $this->getView()->respond(
            $response ?? $this->getResponse(),
            $engine ?? $this->getEngine(),
            $target ?? $this->getTarget(),
            $params ?? $this->getParams()
        );
    }


    function move(
        string $target = null,
        array $params = null,
        Engine $engine = null,
        Response $response = null
    ): self {
        return new static(
            $this->getView(),
            $response ?? $this->getResponse(),
            $target ?? $this->getTarget(),
            $params ?? $this->getParams(),
            $engine ?? $this->getEngine(),
            ...$this->getMore()
        );
    }


    function withParams(array $params): self
    {
        return new static(
            $this->getView(),
            $this->getResponse(),
            $this->getTarget(),
            $params,
            $this->getEngine(),
            ...$this->getMore()
        );
    }


    function withTarget(string $target): self
    {
        return new static(
            $this->getView(),
            $this->getResponse(),
            $target,
            $this->getParams(),
            $this->getEngine(),
            ...$this->getMore()
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
     * @return View
     */
    function getView(): View
    {
        return $this->view;
    }


    /**
     * @return Engine|null
     */
    function getEngine(): ?Engine
    {
        return $this->engine ?? $this->getView()->getEngine();
    }


    /**
     * @return array
     */
    function getParams(): array
    {
        return $this->params;
    }


    /**
     * @return string|null
     */
    function getTarget(): ?string
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