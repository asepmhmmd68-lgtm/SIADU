<?php
function getDbConnection() {
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db   = 'siadu_db';

    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception('Koneksi database gagal: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}