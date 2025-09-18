<?php

use Docker\Slim\App\Config\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\App\Config\Database as db;
require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->add( function ($request, $handler) {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, PATCH, DELETE')
        ->withHeader('Content-Type', 'application/json')
    ;
});

// ACÁ VAN LOS ENDPOINTS


$app ->get('/api/users', function(Request $request, Response $response)
{
        $database = new Database();
        $db = $database->getConnection();

        if ($db){
            try{
                $statement = $db->query("SELECT * FROM users");
                $users = $statement->fetchAll((PDO::FETCH_ASSOC));
            }
            catch(PDOException $e){
                http_response_code(500);
                echo json_encode(["message"=>"Error al obtener los datos: " . $e->getMessage()]);
                exit(); // Termina la ejecución del script

            }
        }
        else {
            // Maneja el caso en que la conexión falle
            http_response_code(503); // Servicio no disponible
            echo json_encode(["message" => "No se pudo conectar a la base de datos."]);
            exit();
        }

        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode($users);

}
);

$app->run();
