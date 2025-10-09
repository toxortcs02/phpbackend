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

    // Helper para respuestas JSON
    private function jsonResponse(Response $response, array $data, int $status): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    public function createCourt(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $name = $data['name'] ?? '';
            $description = $data['description'] ?? '';

            if (empty($name) || empty($description)) {
                return $this->jsonResponse($response, [
                    "error" => "Todos los campos son requeridos"
                ], 400);
            }

            $court = new Court($this->db);

            if ($court->findByName($name)) {
                return $this->jsonResponse($response, [
                    "error" => "El nombre de la cancha ya está registrado"
                ], 409);
            }

            $court->name = $name;
            $court->description = $description;
            $courtId = $court->create();

            if ($courtId) {
                return $this->jsonResponse($response, [
                    "message" => "Cancha creada exitosamente",
                    "court_id" => $courtId
                ], 201);
            } else {
                return $this->jsonResponse($response, [
                    "error" => "Error al crear la cancha"
                ], 500);
            }

        } catch (PDOException $e) {
            return $this->jsonResponse($response, [
                "error" => "Error del servidor: " . $e->getMessage()
            ], 500);
        }
    }
    public function updateCourt(Request $request, Response $response, array $args) {
         try {
            $courtId = $args['id'];
            $data = $request->getParsedBody();
            $isAdmin = $request->getAttribute('is_admin');
            if (!$isAdmin) {
                return $this->jsonResponse($response, [
                    "error" => "No autorizado para actualizar este perfil"
                ], 401);
            }
            $name = $data['name'] ?? null;
            $description = $data['description'] ?? null;
            if (empty($name) && empty($description)) {
                return $this->jsonResponse($response, [
                    "error" => "Todos los campos son requeridos"
                ], 400);
            }
            $court = new Court($this->db);
            $courtData = $court->editCourt($courtId, $name, $description);
            if ($courtData) {
                return $this->jsonResponse($response, [
                    "message" => "Cancha actualizada exitosamente"
                ], 200);
            } else {
                return $this->jsonResponse($response, [
                    "error" => "Error al actualizar la cancha o no se encontraron cambios"
                ], 500);
            }

         } catch (\Throwable $th) {
            
         }   


    }
}