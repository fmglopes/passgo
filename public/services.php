<?php
require __DIR__ . '/../src/bootstrap.php';

use PassGo\Database;
use PassGo\Repository\ServiceRepository;

$repo = new ServiceRepository(Database::pdo());

$q   = input_str('q');
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}
$cat = input_int('category');
$go  = input_str('go');
if (!in_array($go, ['documents', 'guide'], true)) {
    $go = '';
}

$categories = $repo->categories();
$results = $repo->search($q, $cat);

// Where each card leads depends on what the user came to do.
function service_target(int $id, string $go): string
{
    if ($go === 'documents') {
        return url('service.php?id=' . $id . '#documents');
    }
    if ($go === 'guide') {
        return url('guide.php?id=' . $id);
    }
    return url('service.php?id=' . $id);
}

// Keep the current search when switching category.
function services_link(string $q, ?int $cat, string $go): string
{
    $params = [];
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($cat !== null) {
        $params['category'] = $cat;
    }
    if ($go !== '') {
        $params['go'] = $go;
    }
    return url('services.php') . ($params ? '?' . http_build_query($params) : '');
}

$hint = [
    'documents' => 'Choose a service to see which documents you need.',
    'guide'     => 'Choose a service to open its step-by-step guide.',
][$go] ?? '';

page_header('Services', ['active' => 'search']);
?>
<h1>Public services</h1>
<?php if ($hint !== ''): ?>
    <p class="lead"><?= e($hint) ?></p>
<?php endif; ?>

<form class="search" action="<?= e(url('services.php')) ?>" method="get" role="search">
    <label class="sr-only" for="q">Search services</label>
    <?= icon('search', 22) ?>
    <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Search, e.g. passport" autocomplete="off">
    <?php if ($cat !== null): ?><input type="hidden" name="category" value="<?= (int) $cat ?>"><?php endif; ?>
    <?php if ($go !== ''): ?><input type="hidden" name="go" value="<?= e($go) ?>"><?php endif; ?>
    <button class="btn btn-primary btn-sm" type="submit">Search</button>
</form>

<nav class="chips" aria-label="Categories">
    <a class="chip<?= $cat === null ? ' is-active' : '' ?>" href="<?= e(services_link($q, null, $go)) ?>"<?= $cat === null ? ' aria-current="true"' : '' ?>>All</a>
    <?php foreach ($categories as $c): ?>
        <a class="chip<?= $cat === (int) $c['id'] ? ' is-active' : '' ?>" href="<?= e(services_link($q, (int) $c['id'], $go)) ?>"<?= $cat === (int) $c['id'] ? ' aria-current="true"' : '' ?>><?= e($c['name']) ?></a>
    <?php endforeach; ?>
</nav>

<p class="result-count" role="status">
    <?= count($results) ?> <?= count($results) === 1 ? 'service' : 'services' ?> found<?= $q !== '' ? ' for “' . e($q) . '”' : '' ?>
</p>

<?php if (!$results): ?>
    <div class="empty-state">
        <h2>No services found</h2>
        <p>Try a different word, or remove the category filter.</p>
        <a class="btn btn-secondary" href="<?= e(url('services.php')) ?>">Show all services</a>
    </div>
<?php else: ?>
    <div class="list list-grid">
        <?php foreach ($results as $s): ?>
            <?= service_card($s, service_target((int) $s['id'], $go)) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php page_footer();
