<?php

declare(strict_types=1);

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

#   ++--------------------------++
#   ||  Completing / Rendering  ||
#   ++--------------------------++

    /**
     * Complete (render) a target template.
     * Return the resulting document as string.
     *
     * @param string      $target
     * @param array       $params
     * @param Engine|null $latte
     * @return string
     */
    public function complete(string $target, array $params = [], Engine $latte = null): string
    {

        // TODO this could be used in general, for any stacks.
        //      there is a class of resulting problems though - like terminating a pipeline by returning a Response (which might itself be obsolete),
        //      but the response would not be in the Runtime object. It could be solved by returning a (new) Result object.
        //
        // TODO interface, Runtime

        // get the rendering routine, fall back to the default one
        $routine = $this->getRoutine($target) ?? $this->getDefaultRoutine();
        // create a starting context
        $context = new Runtime($target, $params, $latte ?? $this->getEngine());
        // execute the routine
        return $this->terminate($context, $routine);
    }


    /**
     * Complete (render) a target template populating a response body.
     *
     * @param Response $response
     * @param string $target
     * @param array $params
     * @param Engine|null $latte
     * @return Response
     */
    public function render(Response $response, string $target, array $params = [], Engine $latte = null): Response
    {
        $content = $this->complete($target, $params, $latte);
        return $this->populateResponse($response, $content);
    }

    /**
     * Render a given template to into a response body.
     * The actual Latte rendering occurs within.
     *
     * @param Response $response
     * @param Engine $latte
     * @param string $template
     * @param array $params
     * @return Response
     */
    public function respond(Response $response, Engine $latte, string $template, array $params): Response
    {
        $content = $latte->renderToString($template, array_merge($this->getDefaultParams(), $params));
        $response->getBody()->write($content);
        return $response;
    }

    /**
     * Render a given template to string.
     * The actual Latte rendering process occurs within.
     *
     * @param Engine   $latte
     * @param string   $template
     * @param array    $params
     * @return string
     */
    private function renderLatteTemplate(Engine $latte, string $template, array $params = []): string
    {
        return $latte->renderToString($template, array_merge($this->getDefaultParams(), $params));
    }


    /**
     * Write given content to a Response object's body.
     *
     * @param Response $response
     * @param string   $content
     * @return Response
     */
    private function populateResponse(Response $response, string $content): Response
    {
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
        $agent = function (array $routines, string $target, array $params = [], Engine $latte = null) use ($executor) {
            if (isset($routines[$target])) {
                throw new LogicException("Duplicate routines '$target' in the pipeline.");
            }
            // add the rendering routine
            $routines[] = $this->getRoutine($target) ?? $this->getDefaultRoutine();
            // add the terminating routine
            $routines[] = $this->terminal();
            // create a starting context
            $context = new Runtime($target, $params, $latte ?? $this->getEngine());
            // execute the pipeline
            return $executor($context, $routines);
        };
        $renderer = function (array $routines, Response $response, string $target, array $params = [], Engine $latte = null) use ($agent) {
            $content =  call_user_func($agent, $routines, $target, $params, $latte);
            return $this->populateResponse($response, $content);
        };
        return new PipelineRelay($queue, $executor, $agent, $renderer);
    }

#   ++-----------------++
#   ||  Configuration  ||
#   ++-----------------++

    /**
     * Register a render routine.
     *
     * Routine signature:
     *      function(
     *          Dakujem\Latter\Runtime  $runtime,
     *      ): string|Dakujem\Latter\Runtime|null
     *
     * A routine can return a string or a Runtime object.
     * If a Runtime object is returned, it is passed to the next routine in the rendering pipeline.
     * If a string is returned, the pipeline ends and the result is used.
     *
     * @param string $name
     * @param callable $routine
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
     * @param string $name the alias name
     * @param string $target a target to be rendered when using the alias
     * @return self
     */
    public function alias(string $name, string $target): self
    {
        $aliasRoutine = function (Runtime $context) use ($target): string {
            $routine = $this->getRoutine($target) ?? $this->getDefaultRoutine();
            return $this->another($context->withTarget($target), $routine);
        };
        return $this->register($name, $aliasRoutine);
    }

    /**
     * Set a single default rendering parameter.
     *
     * @param string $name
     * @param mixed $value
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
     * @param mixed ...$args
     * @return self
     */
    public function configure(Closure $configurator, ...$args): self
    {
        ($configurator->bindTo($this))(...$args);
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
     * @param Runtime $context
     * @param callable|null $routine
     * @return string
     */
    public function another(Runtime $context, callable $routine = null): string
    {
        return $this->terminate($context, $routine);
    }

    /**
     * Execute given routines and return either a response or the final rendering context.
     *
     * This method is meant to be used within rendering routines to enable explicit chaining.
     *
     * @param Runtime $context the initial context
     * @param callable[] $routines
     * @return string|Runtime
     */
    public function execute(Runtime $context, array $routines)
    {
        foreach ($routines as $routine) {
            if ($routine !== null) {
                // execute a routine
                $result = $routine($context);

                // if a Response is returned by the routine, return it
                if (is_string($result)) {
                    return $result;
                }

                // if a new context is returned, it becomes the context for the next routine
                if ($result instanceof Runtime) {
                    $context = $result;
                }
            }
        }

        // if no routine returned a string result, return the final context
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
        return $this->engine ? ($this->engine)() : null;
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
     * @param Runtime $context the initial context
     * @param callable|null $routine
     * @return string
     */
    protected function terminate(Runtime $context, callable $routine = null): string
    {
        $pipeline = [
            $routine, // Note: if a routine is `null`, it is ignored
            $this->terminal(),
        ];
        $result = $this->execute($context, $pipeline);
        if (is_string($result)) {
            return $result;
        }
        throw new RuntimeException('Rendering pipeline did not produce an expected result.');
    }

    /**
     * Return a function that will terminate rendering using a given context.
     * The terminal routine will invoke Latte rendering.
     *
     * @return callable
     */
    protected function terminal(): callable
    {
        return function (Runtime $context): string {
            // no rendering routine exists, use the default one (needs an Engine instance)
            if ($context->getEngine() === null) {
                throw new LogicException('Engine is needed.');
            }
            return $this->renderLatteTemplate($context->getEngine(), $context->getTarget(), $context->getParams());
        };
    }
}
