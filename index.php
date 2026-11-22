<?php
require __DIR__ . '/../src/bootstrap.php';

use PassGo\Auth;
use PassGo\Database;
use PassGo\Repository\AppointmentRepository;
use PassGo\Repository\ServiceRepository;

$pdo  = Database::pdo();
$user = Auth::user();
$popular = (new ServiceRepository($pdo))->popular(4);

$next = null;
if ($user) {
    $appointments = new AppointmentRepository($pdo);
    $appointments->markPastCompleted((int) $user['id']);
    $next = $appointments->nextUpcoming((int) $user['id']);
}

$tiles = [
    ['landmark', 'Public services',    'Find the right service',   'services.php'],
    ['file',     'Required documents', 'See what you need',        'services.php?go=documents'],
    ['list',     'Step-by-step guides', 'Follow the process',      'services.php?go=guide'],
    ['ticket',   'Queue tickets',      'Book and manage',          'appointments.php'],
];

page_header('Home', ['active' => 'home']);
?>
<section class="hero">
    <p class="eyebrow">Public services, simplified</p>
    <h1><?= $user ? 'Hello, ' . e(explode(' ', $user['name'])[0]) . '.' : 'Getting things done shouldn’t be this complicated.' ?></h1>
    <form class="search" action="<?= e(url('services.php')) ?>" method="get" role="search">
        <label class="sr-only" for="q">What do you need to do?</label>
        <?= icon('search', 22) ?>
        <input id="q" name="q" type="search" placeholder="What do you need to do?" autocomplete="off">
        <button class="btn btn-primary btn-sm" type="submit">Search</button>
    </form>
</section>

<section aria-labelledby="tiles-h">
    <h2 id="tiles-h" class="sr-only">Main areas</h2>
    <div class="tiles">
        <?php foreach ($tiles as $t): ?>
            <a class="card card-link tile" href="<?= e(url($t[3])) ?>">
                <span class="card-icon"><?= icon($t[0], 22) ?></span>
                <span class="card-body">
                    <span class="card-title"><?= e($t[1]) ?></span>
                    <span class="card-sub"><?= e($t[2]) ?></span>
                </span>
                <span class="card-chevron"><?= icon('chevron', 20) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section aria-labelledby="next-h">
    <h2 id="next-h">Your next appointment</h2>
    <?php if ($next): ?>
        <?= appointment_card($next, false) ?>
    <?php elseif ($user): ?>
        <div class="callout">
            <p>You have no upcoming appointments.</p>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('services.php')) ?>">Find a service</a>
        </div>
    <?php else: ?>
        <div class="callout">
            <p>Log in to track your documents and steps, and to book queue tickets.</p>
            <a class="btn btn-primary btn-sm" href="<?= e(url('login.php')) ?>">Log in</a>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('register.php')) ?>">Create account</a>
        </div>
    <?php endif; ?>
</section>

<section aria-labelledby="pop-h">
    <h2 id="pop-h">Popular services</h2>
    <div class="list">
        <?php foreach ($popular as $s): ?>
            <?= service_card($s, url('service.php?id=' . (int) $s['id'])) ?>
        <?php endforeach; ?>
    </div>
    <p class="more"><a href="<?= e(url('services.php')) ?>">See all services</a></p>
</section>
<?php page_footer();
