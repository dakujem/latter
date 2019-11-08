<?php


namespace Dakujem\Latter;


/**
 * DecoratorPipeline
 */
class DecoratorPipeline implements TemplateDecorator
{

	function decorate(Template $template, ...$args):Template
	{
		foreach ($this->decorators as $decorator) {
			$template = $decorator->decorate($template, ...$args);
		}
		return $template;
	}


}