<?php
require __DIR__ . '/../src/bootstrap.php';

use PassGo\Auth;
use PassGo\Csrf;
use PassGo\Database;
use PassGo\Flash;
use PassGo\Service\AccountService;

if (Auth::user()) {
    redirect('index.php');
}

$next  = safe_next(input_str('next', is_post() ? 'POST' : 'GET'));
$email = '';
$error = '';

if (is_post()) {
    Csrf::check();
    $email = input_str('email', 'POST');
    $user = (new AccountService(Database::pdo()))->authenticate($email, isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '');
    if ($user) {
        Auth::login((int) $user['id']);
        Flash::add('success', 'Welcome back, ' . explode(' ', $user['name'])[0] . '.');
        redirect($next);
    }
    // Same message for unknown e-mail and wrong password.
    $error = 'Incorrect email or password.';
}

page_header('Log in', ['active' => 'profile']);
?>
<section class="auth-card">
    <h1>Log in</h1>
    <p class="lead">Track your documents and steps, and manage your queue tickets.</p>

    <?php if ($error !== ''): ?>
        <div class="flash flash-error" role="alert"><?= icon('info', 20) ?><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login.php')) ?>" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" inputmode="email" required>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Log in</button>
    </form>
    <p class="auth-alt">New here? <a href="<?= e(url('register.php?next=' . rawurlencode($next))) ?>">Create an account</a></p>
    <p class="auth-alt demo-note">Demo account: demo@passgo.pt / demo1234</p>
</section>
<?php page_footer();
