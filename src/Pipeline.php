<?php


namespace Dakujem\Latter;

use Latte\Engine;
use LogicException;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;

/**
 * A pipeline renderer.
 */
final class Pipeline implements Renderer
{
    /** @var View */
    private $view;

    /** @var callable[] */
    private $queue = [];


    /**
     * @param View       $view
     * @param callable[] $queue
     */
    function __construct(View $view, array $queue)
    {
        $this->view = $view;
        $this->queue = $queue;
    }


    public function __invoke(Runtime $context): Response
    {
        return static::execute($this->queue, $context);
    }


    public function render(Response $response, string $target, array $params = [], Engine $latte = null): Response
    {
        $name = $this->view->getName($target) ?? $target;
        $routines = $this->queue;
        if (isset($routines[$name])) {
            throw new LogicException("Duplicate routines '$name' in the pipeline.");
        }
        $routines[$name] = function (Runtime $context): Response {
            return $this->view->render(
                $context->getResponse(),
                $context->getTarget(),
                $context->getParams(),
                $context->getEngine()
            );
        };
        $context = new Runtime($this->view, $response, $target, $params, $latte);
        return static::execute($routines, $context);
    }


    private static function execute(array $routines, Runtime $context): Response
    {
        foreach ($routines as $name => $routine) {
            $result = call_user_func($routine, $context, $name);

            // if a Response is returned, return it
            if ($result instanceof Response) {
                return $result;
            }

            // if a new context is returned, it becomes the context for the next routine
            if ($result instanceof Runtime) {
                $context = $result;
            }
        }

        throw new RuntimeException('Rendering pipeline did not produce a response.');
    }

}