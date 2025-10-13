<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Booking;
use App\Models\BookingParticipant;
use PDO;

class BookingParticipantsController {

    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    private function jsonResponse(Response $response, array $data, int $status): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
    
    public function updateParticipants(Request $request, Response $response, array $args): Response {
        try {
            $bookingId = (int)$args['id'];
            $data = $request->getParsedBody();
            $newParticipants = $data['participants'] ?? [];
            $userId = $request->getAttribute('user_id');

            if (!is_array($newParticipants)) {
                return $this->jsonResponse($response, [
                    "error" => "El campo 'participants' debe ser un array de IDs de usuarios"
                ], 400);
            }

            $bookingModel = new Booking($this->db);
            $booking = $bookingModel->getById($bookingId);
            
            if (!$booking) {
                return $this->jsonResponse($response, [
                    "error" => "Reserva no encontrada"
                ], 404);
            }

            if ($booking['created_by'] != $userId) {
                return $this->jsonResponse($response, [
                    "error" => "Solo el creador de la reserva puede modificar los participantes"
                ], 403);
            }

            foreach ($newParticipants as $participantId) {
                if (!is_numeric($participantId)) {
                    return $this->jsonResponse($response, [
                        "error" => "Todos los IDs de participantes deben ser numéricos"
                    ], 400);
                }
            }

            if (count($newParticipants) !== count(array_unique($newParticipants))) {
                return $this->jsonResponse($response, [
                    "error" => "No se permiten participantes duplicados"
                ], 400);
            }

            if (in_array($userId, $newParticipants)) {
                return $this->jsonResponse($response, [
                    "error" => "El creador no debe incluirse en la lista de participantes"
                ], 400);
            }

            $totalPlayers = count($newParticipants) + 1; 
            
            if ($totalPlayers != 2 && $totalPlayers != 4) {
                return $this->jsonResponse($response, [
                    "error" => "Debe haber 2 jugadores (singles) o 4 jugadores (dobles). Total propuesto: {$totalPlayers}"
                ], 400);
            }

            $participantModel = new BookingParticipant($this->db);

            foreach ($newParticipants as $participantId) {
                if (!$participantModel->userExists($participantId)) {
                    return $this->jsonResponse($response, [
                        "error" => "El usuario con ID {$participantId} no existe o es administrador"
                    ], 404);
                }
            }

            $conflicts = [];
            
            foreach ($newParticipants as $participantId) {
                $conflict = $bookingModel->hasUserConflict(
                    $participantId, 
                    $booking['booking_datetime'], 
                    $booking['duration_blocks'],
                    $bookingId 
                );
                
                if ($conflict) {
                    $conflicts[] = [
                        'user_id' => $participantId,
                        'court' => $conflict['court_name'],
                        'datetime' => $conflict['booking_datetime']
                    ];
                }
            }

            if (!empty($conflicts)) {
                $errorMessages = [];
                foreach ($conflicts as $conflict) {
                    $errorMessages[] = "Usuario {$conflict['user_id']} tiene conflicto en cancha '{$conflict['court']}'";
                }
                
                return $this->jsonResponse($response, [
                    "error" => "Uno o más participantes tienen conflictos de horario",
                    "conflicts" => $errorMessages
                ], 409);
            }

            $allParticipants = array_merge([$userId], $newParticipants);
            
            $participantModel->updateParticipants($bookingId, $allParticipants);

            $updatedParticipants = $participantModel->getByBookingId($bookingId);

            return $this->jsonResponse($response, [
                "message" => "Participantes actualizados exitosamente",
                "booking_id" => $bookingId,
                "participants" => $updatedParticipants
            ], 200);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                "error" => "Error al actualizar participantes"
            ], 500);
        }
    }
}