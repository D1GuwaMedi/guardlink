<?php
// Public page: the visitor opens the link the resident sent them.
require 'config.php';
function load($pdo) {
    $s = $pdo->prepare('SELECT i.*, u.name AS resident, u.phone AS rphone FROM invitations i JOIN users u ON u.id=i.resident_id WHERE token=?');
    $s->execute([$_GET['token'] ?? '']); return $s->fetch();
}
$inv = load($pdo);
if ($inv && $inv['status'] === 'pending' && $_SERVER['REQUEST_METHOD'] === 'POST' && trim($_POST['visitor_id_no']) !== '') {
    $pdo->prepare("UPDATE invitations SET visitor_name=?, visitor_id_no=?, status='registered' WHERE id=? AND status='pending'")
        ->execute([trim($_POST['visitor_name']), trim($_POST['visitor_id_no']), $inv['id']]);
    audit($pdo, null, 'VISITOR_REGISTERED', $inv['id']);
    notify($pdo, $inv['rphone'], 'GuardLink: ' . trim($_POST['visitor_name']) . ' has registered for your invitation.');
    $inv = load($pdo);
}
top('Visitor registration');
if (!$inv) echo '<p class="no">Invalid invitation link.</p>';
elseif ($inv['status'] === 'pending') { ?>
<p>You were invited by <b><?= h($inv['resident']) ?></b>.<br>Valid: <?= h($inv['valid_from']) ?> to <?= h($inv['valid_to']) ?></p>
<form method="post"><?= csrf() ?><label>Full name</label><input name="visitor_name" value="<?= h($inv['visitor_name']) ?>" required>
<label>ID / passport number</label><input name="visitor_id_no" required><button>Register</button></form>
<?php } elseif ($inv['status'] === 'registered') { ?>
<div class="ok">Registered! Show this entry pass at the gate.<h1><?= h($inv['code']) ?></h1><div id="qr"></div>
<p>Valid <?= h($inv['valid_from']) ?> to <?= h($inv['valid_to']) ?>. Works once.</p></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById('qr'), {text: <?= json_encode($inv['code']) ?>, width: 180, height: 180});</script>
<?php } else echo '<p class="no">This invitation is ' . h($inv['status']) . '.</p>';
bottom();
