<?php
// ============================================================
// CHANGE THESE to your InfinityFree database credentials
// You get these from: InfinityFree Panel > MySQL Databases
// ============================================================
$host     = "sql107.infinityfree.com"; // replace with your DB host
$user     = "if0_41817308";     // replace with your DB username
$password = "vIiibK8o91uIEl";        // replace with your DB password
$db_name  = "if0_41817308_kapenatin ";     // replace with your DB name

$mysqli = new mysqli($host, $user, $password, $db_name);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Always start session here so session_id() works on every page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
