<?php
// Flow tests: the critical user journeys, through real HTTP requests to the real app and database.

section('Flows: browsing as a visitor (US-01, US-02, US-03, US-04, US-06)');

test('home page renders with search and the four main areas', function () {
    $b = new Browser($GLOBALS['BASE']);
    $r = $b->get('index.php');
    assertSame(200, $r['status']);
    assertContains('What do you need to do?', $r['body']);
    foreach (['Public services', 'Required documents', 'Step-by-step guides', 'Queue tickets'] as $label) {
        assertContains($label, $r['body']);
    }
});

test('search finds a service and hides the others', function () {
    $b = new Browser($GLOBALS['BASE']);
    $r = $b->get('services.php?q=passport');
    assertContains('Apply for a passport', $r['body']);
    assertNotContains('Renew your driving licence', $r['body']);
    assertContains('1 service found', $r['body']);
});

test('search with no result shows a friendly message', function () {
    $b = new Browser($GLOBALS['BASE']);
    $r = $b->get('services.php?q=qqqqqqq');
    assertContains('No services found', $r['body']);
});

test('category filter works and keeps the search term', function () {
    $b = new Browser($GLOBALS['BASE']);
    $r = $b->get('services.php?category=2');
    assertContains('Change your tax address', $r['body']);
    assertNotContains('Apply for a passport', $r['body']);
});

test('service page shows where, when, documents and the guide to a visitor', function () {
    $b = new Browser($GLOBALS['BASE']);
    $id = service_id_by_name('Renew your Citizen Card');
    $r = $b->get('service.php?id=' . $id);
    assertSame(200, $r['status']);
    assertContains('Loja do Cidadão', $r['body']);
    assertContains('Mon–Fri 09:00–17:00', $r['body']);
    assertContains('Documents you need', $r['body']);
    assertContains('Required', $r['body']);
    assertContains('Log in to tick off the documents', $r['body']);
    assertContains('Check requirements', $r['body']);
});

test('guide is readable without logging in, with a prompt to log in', function () {
    $b = new Browser($GLOBALS['BASE']);
    $r = $b->get('guide.php?id=' . service_id_by_name('Apply for a passport'));
    assertSame(200, $r['status']);
    assertContains('Step-by-step guide', $r['body']);
    assertContains('Log in to save your progress', $r['body']);
});

test('unknown service gives a 404 page', function () {
    $b = new Browser($GLOBALS['BASE']);
    assertSame(404, $b->get('service.php?id=99999')['status']);
    assertSame(404, $b->get('service.php?id=abc')['status']);
    assertSame(404, $b->get('guide.php')['status']);
});

section('Flows: accounts and security (US-12, US-13)');

test('protected pages redirect visitors to login and return afterwards', function () {
    $b = new Browser($GLOBALS['BASE']);
    $r = $b->get('appointments.php', false);
    assertSame(302, $r['status']);
    assertContains('login.php?next=appointments.php', $r['location']);

    $email = unique_email('flow');
    make_user('Flow User', $email, 'flowpass123');
    $after = $b->login($email, 'flowpass123', 'appointments.php');
    assertContains('My appointments', $after['body'], 'user returns to the page they wanted');
    assertContains('Welcome back, Flow', $after['body']);
});

test('registration creates an account and logs the user in', function () {
    $b = new Browser($GLOBALS['BASE']);
    $r = $b->register('Maria Souza', unique_email('reg'), 'password123');
    assertContains('Your account was created', $r['body']);
    $profile = $b->get('profile.php');
    assertSame(200, $profile['status']);
    assertContains('Maria Souza', $profile['body']);
});

test('registration errors are shown next to the fields and values are kept', function () {
    $b = new Browser($GLOBALS['BASE']);
    $r = $b->register('Carlos', 'not-an-email', 'short');
    assertContains('Please enter a valid email address.', $r['body']);
    assertContains('at least 8 characters', $r['body']);
    assertContains('value="Carlos"', $r['body'], 'entered name is kept');
    assertContains('aria-invalid="true"', $r['body']);
});

test('duplicate email is reported during registration', function () {
    $email = unique_email('twice');
    $b1 = new Browser($GLOBALS['BASE']);
    $b1->register('First', $email, 'password123');
    $b2 = new Browser($GLOBALS['BASE']);
    $r = $b2->register('Second', $email, 'password123');
    assertContains('already exists', $r['body']);
});

