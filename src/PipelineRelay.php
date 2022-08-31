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
 *
 * Immutable.
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


    public function __invoke(...$args)
    {
        return $this->execute(...$args);
    }


    public function execute(Runtime $context, ...$args)
    {
        return ($this->executor)($context, $this->routines, ...$args);
    }


    public function render(Response $response, string $target, array $params = [], Engine $latte = null, ...$args): Response
    {
        return ($this->renderHandler)($this->routines, $response, $target, $params, $latte, ...$args);
    }
}
