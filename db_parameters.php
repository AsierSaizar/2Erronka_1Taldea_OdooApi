
<?php
$servername = "localhost:3306";
$username = "root";
$password = "";
$database = "5_erronka1";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Konexioaren akatsa: " . $conn->connect_error);
}
