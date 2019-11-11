<?php


namespace Dakujem\Latter;


use Latte\Engine;

/**
 * DecoratorPipeline
 */
class DecoratorPipeline implements EngineDecorator
{

	private $decorators = [];


	function add(EngineDecorator $decorator): self
	{
		$this->decorators[] = $decorator;
		return $this;
	}


	function decorate(Engine $latte, ...$args): Engine
	{
		foreach ($this->decorators as $decorator) {
			$latte = $decorator->decorate($latte, ...$args);
		}
		return $latte;
	}


	public function __invoke(Engine $latte, ...$args)
	{
		return $this->decorate($latte, ...$args);
	}

}