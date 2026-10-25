<?php
/**
 * Regras de rewrite para expor /api/v1/* e /api.php aos controladores xkhm.
 */
defined('ABSPATH') || exit;

/**
 * Adiciona query vars personalizadas.
 */
add_filter('query_vars', function ($vars) {
    $vars[] = 'xkhm_api_v1';
    $vars[] = 'xkhm_api_legacy';
    return $vars;
});

/**
 * Regras de rewrite – prioridade alta (top).
 */
add_action('init', function () {
    // /api/v1/<resto>
    add_rewrite_rule(
        '^api/v1/(.*)$',
        'index.php?xkhm_api_v1=$matches[1]',
        'top'
    );
    // /api.php (compatibilidade WHMCS)
    add_rewrite_rule(
        '^api\.php$',
        'index.php?xkhm_api_legacy=1',
        'top'
    );
    // /xkhm-admin e /xkhm-admin/* (painel xkhm)
    add_rewrite_rule(
        '^xkhm-admin/?(.*)$',
        'index.php?xkhm_admin=$matches[1]',
        'top'
    );
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'xkhm_admin';
    return $vars;
});

/**
 * Dispatch – intercepta template_redirect quando as query vars estão presentes.
 */
add_action('template_redirect', function () {
    // API v1
    $v1 = get_query_var('xkhm_api_v1');
    if ($v1 !== '') {
        xkhm_dispatch_api_v1($v1);
        exit;
    }

    // API legada
    if (get_query_var('xkhm_api_legacy')) {
        xkhm_dispatch_api_legacy();
        exit;
    }

    // Painel admin xkhm
    if (get_query_var('xkhm_admin') !== false && get_query_var('xkhm_admin') !== '') {
        xkhm_dispatch_admin(get_query_var('xkhm_admin'));
        exit;
    }
    // Handle /admin (empty path)
    if (isset($_GET['xkhm_admin'])) {
        xkhm_dispatch_admin('');
        exit;
    }
});

/**
 * Despacha para ApiV1Controller (reutiliza o mesmo container da aplicação principal).
 */
function xkhm_dispatch_api_v1(string $path): void
{
    // Carrega o bootstrap da aplicação principal (Slim + DI)
    $bootstrap = require_once dirname(XKHM_PLUGIN_DIR, 2) . '/public/index.php';
    // O ficheiro public/index.php executa $app->run(); não retorna.
    // Em vez disso, instanciamos apenas o container e o controlador necessário.
    // Como o projeto usa Slim, criamos uma instância mínima aqui.

    $container = xkhm_build_container();

    /** @var \Xkhm\Http\Controller\Api\ApiV1Controller $ctrl */
    $ctrl = $container->get('api.v1.controller', function ($c) {
        return new \Xkhm\Http\Controller\Api\ApiV1Controller(
            $c->get('auth.service'),
            $c->get('billing.service'),
            $c->get('ticket.service'),
            $c->get('domain.service'),
            $c->get('kb.service'),
            $c->get('announcement.service'),
            $c->get('server.status.service'),
            $c->get('ssl.service'),
            $c->get('affiliate.service'),
            $c->get('order.repository'),
            $c->get('password.reset.service'),
            $c->get('admin.password.reset.service'),
            $c->get('client.repository'),
            $c->get('mfa.service'),
            $c->get('device.trust'),
            $c->get('invoice.notice.service'),
            $c->get('payment.notice.pdf'),
            $c->get('notice.queue'),
            $c->get('order.service')
        );
    });

    // Simula request PSR-7 a partir de $_SERVER / $_GET / php://input
    $request  = \Xkhm\Bootstrap::createPsr7Request($path, 'GET'); // método real detectado dentro
    $response = new \Slim\Psr7\Response();

    // Roteamento simples: o path após /api/v1/ é o resto
    $route = '/' . ltrim($path, '/');
    // Usa o router do controlador (métodos públicos correspondem a rotas)
    // Para simplificar, delegamos ao handleApi genérico se não houver método específico.
    if (method_exists($ctrl, $route)) {
        $ctrl->{$route}($request, $response);
    } else {
        // fallback: query param action
        $_GET['action'] = $route;
        $ctrl->handleApi($request, $response);
    }

    // Emite resposta
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header("{$name}: {$value}", false, $response->getStatusCode());
        }
    }
    echo $response->getBody();
}

/**
 * Despacha para ApiController (WHMCS legacy API).
 */
