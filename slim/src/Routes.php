<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use App\Controllers\UserController;
use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Middleware\IsAdminMiddleware;





return function (App $app) {

    $database = new Database();
    $connection = $database->getConnection();
    $userController = new UserController($connection);
    $authMiddleware = new AuthMiddleware($connection);
    $adminMiddleware = new IsAdminMiddleware();

    $app->get('/api/test', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'message' => 'API funcionando correctamente',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // Ruta para registro de usuario
/*
    La ruta de registro crea un nuevo usuario. Espera un JSON en el cuerpo de la solicitud con los campos:
    - email
    - password
    - first_name
    - last_name
*/
    $app->post('/api/users', [$userController, 'register']);
    // Login
    $app->post('/api/users/login', [$userController, 'login']);

    $app->get('/api/users', [$userController, 'getAll']);





    
$app->group('/api/users', function ($group) use ($userController) {
        
        // Obtener perfil del usuario autenticado
        $group->get('/profile', [$userController, 'getProfile']);
        
        // Actualizar perfil del usuario autenticado
        $group->put('/profile/{id}', [$userController, 'updateProfile']);
        
        // Logout
        $group->post('/logout', [$userController, 'logout']);
        
        
    })->add($authMiddleware);
    




};