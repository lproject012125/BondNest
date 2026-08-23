<?php
require_once 'db_connection.php';

$sql = "SELECT * FROM posts WHERE status = 'approved'";
$stmt = $pdo->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['title'] . "<br>";
}

