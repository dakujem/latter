<?php


namespace Dakujem\Latter;

use Latte\Engine;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;

/**
 * Pipeline
 */
class Pipeline
{
    /** @var View */
    private $view;

    /** @var callable[] */
    private $queue = [];


    /**
     * @param View $view
     * @param callable[] $queue
     */
    function __construct(View $view, array $queue)
    {
        $this->view = $view;
        $this->queue = $queue;
    }


    function render(Response $response, string $target, array $params = [], Engine $latte = null): Response
    {
        $context = new Runtime($this->view, $response, $target, $params, $latte);
        $routine = function (Runtime $context, callable $next, string $name): Response {
            return $this->view->render(
                $context->getResponse(),
                $context->getTarget(),
                $context->getParams(),
                $context->getEngine()
            );
        };

        $name = $this->view->getName($target) ?? $target;

        $routines = $this->queue;
        $routines[$name] = $routine;

        return $this->execute($routines, $context);
    }


    private function execute(array $routines, $context): Response
    {
        foreach ($routines as $name => $routine) {

            // TODO invoke the pipeline recursively

            $context = call_user_func($routine, $context, $next, $name);

            if ($context instanceof Response) {
                return $context;
            }
        }

        throw new RuntimeException('Rendering pipeline did not produce a response.');
    }


}