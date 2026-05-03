<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require("config.php");

$session_id = session_id();
$stmt = $mysqli->prepare("DELETE FROM cart WHERE session_id = ?");
$stmt->bind_param("s", $session_id);
$stmt->execute();
$stmt->close();

session_destroy();
header('Location: login.php');
exit;
?>
