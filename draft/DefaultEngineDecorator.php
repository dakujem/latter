<?php


namespace Dakujem\Latter;


use Latte\Engine;

/**
 * DefaultTemplateDecorator
 */
class DefaultEngineDecorator implements EngineDecorator
{

	/** @var array */
	protected $providers = [];

	/** @var callable[] */
	protected $lazyProviders = [];

	/** @var array */
	protected $variables = [];

	/** @var array */
	protected $filters = [];


	public function decorate(Engine $latte, ...$args): Engine
	{
		// Providers
		foreach ($this->providers as $key => $provider) {
			$latte->addProvider($key, $provider);
		}
		foreach ($this->lazyProviders as $key => $lazyProvider) {
			$latte->addProvider($key, call_user_func($lazyProvider, ...$args)); // resolve lazy providers
		}

		// Variables
		foreach ($this->variables as $key => $value) {
			$latte->{$key} = $value;
		}

		// Filters
		foreach ($this->filters as $key => $value) {
			$latte->addFilter($key, $value);
		}

		// Macros

		// TODO add macros

		return $latte;
	}


	public function addProvider(string $key, $provider): self
	{
		$this->providers[$key] = $provider;
		return $this;
	}


	public function addLazyProvider(string $key, callable $providerProvider): self
	{
		$this->lazyProviders[$key] = $providerProvider;
		return $this;
	}


	public function addVariable(string $key, $value): self
	{
		$this->variables[$key] = $value;
		return $this;
	}


	public function addFilter(string $name, callable $filter): self
	{
		$this->filters[$name] = $filter;
		return $this;
	}


}