<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'timeslot_voting';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_errno) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
?>
