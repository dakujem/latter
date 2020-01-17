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
     *
     * @param Response    $response
     * @param string      $target
     * @param array       $params
     * @param Engine|null $latte
     * @return Response
     */
    function render(Response $response, string $target, array $params = [], Engine $latte = null): Response;
}
