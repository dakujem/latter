<?php


namespace Dakujem\Latter;


use Latte\Engine;
use LogicException;
use Psr\Http\Message\ResponseInterface as Response;

class View implements Renderer
{
    /** @var callable[] */
    private $routines = [];

    /** @var callable|null */
    private $defaultRoutine = null;

    /** @var array */
    private $defaultParams = [];

    /** @var callable|null function():Engine */
    private $engine = null;

    /** @var string[] */
    private $aliases = [];

    /** @var callable|null */
    private $decorator = null;


    function __construct(array $defaultParams = [])
    {
        $this->defaultParams = $defaultParams;
    }


    function render(Response $response, string $target, array $params = [], Engine $latte = null): Response
    {
        // check for $target alias
        $name = $this->getName($target) ?? $target;

        // check if a registered rendering routine exists
        $routine = $this->getRoutine($name) ?? $this->getDefaultRoutine();

        // a rendering routine exists, use it
        if ($routine !== null) {
            $context = new Runtime($this, $response, $target, $params, $latte);
            return call_user_func($routine, $context, $name);
        }

        // no rendering routine exists, use the default one (needs an Engine instance)
        $engine = $latte ?? $this->getEngine();
        if (!$engine instanceof Engine) {
            throw new LogicException();
        }
        return $this->respond($response, $engine, $name, $params);
    }


    function respond(Response $response, Engine $latte, string $template, array $params): Response
    {
        $content = $latte->renderToString($template, array_merge($this->getDefaultParams(), $params));
        $response->getBody()->write($content);
        return $response;
    }


    function pipeline(...$routines): Pipeline
    {
        $queue = [];
        foreach ($routines as $key) {
            $name = $this->getName($key) ?? $key;
            $routine = $this->getRoutine($name);
            if ($routine === null) {
                $target = $name . ($name !== $key ? ' ' . $key : '');
                throw new LogicException("Routine {$target} not registered.");
            }
            if (isset($queue[$name])) {
                throw new LogicException("Duplicate routines '$name' in the pipeline.");
            }
            $queue[$name] = $routine;
        }
        return new Pipeline($this, $queue);
    }


    function register(string $name, callable $routine, string $alias = null): self
    {
        $this->routines[$name] = $routine;
        $alias !== null && $this->alias($name, $alias);
        return $this;
    }


    function defaultRoutine(callable $routine = null): self
    {
        $this->defaultRoutine = $routine;
        return $this;
    }


    function alias(string $name, string $alias): self
    {
        $this->aliases[$alias] = $name;
        return $this;
    }


    function param(string $name, $value): self
    {
        $this->defaultParams[$name] = $value;
        return $this;
    }


    function engine(callable $provider): self
    {
        $this->engine = $provider;
        return $this;
    }


    function getRoutine(string $name): ?callable
    {
        return $this->routines[$name] ?? null;
    }


    function getDefaultRoutine(): ?callable
    {
        return $this->defaultRoutine;
    }


    function getName(string $target): ?string
    {
        return $this->aliases[$target] ?? null;
    }


    function getDefaultParams(): array
    {
        return $this->defaultParams;
    }


    function getEngine(): ?Engine
    {
        return $this->engine ? call_user_func($this->engine) : null;
    }

}