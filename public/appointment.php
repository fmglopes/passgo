<?php
require __DIR__ . '/../src/bootstrap.php';

use PassGo\Auth;
use PassGo\Csrf;
use PassGo\Database;
use PassGo\Repository\AppointmentRepository;
use PassGo\Service\ProgressService;

$user = Auth::requireLogin();
$pdo  = Database::pdo();
$repo = new AppointmentRepository($pdo);

$id = input_int('id');
$repo->markPastCompleted((int) $user['id']);
// Only the owner can open a ticket: the query filters on the logged-in user.
$a = $id !== null ? $repo->findForUser($id, (int) $user['id']) : null;
if (!$a) {
    abort(404, 'We could not find that appointment.');
}

$docs = (new ProgressService($pdo))->documentProgress((int) $user['id'], (int) $a['service_id']);
$canCancel = $a['status'] === 'booked' && $a['is_upcoming'];

page_header('Ticket ' . $a['ticket_code'], ['active' => 'appointments', 'back' => 'appointments.php', 'heading' => 'Your ticket']);
?>
<section class="ticket<?= $a['status'] !== 'booked' ? ' ticket-inactive' : '' ?>" aria-labelledby="ticket-h">
    <p class="eyebrow">Digital queue ticket</p>
    <h1 id="ticket-h"><?= e($a['service_name']) ?></h1>
    <p class="ticket-code" aria-label="Ticket code <?= e($a['ticket_code']) ?>"><?= e($a['ticket_code']) ?></p>
    <?= status_badge($a['status']) ?>
    <dl class="ticket-details">
        <div><dt>Date</dt><dd><?= e(to_datetime($a['starts_at'])->format('l j F Y')) ?></dd></div>
        <div><dt>Time</dt><dd><?= e(fmt_time($a['starts_at'])) ?></dd></div>
        <div><dt>Where</dt><dd><?= e($a['institution_name']) ?><br><?= e($a['address']) ?>, <?= e($a['city']) ?></dd></div>
        <div><dt>Opening hours</dt><dd><?= e($a['opening_hours']) ?></dd></div>
    </dl>
    <p class="ticket-tip">Arrive about 10 minutes early and show this code at the desk.</p>
</section>

<?php if ($canCancel && $docs['required_total'] > 0): ?>
    <section class="panel" aria-labelledby="remind-h">
        <h2 id="remind-h">Before you go</h2>
        <?php if ($docs['all_ready']): ?>
            <p class="detail"><?= icon('check', 18) ?><span>All required documents are ready.</span></p>
        <?php else: ?>
            <p><strong><?= count($docs['missing']) ?></strong> required <?= count($docs['missing']) === 1 ? 'document is' : 'documents are' ?> not ticked yet:</p>
            <ul class="plain-list">
                <?php foreach ($docs['missing'] as $m): ?><li><?= e($m['name']) ?></li><?php endforeach; ?>
            </ul>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('service.php?id=' . (int) $a['service_id'] . '#documents')) ?>">Open my checklist</a>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($canCancel): ?>
    <details class="confirm">
        <summary>Cancel this appointment</summary>
        <form method="post" action="<?= e(url('appointments.php')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="appointment_id" value="<?= (int) $a['id'] ?>">
            <p>Are you sure? Your place will be given to someone else.</p>
            <button class="btn btn-danger btn-sm" type="submit">Yes, cancel appointment</button>
        </form>
    </details>
<?php endif; ?>

<div class="actions">
    <a class="btn btn-secondary" href="<?= e(url('appointments.php')) ?>">All my appointments</a>
</div>
<?php page_footer();
