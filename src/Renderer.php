<?php

declare(strict_types=1);

namespace Dakujem\Latter;

use Latte\Engine;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Renderer interface.
 */
interface Renderer extends Agent
{
    /**
     * Complete a target template populating a response body.
     */
    function render(Response $response, string $target, array $params = [], ?Engine $latte = null): Response;
}
