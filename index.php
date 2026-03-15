<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    enforce_session_timeout();
    redirect('/dashboard.php');
}

$pageTitle = page_title('Login');
$errors = [];

if (is_post()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please refresh the page and try again.';
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $errors = array_merge($errors, validate_required($_POST, [
        'username' => 'Username',
        'password' => 'Password',
    ]));

    if ($errors === []) {
        // Look up the account by username and verify the bcrypt hash securely.
        $statement = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user);
            set_flash('success', 'Login successful.');
            redirect('/dashboard.php');
        }

        $errors[] = 'Invalid username or password.';
    }
}

require __DIR__ . '/partials/header.php';
?>
<section class="auth-card">
    <div class="card auth-shell">
        <div class="auth-showcase">
            <div class="auth-copy">
                <span class="eyebrow">Campus Access Portal</span>
                <h2>Welcome back</h2>
                <p class="muted">Use your account credentials to access the student registration workspace.</p>
            </div>

            <div class="auth-highlights">
                <article class="auth-highlight">
                    <strong>Fast record access</strong>
                    <p>Review student information, uploaded documents, and enrollment details in one place.</p>
                </article>
                <article class="auth-highlight">
                    <strong>Admin-ready workflow</strong>
                    <p>Create student accounts, manage profiles, and keep school records organized.</p>
                </article>
            </div>
        </div>

        <div class="auth-panel">
            <?php if ($errors !== []): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

                <label>
                    Username
                    <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required>
                </label>

                <label>
                    Password
                    <input type="password" name="password" required>
                </label>

                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>

            <div class="login-hint">
                <p><strong>Sample admin username:</strong> admin</p>
                <p><strong>Sample password:</strong> Admin@2026</p>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
