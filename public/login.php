<?php
/**
 * Login.
 */
require_once __DIR__ . '/includes/init.php';

$next = get('next', 'index.php');
if (preg_match('#^[a-z]+://#i', $next)) {
    $next = 'index.php';
}
if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = post('email');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both your e-mail and password.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE Email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['Password'])) {
            login_user($row);
            flash('success', 'Welcome back, ' . explode(' ', $row['Name'])[0] . '!');
            redirect($next);
        }
        $errors[] = 'Invalid e-mail or password.';
    }
}

$pageTitle = 'Log in';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <form method="post" action="login.php?next=<?= urlencode($next) ?>" class="auth-card" data-validate novalidate>
    <h1>Log in</h1>
    <?php if ($errors): ?>
      <div class="flash flash-error"><ul><?php foreach ($errors as $er) { echo '<li>' . e($er) . '</li>'; } ?></ul></div>
    <?php endif; ?>

    <div class="field">
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" value="<?= e($email) ?>" required data-rules="required|email">
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required data-rules="required">
    </div>
    <button type="submit" class="btn btn-dark btn-block">Log in</button>

    <p class="auth-alt muted">New here? <a href="register.php<?= $next !== 'index.php' ? '?next=' . urlencode($next) : '' ?>">Create an account</a></p>

    <details class="demo-creds">
      <summary>Demo accounts</summary>
      <ul>
        <li><b>Customer:</b> demo@booknest.com / Password1!</li>
        <li><b>Admin:</b> admin@booknest.com / Admin123!</li>
      </ul>
    </details>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
