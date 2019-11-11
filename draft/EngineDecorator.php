<?php


namespace Dakujem\Latter;


use Latte\Engine;

/**
 * Generic decorator interface for Latte Engine instances.
 */
interface EngineDecorator
{

	function decorate(Engine $latte): Engine;

}