<?php
// Unit tests: pure logic, no database needed.

use PassGo\Repository\ServiceRepository;
use PassGo\Service\BookingService;
use PassGo\Service\ProgressService;
use PassGo\Validator;

section('Unit: validation (US-12, US-14)');

test('accepts a valid registration', function () {
    assertSame([], Validator::registration('Ana Silva', 'ana@example.com', 'longenough1', 'longenough1'));
});

test('rejects an empty name', function () {
    $errors = Validator::registration('   ', 'ana@example.com', 'longenough1', 'longenough1');
    assertTrue(isset($errors['name']));
});

test('rejects invalid email addresses', function () {
    foreach (['', 'not-an-email', 'a@b', 'a b@c.com', '@example.com'] as $bad) {
        $errors = Validator::registration('Ana', $bad, 'longenough1', 'longenough1');
        assertTrue(isset($errors['email']), "email '$bad' should be rejected");
    }
});

test('rejects passwords shorter than 8 characters', function () {
    $errors = Validator::registration('Ana', 'ana@example.com', 'short7!', 'short7!');
    assertTrue(isset($errors['password']));
});

test('rejects passwords longer than 72 characters (bcrypt limit)', function () {
    $long = str_repeat('a', 73);
    $errors = Validator::registration('Ana', 'ana@example.com', $long, $long);
    assertTrue(isset($errors['password']));
});

test('rejects mismatching password confirmation', function () {
    $errors = Validator::registration('Ana', 'ana@example.com', 'longenough1', 'longenough2');
    assertTrue(isset($errors['confirm']));
});

test('name must not be longer than 100 characters', function () {
    assertTrue(Validator::nameError(str_repeat('x', 101)) !== null);
    assertSame(null, Validator::nameError(str_repeat('x', 100)));
});

section('Unit: tickets and helpers (US-09)');

test('ticket code has the format PG-XXXXXX without ambiguous characters', function () {
    for ($i = 0; $i < 200; $i++) {
        assertMatches('/^PG-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', BookingService::generateTicketCode());
    }
});

test('ticket codes are practically unique', function () {
    $codes = [];
    for ($i = 0; $i < 500; $i++) {
        $codes[BookingService::generateTicketCode()] = true;
    }
    assertTrue(count($codes) > 495, 'too many duplicate ticket codes');
});

test('e() escapes HTML so injected markup is shown as text', function () {
    assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
    assertSame('Tom &amp; Jerry', e('Tom & Jerry'));
});

test('safe_next only allows our own pages', function () {
    assertSame('guide.php?id=3', safe_next('guide.php?id=3'));
    assertSame('index.php', safe_next('https://evil.example/steal'));
    assertSame('index.php', safe_next('//evil.example'));
    assertSame('index.php', safe_next('../../etc/passwd'));
    assertSame('index.php', safe_next(null));
});

test('LIKE wildcards typed by the user are escaped', function () {
    assertSame('%100\\%%', ServiceRepository::likePattern('100%'));
    assertSame('%a\\_b%', ServiceRepository::likePattern('a_b'));
});

section('Unit: progress summaries (US-05, US-07)');

test('step summary counts done steps and flags the next one', function () {
    $steps = [['id' => 10], ['id' => 11], ['id' => 12], ['id' => 13]];
    $s = ProgressService::summarizeSteps($steps, [10, 11]);
    assertSame(2, $s['done']);
    assertSame(4, $s['total']);
    assertSame(50, $s['percent']);
    assertFalse($s['complete']);
    assertTrue($s['steps'][0]['done']);
    assertFalse($s['steps'][2]['done']);
    assertTrue($s['steps'][2]['is_next']);
    assertFalse($s['steps'][3]['is_next']);
});

test('step summary with nothing done starts at step one', function () {
    $s = ProgressService::summarizeSteps([['id' => 1], ['id' => 2]], []);
    assertSame(0, $s['percent']);
    assertTrue($s['steps'][0]['is_next']);
});

test('step summary is complete when all steps are done, and has no next step', function () {
    $s = ProgressService::summarizeSteps([['id' => 1], ['id' => 2]], [1, 2]);
    assertTrue($s['complete']);
    assertSame(100, $s['percent']);
    assertFalse($s['steps'][0]['is_next'] || $s['steps'][1]['is_next']);
});

test('step summary handles a guide without steps', function () {
    $s = ProgressService::summarizeSteps([], []);
    assertSame(0, $s['total']);
    assertSame(0, $s['percent']);
    assertFalse($s['complete']);
});

test('document summary only counts required documents as "ready"', function () {
    $docs = [
        ['id' => 1, 'is_required' => true,  'name' => 'A'],
        ['id' => 2, 'is_required' => true,  'name' => 'B'],
        ['id' => 3, 'is_required' => false, 'name' => 'C'],
    ];
    $s = ProgressService::summarizeDocuments($docs, [1, 3]);
    assertSame(2, $s['required_total']);
    assertSame(1, $s['required_ready']);
    assertSame(1, count($s['missing']));
    assertSame('B', $s['missing'][0]['name']);
    assertFalse($s['all_ready']);
    $all = ProgressService::summarizeDocuments($docs, [1, 2]);
    assertTrue($all['all_ready']);
});
