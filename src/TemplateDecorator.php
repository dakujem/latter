<?php


namespace Dakujem\Latter;


/**
 * TemplateDecorator
 */
interface TemplateDecorator
{

	function decorate(Template $template):Template;

}