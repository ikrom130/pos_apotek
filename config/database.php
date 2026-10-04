<?php
// config/database.php

class Database {
    // Atribut untuk kredensial server database MySQL Anda
    private $host = "localhost";
    private $port = "3307";
    private $db_name = "pos_apotek";
    private $username = "root";
    private $password = "";
    public $conn;

    // Fungsi untuk membuat dan mengembalikan koneksi database
    public function getConnection() {
        $this->conn = null;

        try {
            // Membuat koneksi baru menggunakan objek PDO
            $this->conn = new PDO(
                "mysql:host=" . $this->host . 
                ";port=" . $this->port . 
                ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );
            
            // Konfigurasi Tambahan PDO (Sangat disukai interviewer jika Anda tahu ini):
            
            // 1. Mengatur Error Mode ke Exception agar jika ada query yang salah, PHP langsung melempar error yang jelas
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 2. Mengatur Default Fetch Mode ke Associative Array agar hasil query otomatis berbentuk array dengan nama kolom database
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // 3. Mematikan emulasi prepared statements agar MySQL yang melakukan eksekusi prepared statement asli (lebih aman)
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        } catch(PDOException $exception) {
            // Jika koneksi gagal, tangkap errornya di sini
            // Di dunia nyata, pesan detail ini biasanya dicatat ke file log, bukan ditampilkan ke publik
            die("Connection failed: " . $exception->getMessage());
        }

        return $this->conn;
    }
}
