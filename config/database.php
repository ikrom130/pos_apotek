<?php
// config/database.php

class Database {

    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Jalankan fungsi loadEnv saat class ini dipanggil
        $this->loadEnv(__DIR__ . '/../.env'); // ../ digunakan karena file .env ada di luar folder config

        // Ambil data dari .env, berikan nilai default jika .env tidak terbaca
        $this->host     = getenv('DB_HOST') ?: "localhost";
        $this->port     = getenv('DB_PORT') ?: "3306";
        $this->db_name  = getenv('DB_NAME') ?: "pos_apotek";
        $this->username = getenv('DB_USER') ?: "root";
        $this->password = getenv('DB_PASS') ?: "";
    }

    private function loadEnv($path) {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    public function getConnection() {
        $this->conn = null;

        try {
            // Ditambahkan parameter port ke dalam DSN PDO
            $this->conn = new PDO(
                "mysql:host=" . $this->host . 
                ";port=" . $this->port . 
                ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);

        } catch(PDOException $exception) {
            die("Connection failed: " . $exception->getMessage());
        }

        return $this->conn;
    }
}
