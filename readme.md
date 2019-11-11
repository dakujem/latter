# Latter

> (☝️ it's not a typo)

**Latte view layer for PSR-7.**

If one wants to use the awesome [Latte templating language](https://latte.nette.org/en/) with a PSR-7 compliant framework like [Slim](https://www.slimframework.com/), one can either do all the setup by himself or use _Latter_.\
The latter will provide him with utility and guidance when dealing with a multitude of templates reducing code repetition.


## Latte with PSR-7 response

A very basic example to render a template like the following
```latte
{*} hello.latte {*}
Hello {$name}!
```
in Slim framework using Latter would be:
```php
// app.php
$app = AppFactory::create();
$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $params = [
        'name' => $args['name'],
    ];
    return (new Dakujem\Latter\View)->render($response, './hello.latte', $params, new Latte\Engine);
});
$app->run();
```

It would of course be an overkill to use Latte in a case like the one above.\
Most of the time, one will use Latte for more complicated templates with multiple variables, filters and macros.

- [Latte documentation](https://latte.nette.org/en/guide)


