<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

use App\Config\Database;
use App\Controllers\UserController;
use App\Controllers\CourtController;
use App\Controllers\BookingController;
use App\Middleware\AuthMiddleware;
use App\Middleware\IsAdminMiddleware;

return function (App $app) {

    // ==============================
    // 🔧 Inicialización
    // ==============================
    $database = new Database();
    $connection = $database->getConnection();

    $userController = new UserController($connection);
    $courtController = new CourtController($connection);
    $bookingController = new BookingController($connection);
    
    $authMiddleware = new AuthMiddleware($connection);
    $adminMiddleware = new IsAdminMiddleware();

    // ==============================
    // 👤 Usuarios
    // ==============================

    // Registro
    // Espera JSON: { email, password, first_name, last_name }
    $app->post('/api/user', [$userController, 'register']);

    // Login
    // Espera JSON: { email, password }
    $app->post('/api/login', [$userController, 'login']);
    // Búsqueda de usuarios por nombre o email
    $app->get('/api/users', [$userController, 'searchUsers']); 
    // Grupo de rutas protegidas por autenticación
    $app->group('/api', function ($group) use ($userController) {

        // Obtener perfil
        $group->get('/user/{id}', [$userController, 'getUser']);
        //actualizar perfil
        //espera JSON: { email, first_name, last_name, password (opcional) }
        $group->patch('/user/{id}', [$userController, 'updateUser']);


        // Eliminar usuario
        //espera JSON: { email, first_name, last_name, password (opcional) }
        $group->delete('/user/{id}', [$userController, 'deleteUser']);

        // Logout
        $group->post('/user/logout', [$userController, 'logout']);

    })->add($authMiddleware);

    // ==============================
    // 🏟️ Canchas
    // ==============================
    $app->group('/api', function ($group) use ($courtController, $authMiddleware, $adminMiddleware) {

        // 🔒 Solo admins autenticados
        $group->group('', function ($adminGroup) use ($courtController) {
            // Crear una cancha
            // Espera JSON: { name, description }
            $adminGroup->post('/court', [$courtController, 'createCourt']);
            
            // Editar una cancha existente 
            // Espera JSON: { name, description }
            //TODO
            $adminGroup->put('/court/{id}', [$courtController, 'updateCourt']);

            // : Eliminar una cancha.
            //TODO
            $adminGroup->delete('/court/{id}', [$courtController, 'deleteCourt']);
        })->add($adminMiddleware)->add($authMiddleware);

        // 🔐 Solo autenticados (no necesariamente admin)
        // Obtener información de una cancha específica. 
        //TODO
        $group->get('/court/{id}', [$courtController, 'getCourt'])
              ->add($authMiddleware);

    });

    // ==============================
    // 📅 Reservas
    // ==============================
    $app->group('/api', function ($group) use ($bookingController, $authMiddleware) {

        // Crear reserva (usuario autenticado)
        // Espera JSON: { court_id, booking_datetime, duration_blocks, participants }
        $group->post('/booking', [$bookingController, 'create'])->add($authMiddleware);

        // Eliminar reserva (creador o admin)
        $group->delete('/booking/{id}', [$bookingController, 'delete'])->add($authMiddleware);

        // Listar reservas del día (público)
        $group->get('/booking', [$bookingController, 'list']);
    });
};
