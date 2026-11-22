<?php
// Integration tests: accounts, search, documents and guides (US-01 .. US-07, US-12 .. US-14).

use PassGo\Repository\DocumentRepository;
use PassGo\Repository\GuideRepository;
use PassGo\Repository\ServiceRepository;
use PassGo\Service\AccountService;
use PassGo\Service\ProgressService;

section('Accounts (US-12, US-13, US-14)');

test('registers a user and stores only a password hash', function () {
    $pdo = test_pdo();
    $email = unique_email('reg');
    $r = (new AccountService($pdo))->register('  Ana Silva ', $email, 'correct horse', 'correct horse');
    assertSame([], $r['errors']);
    $st = $pdo->prepare('SELECT name, password_hash FROM users WHERE id = :id');
    $st->execute([':id' => $r['user_id']]);
    $row = $st->fetch();
    assertSame('Ana Silva', $row['name'], 'name is trimmed');
    assertTrue($row['password_hash'] !== 'correct horse');
    assertTrue(password_verify('correct horse', $row['password_hash']));
});

test('rejects a duplicate email, ignoring upper/lower case', function () {
    $pdo = test_pdo();
    $email = unique_email('dup');
    $svc = new AccountService($pdo);
    assertSame([], $svc->register('One', $email, 'password123', 'password123')['errors']);
    $r = $svc->register('Two', strtoupper($email), 'password123', 'password123');
    assertTrue(isset($r['errors']['email']));
    assertSame(null, $r['user_id']);
});

test('registration returns validation errors without creating a user', function () {
    $r = (new AccountService(test_pdo()))->register('', 'bad', 'x', 'y');
    assertTrue(isset($r['errors']['name']) && isset($r['errors']['email']) && isset($r['errors']['password']));
    assertSame(null, $r['user_id']);
});

test('login works with the right password and ignores email case', function () {
    $email = unique_email('login');
    make_user('Login User', $email, 'my-secret-pw');
    $svc = new AccountService(test_pdo());
    $user = $svc->authenticate(strtoupper($email), 'my-secret-pw');
    assertTrue($user !== null);
    assertSame('Login User', $user['name']);
});

test('login fails for a wrong password and for an unknown email', function () {
    $email = unique_email('login');
    make_user('Login User', $email, 'my-secret-pw');
    $svc = new AccountService(test_pdo());
    assertSame(null, $svc->authenticate($email, 'wrong-password'));
    assertSame(null, $svc->authenticate('nobody@example.test', 'my-secret-pw'));
});

test('the user can change their name, but not to an empty one', function () {
    $pdo = test_pdo();
    $id = make_user('Old Name');
    $svc = new AccountService($pdo);
    assertSame(null, $svc->updateName($id, ' New Name '));
    $st = $pdo->prepare('SELECT name FROM users WHERE id = :id');
    $st->execute([':id' => $id]);
    assertSame('New Name', $st->fetchColumn());
    assertTrue($svc->updateName($id, '  ') !== null);
});

section('Search and filter (US-01, US-02, US-03)');

test('keyword search matches service names, case-insensitively', function () {
    $repo = new ServiceRepository(test_pdo());
    $names = array_column($repo->search('PASSPORT'), 'name');
    assertSame(['Apply for a passport'], $names);
});

test('keyword search also matches the institution name and the summary', function () {
    $repo = new ServiceRepository(test_pdo());
    assertSame(['Change your tax address'], array_column($repo->search('Serviço de Finanças'), 'name'));
    assertTrue(in_array('Residence certificate', array_column($repo->search('proves where you live'), 'name'), true));
});

test('category filter and keyword can be combined', function () {
    $repo = new ServiceRepository(test_pdo());
    $cats = [];
    foreach ($repo->categories() as $c) {
        $cats[$c['slug']] = (int) $c['id'];
    }
    $identity = array_column($repo->search('', $cats['identity-travel']), 'name');
    sort($identity);
    assertSame(['Apply for a passport', 'Renew your Citizen Card'], $identity);
    assertSame(['Renew your Citizen Card'], array_column($repo->search('card', $cats['identity-travel']), 'name'));
    assertSame([], $repo->search('passport', $cats['tax']));
});

test('no match returns an empty list; wildcard characters are literal', function () {
    $repo = new ServiceRepository(test_pdo());
    assertSame([], $repo->search('zzzz-not-a-service'));
    assertSame([], $repo->search('%'), 'a lone % must not match everything');
    assertSame([], $repo->search("'; DROP TABLE services; --"));
    assertSame(7, count($repo->search('')), 'all seeded services are listed without filters');
});

