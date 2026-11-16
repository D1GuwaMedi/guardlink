<?php
require 'config.php'; need('management'); $me = $_SESSION['u']['id']; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle'])) {
        $pdo->prepare('UPDATE users SET active=1-active WHERE id=? AND id<>?')->execute([$_POST['toggle'], $me]);
        audit($pdo, $me, 'TOGGLE_USER', $_POST['toggle']);
    } elseif (strlen($_POST['password']) < 6) $err = 'Password must be at least 6 characters.';
    elseif (!in_array($_POST['role'], ['resident', 'guard', 'management'])) $err = 'Invalid role.';
    else try {
        $pdo->prepare('INSERT INTO users(name,username,phone,password_hash,role) VALUES(?,?,?,?,?)')
            ->execute([trim($_POST['name']), trim($_POST['username']), trim($_POST['phone']), password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['role']]);
        audit($pdo, $me, 'ADD_USER', $_POST['username']);
    } catch (PDOException $e) { $err = 'That username already exists.'; }
    if (!$err) { header('Location: management.php'); exit; }
}
$n = fn($sql) => $pdo->query($sql)->fetchColumn();
$inside = $pdo->query('SELECT i.visitor_name, u.name AS resident, v.check_in FROM visit_logs v JOIN invitations i ON i.id=v.invitation_id JOIN users u ON u.id=i.resident_id WHERE v.check_out IS NULL')->fetchAll();
$visits = $pdo->query('SELECT v.check_in, v.check_out, i.visitor_name, i.visitor_id_no, u.name AS resident FROM visit_logs v JOIN invitations i ON i.id=v.invitation_id JOIN users u ON u.id=i.resident_id ORDER BY v.id DESC LIMIT 50')->fetchAll();
$peak = $pdo->query('SELECT HOUR(check_in) AS hr, COUNT(*) AS c FROM visit_logs GROUP BY hr ORDER BY c DESC LIMIT 5')->fetchAll();
$users = $pdo->query('SELECT * FROM users ORDER BY role, name')->fetchAll();
$sms = $pdo->query('SELECT * FROM notifications ORDER BY id DESC LIMIT 15')->fetchAll();
$audit = $pdo->query('SELECT a.created_at, a.action, a.details, u.name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 30')->fetchAll();
top('Management reports');
if ($err) echo '<p class="no">' . h($err) . '</p>'; ?>
<div class="grid">
<div class="card"><h3><?= $n('SELECT COUNT(*) FROM visit_logs WHERE check_out IS NULL') ?></h3>Inside now</div>
<div class="card"><h3><?= $n('SELECT COUNT(*) FROM visit_logs') ?></h3>Total visits</div>
<div class="card"><h3><?= $n('SELECT COUNT(*) FROM invitations') ?></h3>Invitations</div></div>

<h3>Who is inside now (use for evacuations)</h3>
<table><tr><th>Visitor</th><th>Visiting</th><th>In at</th></tr>
<?php foreach ($inside as $r) { ?><tr><td><?= h($r['visitor_name']) ?></td><td><?= h($r['resident']) ?></td><td><?= h($r['check_in']) ?></td></tr><?php } ?></table>

<h3>Visit history <a class="btn green" href="export.php">Download CSV</a></h3>
<table><tr><th>Visitor</th><th>ID</th><th>Visited</th><th>In</th><th>Out</th></tr>
<?php foreach ($visits as $r) { ?><tr><td><?= h($r['visitor_name']) ?></td><td><?= h($r['visitor_id_no']) ?></td><td><?= h($r['resident']) ?></td><td><?= h($r['check_in']) ?></td><td><?= h($r['check_out'] ?? 'still inside') ?></td></tr><?php } ?></table>

<h3>Peak hours (for guard staffing)</h3>
<table><tr><th>Hour of day</th><th>Check-ins</th></tr>
<?php foreach ($peak as $r) { ?><tr><td><?= sprintf('%02d:00', $r['hr']) ?></td><td><?= $r['c'] ?></td></tr><?php } ?></table>

<h3>User accounts</h3>
<form method="post"><?= csrf() ?><label>Full name</label><input name="name" required><label>Username</label><input name="username" required>
<label>Phone</label><input name="phone" required><label>Password (min 6)</label><input type="password" name="password" required>
<label>Role</label><select name="role"><option>resident</option><option>guard</option><option>management</option></select><button>Add user</button></form>
<table><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th></th></tr>
<?php foreach ($users as $u) { ?><tr><td><?= h($u['name']) ?></td><td><?= h($u['username']) ?></td><td><?= h($u['role']) ?></td><td><?= $u['active'] ? 'active' : 'disabled' ?></td>
<td><?php if ($u['id'] != $me) { ?><form method="post" class="inline"><?= csrf() ?><button name="toggle" value="<?= $u['id'] ?>"><?= $u['active'] ? 'Disable' : 'Enable' ?></button></form><?php } ?></td></tr><?php } ?></table>

<h3>SMS outbox (latest 15)</h3>
<table><tr><th>Time</th><th>To</th><th>Message</th></tr>
<?php foreach ($sms as $r) { ?><tr><td><?= h($r['created_at']) ?></td><td><?= h($r['to_phone']) ?></td><td><?= h($r['message']) ?></td></tr><?php } ?></table>

<h3>Audit trail</h3>
<table><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr>
<?php foreach ($audit as $r) { ?><tr><td><?= h($r['created_at']) ?></td><td><?= h($r['name'] ?? 'visitor') ?></td><td><?= h($r['action']) ?></td><td><?= h($r['details']) ?></td></tr><?php } ?></table>
<?php bottom();