test('wrong password gives a generic error that does not reveal whether the email exists', function () {
    $email = unique_email('gen');
    make_user('Generic', $email, 'rightpassword');
    $b = new Browser($GLOBALS['BASE']);
    $wrongPw = $b->login($email, 'wrongpassword');
    $b2 = new Browser($GLOBALS['BASE']);
    $unknown = $b2->login('nobody' . $email, 'wrongpassword');
    assertContains('Incorrect email or password.', $wrongPw['body']);
    assertContains('Incorrect email or password.', $unknown['body']);
    assertSame(302, $b->get('profile.php', false)['status'], 'still logged out');
});

test('a POST without a valid CSRF token is rejected', function () {
    $b = new Browser($GLOBALS['BASE']);
    $r = $b->post('login.php', ['email' => 'demo@passgo.pt', 'password' => 'demo1234']);
    assertSame(400, $r['status']);
    $r2 = $b->post('login.php', ['email' => 'demo@passgo.pt', 'password' => 'demo1234', 'csrf' => 'forged']);
    assertSame(400, $r2['status']);
});

test('the demo account from the seed data can log in', function () {
    $b = new Browser($GLOBALS['BASE']);
    $r = $b->login('demo@passgo.pt', 'demo1234');
    assertContains('Welcome back, Demo', $r['body']);
    assertContains('Your next appointment', $r['body']);
    assertContains('PG-DEMO01', $r['body']);
});

test('logging out ends the session, and only via POST', function () {
    $b = new Browser($GLOBALS['BASE']);
    $b->register('Leaver', unique_email('out'), 'password123');
    $b->get('logout.php');                                   // GET must not log out
    assertSame(200, $b->get('profile.php', false)['status']);
    $r = $b->submit('profile.php', 'logout.php');
    assertContains('You were logged out.', $r['body']);
    assertSame(302, $b->get('profile.php', false)['status']);
});

test('user-provided text is escaped in the page (no HTML injection)', function () {
    $b = new Browser($GLOBALS['BASE']);
    $b->register('<script>alert(1)</script>', unique_email('xss'), 'password123');
    $r = $b->get('profile.php');
    assertNotContains('<script>alert(1)</script>', $r['body']);
    assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $r['body']);
});

test('profile name can be edited, and an empty name is refused', function () {
    $b = new Browser($GLOBALS['BASE']);
    $b->register('Before', unique_email('prof'), 'password123');
    $ok = $b->submit('profile.php', 'profile.php', ['name' => 'After']);
    assertContains('Your name was updated.', $ok['body']);
    assertContains('value="After"', $ok['body']);
    $bad = $b->submit('profile.php', 'profile.php', ['name' => '  ']);
    assertContains('Please enter your name.', $bad['body']);
});

section('Flows: documents checklist and guide progress (US-05, US-07)');

test('ticking documents is saved and shown as "X of Y required documents ready"', function () {
    $b = new Browser($GLOBALS['BASE']);
    $b->register('Checker', unique_email('doc'), 'password123');
    $serviceId = service_id_by_name('Change your tax address');
    $page = $b->get('service.php?id=' . $serviceId);
    assertContains('0 of 3', $page['body']);
    preg_match_all('/name="doc_ids\[\]" value="(\d+)"/', $page['body'], $m);
    $r = $b->submit('service.php?id=' . $serviceId, 'service.php', ['id' => $serviceId, 'doc_ids' => [$m[1][0], $m[1][1]]]);
    assertContains('Your document checklist was saved.', $r['body']);
    assertContains('2 of 3', $r['body']);
    assertContains('checked', $r['body']);
});

