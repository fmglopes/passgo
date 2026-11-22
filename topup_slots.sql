-- PASS GO — keep the demo bookable: make sure every institution has slots for the next 21 days.
-- Safe to run any time (existing slots are left untouched). Used by bin/setup_db.php on every start.
SET client_min_messages TO WARNING;

INSERT INTO time_slots (institution_id, starts_at, capacity)
SELECT i.id,
       ((CURRENT_DATE + d.n) + (TIME '09:00' + t.n * INTERVAL '30 minutes')) AT TIME ZONE 'Europe/Lisbon',
       2
FROM institutions i
CROSS JOIN generate_series(0, 20) AS d(n)
CROSS JOIN generate_series(0, 15) AS t(n)
WHERE EXTRACT(ISODOW FROM (CURRENT_DATE + d.n)) < 6
ON CONFLICT (institution_id, starts_at) DO NOTHING;
