<?php
require 'config.php'; need('guard'); $gid = $_SESSION['u']['id']; $msg = ''; $ok = false; $now = date('Y-m-d H:i:s');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['checkout'])) {
        $pdo->prepare('UPDATE visit_logs SET check_out=? WHERE id=? AND check_out IS NULL')->execute([$now, $_POST['checkout']]);
        audit($pdo, $gid, 'CHECK_OUT', $_POST['checkout']);
        $s = $pdo->prepare('SELECT i.visitor_name, u.phone FROM visit_logs v JOIN invitations i ON i.id=v.invitation_id JOIN users u ON u.id=i.resident_id WHERE v.id=?');
        $s->execute([$_POST['checkout']]); if ($r = $s->fetch()) notify($pdo, $r['phone'], "GuardLink: {$r['visitor_name']} has left at " . date('H:i') . '.');
    } else [$ok, $msg] = verify_code($pdo, $_POST['code'], $gid, $now);
}
$inside = $pdo->query('SELECT v.id, v.check_in, i.visitor_name, i.visitor_id_no, u.name AS resident FROM visit_logs v JOIN invitations i ON i.id=v.invitation_id JOIN users u ON u.id=i.resident_id WHERE v.check_out IS NULL ORDER BY v.id DESC')->fetchAll();
// Expected-visitor list, saved on the device for offline use:
$e = $pdo->prepare("SELECT code, visitor_name AS name, visitor_id_no AS idno, valid_from AS vfrom, valid_to AS vto FROM invitations WHERE status='registered' AND valid_to>=?");
$e->execute([$now]); $expected = $e->fetchAll();
top('Gate verification'); ?>
<div id="msg"><?php if ($msg) echo '<p class="' . ($ok ? 'ok' : 'no') . '">' . h($msg) . '</p>'; ?></div>
<div id="net" class="warn" style="display:none"></div>
<form method="post" id="vf"><?= csrf() ?><label>Visitor entry code</label>
<input name="code" id="code" maxlength="6" inputmode="numeric" required autofocus>
<button>Verify &amp; check in</button> <button type="button" class="green" onclick="scan()">📷 Scan QR</button>
<div id="reader"></div></form>
<h3>Currently inside</h3>
<table><tr><th>Visitor</th><th>ID</th><th>Visiting</th><th>In at</th><th></th></tr>
<?php foreach ($inside as $r) { ?><tr><td><?= h($r['visitor_name']) ?></td><td><?= h($r['visitor_id_no']) ?></td><td><?= h($r['resident']) ?></td><td><?= h($r['check_in']) ?></td>
<td><form method="post" class="inline"><?= csrf() ?><button class="red" name="checkout" value="<?= $r['id'] ?>">Check out</button></form></td></tr><?php } ?></table>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
localStorage.setItem('gl_expected', JSON.stringify(<?= json_encode($expected) ?>));   // cache for offline
const queue = () => JSON.parse(localStorage.getItem('gl_queue') || '[]');
const pad = n => String(n).padStart(2, '0');
const nowStr = () => { const d = new Date(); return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`; };
function banner() {
  const n = queue().length, b = document.getElementById('net');
  b.style.display = (!navigator.onLine || n) ? 'block' : 'none';
  b.textContent = (navigator.onLine ? '' : 'OFFLINE MODE: check-ins are saved on this device. ') + (n ? n + ' check-in(s) waiting to sync.' : '');
}
function show(ok, t) { document.getElementById('msg').innerHTML = '<p class="' + (ok ? 'ok' : 'no') + '"></p>'; document.querySelector('#msg p').textContent = t; }
document.getElementById('vf').addEventListener('submit', e => {
  if (navigator.onLine) return;                       // online: normal submit to the server
  e.preventDefault();
  const code = document.getElementById('code').value.trim(), at = nowStr(), q = queue();
  const v = JSON.parse(localStorage.getItem('gl_expected') || '[]').find(x => x.code === code);
  if (!v) show(false, 'DENIED (offline): code not in the expected-visitor list');
  else if (q.some(x => x.code === code)) show(false, 'DENIED (offline): code already used');
  else if (at < v.vfrom || at > v.vto) show(false, 'DENIED (offline): outside the valid time');
  else { q.push({code, at}); localStorage.setItem('gl_queue', JSON.stringify(q)); show(true, 'APPROVED (offline): ' + v.name + ' (ID ' + v.idno + ')'); }
  document.getElementById('code').value = ''; banner();
});
async function sync() {                               // send saved check-ins when the internet is back
  const items = queue(); if (!items.length || !navigator.onLine) return;
  try { const r = await fetch('sync.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({csrf: CSRF, items})});
        if (r.ok) { localStorage.removeItem('gl_queue'); location.reload(); } } catch (err) {}
}
addEventListener('online', () => { banner(); sync(); }); addEventListener('offline', banner); banner(); sync();
let sc = null;
function scan() {
  const box = document.getElementById('reader');
  if (sc) { sc.stop().then(() => { sc = null; box.style.display = 'none'; }); return; }
  box.style.display = 'block'; sc = new Html5Qrcode('reader');
  sc.start({facingMode: 'environment'}, {fps: 10, qrbox: 200}, t => {
    document.getElementById('code').value = t; sc.stop().then(() => { sc = null; box.style.display = 'none'; document.getElementById('vf').requestSubmit(); });
  });
}
</script>
<?php bottom();
