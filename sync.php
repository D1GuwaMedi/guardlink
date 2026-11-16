<?php
// Receives check-ins that the guard saved while offline and records them (same rules as online).
define('NO_CSRF', 1);
require 'config.php';
header('Content-Type: application/json');
$d = json_decode(file_get_contents('php://input'), true);
if (empty($_SESSION['u']) || $_SESSION['u']['role'] !== 'guard' || !$d || !hash_equals($_SESSION['csrf'], $d['csrf'] ?? '')) { http_response_code(403); exit('[]'); }
$out = [];
foreach ($d['items'] ?? [] as $it) {
    if (!preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $it['at'] ?? '')) continue;
    $out[] = verify_code($pdo, (string)$it['code'], $_SESSION['u']['id'], $it['at']);
}
echo json_encode($out);
