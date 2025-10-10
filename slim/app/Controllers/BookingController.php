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

    /**
     * Crea una nueva reserva de cancha.
     * Realiza múltiples validaciones antes de crear la reserva y sus participantes.
     */
public function create(Request $request, Response $response): Response {
        try {
            $data = $request->getParsedBody();
            $creatorId = $request->getAttribute('user_id');

            // 1. Validar que los campos requeridos existan
            $requiredFields = ['court_id', 'booking_datetime', 'duration_blocks', 'participants'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->jsonResponse($response, ["error" => "Falta el campo requerido: {$field}"], 400);
                }
            }

            // 2. ¡NUEVO! Validar el FORMATO de cada campo
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
            // Validar que cada participante en el array sea numérico
            foreach ($participants as $participantId) {
                if (!is_numeric($participantId)) {
                    return $this->jsonResponse($response, ["error" => "Cada ID en 'participants' debe ser un número."], 400);
                }
            }

            // Convertir a tipos correctos después de validar
            $courtId = (int)$courtId;
            $durationBlocks = (int)$durationBlocks;

            // 3. Validar Reglas de Negocio (la lógica que ya tenías)
            if ($durationBlocks > 6) { // Ya no hace falta < 1 porque validamos que sea positivo arriba
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
            
            // Normalizar segundos a 00
            $datetime->setTime((int)$datetime->format('H'), (int)$datetime->format('i'), 0);
            $bookingDatetime = $datetime->format('Y-m-d H:i:s'); // Actualizar la variable

            $minutes = (int)$datetime->format('i');
            if ($minutes !== 0 && $minutes !== 30) {
                return $this->jsonResponse($response, ["error" => "La reserva debe comenzar a las :00 o :30 minutos."], 400);
            }

            $hour = (int)$datetime->format('H');
            $endTime = (clone $datetime)->modify('+' . ($durationBlocks * 30) . ' minutes');
            if ($hour < 8 || $hour >= 22 || $endTime->format('H') > 22 || ($endTime->format('H') == 22 && $endTime->format('i') > 0)) {
                return $this->jsonResponse($response, ["error" => "El horario debe estar entre las 8:00 y las 22:00 y no excederlo."], 400);
            }
            
            // ... El resto de la lógica (verificación de disponibilidad, conflictos, etc.) continúa aquí ...
            // (Se omite por brevedad, ya que es idéntica a la tuya)

            $booking = new Booking($this->db);
            $participant = new BookingParticipant($this->db);

            if (!$booking->courtExists($courtId)) {
                return $this->jsonResponse($response, ["error" => "La cancha especificada no existe"], 404);
            }

            if (!$booking->isCourtAvailable($courtId, $bookingDatetime, $durationBlocks)) {
                return $this->jsonResponse($response, ["error" => "La cancha no está disponible en el horario seleccionado"], 409);
            }
            
            // (Y así sucesivamente con el resto de tus validaciones...)

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ["error" => "Error del servidor: " . $e->getMessage()], 500);
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