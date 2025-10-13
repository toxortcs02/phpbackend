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

    public function create() {
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
    }

    public function getByBookingId($bookingId) {
        $query = "SELECT bp.*, u.first_name, u.last_name, u.email
                 FROM {$this->table} bp
                 INNER JOIN users u ON bp.user_id = u.id
                 WHERE bp.booking_id = :booking_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteByBookingId($bookingId) {
        $query = "DELETE FROM {$this->table} WHERE booking_id = :booking_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    public function userExists($userId) {
        $query = "SELECT id FROM users WHERE id = :id AND is_admin = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch() !== false;
    }

    public function updateParticipants($bookingId, $userIds) {
        $this->deleteByBookingId($bookingId);
        
        foreach ($userIds as $userId) {
            $this->booking_id = $bookingId;
            $this->user_id = $userId;
            $this->create();
        }
        
        return true;
    }
}