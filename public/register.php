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

$next   = safe_next(input_str('next', is_post() ? 'POST' : 'GET'));
$name   = '';
$email  = '';
$errors = [];

if (is_post()) {
    Csrf::check();
    $name     = input_str('name', 'POST');
    $email    = input_str('email', 'POST');
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $confirm  = isset($_POST['confirm']) && is_string($_POST['confirm']) ? $_POST['confirm'] : '';

    $result = (new AccountService(Database::pdo()))->register($name, $email, $password, $confirm);
    if (!$result['errors']) {
        Auth::login((int) $result['user_id']);
        Flash::add('success', 'Your account was created. Welcome to PASS GO!');
        redirect($next);
    }
    $errors = $result['errors'];
}

page_header('Create account', ['active' => 'profile']);
?>
<section class="auth-card">
    <h1>Create your account</h1>
    <p class="lead">It takes less than a minute.</p>

    <?php if ($errors): ?>
        <div class="flash flash-error" role="alert"><?= icon('info', 20) ?><span>Please fix the highlighted fields.</span></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('register.php')) ?>" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" value="<?= e($name) ?>" autocomplete="name" required<?= describedby($errors, 'name') ?>>
            <?= field_error($errors, 'name') ?>
        </div>
        <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" inputmode="email" required<?= describedby($errors, 'email') ?>>
            <?= field_error($errors, 'email') ?>
        </div>
        <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required<?= describedby($errors, 'password') ?>>
            <p class="hint">At least 8 characters.</p>
            <?= field_error($errors, 'password') ?>
        </div>
        <div class="field<?= isset($errors['confirm']) ? ' has-error' : '' ?>">
            <label for="confirm">Repeat password</label>
            <input id="confirm" name="confirm" type="password" autocomplete="new-password" required<?= describedby($errors, 'confirm') ?>>
            <?= field_error($errors, 'confirm') ?>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>
    <p class="auth-alt">Already have an account? <a href="<?= e(url('login.php?next=' . rawurlencode($next))) ?>">Log in</a></p>
</section>
<?php page_footer();
