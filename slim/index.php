<?php

require __DIR__ . '/vendor/autoload.php';

use App\Config\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, PATCH, DELETE')
        ->withHeader('Content-Type', 'application/json');
});

// ACÁ VAN LOS ENDPOINTS

$app->get('/api/users', function (Request $request, Response $response) {
    $database = new Database();
    $db = $database->getConnection();

    if ($db) {
        try {
            $statement = $db->query("SELECT * FROM users");
            $users = $statement->fetchAll(PDO::FETCH_ASSOC);

            $payload = json_encode($users, JSON_UNESCAPED_UNICODE);
            $response->getBody()->write($payload);
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);

        } catch (PDOException $e) {
            $error = ["message" => "Error al obtener los datos: " . $e->getMessage()];
            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    } else {
        $error = ["message" => "No se pudo conectar a la base de datos."];
        $response->getBody()->write(json_encode($error));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(503);
    }
});

$app->run();
