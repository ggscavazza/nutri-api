<?php

use CodeIgniter\Router\RouteCollection;
/** @var RouteCollection $routes */

$routes->add('wp-login.php', static fn() => service('response')->setStatusCode(410));
$routes->add('wp-admin', static fn() => service('response')->setStatusCode(410));
$routes->add('.env', static fn() => service('response')->setStatusCode(410));
$routes->add('server-status', static fn() => service('response')->setStatusCode(410));

$routes->get('openapi.json', 'OpenApiController::json', ['filter' => 'ratelimit:30,60']);
$routes->get('docs',        'OpenApiController::ui');
$routes->post('tasks/deploy/request', 'DeployController::request', ['filter' => 'ratelimit:2,60']);


$routes->setAutoRoute(false);           // 🚫 sem AutoRoute
$routes->setDefaultController('');      // sem default
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);

// 404 JSON consistente
$routes->set404Override(static function () {
    return json_error('Rota não encontrada.', 'http.not_found', 404);
});

// Bloqueia a raiz explícita (sem “home”)
$routes->get('/', static function () {
    return json_error('Endpoint não disponível.', 'http.no_root', 404);
});

// Preflight CORS genérico
$routes->options('(:any)', static function () {
    return service('response')->setStatusCode(204);
});

// Healthcheck
$routes->get('health', static function () {
    return json_ok(['status' => 'ok', 'time' => date('c')]);
});

// robots.txt → desabilita indexação no subdomínio da API
$routes->get('robots.txt', static function () {
    return service('response')
        ->setHeader('Content-Type', 'text/plain')
        ->setBody("User-agent: *\nDisallow: /\n");
});

// Endpoints AUTH (com rate limit específico abaixo)
$routes->group('auth', static function ($routes) {
    $routes->post('login', 'AuthController::login', ['filter' => 'ratelimit:10,60']);         // 10/min
    $routes->post('refresh', 'AuthController::refresh', ['filter' => 'ratelimit:20,60']);     // 20/min
    $routes->post('logout', 'AuthController::logout', ['filter' => 'ratelimit:20,60']);       // 20/min

    $routes->post('forgot-password', 'AuthController::forgotPassword', ['filter' => 'ratelimit:3,3600']); // 3/h
    $routes->post('reset-password',  'AuthController::resetPassword',  ['filter' => 'ratelimit:6,3600']); // 6/h

    $routes->get('me', 'AuthController::me', ['filter' => 'jwt']);
});

// --- EXEMPLOS ADMIN (proteção por papel) ---
// Grupo com JWT obrigatório
$routes->group('users', ['filter' => 'jwt'], static function ($routes) {
    // master cria/edita nutricionistas; nutri cria/edita pacientes
    $routes->get('',          'UsersController::index', ['filter' => 'role:nutricionista,master']);
    $routes->post('',         'UsersController::create', ['filter' => 'role:nutricionista,master']);
    $routes->get('(:num)',    'UsersController::show/$1', ['filter' => 'role:nutricionista,master']);
    $routes->put('(:num)',    'UsersController::update/$1', ['filter' => 'role:nutricionista,master']);
    $routes->patch('(:num)/status', 'UsersController::toggleStatus/$1', ['filter' => 'role:nutricionista,master']);
    $routes->delete('(:num)', 'UsersController::delete/$1', ['filter' => 'role:nutricionista,master']);

    // endpoint para o paciente editar o próprio perfil (o controller valida owner)
    $routes->put('me', 'UsersController::updateSelf'); // apenas jwt
});

// Vínculos nutri↔paciente
$routes->group('nutritionists', ['filter' => 'jwt'], static function ($routes) {
    $routes->get('(:num)/patients',           'LinksController::listPatients/$1', ['filter' => 'role:nutricionista,master']);
    $routes->post('(:num)/patients/(:num)',   'LinksController::attach/$1/$2', ['filter' => 'role:nutricionista,master']);
    $routes->delete('(:num)/patients/(:num)', 'LinksController::detach/$1/$2', ['filter' => 'role:nutricionista,master']);
});

// Documentos (upload/download, escopo geral/paciente)
$routes->group('documents', ['filter' => 'jwt'], static function ($routes) {
    $routes->get('',        'DocumentsController::index');                // lista conforme papel/perm
    $routes->post('',       'DocumentsController::create', ['filter' => 'role:nutricionista']); // nutri upa
    $routes->get('(:num)',  'DocumentsController::show/$1');
    $routes->put('(:num)',  'DocumentsController::update/$1', ['filter' => 'role:nutricionista']);
    $routes->patch('(:num)/status', 'DocumentsController::toggleStatus/$1', ['filter' => 'role:nutricionista']);
    $routes->delete('(:num)', 'DocumentsController::delete/$1', ['filter' => 'role:nutricionista']);
    $routes->get('(:num)/download', 'DocumentsController::download/$1');  // paciente/nutri/master conforme permissão
});

// exemplo anterior de comentário:
// $routes->get('admin/pacientes', 'UsersController::index', ['filter' => 'jwt,role:nutricionista,master']);
// Também funciona com 'filter' múltiplo em string, mas prefiro agrupar como acima.
