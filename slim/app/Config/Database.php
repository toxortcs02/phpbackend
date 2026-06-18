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
        $databaseUrl = getenv('DATABASE_URL');

        if ($databaseUrl) {
            $parsed          = parse_url($databaseUrl);
            $this->host      = $parsed['host'] ?? '';
            $this->db_name   = ltrim($parsed['path'] ?? '', '/');
            $this->username  = $parsed['user'] ?? '';
            $this->password  = $parsed['pass'] ?? '';
            $this->port      = $parsed['port'] ?? '5432';
        } else {
            $this->host     = getenv('PGHOST');
            $this->db_name  = getenv('PGDATABASE');
            $this->username = getenv('PGUSER');
            $this->password = getenv('PGPASSWORD');
            $this->port     = getenv('PGPORT') ?: '5432';
        }
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name}",
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