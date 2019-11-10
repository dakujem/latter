<?php


namespace Dakujem\Latter;


use Latte\Engine;
use LogicException;
use Psr\Http\Message\ResponseInterface as Response;

class View
{

    /** @var array */
    private $defaultParams = [];

    /** @var callable[] */
    private $routines = [];

    /** @var callable */
    private $loader = null;

    /** @var string[] */
    private $aliases = [];

    /** @var EngineDecorator|null */
    private $decorator = null;


    public function __construct(array $defaultParams = [])
    {
        $this->defaultParams = $defaultParams;
    }


    function render(Response $response, string $name, array $params = [], Engine $latte = null): Response
    {
        // check if a registered rendering routine exists (also check aliases)
        $routine = $this->getRoutine($name) ?? $this->getLoader();

        // a rendering routine exists, use it
        if ($routine !== null) {
            return call_user_func($routine, $response, $name, $params, $latte, $this);
        }

        // no rendering routine exists, use the default one (needs an Engine instance)
        if (!$latte instanceof Engine) {
            throw new LogicException();
        }
        return $this->respond($response, $latte, $name, $params);
    }


    function respond(Response $response, Engine $latte, string $template, array $params): Response
    {
        $content = $this->decorateEngine($latte)->renderToString($template, array_merge($this->defaultParams, $params));
        $response->getBody()->write($content);
        return $response;
    }


    function register(string $name, callable $routine, string $alias = null): self
    {
        $this->routines[$name] = $routine;
        $alias !== null && $this->alias($name, $alias);
        return $this;
    }


    function loader(callable $loader = null): self
    {
        $this->loader = $loader;
        return $this;
    }


    function decorator(EngineDecorator $decorator = null): self
    {
        $this->decorator = $decorator;
        return $this;
    }


    function alias(string $target, string $alias): self
    {
        $this->aliases[$alias] = $target;
        return $this;
    }


    function getRoutine(string $name): ?callable
    {
        return $this->routines[$this->aliases[$name] ?? $name] ?? null;
    }


    function getLoader(): ?callable
    {
        return $this->loader;
    }


    function decorateEngine(Engine $latte): Engine
    {
        return $this->decorator !== null ? $this->decorator->decorate($latte) : $latte;
    }




//	function default(string $name, $value): self
//	{
//		$this->defaultParams[$name] = $value;
//		return $this;
//	}

}