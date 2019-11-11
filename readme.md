# Latter

> (☝️ it's not a typo)

**Latte view layer for PSR-7 frameworks and stacks.**

If one wants to use the awesome [Latte templating language](https://latte.nette.org/en/) with a PSR-7 compliant framework like [Slim](https://www.slimframework.com/), one can either do all the setup by himself or use _Latter_.\
The latter will provide him with utility and guidance when dealing with a multitude of templates reducing code repetition.

> 💡\
> Check out the [Latte documentation](https://latte.nette.org/en/guide) if you are not fluent in Latte.

Latter is a very flexible thin layer that can be tuned and tamed as one desires.


## Render Latte template as PSR-7 response

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
    return (new Dakujem\Latter\View)->render($response, __DIR__ . '/templates/hello.latte', $params, new Latte\Engine);
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
    $engine = new Engine();

    // Configure the file loader to search for templates in a dedicated directory.
    $loader = new FileLoader(__DIR__ . '/templates');
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
(new Dakujem\Latter\View)->render($response, 'hello.latte', $params, $container->get('latte'));
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
    $view = new View($defaultParams);

    // optionally set an engine factory
    $view->engine(function () use ($container): Engine {
        return $container->get('latte');
    });

    return $view;
});
```

If an engine factory is provided to the `View`, it is possible to omit providing the `Engine` instance for each rendered template.
```php
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

Render routines should be used to apply template-specific setup without the need for code reptition.
They may be used to
- define filters
- define tags (macros)
- modify input parameters
- modify template name
- or even to use a completely different Engine instance or render own Response

A render routine is a _callable_ with the following signature:
```
function(
    Dakujem\Latter\View $view,
    Psr\Http\Message\ResponseInterface $response,
    array $params,          // render params
    ?Latte\Engine $latte,   // engine provided to the rendr call (if any)
    string $name,           // name under which the routine is registered
    string $callName        // name used for the render call (can be an alias)
): Psr\Http\Message\ResponseInterface
```

Example:
```php
$view->register('shopping-cart', function (View $view, Response $response, array $params, ?Engine $latte) {
    // this is the place to register filters, variables and stuff for the template
    if ($latte === null) {
        // if Engine has not been provided to the call
        $latte = $view->getEngine();
    }

    // do any setup of the Engine that is needed for the template to render correctly
    $latte->addFilter('count', function(){
        // return the count of items in the shopping cart here
    });

    // one can either use aliases or provide the path to hte template file in the render reutine
    $template = 'ClientModule/Cart/list.latte';

    // the params can be modified at will, for example to provide defaults
    $params = array_merge(['default' => 'value'], $params);

    // the View::respond method can be used for default rendering
    return $view->respond($response, $latte, $template, $params);
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

Default parameters are merged with the parameters provided to the render call.

If one wants to define per-template default parameters, render routines can be used.

```php
$view->default('username', 'Guest');
```

Default parameters can also be passed in bulk to the `View`'s constructor.


### Engine decorator

An engine decorator is a callable that receives an `Engine` instance during every render call, and is supposed to configure it. It is common for the whole `View` instance.

The callable can be used to configure the `Engine` instances before each template is rendered using a `View` service. It is convenient in cases where the Latte engine factory is used for manual rendering as well, or when multiple `View` services are defined.

```php
$view->decorator(function(Engine $latte): Engine {
    $latte->addFilter('module', function() {
        return 'Client Module';
    });
    return $latte;
});
```


## Resources

- latte dox
- slim dox
- psr7

