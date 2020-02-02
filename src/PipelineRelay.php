<?php

declare(strict_types=1);

namespace Dakujem\Latter;

use Latte\Engine;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * A pipeline renderer relay.
 *
 * Allows for the following constructions:
 * $view->pipeline( ... )->complete( ... );
 * $view->pipeline( ... )->render( ... );
 * $view->register( ..., $view->pipeline( ... ) );
 *
 * Immutable.
 */
final class PipelineRelay implements Renderer
{
    /** @var callable[] */
    private array $routines;
    /** @var callable */
    private $executor;
    /** @var callable|null */
    private $agent;
    /** @var callable */
    private $renderer;

    public function __construct(
        array $routines,
        callable $executor,
        callable $agent,
        callable $renderer = null
    )
    {
        $this->routines = $routines;
        $this->executor = $executor;
        $this->agent = $agent;
        $this->renderer = $renderer;
    }

    public function __invoke(...$args)
    {
        return $this->execute(...$args);
    }

    public function execute(Runtime $context, ...$args)
    {
        return ($this->executor)($context, $this->routines, ...$args);
    }

    public function complete(string $target, array $params = [], Engine $latte = null, ...$args): string
    {
        return call_user_func($this->agent, $this->routines, $target, $params, $latte, ...$args);
    }


    public function render(Response $response, string $target, array $params = [], Engine $latte = null, ...$args): Response
    {
        return ($this->renderer)($this->routines, $response, $target, $params, $latte, ...$args);
    }
}
