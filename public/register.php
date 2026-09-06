<?php
/**
 * Register a new customer account.
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
$old = static fn(string $k): string => e(post($k));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = post('name');
    $email    = strtolower(post('email'));
    $pass     = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password2'] ?? '');

    if (mb_strlen($name) < 2)                 { $errors[] = 'Please enter your full name.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Please enter a valid e-mail address.'; }
    if (mb_strlen($pass) < 8)                 { $errors[] = 'Password must be at least 8 characters.'; }
    if ($pass !== $confirm)                   { $errors[] = 'Passwords do not match.'; }

    if ($errors === []) {
        $check = db()->prepare('SELECT 1 FROM users WHERE Email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'That e-mail is already registered — <a href="login.php">log in instead</a>.';
        }
    }

    if ($errors === []) {
        $stmt = db()->prepare('INSERT INTO users (Name, Email, Password, Role) VALUES (?, ?, ?, "Customer")');
        $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        $stmt = db()->prepare('SELECT * FROM users WHERE Email = ?');
        $stmt->execute([$email]);
        login_user($stmt->fetch());
        flash('success', 'Account created — welcome to ' . APP_NAME . '!');
        redirect($next);
    }
}

$pageTitle = 'Register';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <form method="post" action="register.php<?= $next !== 'index.php' ? '?next=' . urlencode($next) : '' ?>"
        class="auth-card" data-validate novalidate>
    <h1>Create an account</h1>
    <?php if ($errors): ?>
      <div class="flash flash-error"><ul><?php foreach ($errors as $er) { echo '<li>' . $er . '</li>'; } ?></ul></div>
    <?php endif; ?>

    <div class="field">
      <label for="name">Full name</label>
      <input type="text" id="name" name="name" value="<?= $old('name') ?>" required data-rules="required|min:2">
    </div>
    <div class="field">
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" value="<?= $old('email') ?>" required data-rules="required|email">
    </div>
    <div class="field">
      <label for="password">Password <span class="muted">(min 8 chars)</span></label>
      <input type="password" id="password" name="password" required data-rules="required|min:8">
    </div>
    <div class="field">
      <label for="password2">Confirm password</label>
      <input type="password" id="password2" name="password2" required data-rules="required|match:password">
    </div>
    <button type="submit" class="btn btn-dark btn-block">Register</button>

    <p class="auth-alt muted">Already have an account? <a href="login.php">Log in</a></p>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
