<?php
// config/database.php

// Konfigurasi database
$host = "localhost";       // Host MySQL, biasanya localhost
$username = "root";        // Username MySQL
$password = "";            // Password MySQL
$database = "ptpkt_database"; // Nama database

// Membuat koneksi
$conn = new mysqli($host, $username, $password, $database);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Mengatur charset agar UTF-8
$conn->set_charset("utf8");

// Fungsi opsional untuk debug
// echo "Koneksi database berhasil!";
?>
