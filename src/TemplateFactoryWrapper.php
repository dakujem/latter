<?php


namespace Dakujem\Latter;


use InvalidArgumentException;
use Nette\Application\UI\ITemplate as Template;
use Nette\Application\UI\ITemplateFactory as TemplateFactory;

/**
 * Factory
 */
class TemplateFactoryWrapper // mirror, wrapper, substitute
{

	private $factory;
	private $decorator;


	/**
	 * @param callable|TemplateFactory $factory
	 * @param TemplateDecorator $decorator
	 */
	public function __construct($factory, TemplateDecorator $decorator)
	{
		if (!$factory instanceof TemplateFactory && !is_callable($factory)) {
			throw new InvalidArgumentException();
		}
		$this->factory = $factory;
		$this->decorator = $decorator;
	}


	function createTemplate(...$args): Template
	{
		$template = $this->factory instanceof TemplateFactory ?
			$this->factory->createTemplate(...$args) :
			call_user_func($this->factory, ...$args);
		return $this->decorator->decorate($template);
	}


}