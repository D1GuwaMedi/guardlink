<?php
// Included by every page: settings, database (auto-created), session, security + helper functions.
const DB_USER = 'root';          // XAMPP default
const DB_PORT = 3306;            // the port shown next to MySQL in the XAMPP Control Panel
const DB_PASS = '';              // XAMPP default (empty)
const AT_USER = 'sandbox';       // Africa's Talking username (optional)
const AT_KEY  = '';              // Africa's Talking API key. Leave empty = messages are only saved in the outbox.

session_start();
date_default_timezone_set('Africa/Nairobi');

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 5]);
} catch (PDOException $e) {
    exit('<h2>Cannot connect to MySQL</h2><p>1) Start MySQL in the XAMPP Control Panel (it must be green).<br>2) Check DB_PORT and DB_PASS at the top of config.php.</p><p>Details: ' . htmlspecialchars($e->getMessage()) . '</p>');
}
$pdo->exec('CREATE DATABASE IF NOT EXISTS guardlink');
$pdo->exec('USE guardlink');
if (!$pdo->query("SHOW TABLES LIKE 'notifications'")->fetch()) {         // first run (or half-built database): create all tables
    $pdo->exec(file_get_contents(__DIR__ . '/database.sql'));            // safe to repeat: every statement is IF NOT EXISTS
}
if (!$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()) {       // first run: demo accounts (password: pass123)
    $s = $pdo->prepare('INSERT INTO users(name,username,phone,password_hash,role) VALUES(?,?,?,?,?)');
    foreach ([['Mary Wanjiku','mary','0712345678','resident'], ['John Otieno','john','0722222222','resident'],
              ['Peter Kamau','guard1','0733333333','guard'], ['Hostel Manager','manager','0744444444','management']] as $u)
        $s->execute([$u[0], $u[1], $u[2], password_hash('pass123', PASSWORD_DEFAULT), $u[3]]);
}
// Invitations past their end time become "expired" automatically.
$pdo->prepare("UPDATE invitations SET status='expired' WHERE status IN('pending','registered') AND valid_to<?")->execute([date('Y-m-d H:i:s')]);

// ---- CSRF protection: every POST form must carry the session token ----
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf() { return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !defined('NO_CSRF') && !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    http_response_code(403); exit('Invalid form token. Go back, refresh the page and try again.');
}

// ---- helpers ----
function h($s) { return htmlspecialchars((string)$s); }                       // escape output (stops XSS)
function need($role) {                                                         // role-based access control
    if (empty($_SESSION['u']) || $_SESSION['u']['role'] !== $role) { header('Location: login.php'); exit; }
}
function audit($pdo, $uid, $action, $details = '') {                           // audit trail
    $pdo->prepare('INSERT INTO audit_log(user_id,action,details) VALUES(?,?,?)')->execute([$uid, $action, $details]);
}
function intl($p) { $p = preg_replace('/\D/', '', $p); return ($p !== '' && $p[0] === '0') ? '254' . substr($p, 1) : $p; }
function base_url() { return (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); }

function notify($pdo, $phone, $msg) {                                          // SMS: saved in outbox + sent if API key set
    $pdo->prepare('INSERT INTO notifications(to_phone,message) VALUES(?,?)')->execute([$phone, $msg]);
    if (AT_KEY !== '' && function_exists('curl_init')) {
        $ch = curl_init('https://api.africastalking.com/version1/messaging');
        curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['apiKey: ' . AT_KEY, 'Accept: application/json'],
            CURLOPT_POSTFIELDS => http_build_query(['username' => AT_USER, 'to' => '+' . intl($phone), 'message' => $msg])]);
        curl_exec($ch); curl_close($ch);
    }
}

// The core rule of GuardLink: is this code valid right now? Returns [approved?, message].
function verify_code($pdo, $code, $gid, $when) {
    $s = $pdo->prepare('SELECT i.*, u.phone AS rphone FROM invitations i JOIN users u ON u.id=i.resident_id WHERE code=?');
    $s->execute([trim($code)]); $i = $s->fetch();
    if (!$i) $r = [false, 'DENIED: code not found'];
    elseif (in_array($i['status'], ['used', 'cancelled'])) $r = [false, 'DENIED: invitation is ' . $i['status']];
    elseif ($i['visitor_id_no'] === null) $r = [false, 'DENIED: visitor has not registered yet'];
    elseif ($when < $i['valid_from'] || $when > $i['valid_to']) $r = [false, 'DENIED: outside the valid time (server time now ' . substr($when, 0, 16) . ', invitation valid ' . substr($i['valid_from'], 0, 16) . ' to ' . substr($i['valid_to'], 0, 16) . ')'];
    else {
        // WHERE status IN(...) makes this atomic: a code can only ever be used once.
        $u = $pdo->prepare("UPDATE invitations SET status='used' WHERE id=? AND status IN('registered','expired')");
        $u->execute([$i['id']]);
        if ($u->rowCount() === 1) {
            $pdo->prepare('INSERT INTO visit_logs(invitation_id,guard_id,check_in) VALUES(?,?,?)')->execute([$i['id'], $gid, $when]);
            notify($pdo, $i['rphone'], "GuardLink: {$i['visitor_name']} has entered at " . substr($when, 11, 5) . '.');
            $r = [true, "APPROVED: {$i['visitor_name']} (ID {$i['visitor_id_no']}) checked in"];
        } else $r = [false, 'DENIED: code already used'];
    }
    audit($pdo, $gid, $r[0] ? 'VERIFY_APPROVED' : 'VERIFY_DENIED', $code);
    return $r;
}

function top($t) { $u = $_SESSION['u'] ?? null; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($t) ?> - GuardLink</title><link rel="stylesheet" href="style.css">
<link rel="manifest" href="manifest.json"><meta name="theme-color" content="#1f4e79"></head><body>
<header><a href="index.html" class="brand">🛡️ GuardLink</a><?php if ($u) { ?><span><?= h($u['name']) ?> (<?= h($u['role']) ?>) · <a href="logout.php">Logout</a></span><?php } ?></header>
<main><h2><?= h($t) ?></h2>
<?php }
function bottom() { echo "</main><script>if('serviceWorker' in navigator)navigator.serviceWorker.register('sw.js');</script></body></html>"; }
