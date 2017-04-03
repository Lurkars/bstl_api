<?php


session_start();

require_once 'vendor/autoload.php';
require_once 'businesslogic/AuthenticationManager.php';

// Create and configure Slim app
$configuration = [
    'settings' => [
        'displayErrorDetails' => true,
        'determineRouteBeforeAppMiddleware' => true,
    ],
];

$slimContainer = new \Slim\Container($configuration);

$app = new \Slim\App($slimContainer);

$applyUserId = function ($request, $response, $next) {

    $userId = null;

    if (isset($_SESSION['userId'])) {
        $userId = $_SESSION['userId'];
    } else {
        $serverParams = $request->getServerParams();
        // simple basic auth
        if (isset($serverParams['PHP_AUTH_USER']) && isset($serverParams['PHP_AUTH_PW'])) {
            $username = $serverParams['PHP_AUTH_USER'];
            $password = $serverParams['PHP_AUTH_PW'];

            if (isset($username) && isset($password)) {
                $authManager = new AuthenticationManager();
                $userId = $authManager->authenticate($username, $password);
            }
        }
    }

    $request = $request->withAttribute('userId', $userId);

    $response = $next($request, $response);

    return $response;
};

$returnJson = function ($request, $response, $next) {
    $response = $response->withHeader('Content-type', 'application/json');
    $response = $next($request, $response);

    return $response;
};

$app->add($returnJson);
$app->add($applyUserId);

$requireAuthorization = function ($request, $response, $next) {
    if (!$request->getAttribute('userId')) {
        return $response->withStatus(401);
    }
    $response = $next($request, $response);

    return $response;
};

// services
include 'services/authService.php';
include 'services/documentService.php';
include 'services/documentsService.php';
include 'services/debugService.php';

// load plugins
$plugins = scandir('./plugins/');

foreach($plugins as $plugin) {
	if ($plugin != '..' && $plugin != '.') {
		include 'plugins/' . $plugin . '/services/' . $plugin. 'Service.php';
	}
}

$app->get('/', function ($request, $response, $args) {
    return $response->write('bstl api v0.4.0')->withStatus(200);
});

// Run app
$app->run();
