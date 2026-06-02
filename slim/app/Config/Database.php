<?php
namespace App\Config;
use PDO;
use PDOException;

class Database {
    private static $connection;
    private $host     = '';
    private $db_name  = '';
    private $username = '';
    private $password = '';
    private $port     = '';
    public $conn;

    public function __construct() {
        $this->host     = getenv('MYSQLHOST');
        $this->db_name  = getenv('MYSQLDATABASE');
        $this->username = getenv('MYSQLUSER');
        $this->password = getenv('MYSQLPASSWORD');
        $this->port     = getenv('MYSQLPORT') ?: '3306';
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
        }
        return $this->conn;
    }
}