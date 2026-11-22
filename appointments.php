<?php
require __DIR__ . '/../src/bootstrap.php';

use PassGo\Auth;
use PassGo\Csrf;
use PassGo\Database;
use PassGo\Flash;
use PassGo\Repository\AppointmentRepository;
use PassGo\Service\BookingException;
use PassGo\Service\BookingService;

$user = Auth::requireLogin();
$pdo  = Database::pdo();
$repo = new AppointmentRepository($pdo);

// Cancel an appointment (US-11)
if (is_post()) {
    Csrf::check();
    $appointmentId = input_int('appointment_id', 'POST');
    try {
        if ($appointmentId === null) {
            throw new BookingException('We could not find that appointment.');
        }
        (new BookingService($pdo))->cancel((int) $user['id'], $appointmentId);
        Flash::add('success', 'Your appointment was cancelled and the place was released.');
    } catch (BookingException $e) {
        Flash::add('error', $e->getMessage());
    }
    redirect('appointments.php');
}

$repo->markPastCompleted((int) $user['id']);
$upcoming = $repo->listForUser((int) $user['id'], true);
$past     = $repo->listForUser((int) $user['id'], false);

page_header('My appointments', ['active' => 'appointments']);
?>
<h1>My appointments</h1>

<section aria-labelledby="up-h">
    <h2 id="up-h">Upcoming</h2>
    <?php if (!$upcoming): ?>
        <div class="empty-state">
            <h3>No upcoming appointments</h3>
            <p>Find a service and book your digital queue ticket.</p>
            <a class="btn btn-primary" href="<?= e(url('services.php')) ?>">Find a service</a>
        </div>
    <?php else: ?>
        <div class="list">
            <?php foreach ($upcoming as $a): ?>
                <div class="appt-item">
                    <?= appointment_card($a) ?>
                    <details class="confirm">
                        <summary>Cancel this appointment</summary>
                        <form method="post" action="<?= e(url('appointments.php')) ?>">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="appointment_id" value="<?= (int) $a['id'] ?>">
                            <p>Are you sure? Your place will be given to someone else.</p>
                            <button class="btn btn-danger btn-sm" type="submit">Yes, cancel appointment</button>
                        </form>
                    </details>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($past): ?>
<section aria-labelledby="past-h">
    <h2 id="past-h">Past and cancelled</h2>
    <div class="list">
        <?php foreach ($past as $a): ?>
            <?= appointment_card($a) ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php page_footer();
