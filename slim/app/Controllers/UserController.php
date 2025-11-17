<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\User;
use PDO;
use PDOException;

class UserController {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    private static function validate(string $password): bool {
        if (strlen($password) < 8) {
            return false;
        }
        $tieneMayus = preg_match('/[A-Z]/', $password);
        $tieneMinus = preg_match('/[a-z]/', $password);
        $tieneNumero = preg_match('/[0-9]/', $password);
        $tieneEspecial = preg_match('/[@$!%*?&]/', $password);
        return $tieneMayus && $tieneMinus && $tieneNumero && $tieneEspecial;
    }

    private function jsonResponse(Response $response, array $data, int $status): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public function register(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $first = $data['first_name'] ?? '';
            $last = $data['last_name'] ?? '';

            // 1. Validar formato y campos requeridos
            if (empty($email) || empty($password) || empty($first) || empty($last)) {
                return $this->jsonResponse($response, ["error" => "Todos los campos son requeridos"], 400);
            }

            if (!self::validate($password)) {
                return $this->jsonResponse($response, ["error" => "La contraseña no cumple con los requisitos de seguridad"], 400);
            }
            
            // 2. Validar reglas de negocio (email único)
            $user = new User($this->db);
            if ($user->findByEmail($email)) {
                return $this->jsonResponse($response, ["error" => "El email ya está registrado"], 409);
            }
            
            // 3. Ejecutar acción
            $result = $user->registerUser($email, $password, $first, $last);

            if ($result) {
                return $this->jsonResponse($response, ["message" => "Usuario registrado exitosamente", "user_id" => $result], 201);
            } else {
                return $this->jsonResponse($response, ["error" => "No se pudo registrar el usuario"], 500);
            }

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    public function login(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                return $this->jsonResponse($response, ["error" => "Email y contraseña son requeridos"], 400);
            }
            
            // 1. Verificar existencia del usuario por email
            $user = new User($this->db);
            if ($user->findByEmail($email) === false) {
                return $this->jsonResponse($response, ["error" => "Credenciales inválidas"], 401); // Se unifica el mensaje por seguridad
            }
            
            // 2. Ejecutar lógica de login (que valida la contraseña)
            $result = $user->loginUser($email, $password);
            
            if ($result) {
                return $this->jsonResponse($response, [
                    "message" => "Login exitoso", 
                    "token" => $result['token'],
                    "id" => $result['id'],
                    "full_name" => $result['first_name'] . ' ' . $result['last_name']
                ], 200);
            } else {
                return $this->jsonResponse($response, ["error" => "Credenciales inválidas"], 401);
            }
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }

    public function getUser(Request $request, Response $response, array $args): Response {
        try {
            $userId = $args['id'];
            $user = new User($this->db);

            // 1. Verificar existencia
            $userData = $user->getUser($userId);
            if (!$userData) {
                return $this->jsonResponse($response, ["error" => "Usuario no encontrado"], 404);
            }

            // 2. Verificar autorización
            $requestingUserId = $request->getAttribute('user_id');
            $isAdmin = $request->getAttribute('is_admin');
            if ($userId != $requestingUserId && !$isAdmin) {
                return $this->jsonResponse($response, ["error" => "No autorizado para ver este perfil"], 403);
            }

            // 3. Devolver datos
            return $this->jsonResponse($response, $userData, 200);

        } catch (PDOException $e) {
            return $this->jsonResponse($response, ["error" => "Error al obtener el perfil: " . $e->getMessage()], 500);
        }
    }

