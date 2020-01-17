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
        // get the rendering routine, fall back to the default one
        $routine = $this->getRoutine($target) ?? $this->getDefaultRoutine();
        // create a starting context
        $context = new Runtime($response, $target, $params, $latte ?? $this->getEngine());
        // execute the routine
        return $this->terminate($context, $routine);
    }


    /**
     * Render a given template to into a response body.
     * The actual Latte rendering occurs within.
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
                $routine = $this->getRoutine($target);
                if ($routine === null) {
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
        $executor = function (Runtime $context, array $routines) {
            return $this->execute($context, $routines);
        };
        $renderer = function (array $routines, Response $response, string $target, array $params = [], Engine $latte = null) use ($executor) {
            if (isset($routines[$target])) {
                throw new LogicException("Duplicate routines '$target' in the pipeline.");
            }
            // add the rendering routine
            $routines[] = $this->getRoutine($target) ?? $this->getDefaultRoutine();
            // add the terminating routine
            $routines[] = $this->terminal();
            // create a starting context
            $context = new Runtime($response, $target, $params, $latte ?? $this->getEngine());
            // execute the pipeline
            return call_user_func($executor, $context, $routines);
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
     * Ads a template alias.
     *
     * This is a shorthand method to register a routine that will render different target once invoked.
     *
     * @param string $name   the alias name
     * @param string $target a target to be rendered when using the alias
     * @return self
     */
    public function alias(string $name, string $target): self
    {
        $aliasRoutine = function (Runtime $context) use ($target): Response {
            $routine = $this->getRoutine($target) ?? $this->getDefaultRoutine();
            return $this->another($context->withTarget($target), $routine);
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


#   ++--------------------++
#   ||  Routine Chaining  ||
#   ++--------------------++

    /**
     * Terminate rendering using another render routine.
     *
     * This method is meant to be used within rendering routines to enable explicit chaining.
     *
     * @param Runtime       $context
     * @param callable|null $routine
     * @return Response
     */
    public function another(Runtime $context, callable $routine = null): Response
    {
        return $this->terminate($context, $routine);
    }


    /**
     * Execute given routines and return either a response or the final rendering context.
     *
     * This method is meant to be used within rendering routines to enable explicit chaining.
     *
     * @param Runtime    $context the initial context
     * @param callable[] $routines
     * @return Response|Runtime
     */
    public function execute(Runtime $context, array $routines)
    {
        foreach ($routines as $routine) {
            if ($routine !== null) {
                // execute a routine
                $result = call_user_func($routine, $context);

                // if a Response is returned by the routine, return it
                if ($result instanceof Response) {
                    return $result;
                }

                // if a new context is returned, it becomes the context for the next routine
                if ($result instanceof Runtime) {
                    $context = $result;
                }
            }
        }

        // if no routine returned a response, return the final context
        return $context;
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
     * @param Runtime       $context the initial context
     * @param callable|null $routine
     * @return Response
     */
    protected function terminate(Runtime $context, callable $routine = null): Response
    {
        $pipeline = [
            $routine, // Note: if a routine is `null`, it is ignored
            $this->terminal(),
        ];
        $result = $this->execute($context, $pipeline);
        if ($result instanceof Response) {
            return $result;
        }
        throw new RuntimeException('Rendering pipeline did not produce a response.');
    }


    /**
     * Return a function that will terminate rendering using a given context.
     * The terminal routine will invoke Latte rendering.
     *
     * @return callable
     */
    protected function terminal(): callable
    {
        return function (Runtime $context): Response {
            // no rendering routine exists, use the default one (needs an Engine instance)
            if ($context->getEngine() === null) {
                throw new LogicException('Engine is needed.');
            }
            return $this->respond($context->getResponse(), $context->getEngine(), $context->getTarget(), $context->getParams());
        };
    }


#   ++----------------------++
#   ||  Unfinished / draft  ||
#   ++----------------------++

    /**
     * This method is a failed attempt to enable implicit chaining,
     * that is, right before the terminal routine is called, the execution checks if the context's target
     * does not point to an existing routine, and if so, renders it,
     * until a response is returned or the target does not exist.
     * This has a problem though - there is no way to know if a pipeline ended with the target being rendered or not,
     * which could cause double rendering of the last routine.
     *
     * @internal
     * @deprecated
     *
     * @param Runtime $context
     * @param string  $target
     * @return Response
     */
    private function finalize(Runtime $context, string $target): Response
    {
        $targets = []; // to prevent loops
        $default = $this->getDefaultRoutine();
        while ($context->getTarget() !== $target) {
            $routine = $this->getRoutine($context->getTarget()) ?? $default;
            $result = $this->execute($context, [$routine]);

            // a response has been returned => terminate rendering.
            if ($result instanceof Response) {
                return $result;
            }
            // the context has not changed or the target has not changed => render the target using Latte
            if ($result === $context || $result->getTarget() === $context->getTarget()) {
                break;
            }
            // the rendering target has changed => try to process it
            $target = $context->getTarget();
            $context = $result;
            if ($routine === $default) {
                // to prevent default routine being called multiple times
                $default = null;
            }
            $targets[$target] = true;
            if (isset($targets[$context->getTarget()])) {
                throw new LogicException('Rendering loop detected: ' . implode('-', array_keys($targets)) . '-' . $context->getTarget());
            }
        }

        // no rendering routine exists, use the default one (needs an Engine instance)
        if ($context->getEngine() === null) {
            throw new LogicException('Engine is needed.');
        }
        return $this->respond($context->getResponse(), $context->getEngine(), $context->getTarget(), $context->getParams());
    }


    private function chainTarget(): callable
    {
        return function (Runtime $context) {
            $routine = $this->getRoutine($context->getTarget());
            return $routine !== null ? $this->another($context, $routine) : null;
        };
    }

    
// The End.
}
