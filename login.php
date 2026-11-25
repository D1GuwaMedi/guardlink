<?php
require 'config.php';
if (!empty($_SESSION['u'])) { header('Location: ' . $_SESSION['u']['role'] . '.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s = $pdo->prepare('SELECT * FROM users WHERE username=? AND active=1');   // prepared statement = no SQL injection
    $s->execute([trim($_POST['username'])]); $u = $s->fetch();
    if ($u && password_verify($_POST['password'], $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        $_SESSION['u'] = ['id' => $u['id'], 'name' => $u['name'], 'role' => $u['role']];
        audit($pdo, $u['id'], 'LOGIN');
        header('Location: ' . $u['role'] . '.php'); exit;                      // resident.php / guard.php / management.php
    }
    $err = 'Wrong username or password';
}
top('Login');
if ($err) echo '<p class="no">' . h($err) . '</p>'; ?>
<form method="post"><?= csrf() ?><label>Username</label><input name="username" required autofocus>
<label>Password</label><input type="password" name="password" required><button>Login</button></form>
<?php bottom();
