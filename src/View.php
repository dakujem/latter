<?php


namespace Dakujem\Latter;


use Latte\Engine;
use LogicException;
use Psr\Http\Message\ResponseInterface as Response;

class View
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


    function render(Response $response, string $name, array $params = [], Engine $latte = null): Response
    {
        // check for $name alias
        $target = $this->getTarget($name);

        // check if a registered rendering routine exists
        $routine = $this->getRoutine($target ?? $name) ?? $this->getDefaultRoutine();

        // a rendering routine exists, use it
        if ($routine !== null) {
            return call_user_func($routine, $this, $response, $params, $latte, $target ?? $name, $name);
        }

        // no rendering routine exists, use the default one (needs an Engine instance)
        if (!$latte instanceof Engine) {
            throw new LogicException();
        }
        return $this->respond($response, $latte, $target ?? $name, $params);
    }


    function respond(Response $response, Engine $latte, string $template, array $params): Response
    {
        $content = $this->decorateEngine($latte)->renderToString($template, array_merge($this->getDefaultParams(), $params));
        $response->getBody()->write($content);
        return $response;
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


    function alias(string $target, string $alias): self
    {
        $this->aliases[$alias] = $target;
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


    function decorator(callable $decorator = null): self
    {
        $this->decorator = $decorator;
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


    function getTarget(string $name): ?string
    {
        return $this->aliases[$name] ?? null;
    }


    function getDefaultParams(): array
    {
        return $this->defaultParams;
    }


    function getEngine(): ?Engine
    {
        return $this->engine ? call_user_func($this->engine) : null;
    }


    function decorateEngine(Engine $latte): Engine
    {
        return $this->decorator !== null ? call_user_func($this->decorator, $latte) : $latte;
    }

}