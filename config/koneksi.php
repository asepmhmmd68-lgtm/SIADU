<?php
$host     = "localhost";
$user     = "root";
$pass     = "";
$database = "db_siadu_desa";

$koneksi = mysqli_connect($host, $user, $pass, $database);

if (!$koneksi) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}
?>