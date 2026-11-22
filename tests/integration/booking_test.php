<?php
// Integration tests: booking rules against the real PostgreSQL test database (US-08, US-09, US-10, US-11).

use PassGo\Database;
use PassGo\Repository\AppointmentRepository;
use PassGo\Repository\SlotRepository;
use PassGo\Service\BookingException;
use PassGo\Service\BookingService;

section('Booking rules (US-09)');

test('books a free slot and creates a ticket', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Apply for a passport');
    $user = make_user();
    $slot = make_slot(institution_of_service($serviceId));
    $id = (new BookingService($pdo))->book($user, $serviceId, $slot);
    $appt = (new AppointmentRepository($pdo))->findForUser($id, $user);
    assertSame('booked', $appt['status']);
    assertMatches('/^PG-[A-Z2-9]{6}$/', $appt['ticket_code']);
    assertSame('Apply for a passport', $appt['service_name']);
    assertTrue($appt['is_upcoming']);
});

test('a slot cannot be overbooked: capacity is enforced', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Change your tax address');
    $slot = make_slot(institution_of_service($serviceId), 48, 1); // one place only
    $svc = new BookingService($pdo);
    $svc->book(make_user(), $serviceId, $slot);
    $second = make_user();
    assertThrows(BookingException::class, function () use ($svc, $second, $serviceId, $slot) {
        $svc->book($second, $serviceId, $slot);
    }, 'full');
});

test('a user cannot have two active appointments for the same service', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Renew your Citizen Card');
    $inst = institution_of_service($serviceId);
    $user = make_user();
    $svc = new BookingService($pdo);
    $svc->book($user, $serviceId, make_slot($inst, 50));
    assertThrows(BookingException::class, function () use ($svc, $user, $serviceId, $inst) {
        $svc->book($user, $serviceId, make_slot($inst, 52));
    }, 'already have an active appointment');
});

test('a user cannot book the same slot twice', function () {
    $pdo = test_pdo();
    $a = service_id_by_name('Apply for a passport');       // same institution as the Citizen Card
    $b = service_id_by_name('Renew your Citizen Card');
    $user = make_user();
    $slot = make_slot(institution_of_service($a), 60, 5);
    $svc = new BookingService($pdo);
    $svc->book($user, $a, $slot);
    assertThrows(BookingException::class, function () use ($svc, $user, $b, $slot) {
        $svc->book($user, $b, $slot);
    }, 'already have an appointment in this time slot');
});

test('past slots cannot be booked', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Apply for a passport');
    $slot = make_slot(institution_of_service($serviceId), -3);
    assertThrows(BookingException::class, function () use ($pdo, $serviceId, $slot) {
        (new BookingService($pdo))->book(make_user(), $serviceId, $slot);
    }, 'passed');
});

test('a slot of another institution cannot be used for the service', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Apply for a passport');            // Loja do Cidadão (1)
    $otherSlot = make_slot(institution_of_service(service_id_by_name('Change your tax address')));
    assertThrows(BookingException::class, function () use ($pdo, $serviceId, $otherSlot) {
        (new BookingService($pdo))->book(make_user(), $serviceId, $otherSlot);
    }, 'not available');
});

test('unknown slot and unknown service are rejected cleanly', function () {
    $pdo = test_pdo();
    $svc = new BookingService($pdo);
    $user = make_user();
    $serviceId = service_id_by_name('Apply for a passport');
    assertThrows(BookingException::class, function () use ($svc, $user, $serviceId) {
        $svc->book($user, $serviceId, 999999999);
    });
    assertThrows(BookingException::class, function () use ($svc, $user) {
        $svc->book($user, 999999999, 1);
    });
});

test('a service that needs no appointment cannot be booked', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Residence certificate');
    $slot = make_slot(institution_of_service($serviceId));
    assertThrows(BookingException::class, function () use ($pdo, $serviceId, $slot) {
        (new BookingService($pdo))->book(make_user(), $serviceId, $slot);
    }, 'does not need an appointment');
});

test('a failed booking leaves no appointment behind (transaction rolled back)', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Apply for a passport');
    $user = make_user();
    try {
        (new BookingService($pdo))->book($user, $serviceId, make_slot(institution_of_service($serviceId), -5));
    } catch (BookingException $e) {
    }
    assertFalse($pdo->inTransaction(), 'transaction must be closed');
    $st = $pdo->prepare('SELECT count(*) FROM appointments WHERE user_id = :u');
    $st->execute([':u' => $user]);
    assertSame(0, (int) $st->fetchColumn());
});

section('Booking concurrency (US-09)');

test('booking locks the slot row: a second connection has to wait', function () {
    // Connection A: starts a booking transaction and holds the slot lock.
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Change your tax address');
    $slot = make_slot(institution_of_service($serviceId), 70, 1);
    $pdo->beginTransaction();
    (new SlotRepository($pdo))->lock($slot);

    // Connection B: a different PDO connection that gives up after 300 ms.
    $c = config('db');
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $c['host'], (int) $c['port'], $c['name']);
    $other = new PDO($dsn, $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $other->exec("SET lock_timeout = '300ms'");
    $user = make_user();
    $e = null;
    try {
        (new BookingService($other))->book($user, $serviceId, $slot);
    } catch (PDOException $ex) {
        $e = $ex;
    }
    $pdo->rollBack();
    assertTrue($e !== null, 'the second booking should have been blocked by the row lock');
    assertSame('55P03', $e->getCode(), 'expected a lock timeout');
});

section('Database safety net');

