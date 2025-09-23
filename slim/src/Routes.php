<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use App\Controllers\UserController;
use App\Config\Database;

return function (App $app) {
    // Crear instancia de base de datos
    $db = new Database();
    $connection = $db->getConnection();
    
    // Instanciar el controlador con la conexión
    $userController = new UserController($connection);

    // Ruta para registro de usuario
    $app->post('/api/users/register', [$userController, 'register']);
    
    // Ruta para login
    $app->post('/api/users/login', [$userController, 'login']);
    
    // Ruta para obtener perfil de usuario (requiere autenticación)
    $app->get('/api/users/profile', [$userController, 'getProfile']);
    
    // Ruta para actualizar perfil
    $app->put('/api/users/profile', [$userController, 'updateProfile']);
    // Ruta para logout
    $app->post('/api/users/logout', [$userController, 'logout']);
    
    // Ruta para validar token
    $app->post('/api/users/validate-token', [$userController, 'validateToken']);
    
    // Ruta de prueba
    $app->get('/api/test', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode(['message' => 'API funcionando correctamente']));
        return $response->withHeader('Content-Type', 'application/json');
    });
};