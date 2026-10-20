<?php
// Session bridge for the Next.js app: reports the user behind the forwarded PHPSESSID cookie.
require_once __DIR__ . '/../includes/bridge_guard.php';
bridge_guard();
session_start();
if (isset($_SESSION['user_id']) && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
echo json_encode([
    'user_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
    'csrf' => $_SESSION['csrf_token'] ?? null,
]);
