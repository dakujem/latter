<?php

namespace Dakujem\Latter;

use Latte\Engine;
use LogicException;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * The standard Latter renderer.
 */
class View implements Renderer
{
    /** @var callable[] */
    protected $routines = [];

    /** @var string[] */
    protected $aliases = [];

    /** @var callable|null */
    protected $defaultRoutine = null;

    /** @var array */
    protected $defaultParams = [];

    /** @var callable|null function():Engine */
    protected $engine = null;


    /**
     * Prepare and render a target template into a response body.
     *
     * @param Response    $response
     * @param string      $target
     * @param array       $params
     * @param Engine|null $latte
     * @return Response
     */
    public function render(Response $response, string $target, array $params = [], Engine $latte = null): Response
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


    /**
     * Render a given template to into a response body.
     * This is the default rendering process.
     *
     * @param Response $response
     * @param Engine   $latte
     * @param string   $template
     * @param array    $params
     * @return Response
     */
    public function respond(Response $response, Engine $latte, string $template, array $params): Response
    {
        $content = $latte->renderToString($template, array_merge($this->getDefaultParams(), $params));
        $response->getBody()->write($content);
        return $response;
    }


    /**
     * Create a rendering pipeline from given routine names.
     *
     * @param string[] ...$routines
     * @return Pipeline
     */
    public function pipeline(...$routines): Pipeline
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


    /**
     * Register a render routine (or a pipeline pre-render routine).
     *
     * Routine signature:
     *      function(
     *          Dakujem\Latter\Runtime  $runtime,
     *          string                  $name,    // name under which the routine is registered
     *      ): Psr\Http\Message\ResponseInterface|Dakujem\Latter\Runtime|void
     *
     * A render routine must return a ResponseInterface implementation.
     * A pre-render routine used in pipelines may return a Runtime object, in which case it is passed
     * to the next routine in the pipeline. If a ResponseInterface implementation is returned,
     * the pipeline ends and the response is used.
     *
     * @param string      $name
     * @param callable    $routine
     * @param string|null $alias
     * @return $this
     */
    public function register(string $name, callable $routine, string $alias = null): self
    {
        $this->routines[$name] = $routine;
        $alias !== null && $this->alias($name, $alias);
        return $this;
    }


    /**
     * Register a default/fallback routine to be used when rendering a template
     * for which no routine has been registered. The callable has the same signature as a normal render routine.
     *
     * @param callable|null $routine
     * @return $this
     */
    public function registerDefault(callable $routine = null): self
    {
        $this->defaultRoutine = $routine;
        return $this;
    }


    /**
     * Ads an alias.
     *
     * @param string $name
     * @param string $alias
     * @return $this
     */
    public function alias(string $name, string $alias): self
    {
        $this->aliases[$alias] = $name;
        return $this;
    }


    /**
     * Set a single default rendering parameter.
     *
     * @param string $name
     * @param mixed  $value
     * @return $this
     */
    public function setParam(string $name, $value): self
    {
        $this->defaultParams[$name] = $value;
        return $this;
    }


    /**
     * Set all default rendering parameters.
     * Note: Will overwrite previously set parameters.
     *
     * @param array $params
     * @return $this
     */
    public function setParams(array $params): self
    {
        $this->defaultParams = $params;
        return $this;
    }


    /**
     * Set Latte engine to be used.
     *
     * @param callable $provider
     * @return $this
     */
    public function setEngine(callable $provider): self
    {
        $this->engine = $provider;
        return $this;
    }


    public function getRoutine(string $name): ?callable
    {
        return $this->routines[$name] ?? null;
    }


    public function getDefaultRoutine(): ?callable
    {
        return $this->defaultRoutine;
    }


    public function getName(string $target): ?string
    {
        return $this->aliases[$target] ?? null;
    }


    public function getDefaultParams(): array
    {
        return $this->defaultParams;
    }


    public function getEngine(): ?Engine
    {
        return $this->engine ? call_user_func($this->engine) : null;
    }

}