<?php
// Photo upload for the Next.js app. Reuses ImageUpload for type/size validation, resizing and thumbnails.
require_once __DIR__ . '/../../includes/bridge_guard.php';
bridge_guard();
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/ImageUpload.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['photo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file']);
    exit();
}

$type = ($_POST['type'] ?? 'listing') === 'user' ? 'user' : 'listing';
$db = (new Database())->getConnection();
$result = (new ImageUpload($db))->upload($_FILES['photo'], $type);
if (empty($result['success'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Upload failed']);
    exit();
}
echo json_encode(['success' => true, 'path' => $result['path']]);
