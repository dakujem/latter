# Latter

![PHP from Packagist](https://img.shields.io/packagist/php-v/dakujem/latter)
[![Test Suite](https://github.com/dakujem/latter/actions/workflows/php-test.yml/badge.svg)](https://github.com/dakujem/latter/actions/workflows/php-test.yml)
[![Coverage Status](https://coveralls.io/repos/github/dakujem/latter/badge.svg?branch=trunk)](https://coveralls.io/github/dakujem/latter?branch=trunk)

**Latte view layer for PSR-7 frameworks and stacks.** It's not a typo.

> 💿 `composer require dakujem/latter`

To use the awesome [Latte templating language](https://latte.nette.org/en/) with a PSR-7 compliant framework like [Slim](https://www.slimframework.com/), one can either struggle to set everything up by himself or use _Latter_.\
The latter is a better choice

- Latter is designed to reduce code repetition (render pipelines, render routines)
- Latter is a flexible thin layer that can be tuned and tamed as one desires
- set up once and forget

> 📖\
> Check out the [Latte documentation](https://latte.nette.org/en/guide) to get fluent in Latte.


## Render Latte template to PSR-7 response

A very basic example to render a Latte template like the following
```latte
{*} hello.latte {*}
Hello {$name}!
```
in Slim framework using Latter would be:
```php
// app.php
$app = AppFactory::create();
// ...

$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $params = [
        'name' => $args['name'],
    ];
    return (new Dakujem\Latter\View)->render(
        $response,
        __DIR__ . '/templates/hello.latte',
        $params,
        new Latte\Engine
    );
});

// ...
$app->run();
```

It would of course be an overkill to use Latte in a case like the one above.\
Most of the time, one will use Latte for more complicated templates with multiple variables, filters and macros.

In such applications, it will make sense to define a `Latter\View` service in a service container for dependency injection. 


## Cofigure Latte\Engine factory service

First, let's create a service container.\
I'll be using [Sleeve](https://github.com/dakujem/sleeve), a trivial extension of Symfony [Pimple container](https://pimple.symfony.com/).
```php
$container = new Dakujem\Sleeve();
```

In most cases, a new `Latte\Engine` instance should be created for each template render. That is why a _factory_ service should be defined. That is, every time the service is requested from the service container, a new instance will be returned.

> 💡\
> Check out the documentation for the service container or framework you are using to configure this step correctly.

```php
$container->set('latte', $container->factory(function () use ($container) {
    $engine = new Latte\Engine();

    // Configure the file loader to search for templates in a dedicated directory.
    $loader = new Latte\Loaders\FileLoader(__DIR__ . '/templates');
    $engine->setLoader($loader);

    // Set a temporary directory, where compiled Latte templates will be stored.
    $engine->setTempDirectory($container->settings['view-temp-dir']);

    return $engine;
}));
```

The definition should contain:
- installation of common [filters](https://latte.nette.org/en/guide#toc-filters)
- installation of [custom tags](https://latte.nette.org/en/guide#toc-user-defined-tags) (macros)
- configuration of an appropriate Latte _loader_
- any other `Latte\Engine` setup calls needed

Now every time we call `$container->get('latte')`, a new instance of a configured `Latte\Engine` will be returned:
```php
(new Dakujem\Latter\View)->render(
    $response,
    'hello.latte',
    $params,
    $container->get('latte')
);
```
Note that we no longer need to prefix the template name with a full path, because of the `FileLoader` configuration.


## Configure Latter\View service

Now let's define a `Latter\View` service.

```php
$container->set('view', function () use ($container) {
    $view = new Dakujem\Latter\View();

    // optionally set an engine factory (recommended)
    $view->setEngine(function () use ($container): Latte\Engine {
        return $container->get('latte');
    });

    return $view;
});
```

An instance of `Latter\View` will now be available in the service container:
```php
$view = $container->get('view');
```

If an engine factory is provided to the `View` service, it is possible to omit providing the `Engine` instance for each rendered template.
```php
// the render calls have gotten shorter:
$view->render($response, 'hello.latte', $params);
```

The `View` service definition can contain these optional definitions:
- template aliases
- render routines (template rendering)
- render pipelines
- engine factory
- default parameters
- default render routine

Each of these are optional.


### Template aliases

It is possible to create template aliases, so that the templates can be referred to using a different name.

```php
$view->alias('hello', 'hello.latte');
$view->alias('index', 'ClientModule/Index/default.latte');
```

To render a template using its alias:
```php
$view->render($response, 'hello', $params);
$view->render($response, 'index', $params);
```


### Render routines

Render routines should be used to apply template-specific setup without the need for code repetition.

They may be used to
- define filters
- define tags (macros)
- modify input parameters
- modify template name
- or even to use a completely different Engine instance or render own Response

A render routine is a _callable_ that receives a [`Runtime`](src/Runtime.php) context object and returns a _response_, with the following signature:
```
function(Dakujem\Latter\Runtime $context): Psr\Http\Message\ResponseInterface | Dakujem\Latter\Runtime
```

Example:
```php
$view->register('shopping-cart', function (Runtime $context) {
    // This callable is the place to register filters,
    // variables and stuff for template named "shopping-cart"

    // Do any setup of the Engine that is needed for the template to render correctly
    $latte = $context->getEngine();
    $latte->addFilter('count', function(){
        // return the count of items in the shopping cart here
    });

    // Template name can be set or changed freely.
    // Note that if one only needs to set a nice name for the template to be rendered,
    // aliases are a simpler option to do so
    $template = 'ClientModule/Cart/list.latte';

    // The params can be modified at will, for example to provide defaults
    $params = array_merge(['default' => 'value'], $context->getParams());

    // the Runtime::toResponse helper method can be used for default rendering
    return $context->withTarget($template)->withParams($params);
});
```

One can render the routine exactly as he would render an alias:
```php
$view->render($response, 'shopping-cart', $params);
```


### Default render routine

A default render routine may optionally be registered, that will be used for all non-registered templates.
```php
$view->registerDefault( function (Runtime $context) { ... } );
```
The default render routine has exactly the same signature as the named ones.

It will be used when rendering a template that has not been registered.
```php
$view->render($response, 'a-template-with-no-registered-routine', $params);
```


### Default parameters

Default parameters are merged with the parameters provided to each render call.

If one wants to define per-template default parameters, render routines can be used.

```php
$view->setParam('userName', 'Guest');      // a single parameter
$view->setParams([
    'userName' => 'Guest',
    'projectName' => 'My Awesome Project',
]); // all parameters at once
```


### Render pipelines

Pipelines allow multiple _pre-render_ routines to be called one after another before the final rendering.\
The routines can be shared across multiple template render calls that share a common layout, common include(s), common setup (filters, variables) or other rendering logic.\
The most obvious case being [layouts](https://latte.nette.org/en/tags#toc-template-expansion-inheritance) or common [file](https://latte.nette.org/en/tags#toc-file-including) / [block](https://latte.nette.org/en/tags#toc-block-including) includes.

First, appropriate pre-render routines have to be registered:
```php
$view->register('base-layout', function (Runtime $context) {
    // do setup needed for templates using the base layout
    $context->getEngine()->addFilter( ... );
    
    // return a context object (!)
    return $context;
});
$view->register('--withUser--', function (Runtime $context) {
    // do setup common for templates using a `$user` variable
    $defaults = [
        'user' => get_user( 'somehow' ),
    ];

    // return a context object (!)
    return $context->withParams($defaults);
});
```

For pre-render routines used in pipelines, it is important to return a `Runtime` context object. If a `Response` was returned, the pipeline would end prematurely (this might be desired in certain cases though). Return value of any other kind is ignored.

Render calls using pipelines could look like these:
```php
// calling a pipeline with 2 _pre-render_ routines and a registered render routine
$view
    ->pipeline('base-layout', '--withUser--')
    ->render($response, 'shopping-cart', $params);

// rendering a file with a common _pre-render_ routine
$view
    ->pipeline('--withUser--')
    ->render($response, 'userProfile.latte', $params);
```

Pipelines are particularly useful when dealing with included templates (header, footer) or layout templates that require specific variables or filters to render.\
Example:
```latte
{*} home.latte {*}
{layout 'base.latte'}
{block #content}
<p>Greetings, stranger!</p>
```
```latte
{*} about.latte {*}
{layout 'base.latte'}
{block #content}
<p>Stay awhile and listen.</p>
```
Both the above share the same layout, that needs specific setup done in the `'base-layout'` pre-render routine:
```php
$view->pipeline('base-layout')->render($response, 'home.latte');
$view->pipeline('base-layout')->render($response, 'about.latte');
```

This kind of rendering could be compared to tagging or decorating templates before rendering.

Alternatively, it is also possible to define the pipeline as a part of the rendering routine:
```php
$view->register('contacts.latte', $view->pipeline('base-layout', function (Runtime $context) {
    // ... do whatever setup needed for rendering the contacts page
    return $context->withParams(['foo' => 'bar']);
}));
```
```latte
{*} contacts.latte {*}
{layout 'base.latte'}
{block #content}
<p>Contact: {$foo}</p>
```
```php
$view->render($response, 'contacts.latte');
```


### Explicit chaining

Sometimes it is desired to invoke one rendering routine from within another. This is possible using `View::another` or `View::execute`.
```php
// register a routine named 'ahoy', that will render `hello.latte`
$view->register('ahoy', function (Runtime $context) {
    return $context->withTarget('hello.latte');
});
// register a routine that will internally invoke it
$view->register('foo', function (Runtime $context) use ($view) {
    return $view->another($context, $view->getRoutine('ahoy'));
});

// render 'hello.latte' using 'foo' routine that internally uses 'ahoy' routine
$view->render($response, 'foo');
```
> Note that these methods are not limited to using registered routines, they can execute any callable provided its signature fits.


## Tips & tricks

### Performance

Latte templates are compiled on first render. All subsequent renders will use compiled and optimized PHP code.

To slightly improve performance on production servers, auto-refresh can be turned off in the Engine factory:\
`$engine->setAutoRefresh($container->settings['dev'] ?? true);`\
This has its caveats, read the Latte docs beforehand.


### Use {link} and n:href macros with Slim framework

It is possible to define `{link}` and `n:href` macros that behave similarly to the macros in Nette framework.
These macros will generate URLs for [_named routes_](http://www.slimframework.com/docs/v4/objects/routing.html#route-names).

First make sure a _filter_ that generates the URL is registered in the Latte Engine, then create a _macro_ that uses the filter.
```php
$app = Slim\Factory\AppFactory::create();
$engine = new Latte\Engine();

// The section below configures the `{link}` and `n:href` macros and the `urlFor` filter.
$engine->addFilter('urlFor', function ($route, ...$args) use ($app) {
    // the filter will call the `urlFor` method of the route parser
    return $app->getRouteCollector()->getRouteParser()->urlFor($route, ...$args);
    // Note: if you are using slim v3, use `$container->get('router')->pathFor( ... )` instead.
});
$macroSet = new MacroSet($engine->getCompiler());
$linkMacro = function (MacroNode $node, PhpWriter $writer) {
    return $writer->using($node)->write('echo ($this->filters->urlFor)(%node.word, %node.args?);');
};
$macroSet->addMacro('link', $linkMacro);
$macroSet->addMacro('href', null, null, function (MacroNode $node, PhpWriter $writer) use ($linkMacro) {
    return ' ?> href="<?php ' . $linkMacro($node, $writer) . ' ?>"<?php ';
});
```
> The above would be best done during the `'latte'` service definition. See [the test](tests/links.phpt) for more details.

Then it is possible to use the macros:
```latte
{*} named routes without route parameters {*}
{link home}

{*} named routes wit route parameters {*}
{link hello [name => $name]}

{*} named routes with route parameters and query parameters {*}
{link rc [resource => apple, action => eat], [param1 => val1, param2 => val2]}

{*} n:href macro has the same syntax {*}
<a n:href='home'>go home</a>
<a n:href='hello [name => hugo], [a => b]'>polite hello</a>

{*} using the filter is of course possible too {*}
{='home'|urlFor}
```
> 💡\
> Note the difference to Nette framework - the first macro argument must be an array.\
> In order for the query parameters to work, `%node.args?` is used in the macro.
> It is possible to replace `%node.args?` with `%node.array?` in order to ditch query parameters in favor of exact Nette {link} syntax.


### Setting up the Engine before rendering

It is the intention of render routines to provide a place to set up the `Latte\Engine` instance before rendering a particular template, however, pipelines can be used to quickly set the engine up:
```php
$view->pipeline(function(Runtime $context) {
    $context->getEngine()->addFilter( ... );
})->render($response, 'myTemplate.latte', ['param' => 'value']);
```


## Contributions
    
... are welcome. 🍵

Go ahead and fork the repository, make your changes, then submit your PR.
