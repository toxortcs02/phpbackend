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

    private function jsonResponse(Response $response, array $data, int $statusCode): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }

    public function create(Request $request, Response $response): Response {
        try {
            $data = $request->getParsedBody();
            $creatorId = $request->getAttribute('user_id');

            $requiredFields = ['court_id', 'booking_datetime', 'duration_blocks', 'participants'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->jsonResponse($response, ["error" => "Falta el campo requerido: {$field}"], 400);
                }
            }

            $courtId = $data['court_id'];
            $durationBlocks = $data['duration_blocks'];
            $participants = $data['participants'];
            $bookingDatetime = $data['booking_datetime'];

            if (!is_numeric($courtId) || (int)$courtId <= 0) {
                return $this->jsonResponse($response, ["error" => "El campo 'court_id' debe ser un número entero positivo."], 400);
            }
            if (!is_numeric($durationBlocks) || (int)$durationBlocks <= 0) {
                return $this->jsonResponse($response, ["error" => "El campo 'duration_blocks' debe ser un número entero positivo."], 400);
            }
            if (!is_array($participants)) {
                return $this->jsonResponse($response, ["error" => "El campo 'participants' debe ser un array de IDs de usuario."], 400);
            }
            foreach ($participants as $participantId) {
                if (!is_numeric($participantId)) {
                    return $this->jsonResponse($response, ["error" => "Cada ID en 'participants' debe ser un número."], 400);
                }
            }

            $courtId = (int)$courtId;
            $durationBlocks = (int)$durationBlocks;

            if ($durationBlocks > 6) {
                return $this->jsonResponse($response, ["error" => "La duración no puede exceder los 6 bloques (3 horas)."], 400);
            }

            $totalPlayers = count($participants) + 1;
            if ($totalPlayers != 2 && $totalPlayers != 4) {
                return $this->jsonResponse($response, ["error" => "Debe haber 2 o 4 jugadores en total."], 400);
            }
            
            try {
                $datetime = new DateTime($bookingDatetime);
            } catch (\Exception $e) {
                return $this->jsonResponse($response, ["error" => "Formato de fecha inválido. Use: Y-m-d H:i:s"], 400);
            }
            
            $datetime->setTime((int)$datetime->format('H'), (int)$datetime->format('i'), 0);
            $bookingDatetime = $datetime->format('Y-m-d H:i:s');

            $minutes = (int)$datetime->format('i');
            if ($minutes !== 0 && $minutes !== 30) {
                return $this->jsonResponse($response, ["error" => "La reserva debe comenzar a las :00 o :30 minutos."], 400);
            }

            $hour = (int)$datetime->format('H');
            $endTime = (clone $datetime)->modify('+' . ($durationBlocks * 30) . ' minutes');
            $endHour = (int)$endTime->format('H');
            $endMinutes = (int)$endTime->format('i');
            
            if ($hour < 8 || $hour >= 22 || $endHour > 22 || ($endHour == 22 && $endMinutes > 0)) {
                return $this->jsonResponse($response, ["error" => "El horario debe estar entre las 8:00 y las 22:00 y no excederlo."], 400);
            }

            $booking = new Booking($this->db);
            $participant = new BookingParticipant($this->db);

            if (!$booking->courtExists($courtId)) {
                return $this->jsonResponse($response, ["error" => "La cancha especificada no existe"], 404);
            }

            foreach ($participants as $participantId) {
                if (!$participant->userExists($participantId)) {
                    return $this->jsonResponse($response, ["error" => "El usuario con ID {$participantId} no existe o es administrador"], 404);
                }
            }

            if (in_array($creatorId, $participants)) {
                return $this->jsonResponse($response, ["error" => "El creador de la reserva no debe incluirse en la lista de participantes"], 400);
            }

            if (!$booking->isCourtAvailable($courtId, $bookingDatetime, $durationBlocks)) {
                return $this->jsonResponse($response, ["error" => "La cancha no está disponible en el horario seleccionado"], 409);
            }

            $creatorConflict = $booking->hasUserConflict($creatorId, $bookingDatetime, $durationBlocks);
            if ($creatorConflict) {
                return $this->jsonResponse($response, ["error" => "Ya tienes una reserva que se solapa con este horario."], 409);
            }

            foreach ($participants as $participantId) {
                if ($booking->hasUserConflict($participantId, $bookingDatetime, $durationBlocks)) {
                    return $this->jsonResponse($response, ["error" => "El usuario con ID {$participantId} tiene un conflicto de horario."], 409);
                }
            }

            $this->db->beginTransaction();
            try {
                $booking->created_by = $creatorId;
                $booking->court_id = $courtId;
                $booking->booking_datetime = $bookingDatetime;
                $booking->duration_blocks = $durationBlocks;
                
                if (!$booking->create()) {
                    throw new \Exception("Error al crear la reserva en la base de datos.");
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

                return $this->jsonResponse($response, [
                    "message" => "Reserva creada exitosamente",
                    "booking" => $bookingData,
                    "participants" => $participantsData
                ], 201);

            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["error" => "Error del servidor"], 500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response {
        try {
            $bookingId = $args['id'];
            $userId = $request->getAttribute('user_id');
            $isAdmin = $request->getAttribute('is_admin');

            $booking = new Booking($this->db);
            $bookingData = $booking->getById($bookingId);

            if (!$bookingData) {
                return $this->jsonResponse($response, ["error" => "Reserva no encontrada"], 404);
            }

            if ($bookingData['created_by'] != $userId && !$isAdmin) {
                return $this->jsonResponse($response, ["error" => "No tienes permiso para eliminar esta reserva"], 403);
            }

            $this->db->beginTransaction();

            try {
                $participant = new BookingParticipant($this->db);
                $participant->deleteByBookingId($bookingId);

                $booking->deleteBooking($bookingId);

                $this->db->commit();

                return $this->jsonResponse($response, ["message" => "Reserva eliminada exitosamente"], 200);

            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["error" => "Error del servidor"], 500);
        }
    }

    public function list(Request $request, Response $response): Response {
        try {
            $params = $request->getQueryParams();
            $date = $params['date'] ?? date('Y-m-d');

            if (!$date) {
                return $this->jsonResponse($response, ["error" => "El parámetro 'date' es requerido (formato: Y-m-d)"], 400);
            }

            $datetime = DateTime::createFromFormat('Y-m-d', $date);
            if (!$datetime || $datetime->format('Y-m-d') !== $date) {
                return $this->jsonResponse($response, ["error" => "Formato de fecha inválido. Use: Y-m-d (ejemplo: 2025-08-06)"], 400);
            }

            $booking = new Booking($this->db);
            $bookings = $booking->getByDate($date);

            $participant = new BookingParticipant($this->db);
            foreach ($bookings as &$bookingItem) {
                $bookingItem['participants'] = $participant->getByBookingId($bookingItem['id']);
            }

            return $this->jsonResponse($response, $bookings, 200);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["error" => "Error del servidor"], 500);
        }
    }
    
}