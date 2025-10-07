<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use App\Controllers\UserController;
use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Middleware\IsAdminMiddleware;
use App\Controllers\CourtController;




return function (App $app) {

    $database = new Database();
    $connection = $database->getConnection();

    $userController = new UserController($connection);
    $courtControler = new CourtController($connection);


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
    // Listado de usuarios
    $app->get('/api/users', [$userController, 'getAll']);





    
$app->group('/api/users', function ($group) use ($userController) {
        
        // Obtener perfil del usuario autenticado
        $group->get('/profile', [$userController, 'getProfile']);
        
        // Actualizar perfil del usuario autenticado
        $group->patch('/profile/{id}', [$userController, 'updateProfile']);
        
        // Logout
        $group->post('/logout', [$userController, 'logout']);
        
        
    })->add($authMiddleware);
    
$app->group('/api', function ($group) use ($courtControler, $authMiddleware, $adminMiddleware) {
    
    // Rutas que requieren ser administrador
    $group->group('', function ($adminGroup) use ($courtControler) {
        $adminGroup->post('/court', [$courtControler, 'createCourt']);
        $adminGroup->put('/court/{id}', [$courtControler, 'updateCourt']);
        $adminGroup->delete('/court/{id}', [$courtControler, 'deleteCourt']);
    })->add($adminMiddleware)->add($authMiddleware);
    
    // Ruta que solo requiere autenticación
    $group->get('/court/{id}', [$courtControler, 'getCourt'])
          ->add($authMiddleware);
    
});

};