# Latter

> ☝️ it's not a typo

**Latte view layer for PSR-7 frameworks and stacks.**

> 💿 composer require dakujem/latter


If one wants to use the awesome [Latte templating language](https://latte.nette.org/en/) with a PSR-7 compliant framework like [Slim](https://www.slimframework.com/), one can either do all the setup by himself or use _Latter_.\
The latter will provide him with utility and guidance when dealing with a multitude of templates reducing code repetition.

Latter is a very flexible thin layer that can be tuned and tamed as one desires.

> 💡\
> Check out the [Latte documentation](https://latte.nette.org/en/guide) if you are not fluent in Latte.


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

First, let's create a service container. I'll be using [Sleeve](https://github.com/dakujem/sleeve), a trivial extension of Symfony [Pimple container](https://pimple.symfony.com/).
```php
$container = new Dakujem\Sleeve();
```

In most cases, a new `Latte\Engine` instance should be created for each template render. That is why a _factory_ service should be defined. That is, every time the service is requested from the service container, a new instance will be returned.

> Check out the documentation for the container you are using to configure this step correctly.

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
- installation of [filters](https://latte.nette.org/en/guide#toc-filters)
- installation of [custom tags](https://latte.nette.org/en/guide#toc-user-defined-tags) (macros)
- installation of providers
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
Note that we no longer need to prefix the template name with a full path.


> Tip
>
> To slightly improve performance on production servers, auto-refresh can be turned off in the Engine factory:\
> `$engine->setAutoRefresh($container->settings['dev'] ?? true);`\
> This has its caveats, read the docs beforehand.


## Configure Latter\View service

Now let's define a `Latter\View` service.

```php
$container->set('view', function () use ($container) {
    $defaultParams = [
        'projectName' => 'My Awesome Project',
    ];
    $view = new Dakujem\Latter\View($defaultParams);

    // optionally set an engine factory
    $view->engine(function () use ($container): Latte\Engine {
        return $container->get('latte');
    });

    return $view;
});
```

If an engine factory is provided to the `View` service, it is possible to omit providing the `Engine` instance for each rendered template.
```php
// the render calls have gotten much shorter:
$container->get('view')->render($response, 'hello.latte', $params);
```

The View service definition can contain these optional definitions:
- template aliases
- template render routines
- engine factory
- default parameters
- engine decorator

Each of these are optional.


### Template aliases

It is possible to create template aliases, so that the templates can be referred to using a different name.

```
$view->alias('hello.latte', 'hello');
$view->alias('ClientModule/Index/default.latte', 'index');
```

To render a template using its alias:
```php
$container->get('view')->render($response, 'hello', $params);
$container->get('view')->render($response, 'index', $params);
```


### Render routines

Render routines should be used to apply template-specific setup without the need for code repetition.
They may be used to
- define filters
- define tags (macros)
- modify input parameters
- modify template name
- or even to use a completely different Engine instance or render own Response

A render routine is a _callable_ that receives a `Runtime` context object and returns a _response_, with the following signature:
```
function(
    Dakujem\Latter\Runtime  $runtime,
    string                  $name,    // name under which the routine is registered
): Psr\Http\Message\ResponseInterface|Dakujem\Latter\Runtime
```

> Note that the callable can also return a Runtime context object, this scenario will be described later.

Example:
```php
$view->register('shopping-cart', function (Runtime $context, string $name) {
    // This callable is the place to register filters, variables and stuff for template named "shopping-cart"

    // Do any setup of the Engine that is needed for the template to render correctly
    $latte = $context->getEngine();
    $latte->addFilter('count', function(){
        // return the count of items in the shopping cart here
    });

    // Template name can be set or changed freely.
    // Note that if one only needs to set a nice name for the template to be rendered, aliases are a simpler option to do so
    $template = 'ClientModule/Cart/list.latte';

    // The params can be modified at will, for example to provide defaults
    $params = array_merge(['default' => 'value'], $context->getParams());

    // the Runtime::toResponse helper method can be used for default rendering
    return $context->toResponse($template, $params);
});
```

One can render the routine exactly as he would render an alias:
```php
$container->get('view')->render($response, 'shopping-cart', $params);
```


### Default render routine

You may optionally specify a default render routine, that will be used for all non-specified templates.
```php
$view->defaultRoutine( $routine );
```
The default render routine call has exactly the same signature as the named ones.


### Default parameters

Default parameters are merged with the parameters provided to each render call.

If one wants to define per-template default parameters, render routines can be used.

```php
$view->default('username', 'Guest');
```

Default parameters can also be passed in bulk to the `View`'s constructor.


### Render pipelines

If a group of templates share a common setup that needs to be performed on each of them to be rendered, pipelines can be used. Pipelines allow multiple _pre-render_ routines to be called one after another before rendering a response.

First, appropriate render routines have to be registered:
```php
$view->register(':ClientModule', function (Runtime $context, string $name) {
    // do setup needed for templates in the client module
    $context->getEngine()->addFilter( ... );
    
    // return a context object (!)
    return $context;
});
$view->register('--withUser--', function (Runtime $context, string $name) {
    // do setup common for templates using a `$user` variable
    $defaults = [
        'user' => get_user( 'somehow' );
    ];

    // return a context object (!)
    return $context->withParams($defaults);
});
```

For routines used in pipelines, it is important to return a `Runtime` context object. If a `Response` was returned, the pipeline would end prematurely (this might be desired in certain cases).

A render calls with a pipeline could look like these:
```php
// calling a pipeline with 2 _pre-render_ routines and a registered render routine
$container->get('view')
    ->pipeline(':ClientModule', '--withUser--')
    ->render($response, 'shopping-cart', $params);

// rendering a file with a common _pre-render_ routine
$container->get('view')
    ->pipeline('--withUser--')
    ->render($response, 'userProfile.latte', $params);
```

This way one can reuse a _pre-render_ routines across multiple templates that share a common setup.

This kind of rendering could be compared to tagging or decorating a template before rendering.


## Resources

- latte dox
- slim dox
- psr7

