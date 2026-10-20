<?php
/**
 * Short-lived signed tokens used to authenticate WebSocket connections.
 * The PHP app issues them for the logged-in session user; the WebSocket
 * server verifies them without trusting any client-supplied user id.
 */
class WsToken {
    const TTL = 120;

    private static function key() {
        $secret = getenv('WS_SECRET') ?: getenv('DB_PASSWORD');
        if (!$secret) {
            throw new RuntimeException('WS_SECRET or DB_PASSWORD must be set');
        }
        return hash('sha256', 'hookup-ws|' . $secret, true);
    }

    private static function b64($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function issue($userId) {
        $payload = self::b64(json_encode(['u' => (int)$userId, 'exp' => time() + self::TTL]));
        return $payload . '.' . self::b64(hash_hmac('sha256', $payload, self::key(), true));
    }

    public static function verify($token) {
        if (!is_string($token) || substr_count($token, '.') !== 1) {
            return null;
        }
        [$payload, $sig] = explode('.', $token);
        $expected = self::b64(hash_hmac('sha256', $payload, self::key(), true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        if (!is_array($data) || empty($data['u']) || ($data['exp'] ?? 0) < time()) {
            return null;
        }
        return (int)$data['u'];
    }
}
