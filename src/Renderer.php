<?php

namespace Dakujem\Latter;

use Latte\Engine;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Renderer interface.
 */
interface Renderer
{
    /**
     * Render a target template populating a response.
     */
    function render(Response $response, string $target, array $params = [], ?Engine $latte = null): Response;
}
