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

	/** @var string[] */
	private $aliases = [];


	public function __construct(array $defaultParams = [])
	{
		$this->defaultParams = $defaultParams;
	}


	function render(Response $response, string $name, array $params = [], Engine $latte = NULL): Response
	{
		// check if a registered rendering routine exists (also check aliases)
		$routine = $this->getRoutine($name);

		// a rendering routine exists, use it
		if ($routine !== NULL) {
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
		$content = $latte->renderToString($template, array_merge($this->defaultParams, $params));
		$response->getBody()->write($content);
		return $response;
	}


	function register(string $name, callable $routine, string $alias = NULL): self
	{
		$this->routines[$name] = $routine;
		$alias !== NULL && $this->alias($name, $alias);
		return $this;
	}


	function alias(string $target, string $alias): self
	{
		$this->aliases[$alias] = $target;
		return $this;
	}


	function getRoutine(string $name): ?callable
	{
		return $this->routines[$this->aliases[$name] ?? $name] ?? NULL;
	}


//	function default(string $name, $value): self
//	{
//		$this->defaultParams[$name] = $value;
//		return $this;
//	}

}