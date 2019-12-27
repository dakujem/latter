<?php

use Dakujem\Latter\Runtime;
use Dakujem\Latter\View;
use Latte\Engine;
use Latte\Loaders\FileLoader;
use Psr\Http\Message\ResponseInterface as Response;

$view = $container->get('view');// new View()
$pipeline = $container->get('viewDecorators');
$latte = $pipeline->decorate($container->factory('latte')); // new instance of Latte, decorated

// dekoratory mozu byt vyuzite priamo v tovarnicke, ale tam je to trochu zbytocne, pretoze mozu volania robit priamo nad isntanciou Engine

$container = new Dakujem\Sleeve();

// Note:
//      In most cases, a new instance of Engine should be created for each template render,
//      thus using a "factory" service definition.
//      This will be different for each container library, find out more in respective docs.
$container->set('latte', $container->factory(function () use ($container) {
    $engine = new Engine();

    // Set a temporary directory, where compiled Latte templates will be stored.
    $engine->setTempDirectory($container->settings['view-temp-dir']);

    // To slightly improve performance on production servers, auto-refresh can be turned off.
    // This has its caveats, read the docs beforehand.
    $engine->setAutoRefresh(false);

    // Configure the file loader to search for templates in a dedicated directory.
    $loader = new FileLoader(__DIR__ . '/templates');
    $engine->setLoader($loader);

    return $engine;
}));

$container->set('view', function () use ($container) {
    $defaultParams = [
        'projectName' => 'My Awesome Project',
    ];
    $view = new View();
    $view->setParams($defaultParams);

    // optionally set an engine factory
    $view->setEngine(function () use ($container): Engine {
        return $container->get('latte');
    });
    $view->register('index.default', function (
        View $view,
        Response $response,
        array $params,
        ?Engine $latte,
        string $name
    ) {
        // this is the place to register filters, variables and stuff for the template
        if ($latte === null) {
            // takto si mozem definovat ziskanie latte
            $latte = $view->getEngine();
        }

        // na tomto mieste si mozem pre danu konkretnu sablonu:
        // - mozem vytvorit novu instanciu Latte\Engine
        // - registrovat filtre
        // - registrovat makra
        // - modifikovat aleboo pridat parametre alebo default hodnoty parametrov

        // na tomto mieste je mozne riesit cestu k fyzickemu suboru sablony a podobne
        $template = TEMPLATES . '/' . $name;
        $template = 'index/default.latte';

        // na tomto mieste sa mozem rozhodnut, ci pouzijem `respond` metodu alebo pouzijem vlastny sposob renderovania
        return $view->respond($response, $latte ?? $view->getEngine(), $template, $params);
    }, 'index'); // `index` is an optional alias for `index.default.latte`

    $view->register('index.default', function (Runtime $context, string $name) {
        // this is the place to register filters, variables and stuff for the template
        $engine = $context->getEngine();

        // na tomto mieste si mozem pre danu konkretnu sablonu:
        // - mozem vytvorit novu instanciu Latte\Engine
        // - registrovat filtre
        // - registrovat makra
        // - modifikovat aleboo pridat parametre alebo default hodnoty parametrov

        // na tomto mieste je mozne riesit cestu k fyzickemu suboru sablony a podobne
        $template = TEMPLATES . '/' . $name;
        $template = 'index/default.latte';

        // na tomto mieste sa mozem rozhodnut, ci pouzijem `respond` metodu alebo pouzijem vlastny sposob renderovania
        return $context->toResponse($name, $params ?? null);
    }, 'index'); // `index` is an optional alias for `index.default.latte`

});

/** @var View $view */
$view = $container->get(View::class);
$view->render($response, 'index', ['foo' => 'bar']); // latte engine nie je nutne poskytovat

$view->pipeline('routine1', 'routine2')->render($response, 'index', ['foo' => 'bar']); // ->pipeline ->select ->as ->through ->thru


// TODO nie je mozne pokryt stav, kedy chcem pajplajnu invokovat uz v render rutine

$view->register('fooo', function(Runtime $context, string $name){
    $response = $context->getView()->pipeline('a','b')->invoke();
//    if instnacneof Runtime .... elsif Response return
});


// TODO (2) nie je pokryty pripad, kedy chcem mat default rutinu ako "pre-render" (t.j. vracia context alebo nic)
//      -----> toto asi nie je uplne prinosne, pretoze default rutina moze proste zavolat $context->toResponse()












