<?php


namespace Dakujem\Latter;


use Latte\Engine;
use Psr\Http\Message\ResponseInterface;

/**
 * Render runtime object.
 */
class Runtime
{

    /**
     * @var ResponseInterface
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
     * @param ResponseInterface $response
     * @param View $view
     * @param Engine|null $engine
     * @param array $params
     * @param string $target
     * @param $more
     */
    function __construct(
        View $view,
        ResponseInterface $response,
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
        ResponseInterface $response,
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


    /**
     * @return ResponseInterface
     */
    function getResponse(): ResponseInterface
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