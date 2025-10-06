<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\User;
use App\Utils\PasswordValidator;
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
    // Helper para respuestas JSON
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

            if (empty($email) || empty($password) || empty($first) || empty($last)) {
                return $this->jsonResponse($response, [
                    "error" => "Todos los campos son requeridos"
                ], 400);
            }

            $user = new User($this->db);

            if ($user->findByEmail($email)) {
                return $this->jsonResponse($response, [
                    "error" => "El email ya está registrado"
                ], 409);
            }

            if (!self::validate($password)) {
                return $this->jsonResponse($response, [
                    "error" => "La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un carácter especial (@$!%*?&)"
                ], 400);
            }
            
            $result = $user->registerUser($email, $password, $first, $last);

            if ($result) {
                return $this->jsonResponse($response, [
                    "message" => "Usuario registrado exitosamente",
                    "user_id" => $result
                ], 201);
            } else {
                return $this->jsonResponse($response, [
                    "error" => "No se pudo registrar el usuario"
                ], 400);
            }

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function updateProfile(Request $request, Response $response, array $args): Response {
        try {
            $userId = $args['id'];
            $data = $request->getParsedBody();
            $isOwner = ($userId == $request->getAttribute('user_id'));
            $isAdmin = $request->getAttribute('is_admin');

            if (!$isOwner && !$isAdmin) {
                return $this->jsonResponse($response, [
                    "error" => "No autorizado para actualizar este perfil"
                ], 403);
            }

            $firstName = $data['first_name'] ?? null;
            $lastName = $data['last_name'] ?? null;
            $password = $data['password'] ?? null;

            if ($password && !self::validate($password)) {
                return $this->jsonResponse($response, [
                    "error" => "La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un carácter especial (@$!%*?&)"
                ], 400);
            }

            if (!$firstName && !$lastName && !$password) {
                return $this->jsonResponse($response, [
                    "error" => "No se proporcionaron campos para actualizar"
                ], 400);
            }

            // ✅ CORRECTO: Delegar al modelo
            $user = new User($this->db);
            $updated = $user->updateProfile($userId, $firstName, $lastName, $password);

            if ($updated) {
                return $this->jsonResponse($response, [
                    "message" => "Perfil actualizado exitosamente"
                ], 200);
            } else {
                return $this->jsonResponse($response, [
                    "error" => "Usuario no encontrado"
                ], 404);
            }

        } catch (PDOException $e) {
            return $this->jsonResponse($response, [
                "error" => "Error al actualizar el perfil: " . $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request, Response $response): Response {
        try {
            $authHeader = $request->getHeaderLine('Authorization');
            $token = null;
            
            if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }

            if (!$token) {
                return $this->jsonResponse($response, [
                    "error" => "Token no proporcionado"
                ], 401);
            }

            // ✅ CORRECTO: Usar el modelo
            $user = new User($this->db);
            $result = $user->logout($token);

            if ($result['success']) {
                return $this->jsonResponse($response, [
                    "message" => "Sesión cerrada exitosamente"
                ], 200);
            } else {
                return $this->jsonResponse($response, [
                    "error" => $result['error']
                ], $result['status']);
            }

        } catch (PDOException $e) {
            return $this->jsonResponse($response, [
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function getAll(Request $request, Response $response): Response {
        try {
            // ✅ CORRECTO: Usar el modelo
            $user = new User($this->db);
            $users = $user->getAll();

            return $this->jsonResponse($response, $users, 200);

        } catch (PDOException $e) {
            return $this->jsonResponse($response, [
                "error" => "Error al obtener los datos: " . $e->getMessage()
            ], 500);
        }
    }
}