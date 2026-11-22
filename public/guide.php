<?php
require __DIR__ . '/../src/bootstrap.php';

use PassGo\Auth;
use PassGo\Csrf;
use PassGo\Database;
use PassGo\Flash;
use PassGo\Repository\GuideRepository;
use PassGo\Repository\ServiceRepository;
use PassGo\Service\ProgressService;

$pdo = Database::pdo();
$id  = input_int('id', is_post() ? 'POST' : 'GET');
$service = $id !== null ? (new ServiceRepository($pdo))->find($id) : null;
if (!$service) {
    abort(404, 'We could not find that guide.');
}
$progress = new ProgressService($pdo);

// Mark a step done / not done (US-07)
if (is_post()) {
    Csrf::check();
    $user = Auth::requireLogin();
    $stepId = input_int('step_id', 'POST');
    $done = input_str('done', 'POST') === '1';
    if ($stepId === null || !$progress->setStepDone((int) $user['id'], (int) $service['id'], $stepId, $done)) {
        abort(404, 'We could not find that step.');
    }
    redirect('guide.php?id=' . (int) $service['id'] . '#step-' . $stepId);
}

$user = Auth::user();
if ($user) {
    $summary = $progress->stepProgress((int) $user['id'], (int) $service['id']);
} else {
    $summary = ProgressService::summarizeSteps((new GuideRepository($pdo))->steps((int) $service['id']), []);
}

page_header('Guide: ' . $service['name'], ['active' => 'search', 'back' => 'service.php?id=' . (int) $service['id'], 'heading' => $service['name']]);
?>
<h1>Step-by-step guide</h1>
<p class="lead"><?= e($service['summary']) ?></p>

<div class="badges badges-lg">
    <?= badge('About ' . (int) $service['estimated_minutes'] . ' min at the service', '', 'clock') ?>
    <?= badge(count($summary['steps']) . ' steps', '', 'list') ?>
</div>

<?php if ($user): ?>
    <p class="progress-label" id="steps-count"><strong><?= (int) $summary['done'] ?> of <?= (int) $summary['total'] ?></strong> steps done</p>
    <div class="progress" role="progressbar" aria-labelledby="steps-count" aria-valuemin="0" aria-valuemax="<?= (int) $summary['total'] ?>" aria-valuenow="<?= (int) $summary['done'] ?>">
        <span style="width: <?= (int) $summary['percent'] ?>%"></span>
    </div>
    <?php if ($summary['complete']): ?>
        <div class="flash flash-success"><?= icon('check', 20) ?><span>All steps done. You are ready!</span></div>
    <?php endif; ?>
<?php else: ?>
    <div class="callout">
        <p>Log in to save your progress and pick up where you left off.</p>
        <a class="btn btn-primary btn-sm" href="<?= e(url('login.php?next=' . rawurlencode('guide.php?id=' . (int) $service['id']))) ?>">Log in</a>
    </div>
<?php endif; ?>

<ol class="steps">
    <?php foreach ($summary['steps'] as $s): ?>
        <li id="step-<?= (int) $s['id'] ?>" class="step<?= $s['done'] ? ' is-done' : '' ?><?= $s['is_next'] && $user ? ' is-next' : '' ?>">
            <span class="step-num" aria-hidden="true"><?= $s['done'] ? icon('check', 18) : (int) $s['position'] ?></span>
            <div class="step-body">
                <h2 class="step-title">
                    <?= e($s['title']) ?>
                    <?php if ($user && $s['is_next']): ?><?= badge('Next', 'req') ?><?php endif; ?>
                    <?php if ($s['done']): ?><span class="sr-only">(done)</span><?php endif; ?>
                </h2>
                <p><?= e($s['description']) ?></p>
                <?php if ($user): ?>
                    <form method="post" action="<?= e(url('guide.php')) ?>">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                        <input type="hidden" name="step_id" value="<?= (int) $s['id'] ?>">
                        <input type="hidden" name="done" value="<?= $s['done'] ? '0' : '1' ?>">
                        <button class="btn <?= $s['done'] ? 'btn-ghost' : 'btn-secondary' ?> btn-sm" type="submit">
                            <?= $s['done'] ? 'Mark as not done' : 'Mark as done' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
</ol>

<div class="actions">
    <?php if ($service['requires_appointment']): ?>
        <a class="btn btn-primary" href="<?= e(url('book.php?service=' . (int) $service['id'])) ?>"><?= icon('ticket', 18) ?> Book an appointment</a>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(url('service.php?id=' . (int) $service['id'] . '#documents')) ?>">Check documents</a>
</div>
<?php page_footer();
