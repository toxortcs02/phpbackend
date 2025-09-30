<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use App\Controllers\UserController;
use App\Config\Database;





return function (App $app) {

    $database = new Database();
    $connection = $database->getConnection();
    $userController = new UserController($connection);


    $app->get('/api/test', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'message' => 'API funcionando correctamente',
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoints_available' => [
                'GET /api/test',
                'GET /api/users',
                'POST /api/users/login',
                'POST /api/users/register',
                'GET /api/users/profile',
                'PUT /api/users/profile',
                'POST /api/users/logout',
                'POST /api/users/validate-token'
            ]
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });
    $app->get('/api/users', function (Request $request, Response $response) use ($connection) {
        if ($connection) {
            try {
                // No mostrar la contraseña en la lista
                $statement = $connection->query("SELECT id, email, first_name, last_name, is_admin, created_at FROM users");
                $users = $statement->fetchAll(PDO::FETCH_ASSOC);

                $payload = json_encode($users, JSON_UNESCAPED_UNICODE);
                $response->getBody()->write($payload);
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(200);

            } catch (PDOException $e) {
                $error = ["error" => "Error al obtener los datos: " . $e->getMessage()];
                $response->getBody()->write(json_encode($error));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(500);
            }
        } else {
            $error = ["error" => "No se pudo conectar a la base de datos"];
            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(503);
        }
    });

    $app->post('/api/users/login', function (Request $request, Response $response) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $userController = new UserController($db);
            return $userController->login($request, $response);
            
        } catch (Exception $e) {
            $error = ["error" => $e->getMessage()];
            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }); 
    // Ruta para registro de usuario
    $app->post('/api/users/register', [$userController, 'register']);
    $app->post('/api/users/register', function (Request $request, Response $response) {
            try{
                $database = new Database();
                $db = $database->getConnection();

                $userController = new UserController($db);
                return $userController->register($request, $response);
            }
             catch (Exception $e) {
            $error = ["error" => $e->getMessage()];
            $response->getBody()->write(json_encode($error));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    });
    // Ruta para login

    
    // Ruta para obtener perfil de usuario (requiere autenticación)
    $app->get('/api/users/profile', [$userController, 'getProfile']);
    
    // Ruta para actualizar perfil
    $app->put('/api/users/profile', [$userController, 'updateProfile']);
    // Ruta para logout
    $app->post('/api/users/logout', [$userController, 'logout']);
    
    // Ruta para validar token
    $app->post('/api/users/validate-token', [$userController, 'validateToken']);
    

    




};