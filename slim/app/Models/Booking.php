<?php

namespace App\Models;

use PDO;
use PDOException;
use DateTime;

class Booking {
    private $conn;
    private $table = 'bookings';

    public $id;
    public $created_by;
    public $court_id;
    public $booking_datetime;
    public $duration_blocks;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO {$this->table} 
                 (created_by, court_id, booking_datetime, duration_blocks) 
                 VALUES (:created_by, :court_id, :booking_datetime, :duration_blocks)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':created_by', $this->created_by, PDO::PARAM_INT);
        $stmt->bindParam(':court_id', $this->court_id, PDO::PARAM_INT);
        $stmt->bindParam(':booking_datetime', $this->booking_datetime);
        $stmt->bindParam(':duration_blocks', $this->duration_blocks, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function isCourtAvailable($courtId, $startDatetime, $durationBlocks) {
        $start = new DateTime($startDatetime);
        $end = clone $start;
        $end->modify('+' . ($durationBlocks * 30) . ' minutes');
        
        $query = "SELECT COUNT(*) as count 
                 FROM {$this->table} 
                 WHERE court_id = :court_id 
                 AND (
                     (booking_datetime < :end_time 
                      AND DATE_ADD(booking_datetime, INTERVAL (duration_blocks * 30) MINUTE) > :start_time)
                 )";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':court_id', $courtId, PDO::PARAM_INT);
        $stmt->bindParam(':start_time', $startDatetime);
        $endTime = $end->format('Y-m-d H:i:s');
        $stmt->bindParam(':end_time', $endTime);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] == 0;
    }

    public function hasUserConflict($userId, $startDatetime, $durationBlocks, $excludeBookingId = null) {
        $start = new DateTime($startDatetime);
        $end = clone $start;
        $end->modify('+' . ($durationBlocks * 30) . ' minutes');
        
        $query = "SELECT b.id, c.name as court_name, b.booking_datetime, b.duration_blocks
                 FROM {$this->table} b
                 INNER JOIN booking_participants bp ON b.id = bp.booking_id
                 INNER JOIN courts c ON b.court_id = c.id
                 WHERE bp.user_id = :user_id 
                 AND (
                     (b.booking_datetime < :end_time 
                      AND DATE_ADD(b.booking_datetime, INTERVAL (b.duration_blocks * 30) MINUTE) > :start_time)
                 )";
        
        if ($excludeBookingId) {
            $query .= " AND b.id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':start_time', $startDatetime);
        $endTime = $end->format('Y-m-d H:i:s');
        $stmt->bindParam(':end_time', $endTime);
        
        if ($excludeBookingId) {
            $stmt->bindParam(':exclude_id', $excludeBookingId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $query = "SELECT b.*, c.name as court_name
                 FROM {$this->table} b
                 INNER JOIN courts c ON b.court_id = c.id
                 WHERE b.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByDate($date) {
        $query = "SELECT b.*, c.name as court_name, 
                        u.first_name, u.last_name
                 FROM {$this->table} b
                 INNER JOIN courts c ON b.court_id = c.id
                 INNER JOIN users u ON b.created_by = u.id
                 WHERE DATE(b.booking_datetime) = :date
                 ORDER BY c.name ASC, b.booking_datetime ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteBooking($id) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    public function courtExists($courtId) {
        $query = "SELECT id FROM courts WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $courtId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch() !== false;
    }

}