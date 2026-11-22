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
    abort(404, 'We could not find that service.');
}
$progress = new ProgressService($pdo);

// Save the document checklist (US-05)
if (is_post()) {
    Csrf::check();
    $user = Auth::requireLogin();
    $ids = isset($_POST['doc_ids']) && is_array($_POST['doc_ids']) ? $_POST['doc_ids'] : [];
    $progress->saveDocumentChecks((int) $user['id'], (int) $service['id'], $ids);
    Flash::add('success', 'Your document checklist was saved.');
    redirect('service.php?id=' . (int) $service['id'] . '#documents');
}

$user = Auth::user();
if ($user) {
    $docs = $progress->documentProgress((int) $user['id'], (int) $service['id']);
} else {
    $docs = ProgressService::summarizeDocuments((new \PassGo\Repository\DocumentRepository($pdo))->forService((int) $service['id']), []);
}
$steps = (new GuideRepository($pdo))->steps((int) $service['id']);

page_header($service['name'], ['active' => 'search', 'back' => 'services.php']);
?>
<article>
    <p class="eyebrow"><?= e($service['category_name']) ?></p>
    <h1><?= e($service['name']) ?></h1>
    <p class="lead"><?= e($service['description']) ?></p>

    <div class="badges badges-lg">
        <?= badge('About ' . (int) $service['estimated_minutes'] . ' min', '', 'clock') ?>
        <?= $service['requires_appointment'] ? badge('Appointment needed', '', 'calendar') : badge('No appointment needed', 'ok', 'check') ?>
    </div>

    <div class="actions">
        <a class="btn btn-primary" href="<?= e(url('guide.php?id=' . (int) $service['id'])) ?>">Start guide <?= icon('chevron', 18) ?></a>
        <?php if ($service['requires_appointment']): ?>
            <a class="btn btn-secondary" href="<?= e(url('book.php?service=' . (int) $service['id'])) ?>"><?= icon('ticket', 18) ?> Book an appointment</a>
        <?php endif; ?>
    </div>

    <section class="panel" aria-labelledby="where-h">
        <h2 id="where-h">Where and when</h2>
        <p class="detail"><?= icon('landmark', 18) ?><span><strong><?= e($service['institution_name']) ?></strong></span></p>
        <p class="detail"><?= icon('pin', 18) ?><span><?= e($service['address']) ?>, <?= e($service['city']) ?></span></p>
        <p class="detail"><?= icon('clock', 18) ?><span><?= e($service['opening_hours']) ?></span></p>
        <?php if (!empty($service['phone'])): ?>
            <p class="detail"><?= icon('phone', 18) ?><span><a href="tel:<?= e(preg_replace('/[^+\d]/', '', $service['phone'])) ?>"><?= e($service['phone']) ?></a></span></p>
        <?php endif; ?>
    </section>

    <section class="panel" id="documents" aria-labelledby="docs-h">
        <h2 id="docs-h">Documents you need</h2>
        <?php if ($docs['required_total'] > 0): ?>
            <p class="progress-label" id="docs-count">
                <?php if ($user): ?>
                    <strong><?= (int) $docs['required_ready'] ?> of <?= (int) $docs['required_total'] ?></strong> required documents ready
                <?php else: ?>
                    <strong><?= (int) $docs['required_total'] ?></strong> required documents
                <?php endif; ?>
            </p>
            <?php if ($user): ?>
                <div class="progress" role="progressbar" aria-labelledby="docs-count" aria-valuemin="0" aria-valuemax="<?= (int) $docs['required_total'] ?>" aria-valuenow="<?= (int) $docs['required_ready'] ?>">
                    <span style="width: <?= (int) round($docs['required_ready'] * 100 / $docs['required_total']) ?>%"></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($user): ?>
            <form method="post" action="<?= e(url('service.php')) ?>" data-autosubmit>
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                <ul class="checklist">
                    <?php foreach ($docs['documents'] as $d): ?>
                        <li>
                            <label class="check">
                                <input type="checkbox" name="doc_ids[]" value="<?= (int) $d['id'] ?>"<?= $d['checked'] ? ' checked' : '' ?>>
                                <span class="check-box"><?= icon('check', 16) ?></span>
                                <span class="check-text">
                                    <span class="check-name"><?= e($d['name']) ?>
                                        <?= $d['is_required'] ? badge('Required', 'req') : badge('If applicable', 'muted') ?></span>
                                    <?php if (!empty($d['note'])): ?><span class="check-note"><?= e($d['note']) ?></span><?php endif; ?>
                                </span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <button class="btn btn-secondary btn-sm" type="submit">Save my checklist</button>
            </form>
        <?php else: ?>
            <ul class="checklist checklist-static">
                <?php foreach ($docs['documents'] as $d): ?>
                    <li>
                        <span class="check-icon"><?= icon('file', 18) ?></span>
                        <span class="check-text">
                            <span class="check-name"><?= e($d['name']) ?>
                                <?= $d['is_required'] ? badge('Required', 'req') : badge('If applicable', 'muted') ?></span>
                            <?php if (!empty($d['note'])): ?><span class="check-note"><?= e($d['note']) ?></span><?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="callout">
                <p>Log in to tick off the documents you already have.</p>
                <a class="btn btn-primary btn-sm" href="<?= e(url('login.php?next=' . rawurlencode('service.php?id=' . (int) $service['id']))) ?>">Log in</a>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel" aria-labelledby="steps-h">
        <h2 id="steps-h">How it works</h2>
        <ol class="step-preview">
            <?php foreach ($steps as $s): ?>
                <li><?= e($s['title']) ?></li>
            <?php endforeach; ?>
        </ol>
        <a class="btn btn-secondary btn-sm" href="<?= e(url('guide.php?id=' . (int) $service['id'])) ?>">Open the full guide</a>
    </section>
</article>
<?php page_footer();
