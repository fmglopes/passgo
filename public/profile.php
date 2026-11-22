<?php
require __DIR__ . '/../src/bootstrap.php';

use PassGo\Auth;
use PassGo\Csrf;
use PassGo\Database;
use PassGo\Flash;
use PassGo\Service\AccountService;

$user  = Auth::requireLogin();
$error = '';
$name  = $user['name'];

// Edit name (US-14)
if (is_post()) {
    Csrf::check();
    $name  = input_str('name', 'POST');
    $error = (string) (new AccountService(Database::pdo()))->updateName((int) $user['id'], $name);
    if ($error === '') {
        Flash::add('success', 'Your name was updated.');
        redirect('profile.php');
    }
}

page_header('Profile', ['active' => 'profile']);
?>
<h1>Your profile</h1>

<section class="panel" aria-labelledby="acc-h">
    <h2 id="acc-h">Account</h2>
    <p class="detail"><?= icon('user', 18) ?><span>Signed in as <strong><?= e($user['email']) ?></strong></span></p>

    <form method="post" action="<?= e(url('profile.php')) ?>" novalidate>
        <?= Csrf::field() ?>
        <div class="field<?= $error !== '' ? ' has-error' : '' ?>">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" value="<?= e($name) ?>" autocomplete="name" required<?= $error !== '' ? ' aria-invalid="true" aria-describedby="err-name"' : '' ?>>
            <?php if ($error !== ''): ?><p class="field-error" id="err-name"><?= e($error) ?></p><?php endif; ?>
        </div>
        <button class="btn btn-secondary btn-sm" type="submit">Save name</button>
    </form>
</section>

<form method="post" action="<?= e(url('logout.php')) ?>">
    <?= Csrf::field() ?>
    <button class="btn btn-ghost" type="submit"><?= icon('logout', 18) ?> Log out</button>
</form>
<?php page_footer();