function xkhm_dispatch_api_legacy(): void
{
    $container = xkhm_build_container();

    /** @var \Xkhm\Http\Controller\ApiController $ctrl */
    $ctrl = $container->get('api.legacy.controller', function ($c) {
        return new \Xkhm\Http\Controller\ApiController(
            $c->get('billing.service'),
            $c->get('ticket.service'),
            $c->get('domain.service')
        );
    });

    $request  = \Xkhm\Bootstrap::createPsr7Request('api.php', $_SERVER['REQUEST_METHOD']);
    $response = new \Slim\Psr7\Response();

    $ctrl->handleApi($request, $response);

    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header("{$name}: {$value}", false, $response->getStatusCode());
        }
    }
    echo $response->getBody();
}

/**
 * Despacha o painel de administração xkhm.
 */
function xkhm_dispatch_admin(string $path): void
{
    // Define XKHM_WP_PLUGIN_ACTIVE para o WpAdminAuthMiddleware
    if (!defined('XKHM_WP_PLUGIN_ACTIVE')) {
        define('XKHM_WP_PLUGIN_ACTIVE', true);
    }

    // Define XKHM_NO_RUN para não executar $app->run()
    if (!defined('XKHM_NO_RUN')) {
        define('XKHM_NO_RUN', true);
    }

    // Propaga credenciais de BD
    if (function_exists('xkhm_db_config')) {
        $cfg = xkhm_db_config();
        $pw = $cfg['password'] ?? '';
        putenv('DB_PASSWORD=' . $pw);
        putenv('XKHM_DB_PASS=' . $pw);
        $_ENV['DB_PASSWORD'] = $pw;
        $_ENV['XKHM_DB_PASS'] = $pw;
        $_SERVER['DB_PASSWORD'] = $pw;
        $_SERVER['XKHM_DB_PASS'] = $pw;
    }

    // Ajusta REQUEST_URI
    $adminPath = '/admin';
    if ($path !== '' && $path !== null) {
        $adminPath = '/admin/' . ltrim($path, '/');
    }
    if (!empty($_GET) && !isset($_GET['xkhm_admin'])) {
        $adminPath .= '?' . http_build_query($_GET);
    }
    $_SERVER['REQUEST_URI'] = $adminPath;
    $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Sessão
    if (!isset($_SESSION)) {
        @session_start();
    }
    if (function_exists('wp_get_current_user')) {
        $wpUser = wp_get_current_user();
        $_SESSION['admin_id'] = $wpUser->ID;
        $_SESSION['admin_name'] = $wpUser->display_name ?: $wpUser->user_login;
        $_SESSION['admin_email'] = $wpUser->user_email;
    }

    $publicIndex = XKHM_PLUGIN_DIR . 'public/index.php';
    if (!file_exists($publicIndex)) {
        http_response_code(500);
        echo 'public/index.php não encontrado';
        return;
    }

    include $publicIndex;

    if (!isset($app) || !($app instanceof \Slim\App)) {
        http_response_code(500);
        echo 'App Slim não inicializada';
        return;
    }

    // Cria request PSR-7
    $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
    $creator = new \Nyholm\Psr7Server\ServerRequestCreator($factory, $factory, $factory, $factory);
    $request = $creator->fromGlobals();
    $uri = $factory->createUri($_SERVER['REQUEST_URI']);
    $request = $request->withUri($uri);

    // Executa o handler
    try {
        $response = $app->handle($request);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo 'Erro: ' . $e->getMessage();
        return;
    }

    // Envia headers
    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header("{$name}: {$value}", false);
        }
    }

    echo $response->getBody();
}

/**
 * Constrói container partilhado (mesmo que public/index.php mas sem rodar app).
 */
function xkhm_build_container(): \Psr\Container\ContainerInterface
{
    // Reutiliza a lógica de DI do projeto principal.
    // Aqui apenas um exemplo simplificado – na prática inclui-se o ficheiro de bootstrap DI.
    $diFile = dirname(XKHM_PLUGIN_DIR, 2) . '/src/Bootstrap/Container.php';
    if (file_exists($diFile)) {
        return require $diFile;
    }

    // Fallback mínimo: cria container Pimple/League manualmente com serviços essenciais.
    // (Para brevidade, omitido – assume-se que o projeto principal expõe um container PSR-11.)
    throw new \RuntimeException('Container DI não encontrado. Certifique-se de que o autoload do projeto principal está disponível.');
}