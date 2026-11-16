<?php
require 'config.php'; need('resident'); $uid = $_SESSION['u']['id']; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel'])) {
        $pdo->prepare("UPDATE invitations SET status='cancelled' WHERE id=? AND resident_id=? AND status IN('pending','registered')")->execute([$_POST['cancel'], $uid]);
        audit($pdo, $uid, 'CANCEL_INVITE', $_POST['cancel']);
    } else {
        $from = str_replace('T', ' ', $_POST['valid_from']) . ':00';
        $to = str_replace('T', ' ', $_POST['valid_to']) . ':00';
        $phone = preg_replace('/[^0-9+]/', '', $_POST['visitor_phone']);
        if ($to <= $from) $err = 'The end time must be after the start time.';
        elseif (strlen($phone) < 9) $err = 'Enter a valid phone number.';
        else {
            $token = bin2hex(random_bytes(8));
            $pdo->prepare('INSERT INTO invitations(resident_id,visitor_name,visitor_phone,valid_from,valid_to,token,code) VALUES(?,?,?,?,?,?,?)')
                ->execute([$uid, trim($_POST['visitor_name']), $phone, $from, $to, $token, random_int(100000, 999999)]);
            audit($pdo, $uid, 'CREATE_INVITE', $_POST['visitor_name']);
            notify($pdo, $phone, $_SESSION['u']['name'] . ' invited you. Register here: ' . base_url() . '/register.php?token=' . $token);
        }
    }
    if (!$err) { header('Location: resident.php'); exit; }
}
$s = $pdo->prepare('SELECT * FROM invitations WHERE resident_id=? ORDER BY id DESC'); $s->execute([$uid]); $rows = $s->fetchAll();
top('My invitations');
if ($err) echo '<p class="no">' . h($err) . '</p>'; ?>
<p class="warn">Time on the server now: <b><?= date('Y-m-d H:i') ?></b>. The visitor can only be approved between "valid from" and "valid until".</p>
<form method="post"><?= csrf() ?><b>New invitation</b><br>
<label>Visitor name</label><input name="visitor_name" required>
<label>Visitor phone</label><input name="visitor_phone" placeholder="0712345678" required>
<label>Valid from</label><input type="datetime-local" name="valid_from" value="<?= date('Y-m-d\TH:i') ?>" required>
<label>Valid until</label><input type="datetime-local" name="valid_to" value="<?= date('Y-m-d\TH:i', strtotime('+3 hours')) ?>" required>
<button>Create &amp; send invitation</button></form>
<table><tr><th>Visitor</th><th>Valid</th><th>Status</th><th>Link / code</th><th></th></tr>
<?php foreach ($rows as $r) { $link = base_url() . '/register.php?token=' . $r['token']; ?><tr>
<td><?= h($r['visitor_name']) ?><br><?= h($r['visitor_phone']) ?></td>
<td><?= h($r['valid_from']) ?><br>to <?= h($r['valid_to']) ?></td><td><?= h($r['status']) ?></td>
<td><?php if ($r['status'] === 'pending') { ?><a class="btn green" target="_blank" href="https://wa.me/<?= intl($r['visitor_phone']) ?>?text=<?= rawurlencode('You are invited. Register here: ' . $link) ?>">Send on WhatsApp</a><br><small><?= h($link) ?></small>
<?php } elseif ($r['status'] === 'registered') echo 'Code: <b>' . h($r['code']) . '</b>'; ?></td>
<td><?php if (in_array($r['status'], ['pending', 'registered'])) { ?><form method="post" class="inline"><?= csrf() ?><button class="red" name="cancel" value="<?= $r['id'] ?>">Cancel</button></form><?php } ?></td>
</tr><?php } ?></table>
<?php bottom();
