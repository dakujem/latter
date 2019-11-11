<?php


use InvalidArgumentException;
use Latte\Engine;


/**
 * Factory
 */
class TemplateFactory // mirror, wrapper, substitute
{

	private $factory;
	private $decorator;


	/**
	 * @param callable|TemplateFactory $factory
	 * @param EngineDecorator $decorator
	 */
	public function __construct($factory, EngineDecorator $decorator)
	{
		if (!$factory instanceof TemplateFactory && !is_callable($factory)) {
			throw new InvalidArgumentException();
		}
		$this->factory = $factory;
		$this->decorator = $decorator;




	}


	function createTemplate(string $name): Engine
	{
//		split by . character

		// tu sa nakkonfiguruje instancia Engine a globalne filtre a podobne veci
		// toto vsetko moze byt v nejakej tovarnicke, optimalne v DIC
		// toto by nemalo byt v tomto balciku, pretoze je to vysoko specificke pre projekt/framework a pod.

		$t = new Engine();

		$t->setTempDirectory($path);
		$t->setAutoRefresh(false); // in debug only

		// multi-dir lookup, split by dot

//
//		$latteFactory = $builder->addFactoryDefinition($this->prefix('latteFactory'))
//			->setImplement(Nette\Bridges\ApplicationLatte\ILatteFactory::class)
//			->getResultDefinition()
//			->setFactory(Latte\Engine::class)
//			->addSetup('setTempDirectory', [$this->tempDir])
//			->addSetup('setAutoRefresh', [$this->debugMode])
//			->addSetup('setContentType', [$config->xhtml ? Latte\Compiler::CONTENT_XHTML : Latte\Compiler::CONTENT_HTML])
//			->addSetup('Nette\Utils\Html::$xhtml = ?', [$config->xhtml]);
//



		$template = $this->factory instanceof TemplateFactory ?
			$this->factory->createTemplate(...$args) :
			call_user_func($this->factory, ...$args);
		return $this->decorator->decorate($template);
	}


}