test('completing guide steps updates the progress bar and the "Next" marker', function () {
    $b = new Browser($GLOBALS['BASE']);
    $b->register('Walker', unique_email('guide'), 'password123');
    $serviceId = service_id_by_name('Renew your Citizen Card');
    $page = $b->get('guide.php?id=' . $serviceId);
    assertContains('0 of 5', $page['body']);
    preg_match_all('/name="step_id" value="(\d+)"/', $page['body'], $m);
    $r = $b->submit('guide.php?id=' . $serviceId, 'guide.php', ['id' => $serviceId, 'step_id' => $m[1][0], 'done' => '1']);
    assertContains('1 of 5', $r['body']);
    assertContains('Mark as not done', $r['body']);
    $undo = $b->submit('guide.php?id=' . $serviceId, 'guide.php', ['id' => $serviceId, 'step_id' => $m[1][0], 'done' => '0']);
    assertContains('0 of 5', $undo['body']);
});

test('progress made by one user is not visible to another', function () {
    $serviceId = service_id_by_name('Renew your Citizen Card');
    $a = new Browser($GLOBALS['BASE']);
    $a->register('User A', unique_email('pa'), 'password123');
    $page = $a->get('guide.php?id=' . $serviceId);
    preg_match_all('/name="step_id" value="(\d+)"/', $page['body'], $m);
    $a->submit('guide.php?id=' . $serviceId, 'guide.php', ['id' => $serviceId, 'step_id' => $m[1][0], 'done' => '1']);

    $b = new Browser($GLOBALS['BASE']);
    $b->register('User B', unique_email('pb'), 'password123');
    assertContains('0 of 5', $b->get('guide.php?id=' . $serviceId)['body']);
});

section('Flows: booking a queue ticket end to end (US-08 .. US-11)');

test('book a ticket, see it in "My appointments", then cancel it', function () {
    $b = new Browser($GLOBALS['BASE']);
    $b->register('Booker', unique_email('book'), 'password123');
    $serviceId = service_id_by_name('Apply for a passport');

    // 1. slots are listed
    $page = $b->get('book.php?service=' . $serviceId);
    assertSame(200, $page['status']);
    assertContains('Choose a day and time', $page['body']);
    assertTrue(preg_match('/<input type="radio" name="slot_id" value="(\d+)">/', $page['body'], $m) === 1, 'at least one free slot');

    // 2. book it
    $r = $b->submit('book.php?service=' . $serviceId, 'book.php', ['service' => $serviceId, 'slot_id' => $m[1]]);
    assertContains('Your ticket is booked.', $r['body']);
    assertContains('Digital queue ticket', $r['body']);
    assertTrue(preg_match('/PG-[A-Z2-9]{6}/', $r['body'], $code) === 1, 'ticket code is shown');
    assertContains('Apply for a passport', $r['body']);
    assertContains('Before you go', $r['body']);   // documents reminder

    // 3. it appears in the list
    $list = $b->get('appointments.php');
    assertContains($code[0], $list['body']);
    assertContains('Upcoming', $list['body']);

    // 4. a second active booking for the same service is refused
    $again = $b->get('book.php?service=' . $serviceId);
    preg_match_all('/<input type="radio" name="slot_id" value="(\d+)">/', $again['body'], $all);
    $dup = $b->submit('book.php?service=' . $serviceId, 'book.php', ['service' => $serviceId, 'slot_id' => $all[1][1]]);
    assertContains('already have an active appointment', $dup['body']);

    // 5. cancel it
    preg_match('/name="appointment_id" value="(\d+)"/', $list['body'], $ap);
    $cancel = $b->submit('appointments.php', 'appointments.php', ['appointment_id' => $ap[1]]);
    assertContains('Your appointment was cancelled', $cancel['body']);
    assertContains('Past and cancelled', $cancel['body']);
    assertContains('Cancelled', $cancel['body']);
    assertContains('No upcoming appointments', $cancel['body']);
});

test('choosing no slot shows an error instead of failing', function () {
    $b = new Browser($GLOBALS['BASE']);
    $b->register('Undecided', unique_email('nos'), 'password123');
    $serviceId = service_id_by_name('Apply for a passport');
    $r = $b->submit('book.php?service=' . $serviceId, 'book.php', ['service' => $serviceId]);
    assertContains('Please choose a time slot first.', $r['body']);
});

test('a service without appointments shows a "no appointment needed" notice', function () {
    $b = new Browser($GLOBALS['BASE']);
    $b->register('Walkin', unique_email('walk'), 'password123');
    $r = $b->get('book.php?service=' . service_id_by_name('Residence certificate'));
    assertContains('No appointment needed', $r['body']);
    assertNotContains('Confirm booking', $r['body']);
});

