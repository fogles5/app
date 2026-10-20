<?php
// Shared guard for server-to-server endpoints used by the Next.js app (loopback + shared secret).
function bridge_guard(): void {
    $secret = getenv('BRIDGE_SECRET') ?: '';
    $given = $_SERVER['HTTP_X_BRIDGE_SECRET'] ?? '';
    $addr = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($secret === '' || !hash_equals($secret, $given) || !in_array($addr, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'forbidden']);
        exit();
    }
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
}
