<?php
/**
 * Admin Login
 */
require_once __DIR__ . '/../../includes/Config.php';
Config::load();
require_once __DIR__ . '/../../includes/AdminAuth.php';

// Already logged in — bounce to dashboard
if (AdminAuth::isLoggedIn()) {
    header('Location: /admin/index.php');
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? '/admin/index.php';

// Validate redirect target (must stay on the same host)
if (!preg_match('#^/admin/#', $redirect)) {
    $redirect = '/admin/index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']     ?? '';

    if (AdminAuth::login($username, $password)) {
        header('Location: ' . $redirect);
        exit;
    }

    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — CleanPlate Admin</title>
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <h1>CleanPlate</h1>
        <p class="login-sub">Admin Dashboard</p>

        <?php if ($error): ?>
            <div class="alert alert-danger">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/admin/login.php?redirect=<?= urlencode($redirect) ?>">
            <div class="form-row">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" autocomplete="username"
                       required autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">Sign in</button>
        </form>

        <p style="text-align:center;margin-top:1.5rem;font-size:.75rem;color:var(--color-text-muted);">
            <a href="/">← Back to CleanPlate</a>
        </p>
    </div>
</div>
</body>
</html>