    public function updateUser(Request $request, Response $response, array $args): Response {
        try {
            $userId = $args['id'];
            $user = new User($this->db);

            // 1. Verificar existencia
            if (!$user->getUser($userId)) {
                return $this->jsonResponse($response, ["error" => "Usuario no encontrado"], 404);
            }

            // 2. Verificar autorización
            $isOwner = ($userId == $request->getAttribute('user_id'));
            $isAdmin = $request->getAttribute('is_admin');
            if (!$isOwner && !$isAdmin) {
                return $this->jsonResponse($response, ["error" => "No autorizado para actualizar este perfil"], 403);
            }

            // 3. Validar datos de entrada
            $data = $request->getParsedBody();
            $firstName = $data['first_name'] ?? null;
            $lastName = $data['last_name'] ?? null;
            $password = $data['password'] ?? null;

            if (!$firstName && !$lastName && !$password) {
                return $this->jsonResponse($response, ["error" => "No se proporcionaron campos para actualizar"], 400);
            }

            if ($password && !self::validate($password)) {
                return $this->jsonResponse($response, ["error" => "La contraseña no cumple con los requisitos de seguridad"], 400);
            }

            // 4. Ejecutar acción
            $updated = $user->updateUser($userId, $firstName, $lastName, $password);
            if ($updated) {
                return $this->jsonResponse($response, ["message" => "Perfil actualizado exitosamente"], 200);
            } else {
                return $this->jsonResponse($response, ["error" => "No se pudo actualizar el perfil o no hubo cambios"], 500);
            }

        } catch (PDOException $e) {
            return $this->jsonResponse($response, ["error" => "Error al actualizar el perfil: " . $e->getMessage()], 500);
        }
    }

    public function deleteUser(Request $request, Response $response, array $args): Response {
        try {
            $userId = $args['id'];
            $user = new User($this->db);

            // 1. Verificar existencia
            $userData = $user->getUser($userId);
            if (!$userData) {
                return $this->jsonResponse($response, ["error" => "Usuario no encontrado"], 404);
            }

            // 2. Verificar autorización
            $isOwner = ($userId == $request->getAttribute('user_id'));
            $isAdmin = $request->getAttribute('is_admin');
            if (!$isOwner && !$isAdmin) {
                return $this->jsonResponse($response, ["error" => "No autorizado para eliminar este usuario"], 403);
            }

            // 3. Validar reglas de negocio
            if ($userData['is_admin']) {
                return $this->jsonResponse($response, ["error" => "No se puede eliminar un usuario administrador"], 403);
            }
            if ($user->getUserBookings($userId)) {
                return $this->jsonResponse($response, ["error" => "No se puede eliminar un usuario con reservas activas"], 409);
            }

            // 4. Ejecutar acción
            if ($user->deleteUser($userId)) {
                return $this->jsonResponse($response, ["message" => "Usuario eliminado exitosamente"], 200);
            } else {
                return $this->jsonResponse($response, ["error" => "Error al eliminar el usuario"], 500);
            }

        } catch (PDOException $e) {
            return $this->jsonResponse($response, ["error" => "Error al eliminar usuario: " . $e->getMessage()], 500);
        }
    }

    public function searchUsers(Request $request, Response $response): Response {
        $queryParams = $request->getQueryParams();
        $searchText = $queryParams['search'] ?? '';
        try {
            $user = new User($this->db);
            if (empty($searchText)) {
                $results = $user->getAllnonAdmin();
            } else {
                $results = $user->searchByText($searchText);
            }
            return $this->jsonResponse($response, $results, 200);
        } catch (PDOException $e) {
            return $this->jsonResponse($response, ["error" => "Error al buscar usuarios: " . $e->getMessage()], 500);
        }
    }

    public function logout(Request $request, Response $response): Response {
        try {
            $authHeader = $request->getHeaderLine('Authorization');
            $token = preg_match('/Bearer\s+(\S+)/', $authHeader, $matches) ? $matches[1] : null;

            if (!$token) {
                return $this->jsonResponse($response, ["error" => "Token no proporcionado"], 401);
            }
            
            $user = new User($this->db);
            $result = $user->logout($token);

            if ($result['success']) {
                return $this->jsonResponse($response, ["message" => "Sesión cerrada exitosamente"], 200);
            } else {
                return $this->jsonResponse($response, ["error" => $result['error']], $result['status']);
            }
        } catch (PDOException $e) {
            return $this->jsonResponse($response, ["error" => $e->getMessage()], 500);
        }
    }
}