test('service detail includes institution address, hours and phone', function () {
    $repo = new ServiceRepository(test_pdo());
    $s = $repo->find(service_id_by_name('Apply for a passport'));
    assertTrue($s['address'] !== '' && $s['opening_hours'] !== '' && $s['phone'] !== '');
    assertSame(null, $repo->find(999999));
});

section('Documents checklist (US-04, US-05)');

test('every seeded service lists its required documents with notes', function () {
    $docs = (new DocumentRepository(test_pdo()))->forService(service_id_by_name('Change your tax address'));
    assertSame(3, count($docs));
    assertTrue($docs[0]['is_required'] === true, 'required documents come first');
});

test('ticked documents are saved per user and replace the previous selection', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Change your tax address');
    $user = make_user();
    $other = make_user();
    $progress = new ProgressService($pdo);
    $docs = (new DocumentRepository($pdo))->forService($serviceId);
    $ids = array_map(function ($d) { return (int) $d['id']; }, $docs);

    $progress->saveDocumentChecks($user, $serviceId, [$ids[0], $ids[1]]);
    $s = $progress->documentProgress($user, $serviceId);
    assertSame(2, $s['required_ready']);

    $progress->saveDocumentChecks($user, $serviceId, [$ids[0]]);      // un-tick one
    assertSame(1, $progress->documentProgress($user, $serviceId)['required_ready']);
    assertSame(0, $progress->documentProgress($other, $serviceId)['required_ready'], 'progress is private');
});

test('document ids that do not belong to the service are ignored', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Change your tax address');
    $user = make_user();
    $progress = new ProgressService($pdo);
    $progress->saveDocumentChecks($user, $serviceId, [999999, 8]);     // 8 = Medical certificate, not needed here
    $st = $pdo->prepare('SELECT count(*) FROM user_document_checks WHERE user_id = :u');
    $st->execute([':u' => $user]);
    assertSame(0, (int) $st->fetchColumn());
});

section('Guide progress (US-06, US-07)');

test('guides have ordered steps starting at 1', function () {
    $steps = (new GuideRepository(test_pdo()))->steps(service_id_by_name('Renew your Citizen Card'));
    assertSame([1, 2, 3, 4, 5], array_map('intval', array_column($steps, 'position')));
});

test('marking steps done and undone updates the progress', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Renew your Citizen Card');
    $user = make_user();
    $progress = new ProgressService($pdo);
    $steps = (new GuideRepository($pdo))->steps($serviceId);

    assertTrue($progress->setStepDone($user, $serviceId, (int) $steps[0]['id'], true));
    assertTrue($progress->setStepDone($user, $serviceId, (int) $steps[1]['id'], true));
    $s = $progress->stepProgress($user, $serviceId);
    assertSame(2, $s['done']);
    assertSame(5, $s['total']);
    assertSame((int) $steps[2]['id'], (int) array_values(array_filter($s['steps'], function ($x) { return $x['is_next']; }))[0]['id']);

    assertTrue($progress->setStepDone($user, $serviceId, (int) $steps[1]['id'], false));
    assertSame(1, $progress->stepProgress($user, $serviceId)['done']);
});

test('marking a step done twice is harmless', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Renew your Citizen Card');
    $user = make_user();
    $progress = new ProgressService($pdo);
    $stepId = (int) (new GuideRepository($pdo))->steps($serviceId)[0]['id'];
    assertTrue($progress->setStepDone($user, $serviceId, $stepId, true));
    assertTrue($progress->setStepDone($user, $serviceId, $stepId, true));
    assertSame(1, $progress->stepProgress($user, $serviceId)['done']);
});

test('a step of another service cannot be marked through this service', function () {
    $pdo = test_pdo();
    $a = service_id_by_name('Renew your Citizen Card');
    $b = service_id_by_name('Apply for a passport');
    $user = make_user();
    $foreignStep = (int) (new GuideRepository($pdo))->steps($b)[0]['id'];
    assertFalse((new ProgressService($pdo))->setStepDone($user, $a, $foreignStep, true));
    assertSame(0, (new ProgressService($pdo))->stepProgress($user, $b)['done']);
});

test('progress is private: another user starts from zero', function () {
    $pdo = test_pdo();
    $serviceId = service_id_by_name('Renew your Citizen Card');
    $progress = new ProgressService($pdo);
    $a = make_user();
    $b = make_user();
    $stepId = (int) (new GuideRepository($pdo))->steps($serviceId)[0]['id'];
    $progress->setStepDone($a, $serviceId, $stepId, true);
    assertSame(0, $progress->stepProgress($b, $serviceId)['done']);
});
