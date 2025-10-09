<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Booking;
use App\Models\BookingParticipant;
use DateTime;
use PDO;

class BookingController {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Crea una nueva reserva de cancha.
     * Realiza múltiples validaciones antes de crear la reserva y sus participantes.
     */
    public function create(Request $request, Response $response): Response {
        try {
            $data = $request->getParsedBody();
            $creatorId = $request->getAttribute('user_id');

            if (empty($data['court_id']) || empty($data['booking_datetime']) || 
                empty($data['duration_blocks']) || empty($data['participants'])) {
                $response->getBody()->write(json_encode([
                    "error" => "Faltan campos requeridos: court_id, booking_datetime, duration_blocks, participants"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $courtId = $data['court_id'];
            $bookingDatetime = $data['booking_datetime'];
            $durationBlocks = $data['duration_blocks'];
            $participants = $data['participants']; 
            if ($durationBlocks > 6 || $durationBlocks < 1) {
                $response->getBody()->write(json_encode([
                    "error" => "La duración debe ser entre 1 y 6 bloques (máximo 3 horas)"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $totalPlayers = count($participants) + 1; 
            if ($totalPlayers != 2 && $totalPlayers != 4) {
                $response->getBody()->write(json_encode([
                    "error" => "Debe haber 2 jugadores (singles) o 4 jugadores (dobles). Total actual: {$totalPlayers}"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            try {
                $datetime = new DateTime($bookingDatetime);
            } catch (\Exception $e) {
                $response->getBody()->write(json_encode([
                    "error" => "Formato de fecha inválido. Use: Y-m-d H:i:s"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $minutes = (int)$datetime->format('i');
            if ($minutes !== 0 && $minutes !== 30) {
                $response->getBody()->write(json_encode([
                    "error" => "La reserva debe comenzar a las :00 o :30 minutos"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $hour = (int)$datetime->format('H');
            if ($hour < 8 || $hour >= 22) {
                $response->getBody()->write(json_encode([
                    "error" => "Las reservas solo están disponibles entre las 8:00 y las 22:00"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $endTime = clone $datetime;
            $endTime->modify('+' . ($durationBlocks * 30) . ' minutes');
            $endHour = (int)$endTime->format('H');
            $endMinute = (int)$endTime->format('i');
            
            if ($endHour > 22 || ($endHour == 22 && $endMinute > 0)) {
                $response->getBody()->write(json_encode([
                    "error" => "La reserva excede el horario límite de 22:00. Terminaría a las " . $endTime->format('H:i')
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $booking = new Booking($this->db);
            $participant = new BookingParticipant($this->db);

            if (!$booking->courtExists($courtId)) {
                $response->getBody()->write(json_encode([
                    "error" => "La cancha especificada no existe"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            if (!$booking->isCourtAvailable($courtId, $bookingDatetime, $durationBlocks)) {
                $response->getBody()->write(json_encode([
                    "error" => "La cancha no está disponible en el horario seleccionado"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            foreach ($participants as $participantId) {
                if (!$participant->userExists($participantId)) {
                    $response->getBody()->write(json_encode([
                        "error" => "El usuario con ID {$participantId} no existe o es administrador"
                    ]));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
                }
            }

            if (in_array($creatorId, $participants)) {
                $response->getBody()->write(json_encode([
                    "error" => "El creador de la reserva no debe incluirse en la lista de participantes"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $creatorConflict = $booking->hasUserConflict($creatorId, $bookingDatetime, $durationBlocks);
            if ($creatorConflict) {
                $conflictEnd = new DateTime($creatorConflict['booking_datetime']);
                $conflictEnd->modify('+' . ($creatorConflict['duration_blocks'] * 30) . ' minutes');
                
                $response->getBody()->write(json_encode([
                    "error" => "Ya tienes una reserva en la cancha '{$creatorConflict['court_name']}' que se solapa con este horario ({$creatorConflict['booking_datetime']} - {$conflictEnd->format('H:i')})"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            $conflicts = [];
            foreach ($participants as $participantId) {
                $conflict = $booking->hasUserConflict($participantId, $bookingDatetime, $durationBlocks);
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
                
                $response->getBody()->write(json_encode([
                    "error" => "Uno o más participantes tienen conflictos de horario",
                    "conflicts" => $errorMessages
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            $this->db->beginTransaction();

            try {
                $booking->created_by = $creatorId;
                $booking->court_id = $courtId;
                $booking->booking_datetime = $bookingDatetime;
                $booking->duration_blocks = $durationBlocks;
                
                if (!$booking->create()) {
                    throw new \Exception("Error al crear la reserva");
                }

                $participant->booking_id = $booking->id;
                $participant->user_id = $creatorId;
                $participant->create();

                foreach ($participants as $participantId) {
                    $participant->booking_id = $booking->id;
                    $participant->user_id = $participantId;
                    $participant->create();
                }
                
                $this->db->commit();

                $bookingData = $booking->getById($booking->id);
                $participantsData = $participant->getByBookingId($booking->id);

                $response->getBody()->write(json_encode([
                    "message" => "Reserva creada exitosamente",
                    "booking" => $bookingData,
                    "participants" => $participantsData
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "error" => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Elimina una reserva existente.
     * Solo el creador o un administrador pueden eliminar la reserva.
     */
    public function delete(Request $request, Response $response, array $args): Response {
        try {
            $bookingId = $args['id'];
            $userId = $request->getAttribute('user_id');
            $isAdmin = $request->getAttribute('is_admin');

            $booking = new Booking($this->db);
            $bookingData = $booking->getById($bookingId);

            if (!$bookingData) {
                $response->getBody()->write(json_encode([
                    "error" => "Reserva no encontrada"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            if ($bookingData['created_by'] != $userId && !$isAdmin) {
                $response->getBody()->write(json_encode([
                    "error" => "No tienes permiso para eliminar esta reserva"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }

            $this->db->beginTransaction();

            try {
                $participant = new BookingParticipant($this->db);
                $participant->deleteByBookingId($bookingId);

                $booking->deleteBooking($bookingId);

                $this->db->commit();

                $response->getBody()->write(json_encode([
                    "message" => "Reserva eliminada exitosamente"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "error" => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Lista todas las reservas para una fecha específica.
     * Devuelve las reservas junto con sus participantes.
     */
    public function list(Request $request, Response $response): Response {
        try {
            $params = $request->getQueryParams();
            $date = $params['date'] ?? null;

            if (!$date) {
                $response->getBody()->write(json_encode([
                    "error" => "El parámetro 'date' es requerido (formato: Y-m-d)"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $datetime = DateTime::createFromFormat('Y-m-d', $date);
            if (!$datetime || $datetime->format('Y-m-d') !== $date) {
                $response->getBody()->write(json_encode([
                    "error" => "Formato de fecha inválido. Use: Y-m-d (ejemplo: 2025-08-06)"
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $booking = new Booking($this->db);
            $bookings = $booking->getByDate($date);

            $participant = new BookingParticipant($this->db);
            foreach ($bookings as &$bookingItem) {
                $bookingItem['participants'] = $participant->getByBookingId($bookingItem['id']);
            }

            $response->getBody()->write(json_encode($bookings));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                "error" => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}