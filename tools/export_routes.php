<?php

require __DIR__ . '/../bootstrap/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$router = $app['router'];
$routes = $router->getRoutes();
$out = [];

foreach ($routes as $route) {
    $out[] = [
        'methods' => array_values(array_filter($route->methods(), function ($m) {
            return $m !== 'HEAD';
        })),
        'uri' => $route->uri(),
        'name' => $route->getName(),
        'action' => $route->getActionName(),
        'middleware' => method_exists($route, 'gatherMiddleware') ? $route->gatherMiddleware() : [],
    ];
}

$target = __DIR__ . '/routes.json';
file_put_contents($target, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "Exported routes to {$target}\n";
