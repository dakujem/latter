<?php

namespace Dakujem\Latter;

use Closure;
use Latte\Engine;
use LogicException;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;

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


#   ++-------------++
#   ||  Rendering  ||
#   ++-------------++

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
//        $name = $this->getName($target) ?? $target;

        // check if a registered rendering routine exists
        $routine = $this->getRoutine($target) ?? $this->getDefaultRoutine();

        $context = new Runtime(/*$this,*/ $response, $target, $params, $latte ?? $this->getEngine());

        return $this->terminate($context, $routine);

        // todo missing $name param if run this way...
        return static::execute([$routine, function (Runtime $context, string $name): Response {
            return $this->terminate($context, $name, null);
        }], $context);
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
     * Create a rendering pipeline from registered routine names or callable routines.
     *
     * @param array ...$routines
     * @return PipelineRelay
     */
    public function pipeline(...$routines): PipelineRelay
    {
        $queue = [];
        foreach ($routines as $target) {
            if (is_string($target)) {
//                $name = $this->getName($target) ?? $target;
                $routine = $this->getRoutine($target);
                if ($routine === null) {
//                    $target = $name . ($name !== $target ? ' ' . $target : '');
                    throw new LogicException("Routine {$target} not registered.");
                }
            } elseif (is_callable($target)) {
                $routine = $target;
                $target = is_object($routine) ? spl_object_hash($routine) : md5(serialize($routine));
            } else {
                throw new LogicException('Invalid routine type. Please provide a routine name or a callable.');
            }
            if (isset($queue[$target])) {
                throw new LogicException("Duplicate routines '{$target}' in the pipeline.");
            }
            $queue[$target] = $routine;
        }
        $executor = function (array $routines, Runtime $context): Response {
            return static::execute($routines, $context);
        };
        $renderer = function (array $routines, Response $response, string $target, array $params = [], Engine $latte = null) use ($executor) {
//            $name = $this->getName($target) ?? $target;
            if (isset($routines[$target])) {
                throw new LogicException("Duplicate routines '$target' in the pipeline.");
            }
//            $routines[$name] = function (Runtime $context): Response {
//                return $this->render(
//                    $context->getResponse(),
//                    $context->getTarget(),
//                    $context->getParams(),
//                    $context->getEngine()
//                );
//            };

            // add the terminating routine
            $routines[$target] = function (Runtime $context): Response {
                return $this->terminate($context, null);
            };
            // create the starting context
            $context = new Runtime($response, $target, $params, $latte ?? $this->getEngine());
            // execute the pipeline
            return call_user_func($executor, $routines, $context);
        };
        return new PipelineRelay($queue, $executor, $renderer);
    }


#   ++-----------------++
#   ||  Configuration  ||
#   ++-----------------++

    /**
     * Register a render routine (or a pipeline pre-render routine).
     *
     * Routine signature:
     *      function(
     *          Dakujem\Latter\Runtime  $runtime,
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
     * @return self
     */
    public function register(string $name, callable $routine, string $alias = null): self
    {
        $this->routines[$name] = $routine;
        $alias !== null && $this->alias($alias, $name);
        return $this;
    }


    /**
     * Register a default/fallback routine to be used when rendering a template
     * for which no routine has been registered. The callable has the same signature as a normal render routine.
     *
     * @param callable|null $routine
     * @return self
     */
    public function registerDefault(callable $routine = null): self
    {
        $this->defaultRoutine = $routine;
        return $this;
    }


    /**
     * Ads an alias.
     * @deprecated brØken
     *
     * @param string $name  the name of the template or the rendering routine
     * @param string $alias an alias that can be used to render the template
     * @return self
     */
    public function alias__broken(string $name, string $alias): self
    {
        // todo aliases cause trouble with $name. Aliases are brØken.
        $this->aliases[$alias] = $name;
        return $this;
    }


    /**
     * Ads a template alias.
     *
     * This is a shorthand method to register a routine that will switch the rendering target once invoked.
     *
     * @param string $name   the alias name
     * @param string $target a target to be rendered when using the alias
     * @return self
     */
    public function alias(string $name, string $target): self
    {
        $aliasRoutine = function (Runtime $context) use ($target) {
            $routine = $this->getRoutine($target) ?? $this->getDefaultRoutine();
            return $this->terminate($context->withTarget($target), $routine);
        };
        return $this->register($name, $aliasRoutine);
    }


    /**
     * Set a single default rendering parameter.
     *
     * @param string $name
     * @param mixed  $value
     * @return self
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
     * @return self
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
     * @return self
     */
    public function setEngine(callable $provider): self
    {
        $this->engine = $provider;
        return $this;
    }


    /**
     * TODO check !!
     *
     * Configure the view instance easier by binding the configurator closure to it.
     *
     * It is then possible to use $this inside the closures to get the instance.
     *
     * @param Closure $configurator
     * @param mixed   ...$args
     * @return self
     */
    public function configure(Closure $configurator, ...$args): self
    {
        call_user_func($configurator->bindTo($this), ...$args);
        return $this;
    }


#   ++-----------++
#   ||  Getters  ||
#   ++-----------++

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


#   ++------------++
#   ||  Internal  ||
#   ++------------++

    /**
     * Terminate rendering using a context and a routine, if provided.
     *
     * If no Response object is returned by the routine,
     * the function will render the target template and write to the Response object's body.
     *
     * @param Runtime       $context
     * @param callable|null $routine
     * @return Response
     */
    private function terminate(Runtime $context, callable $routine = null): Response
    {
        if ($routine !== null) {
            $result = call_user_func($routine, $context);
            if ($result instanceof Response) {
                return $result;
            }
            if ($result instanceof Runtime) {
                $context = $result;
            }
        }

        // no rendering routine exists, use the default one (needs an Engine instance)
        if ($context->getEngine() === null) {
            throw new LogicException('Engine is needed.');
        }
        return $this->respond($context->getResponse(), $context->getEngine(), $context->getTarget(), $context->getParams());
    }


    /**
     * @internal todo
     * TODO make this public or remove all together ??
     *
     * Terminate rendering using a different render routine.
     * Note: Using this will cause dependency. Consider using pipelines instead.
     *
     * @param Runtime $context
     * @param string  $name
     * @return Response
     */
    public function next(Runtime $context, string $name)
    {
        return $this->terminate($context, $this->getRoutine($name));
    }


    private static function execute(array $routines, Runtime $context): Response
    {
        foreach ($routines as $routine) {
            $result = call_user_func($routine, $context);

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