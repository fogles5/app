<?php
// Legacy messaging URLs redirect to the real-time messages page.
$id = (int)($_GET['user'] ?? $_GET['to'] ?? $_GET['with'] ?? $_GET['user_id'] ?? 0);
header('Location: /messages.php' . ($id > 0 ? '?with=' . $id : ''), true, 302);
exit();
