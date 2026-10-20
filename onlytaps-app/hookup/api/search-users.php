<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$query = trim($_GET['q'] ?? '');

if(strlen($query) < 2) {
    echo json_encode(['success' => false, 'error' => 'Query too short']);
    exit();
}

$search = "%{$query}%";

$sql = "SELECT id, username, is_online 
        FROM users 
        WHERE username LIKE :search 
        AND id != :user_id 
        AND is_active = TRUE
        ORDER BY 
            CASE WHEN is_online = TRUE THEN 0 ELSE 1 END,
            username ASC
        LIMIT 10";

$stmt = $db->prepare($sql);
$stmt->bindParam(':search', $search);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

$users = $stmt->fetchAll();

echo json_encode(['success' => true, 'users' => $users]);