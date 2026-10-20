<?php
error_reporting(E_ALL & ~E_DEPRECATED);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/includes/ws_token.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ChatServer implements MessageComponentInterface {
    const MAX_FRAME = 8192;
    const MAX_BODY = 2000;
    const MAX_CONNS_PER_USER = 10;
    const AUTH_TIMEOUT = 10;
    const RATE_WINDOW = 10;
    const RATE_MAX = 15;
    const PAGE = 50;

    private $db;
    private $loop;
    private $origins;
    /** @var \SplObjectStorage conn => state */
    private $conns;
    /** @var array userId => [resourceId => conn] */
    private $byUser = [];
    /** @var array userId => timestamps */
    private $sendLog = [];

    public function __construct($loop) {
        $this->loop = $loop;
        $this->conns = new \SplObjectStorage();
        $list = getenv('WS_ALLOWED_ORIGINS') ?: 'https://18.189.7.19';
        $this->origins = array_filter(array_map('trim', explode(',', $list)));
        $this->connect();
    }

    private function connect() {
        $host = getenv('DB_HOST') ?: 'localhost';
        $name = getenv('DB_NAME') ?: 'doublelist_clone';
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASSWORD');
        if (!$user || $pass === false || $pass === '') {
            fwrite(STDERR, "DB_USER and DB_PASSWORD must be set\n");
            exit(1);
        }
        $this->db = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function q($sql, array $params = []) {
        for ($attempt = 0; ; $attempt++) {
            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            } catch (PDOException $e) {
                $gone = in_array($e->errorInfo[1] ?? 0, [2006, 2013], true);
                if ($gone && $attempt === 0) {
                    $this->connect();
                    continue;
                }
                throw $e;
            }
        }
    }

    // ---- connection lifecycle -------------------------------------------

    public function onOpen(ConnectionInterface $conn) {
        $origin = $conn->httpRequest->getHeaderLine('Origin');
        if (!in_array($origin, $this->origins, true)) {
            $conn->close();
            return;
        }
        $state = new \stdClass();
        $state->user = null;
        $state->watch = [];
        $state->timer = $this->loop->addTimer(self::AUTH_TIMEOUT, function () use ($conn, $state) {
            if (!$state->user) {
                $conn->close();
            }
        });
        $this->conns->attach($conn, $state);
    }

    public function onClose(ConnectionInterface $conn) {
        if (!$this->conns->contains($conn)) {
            return;
        }
        $state = $this->conns[$conn];
        $this->loop->cancelTimer($state->timer);
        $uid = $state->user['id'] ?? null;
        $this->conns->detach($conn);
        if ($uid) {
            unset($this->byUser[$uid][$conn->resourceId]);
            if (empty($this->byUser[$uid])) {
                unset($this->byUser[$uid], $this->sendLog[$uid]);
                $this->setOnline($uid, false);
                $this->broadcastPresence($uid, false);
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        error_log('ws error: ' . $e->getMessage());
        $conn->close();
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        if (!$this->conns->contains($from)) {
            return;
        }
        if (strlen($msg) > self::MAX_FRAME) {
            $from->close();
            return;
        }
        $data = json_decode($msg, true);
        if (!is_array($data) || !isset($data['type']) || !is_string($data['type'])) {
            return;
        }
        $state = $this->conns[$from];

        try {
            if (!$state->user) {
                if ($data['type'] === 'auth') {
                    $this->handleAuth($from, $state, $data);
                } else {
                    $from->close();
                }
                return;
            }
            switch ($data['type']) {
                case 'ping':          $this->send($from, ['type' => 'pong']); break;
                case 'conversations': $this->handleConversations($from, $state); break;
                case 'history':       $this->handleHistory($from, $state, $data); break;
                case 'send':          $this->handleSend($from, $state, $data); break;
                case 'typing':        $this->handleTyping($state, $data); break;
                case 'read':          $this->handleRead($state, $data); break;
            }
        } catch (\Throwable $e) {
            error_log('ws handler error: ' . $e->getMessage());
            $this->send($from, ['type' => 'error', 'error' => 'Something went wrong']);
        }
    }

    // ---- helpers ----------------------------------------------------------

    private function send(ConnectionInterface $conn, array $payload) {
        $conn->send(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function sendToUser($uid, array $payload, $exceptConn = null) {
        foreach ($this->byUser[$uid] ?? [] as $c) {
            if ($c !== $exceptConn) {
                $this->send($c, $payload);
            }
        }
    }

    private function isBlocked($a, $b) {
        $r = $this->q(
            'SELECT 1 FROM blocked_users WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1',
            [$a, $b, $b, $a]
        );
        return (bool)$r->fetchColumn();
    }

    private function findUser($id) {
        return $this->q(
            'SELECT id, username, display_name, avatar, is_banned, is_suspended, is_demo FROM users WHERE id = ? LIMIT 1',
            [$id]
        )->fetch();
    }

    private function setOnline($uid, $online) {
        try {
            $this->q('UPDATE users SET is_online = ?, last_seen = NOW() WHERE id = ?', [$online ? 1 : 0, $uid]);
        } catch (\Throwable $e) {
            error_log('presence update failed: ' . $e->getMessage());
        }
    }

    private function broadcastPresence($uid, $online) {
        $payload = ['type' => 'presence', 'user_id' => $uid, 'online' => $online, 'last_seen' => gmdate('c')];
        foreach ($this->conns as $c) {
            $st = $this->conns[$c];
            if ($st->user && isset($st->watch[$uid])) {
                $this->send($c, $payload);
            }
        }
    }

    private function shape(array $m) {
        return [
            'id' => (int)$m['id'],
            'sender_id' => (int)$m['sender_id'],
            'receiver_id' => (int)$m['receiver_id'],
            'body' => $m['message'],
            'created_at' => gmdate('c', strtotime($m['created_at'] . ' UTC')),
            'read' => (bool)$m['is_read'],
        ];
    }

    private function name(array $u) {
        return ($u['display_name'] ?? '') !== '' && $u['display_name'] !== null ? $u['display_name'] : $u['username'];
    }

    // ---- handlers ----------------------------------------------------------

    private function handleAuth($conn, $state, $data) {
        $uid = WsToken::verify($data['token'] ?? '');
        $user = $uid ? $this->findUser($uid) : null;
        if (!$user || $user['is_banned'] || $user['is_suspended']
            || count($this->byUser[$uid] ?? []) >= self::MAX_CONNS_PER_USER) {
            $this->send($conn, ['type' => 'auth_failed']);
            $conn->close();
            return;
        }
        $this->loop->cancelTimer($state->timer);
        $state->user = ['id' => (int)$user['id'], 'username' => $user['username']];
        $first = empty($this->byUser[$uid]);
        $this->byUser[$uid][$conn->resourceId] = $conn;
        if ($first) {
            $this->setOnline($uid, true);
            $this->broadcastPresence($uid, true);
        }
        $this->send($conn, ['type' => 'auth_ok', 'user' => ['id' => (int)$user['id'], 'name' => $this->name($user)]]);
    }

    private function handleConversations($conn, $state) {
        $me = $state->user['id'];
        $rows = $this->q(
            'SELECT IF(sender_id = :me, receiver_id, sender_id) AS other, MAX(id) AS last_id,
                    SUM(receiver_id = :me2 AND is_read = 0) AS unread
             FROM messages
             WHERE (sender_id = :me3 AND is_deleted_by_sender = 0) OR (receiver_id = :me4 AND is_deleted_by_receiver = 0)
             GROUP BY other ORDER BY last_id DESC LIMIT 100',
            ['me' => $me, 'me2' => $me, 'me3' => $me, 'me4' => $me]
        )->fetchAll();

        $out = [];
        $state->watch = [];
        foreach ($rows as $r) {
            $other = $this->findUser($r['other']);
            if (!$other) {
                continue;
            }
            $last = $this->q('SELECT * FROM messages WHERE id = ?', [$r['last_id']])->fetch();
            $state->watch[(int)$other['id']] = true;
            $out[] = [
                'user' => [
                    'id' => (int)$other['id'],
                    'name' => $this->name($other),
                    'avatar' => $other['avatar'],
                    'online' => !empty($this->byUser[$other['id']]),
                ],
                'last' => $this->shape($last),
                'unread' => (int)$r['unread'],
            ];
        }
        $this->send($conn, ['type' => 'conversations', 'items' => $out]);
    }

    private function handleHistory($conn, $state, $data) {
        $me = $state->user['id'];
        $with = (int)($data['with'] ?? 0);
        $before = (int)($data['before'] ?? 0);
        $other = $with ? $this->findUser($with) : null;
        if (!$other || $with === $me) {
            $this->send($conn, ['type' => 'error', 'error' => 'User not found']);
            return;
        }
        $state->watch[$with] = true;
        $sql = 'SELECT * FROM messages WHERE
                ((sender_id = :me AND receiver_id = :o AND is_deleted_by_sender = 0)
                 OR (sender_id = :o2 AND receiver_id = :me2 AND is_deleted_by_receiver = 0))';
        $params = ['me' => $me, 'o' => $with, 'o2' => $with, 'me2' => $me];
        if ($before > 0) {
            $sql .= ' AND id < :before';
            $params['before'] = $before;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . (self::PAGE + 1);
        $rows = $this->q($sql, $params)->fetchAll();
        $more = count($rows) > self::PAGE;
        $rows = array_reverse(array_slice($rows, 0, self::PAGE));
        $this->send($conn, [
            'type' => 'history',
            'with' => [
                'id' => (int)$other['id'],
                'name' => $this->name($other),
                'avatar' => $other['avatar'],
                'online' => !empty($this->byUser[$other['id']]),
            ],
            'before' => $before,
            'more' => $more,
            'items' => array_map([$this, 'shape'], $rows),
        ]);
    }

    private function allowSend($uid) {
        $now = microtime(true);
        $log = array_filter($this->sendLog[$uid] ?? [], fn($t) => $now - $t < self::RATE_WINDOW);
        if (count($log) >= self::RATE_MAX) {
            $this->sendLog[$uid] = $log;
            return false;
        }
        $log[] = $now;
        $this->sendLog[$uid] = $log;
        return true;
    }

    private function conversationId($a, $b) {
        $row = $this->q(
            'SELECT id FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?) LIMIT 1',
            [$a, $b, $b, $a]
        )->fetch();
        if ($row) {
            return (int)$row['id'];
        }
        $this->q('INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)', [min($a, $b), max($a, $b)]);
        return (int)$this->db->lastInsertId();
    }

    private function handleSend($conn, $state, $data) {
        $me = $state->user['id'];
        $to = (int)($data['to'] ?? 0);
        $cid = is_string($data['cid'] ?? null) ? substr($data['cid'], 0, 64) : null;
        $body = is_string($data['body'] ?? null) ? $data['body'] : '';
        $body = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body));

        $fail = function ($error) use ($conn, $cid) {
            $this->send($conn, ['type' => 'send_failed', 'cid' => $cid, 'error' => $error]);
        };

        if ($body === '') {
            return $fail('Message cannot be empty');
        }
        if (mb_strlen($body) > self::MAX_BODY) {
            return $fail('Message is too long (max ' . self::MAX_BODY . ' characters)');
        }
        $target = $to && $to !== $me ? $this->findUser($to) : null;
        if ($target && $target['is_demo']) {
            return $fail('This is a sample profile and cannot receive messages');
        }
        if (!$target || $target['is_banned'] || $target['is_suspended'] || $this->isBlocked($me, $to)) {
            return $fail('You cannot message this user');
        }
        if (!$this->allowSend($me)) {
            return $fail('You are sending messages too quickly');
        }

        $convId = $this->conversationId($me, $to);
        $this->q('INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)', [$convId, $me, $to, $body]);
        $id = (int)$this->db->lastInsertId();
        $this->q(
            'UPDATE conversations SET last_message_at = NOW(),
                user1_unread = user1_unread + (user1_id = ?), user2_unread = user2_unread + (user2_id = ?)
             WHERE id = ?',
            [$to, $to, $convId]
        );
        $row = $this->q('SELECT * FROM messages WHERE id = ?', [$id])->fetch();
        $msg = $this->shape($row);

        $this->send($conn, ['type' => 'message', 'cid' => $cid, 'message' => $msg, 'peer' => $to]);
        $this->sendToUser($me, ['type' => 'message', 'message' => $msg, 'peer' => $to], $conn);
        $sender = $this->findUser($me);
        $this->sendToUser($to, [
            'type' => 'message', 'message' => $msg, 'peer' => $me,
            'from' => ['id' => $me, 'name' => $this->name($sender), 'avatar' => $sender['avatar']],
        ]);
    }

    private function handleTyping($state, $data) {
        $me = $state->user['id'];
        $to = (int)($data['to'] ?? 0);
        if ($to && $to !== $me && !empty($this->byUser[$to])) {
            $this->sendToUser($to, ['type' => 'typing', 'user_id' => $me, 'typing' => !empty($data['typing'])]);
        }
    }

    private function handleRead($state, $data) {
        $me = $state->user['id'];
        $with = (int)($data['with'] ?? 0);
        if (!$with || $with === $me) {
            return;
        }
        $res = $this->q(
            'UPDATE messages SET is_read = 1, read_at = NOW() WHERE sender_id = ? AND receiver_id = ? AND is_read = 0',
            [$with, $me]
        );
        if ($res->rowCount() > 0) {
            $this->q(
                'UPDATE conversations SET user1_unread = IF(user1_id = ?, 0, user1_unread), user2_unread = IF(user2_id = ?, 0, user2_unread)
                 WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)',
                [$me, $me, $me, $with, $with, $me]
            );
            $this->sendToUser($with, ['type' => 'read', 'by' => $me]);
        }
        $this->sendToUser($me, ['type' => 'read_ack', 'with' => $with]);
    }
}

$port = (int)(getenv('WS_PORT') ?: 8081);
$loop = \React\EventLoop\Loop::get();
$socket = new \React\Socket\SocketServer('127.0.0.1:' . $port, [], $loop);
$server = new IoServer(new HttpServer(new WsServer(new ChatServer($loop))), $socket, $loop);
echo "Chat server listening on 127.0.0.1:$port\n";
$server->run();
