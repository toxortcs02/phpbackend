<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Court;
use PDO;
use PDOException;

class CourtController {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    private function jsonResponse(Response $response, array $data, int $status): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public function createCourt(Request $request, Response $response): Response {
        try {
            if (!$request->getAttribute('is_admin')) {
                return $this->jsonResponse($response, ["error" => "No autorizado"], 403);
            }

            $data = $request->getParsedBody();
            $name = $data['name'] ?? '';
            $description = $data['description'] ?? '';

            if (empty($name) || empty($description)) {
                return $this->jsonResponse($response, ["error" => "El nombre y la descripción son requeridos"], 400);
            }

            $court = new Court($this->db);

            if ($court->findByName($name)) {
                return $this->jsonResponse($response, ["error" => "El nombre de la cancha ya está en uso"], 409);
            }

            $courtId = $court->create($name, $description);

            if ($courtId) {
                return $this->jsonResponse($response, ["message" => "Cancha creada exitosamente", "court_id" => $courtId], 201);
            } else {
                return $this->jsonResponse($response, ["error" => "Error al crear la cancha"], 500);
            }

        } catch (PDOException $e) {
            return $this->jsonResponse($response, ["error" => "Error de base de datos"], 500);
        }
    }

    public function updateCourt(Request $request, Response $response, array $args) {
        try {
            if (!$request->getAttribute('is_admin')) {
                return $this->jsonResponse($response, ["error" => "No autorizado para esta acción"], 403);
            }

            $courtId = $args['id'];
            $court = new Court($this->db);

            if (!$court->findById($courtId)) {
                return $this->jsonResponse($response, ["error" => "Cancha no encontrada"], 404);
            }

            $data = $request->getParsedBody();
            $name = $data['name'] ?? null;
            $description = $data['description'] ?? null;
            
            if (empty($name) && empty($description)) {
                return $this->jsonResponse($response, ["error" => "Se requiere al menos un campo (nombre o descripción) para actualizar"], 400);
            }


            if (!empty($name)) {
                $existingCourt = $court->findByName($name);
                if ($existingCourt && $existingCourt['id'] != $courtId) {
                    return $this->jsonResponse($response, ["error" => "El nombre de la cancha ya está en uso"], 409);
                }
            }
            $updated = $court->update($courtId, $name, $description);
            if ($updated) {
                return $this->jsonResponse($response, ["message" => "Cancha actualizada exitosamente"], 200);
            } else {
                return $this->jsonResponse($response, ["message" => "No se realizaron cambios"], 200);
            }

        } catch (PDOException $e) {
            return $this->jsonResponse($response, ["error" => "Error de base de datos: " . $e->getMessage()], 500);
        }
    }

    public function deleteCourt(Request $request, Response $response, array $args): Response {
        try {
            
            if (!$request->getAttribute('is_admin')) {
                return $this->jsonResponse($response, ["error" => "No autorizado para esta acción"], 403);
            }
            $courtId = $args['id'];
            $court = new Court($this->db);

            if (!$court->findById($courtId)) {
                return $this->jsonResponse($response, ["error" => "Cancha no encontrada"], 404);
            }
            //eliminar reservas vencidas NUEVO
            $court->deleteOldBookings($courtId);


            if ($court->hasBookings($courtId)) {
                return $this->jsonResponse($response, ["error" => "No se puede eliminar una cancha con reservas activas"], 409);
            }

            if ($court->delete($courtId)) {
                return $this->jsonResponse($response, ["message" => "Cancha eliminada exitosamente"], 200);
            } else {
                return $this->jsonResponse($response, ["error" => "Error al eliminar la cancha"], 500);
            }
        } catch (PDOException $e) {
            return $this->jsonResponse($response, ["error" => "Error de base de datos: " . $e->getMessage()], 500);
        }
    }
    public function getCourtById(Request $request, Response $response, array $args): Response {
        try {
            $courtId = $args['id'];
            $court = new Court($this->db);
            $courtData = $court->findById($courtId);
            if ($courtData) {
                return $this->jsonResponse($response, $courtData, 200);
            } else {
                return $this->jsonResponse($response, ["error" => "Cancha no encontrada"], 404);
            }
        } catch (PDOException $e) {
            return $this->jsonResponse($response, ["error" => "Error al obtener la cancha"], 500);
        }
    }
    public function getCourtBookings(Request $request, Response $response, array $args): Response {
        try {
            $courtId = $args['id'];
            $court = new Court($this->db);
            if (!$court->findById($courtId)) {
                return $this->jsonResponse($response, ["error" => "Cancha no encontrada"], 404);
            }
            $bookings = $court->getBookingsByCourtId($courtId);
            return $this->jsonResponse($response, $bookings, 200);
        } catch (PDOException $e) {
            return $this->jsonResponse($response, ["error" => "Error al obtener las reservas de la cancha"], 500);
        }
    }

    public function getAllCourts(Request $request, Response $response): Response {
        try {
            $court = new Court($this->db);
            $courts = $court->getAllCourts();
            return $this->jsonResponse($response, $courts, 200);
        } catch (PDOException $e) {
            return $this->jsonResponse($response, ["error" => "Error al obtener las canchas"], 500);
        }
    }
}
