<?php


use Dakujem\Latter\View;
use Latte\Engine;
use Psr\Http\Message\ResponseInterface as Response;

$view = $dic->get('view');// new View()
$pipeline = $dic->get('viewDecorators');
$latte = $pipeline->decorate($dic->factory('latte')); // new instance of Latte, decorated

// dekoratory mozu byt vyuzite priamo v tovarnicke, ale tam je to trochu zbytocne, pretoze mozu volania robit priamo nad isntanciou Engine


$dic->set('latte', function(){
    $engine = new Engine();

    //TODO loader, co bude robit split podla . a hladat vo viacerych adresaroch
    $loader = new SplitLoader([$basePath1, $basePath2], '.');

    $engine->setLoader($loader);
    return $engine;
});

// konfiguracia View instancie by mala byt priamo v DIC
$view->register('*', function (): Engine {
    return $dic->get('latte');
});
$view->register('index.default.latte', function (Response $response, string $name, array $params, ?Engine $latte, View $view) {
    // this is the place to register filters, variables and stuff for the Engine
    if ($latte === null) {
        // takto si mozem definovat ziskanie latte
        $latte = $view->getRoutine('*')();
    }

    // na tomto mieste si mozem pre danu konkretnu sablonu:
    // - mozem vytvorit novu instanciu Latte\Engine
    // - registrovat filtre
    // - registrovat makra
    // - modifikovat aleboo pridat parametre alebo default hodnoty parametrov

    // na tomto mieste je mozne riesit cestu k fyzickemu suboru sablony a podobne
    $template = TEMPLATES . '/' . $name;

    // na tomto mieste sa mozem rozhodnut, ci pouzijem `respond` metodu alebo pouzijem vlastny sposob renderovania
    return $view->respond($response, $latte, $template, $params);
}, 'index'); // `index` is an optional alias for `index.default.latte`


$view = $dic->get(View::class);
$view->render($response, 'index', ['foo' => 'bar']); // latte engine nie je nutne poskytovat




