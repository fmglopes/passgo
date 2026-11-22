<?php
require __DIR__ . '/../src/bootstrap.php';

use PassGo\Auth;
use PassGo\Csrf;
use PassGo\Database;
use PassGo\Flash;
use PassGo\Repository\ServiceRepository;
use PassGo\Repository\SlotRepository;
use PassGo\Service\BookingException;
use PassGo\Service\BookingService;
use PassGo\Service\ProgressService;

$user = Auth::requireLogin();
$pdo  = Database::pdo();

$serviceId = input_int('service', is_post() ? 'POST' : 'GET');
$service = $serviceId !== null ? (new ServiceRepository($pdo))->find($serviceId) : null;
if (!$service) {
    abort(404, 'We could not find that service.');
}

// Confirm the booking (US-09)
if (is_post()) {
    Csrf::check();
    $slotId = input_int('slot_id', 'POST');
    if ($slotId === null) {
        Flash::add('error', 'Please choose a time slot first.');
        redirect('book.php?service=' . (int) $service['id']);
    }
    try {
        $appointmentId = (new BookingService($pdo))->book((int) $user['id'], (int) $service['id'], $slotId);
    } catch (BookingException $e) {
        Flash::add('error', $e->getMessage());
        redirect('book.php?service=' . (int) $service['id']);
    }
    Flash::add('success', 'Your ticket is booked.');
    redirect('appointment.php?id=' . $appointmentId);
}

// Show the available slots (US-08), grouped by day
$days = [];
if ($service['requires_appointment']) {
    foreach ((new SlotRepository($pdo))->availableFor((int) $service['id']) as $slot) {
        $key = fmt_day_key($slot['starts_at']);
        if (!isset($days[$key])) {
            $days[$key] = ['label' => to_datetime($slot['starts_at'])->format('l j F'), 'slots' => [], 'free' => 0];
        }
        $free = (int) $slot['capacity'] - (int) $slot['taken'];
        $slot['free'] = $free;
        $days[$key]['slots'][] = $slot;
        if ($free > 0) {
            $days[$key]['free']++;
        }
    }
}
$docs = (new ProgressService($pdo))->documentProgress((int) $user['id'], (int) $service['id']);

page_header('Book: ' . $service['name'], ['active' => 'appointments', 'back' => 'service.php?id=' . (int) $service['id'], 'heading' => 'Book a ticket']);
?>
<h1><?= e($service['name']) ?></h1>
<p class="lead"><?= e($service['institution_name']) ?> · <?= e($service['city']) ?></p>

<?php if (!$service['requires_appointment']): ?>
    <div class="empty-state">
        <h2>No appointment needed</h2>
        <p>You can simply walk in during opening hours: <?= e($service['opening_hours']) ?>.</p>
        <a class="btn btn-secondary" href="<?= e(url('service.php?id=' . (int) $service['id'])) ?>">Back to the service</a>
    </div>
<?php else: ?>
    <?php if ($docs['required_total'] > 0): ?>
        <div class="callout <?= $docs['all_ready'] ? 'callout-ok' : '' ?>">
            <p><?= icon($docs['all_ready'] ? 'check' : 'file', 18) ?>
                <span><strong><?= (int) $docs['required_ready'] ?> of <?= (int) $docs['required_total'] ?></strong> required documents ready.</span></p>
            <?php if (!$docs['all_ready']): ?>
                <a class="btn btn-secondary btn-sm" href="<?= e(url('service.php?id=' . (int) $service['id'] . '#documents')) ?>">Check my documents</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$days): ?>
        <div class="empty-state">
            <h2>No time slots available</h2>
            <p>There are no free slots in the next 14 days. Please try again later.</p>
        </div>
    <?php else: ?>
        <form method="post" action="<?= e(url('book.php')) ?>" class="booking">
            <?= Csrf::field() ?>
            <input type="hidden" name="service" value="<?= (int) $service['id'] ?>">
            <fieldset>
                <legend>Choose a day and time</legend>
                <?php $first = true; foreach ($days as $day): ?>
                    <details class="day"<?= $first ? ' open' : '' ?>>
                        <summary>
                            <span class="day-name"><?= e($day['label']) ?></span>
                            <span class="day-free"><?= $day['free'] > 0 ? (int) $day['free'] . ' times available' : 'Full' ?></span>
                        </summary>
                        <div class="slot-grid">
                            <?php foreach ($day['slots'] as $slot): ?>
                                <?php $full = $slot['free'] <= 0; ?>
                                <label class="slot<?= $full ? ' is-full' : '' ?>">
                                    <input type="radio" name="slot_id" value="<?= (int) $slot['id'] ?>"<?= $full ? ' disabled' : '' ?>>
                                    <span><?= e(fmt_time($slot['starts_at'])) ?><?= $full ? '<small>Full</small>' : '' ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php $first = false; endforeach; ?>
            </fieldset>
            <div class="actions">
                <button class="btn btn-primary" type="submit"><?= icon('ticket', 18) ?> Confirm booking</button>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>
<?php page_footer();
