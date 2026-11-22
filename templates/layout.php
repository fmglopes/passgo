<?php
use PassGo\Auth;
use PassGo\Flash;

/**
 * Opens the page: <head>, header, flash messages and <main>.
 * Options: 'active' (home|search|appointments|profile), 'back' (relative URL), 'heading' (title in top bar).
 */
function page_header(string $title, array $opts = []): void
{
    $active  = isset($opts['active']) ? $opts['active'] : '';
    $GLOBALS['passgo_active'] = $active; // remembered for the tab bar in page_footer()
    $back    = isset($opts['back']) ? $opts['back'] : null;
    $heading = isset($opts['heading']) ? $opts['heading'] : null;
    $user    = null;
    try {
        $user = Auth::user();
    } catch (\Throwable $e) {
        error_log('PASS GO: could not load user for header: ' . $e->getMessage());
    }
    $nav = [
        'home'         => ['index.php', 'Home', 'home'],
        'search'       => ['services.php', 'Services', 'search'],
        'appointments' => ['appointments.php', 'My appointments', 'calendar'],
        'profile'      => [$user ? 'profile.php' : 'login.php', $user ? 'Profile' : 'Log in', 'user'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FDF8F2">
    <title><?= e($title) ?> · PASS GO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="topbar">
    <div class="topbar-inner">
        <?php if ($back): ?>
            <a class="icon-btn" href="<?= e(url($back)) ?>" aria-label="Go back"><?= icon('back', 22) ?></a>
        <?php endif; ?>
        <?php if ($heading !== null): ?>
            <p class="topbar-title"><?= e($heading) ?></p>
        <?php else: ?>
            <a class="brand" href="<?= e(url('index.php')) ?>"><?= logo() ?></a>
        <?php endif; ?>
        <nav class="desktop-nav" aria-label="Main">
            <?php foreach ($nav as $key => $item): ?>
                <a href="<?= e(url($item[0])) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($item[1]) ?></a>
            <?php endforeach; ?>
        </nav>
        <a class="icon-btn profile-btn" href="<?= e(url($user ? 'profile.php' : 'login.php')) ?>" aria-label="<?= $user ? 'Your profile' : 'Log in' ?>"><?= icon('user', 22) ?></a>
    </div>
</header>
<main id="main" class="container">
<?php foreach (Flash::consume() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
        <?= icon($f['type'] === 'success' ? 'check' : 'info', 20) ?><span><?= e($f['message']) ?></span>
    </div>
<?php endforeach;
}

/** Closes <main>, adds the demo-data notice, the phone tab bar and the script. */
function page_footer(): void
{
    $active = isset($GLOBALS['passgo_active']) ? $GLOBALS['passgo_active'] : '';
    $user = null;
    try {
        $user = Auth::user();
    } catch (\Throwable $e) {
        // header already logged this
    }
    $nav = [
        'home'         => ['index.php', 'Home', 'home'],
        'search'       => ['services.php', 'Services', 'search'],
        'appointments' => ['appointments.php', 'Appointments', 'calendar'],
        'profile'      => [$user ? 'profile.php' : 'login.php', $user ? 'Profile' : 'Log in', 'user'],
    ];
    ?>
</main>
<footer class="site-footer">
    <p>PASS GO is a student project. Services, hours and documents shown are illustrative demo data: always confirm on the official website of each institution.</p>
</footer>
<nav class="tabbar" aria-label="Main">
    <?php foreach ($nav as $key => $item): ?>
        <a href="<?= e(url($item[0])) ?>" class="tab<?= $active === $key ? ' is-active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
            <?= icon($item[2], 24) ?><span><?= e($item[1]) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
<?php
}
