<?php


namespace Dakujem\Latter;


use Latte\Engine;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Renderer
 */
interface Renderer
{

    function render(Response $response, string $target, array $params = [], Engine $latte = null): Response;

}