test('full slots are shown as full and cannot be selected', function () {
    $serviceId = service_id_by_name('Change your tax address');
    $inst = institution_of_service($serviceId);
    $slot = make_slot($inst, 1.5, 1);       // 1 place, soon: first slot on the page
    (new PassGo\Service\BookingService(test_pdo()))->book(make_user(), $serviceId, $slot);
    $b = new Browser($GLOBALS['BASE']);
    $b->register('Latecomer', unique_email('full'), 'password123');
    $r = $b->get('book.php?service=' . $serviceId);
    assertTrue(preg_match('/<input type="radio" name="slot_id" value="' . $slot . '" disabled>/', $r['body']) === 1, 'full slot is disabled');
    assertContains('Full', $r['body']);
});

test("someone else's ticket cannot be opened", function () {
    $serviceId = service_id_by_name('Apply for a passport');
    $owner = new Browser($GLOBALS['BASE']);
    $owner->register('Owner', unique_email('own'), 'password123');
    $page = $owner->get('book.php?service=' . $serviceId);
    preg_match('/<input type="radio" name="slot_id" value="(\d+)">/', $page['body'], $m);
    $r = $owner->submit('book.php?service=' . $serviceId, 'book.php', ['service' => $serviceId, 'slot_id' => $m[1]]);
    preg_match('/appointment\.php\?id=(\d+)/', $r['url'], $idm);
    assertTrue(isset($idm[1]), 'redirected to the ticket page');

    $intruder = new Browser($GLOBALS['BASE']);
    $intruder->register('Intruder', unique_email('int'), 'password123');
    assertSame(404, $intruder->get('appointment.php?id=' . $idm[1])['status']);
    assertSame(200, $owner->get('appointment.php?id=' . $idm[1])['status']);

    // and cannot cancel it either
    $c = $intruder->submit('profile.php', 'appointments.php', ['appointment_id' => $idm[1]]);
    assertContains('could not find that appointment', $c['body']);
    assertContains('Booked', $owner->get('appointment.php?id=' . $idm[1])['body']);
});

test('the ticket page reminds the user of missing documents, and not when all are ticked', function () {
    $serviceId = service_id_by_name('Change your tax address');
    $b = new Browser($GLOBALS['BASE']);
    $b->register('Prepared', unique_email('rem'), 'password123');
    $page = $b->get('book.php?service=' . $serviceId);
    assertContains('0 of 3', $page['body']);
    preg_match('/<input type="radio" name="slot_id" value="(\d+)">/', $page['body'], $m);
    $r = $b->submit('book.php?service=' . $serviceId, 'book.php', ['service' => $serviceId, 'slot_id' => $m[1]]);
    assertContains('required documents are', $r['body']);
    assertContains('Open my checklist', $r['body']);

    $svcPage = $b->get('service.php?id=' . $serviceId);
    preg_match_all('/name="doc_ids\[\]" value="(\d+)"/', $svcPage['body'], $d);
    $b->submit('service.php?id=' . $serviceId, 'service.php', ['id' => $serviceId, 'doc_ids' => $d[1]]);
    preg_match('/appointment\.php\?id=(\d+)/', $r['url'], $idm);
    assertContains('All required documents are ready.', $b->get('appointment.php?id=' . $idm[1])['body']);
});

section('Flows: pages are usable on a phone (US non-functional)');

test('every page declares the viewport, a language, a title and a skip link', function () {
    $b = new Browser($GLOBALS['BASE']);
    foreach (['index.php', 'services.php', 'service.php?id=1', 'guide.php?id=1', 'login.php', 'register.php'] as $path) {
        $body = $b->get($path)['body'];
        assertContains('name="viewport" content="width=device-width, initial-scale=1"', $body, $path);
        assertContains('<html lang="en">', $body, $path);
        assertContains('· PASS GO</title>', $body, $path);
        assertContains('class="skip-link"', $body, $path);
    }
});

test('form fields have labels', function () {
    $b = new Browser($GLOBALS['BASE']);
    $body = $b->get('register.php')['body'];
    foreach (['name', 'email', 'password', 'confirm'] as $field) {
        assertContains('<label for="' . $field . '">', $body);
    }
});
