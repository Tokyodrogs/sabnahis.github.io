<?php
/**
 * Database connection (PDO)
 * SABNAHIS Local Web-Based Enrollment System
 */

$DB_HOST = 'localhost';
$DB_NAME = 'sabnahis_enrollment';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Please make sure XAMPP MySQL is running and the '
        . '"sabnahis_enrollment" database has been imported. Error: ' . $e->getMessage());
}
