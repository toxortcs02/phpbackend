<?php

namespace App\Models;

use PDO;
use PDOException;

class BookingParticipant {
    private $conn;
    private $table = 'booking_participants';

    public $id;
    public $booking_id;
    public $user_id;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    // Agregar participante a una reserva
    public function create() {
        try {
            $query = "INSERT INTO {$this->table} 
                     (booking_id, user_id) 
                     VALUES (:booking_id, :user_id)";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':booking_id', $this->booking_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            }
            return false;
            
        } catch (PDOException $e) {
            // Error por duplicado (UNIQUE constraint)
            if ($e->getCode() == 23000) {
                throw new \Exception("El usuario ya está registrado en esta reserva");
            }
            throw new \Exception("Error adding participant: " . $e->getMessage());
        }
    }

    // Obtener participantes de una reserva
    public function getByBookingId($bookingId) {
        try {
            $query = "SELECT bp.*, u.first_name, u.last_name, u.email
                     FROM {$this->table} bp
                     INNER JOIN users u ON bp.user_id = u.id
                     WHERE bp.booking_id = :booking_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new \Exception("Error getting participants: " . $e->getMessage());
        }
    }

    // Eliminar todos los participantes de una reserva
    public function deleteByBookingId($bookingId) {
        try {
            $query = "DELETE FROM {$this->table} WHERE booking_id = :booking_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            throw new \Exception("Error deleting participants: " . $e->getMessage());
        }
    }

    // Verificar si un usuario existe
    public function userExists($userId) {
        try {
            $query = "SELECT id FROM users WHERE id = :id AND is_admin = 0";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch() !== false;
            
        } catch (PDOException $e) {
            throw new \Exception("Error checking user: " . $e->getMessage());
        }
    }

    // Actualizar participantes de una reserva
    public function updateParticipants($bookingId, $userIds) {
        try {
            // Iniciar transacción
            $this->conn->beginTransaction();
            
            // Eliminar participantes actuales
            $this->deleteByBookingId($bookingId);
            
            // Agregar nuevos participantes
            foreach ($userIds as $userId) {
                $this->booking_id = $bookingId;
                $this->user_id = $userId;
                $this->create();
            }
            
            // Confirmar transacción
            $this->conn->commit();
            return true;
            
        } catch (\Exception $e) {
            // Revertir transacción en caso de error
            $this->conn->rollBack();
            throw $e;
        }
    }
}