test('the database itself refuses a second active appointment for the same service', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Apply for a passport');
    $inst = institution_of_service($serviceId);
    $user = make_user();
    $repo = new AppointmentRepository($pdo);
    $repo->create($user, $serviceId, make_slot($inst, 80), 'PG-AAAAA2');
    $e = assertThrows(PDOException::class, function () use ($repo, $user, $serviceId, $inst) {
        $repo->create($user, $serviceId, make_slot($inst, 82), 'PG-AAAAA3');
    });
    assertSame('23505', $e->getCode());
});

test('ticket codes are unique in the database', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Apply for a passport');
    $inst = institution_of_service($serviceId);
    $repo = new AppointmentRepository($pdo);
    $repo->create(make_user(), $serviceId, make_slot($inst, 84), 'PG-DUPE22');
    $e = assertThrows(PDOException::class, function () use ($repo, $serviceId, $inst) {
        $repo->create(make_user(), $serviceId, make_slot($inst, 86), 'PG-DUPE22');
    });
    assertSame('23505', $e->getCode());
});

section('Available slots (US-08)');

test('lists only future slots of the service institution, with free places', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Renew your driving licence');
    $inst = institution_of_service($serviceId);
    $past = make_slot($inst, -2);
    $soon = make_slot($inst, 5, 1);
    $far  = make_slot($inst, 24 * 30);               // beyond the 14-day window
    $otherInst = make_slot(institution_of_service(service_id_by_name('Change your tax address')), 5);
    (new BookingService($pdo))->book(make_user(), $serviceId, $soon);

    $slots = (new SlotRepository($pdo))->availableFor($serviceId);
    $byId = [];
    foreach ($slots as $s) {
        $byId[(int) $s['id']] = $s;
    }
    assertFalse(isset($byId[$past]), 'past slot must not be listed');
    assertFalse(isset($byId[$far]), 'slot beyond 14 days must not be listed');
    assertFalse(isset($byId[$otherInst]), 'slot of another institution must not be listed');
    assertTrue(isset($byId[$soon]), 'future slot should be listed');
    assertSame(1, (int) $byId[$soon]['taken']);
    assertSame(1, (int) $byId[$soon]['capacity']);
});

section('Cancelling and history (US-10, US-11)');

test('cancelling frees the place for someone else', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Change your tax address');
    $slot = make_slot(institution_of_service($serviceId), 90, 1);
    $svc = new BookingService($pdo);
    $first = make_user();
    $second = make_user();
    $id = $svc->book($first, $serviceId, $slot);
    $svc->cancel($first, $id);
    assertSame('cancelled', appointment_status($id));
    // the freed place can now be booked by someone else
    $id2 = $svc->book($second, $serviceId, $slot);
    assertSame('booked', appointment_status($id2));
});

test('after cancelling, the user can book the same service again', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Apply for a passport');
    $inst = institution_of_service($serviceId);
    $user = make_user();
    $svc = new BookingService($pdo);
    $id = $svc->book($user, $serviceId, make_slot($inst, 92));
    $svc->cancel($user, $id);
    $again = $svc->book($user, $serviceId, make_slot($inst, 94));
    assertTrue($again > $id);
});

test("a user cannot cancel someone else's appointment", function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Apply for a passport');
    $owner = make_user();
    $intruder = make_user();
    $svc = new BookingService($pdo);
    $id = $svc->book($owner, $serviceId, make_slot(institution_of_service($serviceId), 96));
    assertThrows(BookingException::class, function () use ($svc, $intruder, $id) {
        $svc->cancel($intruder, $id);
    }, 'could not find');
    assertSame('booked', appointment_status($id));
});

test('an appointment cannot be cancelled twice', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Apply for a passport');
    $user = make_user();
    $svc = new BookingService($pdo);
    $id = $svc->book($user, $serviceId, make_slot(institution_of_service($serviceId), 98));
    $svc->cancel($user, $id);
    assertThrows(BookingException::class, function () use ($svc, $user, $id) {
        $svc->cancel($user, $id);
    }, 'already cancelled');
});

test('past appointments cannot be cancelled and become "completed"', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Apply for a passport');
    $user = make_user();
    $repo = new AppointmentRepository($pdo);
    // insert directly: a booking in a slot that is already in the past
    $id = $repo->create($user, $serviceId, make_slot(institution_of_service($serviceId), -4), 'PG-PAST22');
    assertThrows(BookingException::class, function () use ($pdo, $user, $id) {
        (new BookingService($pdo))->cancel($user, $id);
    }, 'in the past');
    $repo->markPastCompleted($user);
    assertSame('completed', appointment_status($id));
    // a completed visit does not block booking the same service again
    $again = (new BookingService($pdo))->book($user, $serviceId, make_slot(institution_of_service($serviceId), 100));
    assertSame('booked', appointment_status($again));
});

test('upcoming and past lists are separated and scoped to the user', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Apply for a passport');
    $inst = institution_of_service($serviceId);
    $user = make_user();
    $other = make_user();
    $repo = new AppointmentRepository($pdo);
    $svc = new BookingService($pdo);
    $upId = $svc->book($user, $serviceId, make_slot($inst, 102));
    $cancelledId = $svc->book($user, service_id_by_name('Renew your Citizen Card'), make_slot($inst, 104));
    $svc->cancel($user, $cancelledId);
    $svc->book($other, $serviceId, make_slot($inst, 106));

    $upcoming = $repo->listForUser($user, true);
    $past = $repo->listForUser($user, false);
    assertSame(1, count($upcoming));
    assertSame($upId, (int) $upcoming[0]['id']);
    assertSame(1, count($past));
    assertSame($cancelledId, (int) $past[0]['id']);
    assertSame(null, $repo->findForUser($upId, $other), 'other users cannot read this ticket');
    assertSame($upId, (int) $repo->nextUpcoming($user)['id']);
});
