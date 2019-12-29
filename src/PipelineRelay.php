<?php

namespace Dakujem\Latter;

use Latte\Engine;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * A pipeline renderer relay.
 *
 * Allows for the following constructions:
 * $view->pipeline( ... )->render( ... );
 * $view->register( ..., $view->pipeline( ... ) );
 */
final class PipelineRelay implements Renderer
{
    /** @var callable[] */
    private $routines = [];
    /** @var callable */
    private $executor;
    /** @var callable */
    private $renderHandler;


    public function __construct(array $routines, callable $executor, callable $renderHandler = null)
    {
        $this->routines = $routines;
        $this->executor = $executor;
        $this->renderHandler = $renderHandler;
    }


    public function __invoke(...$args): Response
    {
        return $this->execute(...$args);
    }


    public function execute(Runtime $context, ...$args): Response
    {
        return call_user_func($this->executor, $this->routines, $context, ...$args);
    }


    public function render(Response $response, string $target, array $params = [], Engine $latte = null, ...$args): Response
    {
        return call_user_func($this->renderHandler, $this->routines, $response, $target, $params, $latte, ...$args);
    }
}
