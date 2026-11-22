-- PASS GO — demo seed data
-- IMPORTANT: this is ILLUSTRATIVE demo data (names, addresses, hours, documents, steps).
-- It must be verified against official sources before any real use.
-- Run after schema.sql:  psql "$DSN" -f database/seed.sql

SET client_min_messages TO WARNING;
SET TIME ZONE 'Europe/Lisbon';

-- ----------------------------------------------------------- categories
INSERT INTO categories (id, name, slug) VALUES
 (1, 'Identity & travel',   'identity-travel'),
 (2, 'Tax',                 'tax'),
 (3, 'Social security',     'social-security'),
 (4, 'Driving & transport', 'driving-transport'),
 (5, 'Residence',           'residence'),
 (6, 'Health',              'health');

-- --------------------------------------------------------- institutions
INSERT INTO institutions (id, name, address, city, phone, opening_hours, website) VALUES
 (1, 'Loja do Cidadão',                       'Rua do Serviço Público 10, 4000-001', 'Porto', '+351 220 000 001', 'Mon–Fri 09:00–17:00', 'https://eportugal.gov.pt'),
 (2, 'Serviço de Finanças',                   'Rua das Finanças 25, 4000-002',       'Porto', '+351 220 000 002', 'Mon–Fri 09:00–16:30', 'https://www.portaldasfinancas.gov.pt'),
 (3, 'Segurança Social',                      'Avenida da Previdência 8, 4000-003',  'Porto', '+351 220 000 003', 'Mon–Fri 09:00–17:00', 'https://www.seg-social.pt'),
 (4, 'IMT – Mobilidade e Transportes',        'Rua dos Transportes 40, 4000-004',    'Porto', '+351 220 000 004', 'Mon–Fri 09:00–16:30', 'https://www.imt-ip.pt'),
 (5, 'Junta de Freguesia de Cedofeita',       'Largo da Freguesia 3, 4000-005',      'Porto', '+351 220 000 005', 'Mon–Fri 09:00–17:30', NULL),
 (6, 'Centro de Saúde',                       'Rua da Saúde 15, 4000-006',           'Porto', '+351 220 000 006', 'Mon–Fri 08:30–17:00', 'https://www.sns.gov.pt');

-- ------------------------------------------------------------- services
INSERT INTO services (id, institution_id, category_id, name, summary, description, estimated_minutes, requires_appointment) VALUES
 (1, 1, 1, 'Renew your Citizen Card',
     'Renew an expiring or expired Citizen Card (Cartão de Cidadão).',
     'Use this service when your Citizen Card is about to expire, has expired, or must be replaced. You will confirm your identity, update your data if needed and request a new card.',
     20, TRUE),
 (2, 1, 1, 'Apply for a passport',
     'Request a new passport or renew an existing one.',
     'Use this service to request a first passport or renew an existing passport. Bring your documents and be ready to confirm your identity in person.',
     25, TRUE),
 (3, 2, 2, 'Change your tax address',
     'Update the address registered with the tax authority.',
     'Use this service when you move home and need the tax authority to hold your new address, so that official letters and tax decisions reach you.',
     15, TRUE),
 (4, 3, 3, 'Get a Social Security number (NISS)',
     'Register with Social Security and receive your NISS.',
     'Use this service if you are starting to work or study and need a Social Security identification number (NISS).',
     30, TRUE),
 (5, 4, 4, 'Renew your driving licence',
     'Renew a driving licence that is about to expire.',
     'Use this service to renew your driving licence. You will need a valid medical certificate and your current licence.',
     25, TRUE),
 (6, 5, 5, 'Residence certificate',
     'Get a certificate that proves where you live.',
     'Use this service when you need an official proof of residence for another procedure. At this institution you can walk in without a booking.',
     10, FALSE),
 (7, 6, 6, 'Register with a family doctor',
     'Register at your local health centre and get a family doctor.',
     'Use this service when you move to a new area or do not yet have a family doctor assigned. You register at your local health centre.',
     20, TRUE);

-- ------------------------------------------------------------ documents
INSERT INTO documents (id, name, description) VALUES
 (1,  'Citizen Card',                    'Your current or expired card'),
 (2,  'Proof of address',                'Utility bill, rental contract or similar document, usually from the last 3 months'),
 (3,  'Tax number (NIF)',                'Your Portuguese tax identification number'),
 (4,  'Birth certificate',               'Full copy of your birth certificate'),
 (5,  'Previous passport',               'Your current or previous passport'),
 (6,  'Passport-style photo',            'Recent colour photo with a plain background'),
 (7,  'Social Security number (NISS)',   'Your Social Security identification number'),
 (8,  'Medical certificate',             'Issued by a licensed doctor for driving licence renewal'),
 (9,  'Driving licence',                 'Your current driving licence'),
 (10, 'Employment contract or enrolment proof', 'Contract, job offer or school/university enrolment proof'),
 (11, 'Health user number',              'Your national health user number, if you already have one');

INSERT INTO service_documents (service_id, document_id, is_required, note) VALUES
 (1, 1, TRUE,  'Bring the card you want to renew. If it was lost or stolen, report it first.'),
 (1, 2, FALSE, 'Only if your address has changed.'),
 (1, 6, FALSE, 'Photos are often taken on site; check before you go.'),
 (2, 1, TRUE,  NULL),
 (2, 6, TRUE,  'Recent and passport-style.'),
 (2, 5, FALSE, 'Bring it if you are renewing.'),
 (2, 3, FALSE, 'If the service asks for it.'),
 (3, 1, TRUE,  'Needed to identify you.'),
 (3, 3, TRUE,  NULL),
 (3, 2, TRUE,  'It must show your new address.'),
 (4, 1, TRUE,  NULL),
 (4, 2, TRUE,  NULL),
 (4, 10, TRUE, 'Any of the listed proofs is accepted.'),
 (4, 3, FALSE, 'If you already have one.'),
 (5, 1, TRUE,  NULL),
 (5, 9, TRUE,  'Your current licence.'),
 (5, 8, TRUE,  'Must be recent; confirm how long it stays valid.'),
 (5, 3, TRUE,  NULL),
 (5, 6, FALSE, 'Check whether a photo is needed.'),
 (6, 1, TRUE,  NULL),
 (6, 2, TRUE,  'Proves where you live.'),
 (6, 3, FALSE, 'If the Junta asks for it.'),
 (7, 1, TRUE,  NULL),
 (7, 2, TRUE,  'Shows that you live in the health centre area.'),
 (7, 11, FALSE, 'If you already have one.');

-- ---------------------------------------------------------- guide steps
INSERT INTO guide_steps (service_id, position, title, description) VALUES
 (1, 1, 'Check requirements',   'Confirm that your Citizen Card is expired or about to expire, and read the notes on the Documents list.'),
 (1, 2, 'Prepare documents',    'Gather the documents listed for this service and tick them off in PASS GO so nothing is missing.'),
 (1, 3, 'Book your ticket',     'Choose a convenient time slot and get your digital queue ticket.'),
 (1, 4, 'Go to the service',    'Arrive about 10 minutes early with your documents and show your ticket code at the desk.'),
 (1, 5, 'Receive your card',    'Follow the delivery instructions you are given and keep your receipt until the new card arrives.'),

 (2, 1, 'Check requirements',   'Check that your passport has expired or will soon, or that you need a first passport.'),
 (2, 2, 'Prepare documents',    'Collect your Citizen Card, a recent photo and your previous passport if you have one.'),
 (2, 3, 'Book your ticket',     'Pick a time slot and receive your digital queue ticket.'),
 (2, 4, 'Go to the service',    'Bring your documents and be ready to confirm your identity in person.'),
 (2, 5, 'Receive your passport','Follow the instructions about delivery or collection of your new passport.'),

 (3, 1, 'Check requirements',   'Make sure you have moved and have a document that shows your new address.'),
 (3, 2, 'Prepare documents',    'Gather your Citizen Card, tax number and proof of your new address.'),
 (3, 3, 'Book your ticket',     'Choose a time slot at the tax office and get your digital queue ticket.'),
 (3, 4, 'Go to the service',    'Show your ticket and documents and ask to update your tax address.'),
 (3, 5, 'Confirm the change',   'Ask for confirmation of the update and keep it with your records.'),

 (4, 1, 'Check requirements',   'Confirm that you do not already have a Social Security number.'),
 (4, 2, 'Prepare documents',    'Gather your Citizen Card, proof of address and an employment or enrolment proof.'),
 (4, 3, 'Book your ticket',     'Choose a time slot and receive your digital queue ticket.'),
 (4, 4, 'Go to the service',    'Present your ticket and documents and complete the registration form.'),
 (4, 5, 'Receive your number',  'You will be given your Social Security number. Store it safely.'),

 (5, 1, 'Check requirements',   'Check the expiry date of your driving licence and the categories you hold.'),
 (5, 2, 'Get a medical certificate', 'Visit a licensed doctor and obtain the medical certificate you need.'),
 (5, 3, 'Book your ticket',     'Choose a time slot at the transport institute and get your digital queue ticket.'),
 (5, 4, 'Go to the service',    'Bring your licence, medical certificate and ID, and show your ticket code.'),
 (5, 5, 'Receive your licence', 'Follow the instructions to receive your renewed licence.'),

 (6, 1, 'Check requirements',   'Confirm which procedure needs the certificate and what it must say.'),
 (6, 2, 'Prepare documents',    'Gather your Citizen Card and proof of address.'),
 (6, 3, 'Go to the Junta',      'Walk in during opening hours; no booking is needed for this service.'),
 (6, 4, 'Receive your certificate', 'Check the details on the certificate before you leave.'),

 (7, 1, 'Check requirements',   'Confirm that you live in the area covered by your local health centre.'),
 (7, 2, 'Prepare documents',    'Gather your Citizen Card, proof of address and your health user number if you have one.'),
 (7, 3, 'Book your ticket',     'Choose a time slot and get your digital queue ticket.'),
 (7, 4, 'Go to the health centre', 'Show your ticket and documents at the reception.'),
 (7, 5, 'Confirm your doctor',  'Ask who your assigned family doctor is and how to book consultations.');

-- ------------------------------------------------------------ time slots
-- Every weekday for the next 3 weeks, every 30 minutes from 09:00 to 16:30, 2 places each.
INSERT INTO time_slots (institution_id, starts_at, capacity)
SELECT i.id,
       ((CURRENT_DATE + d.n) + (TIME '09:00' + t.n * INTERVAL '30 minutes')) AT TIME ZONE 'Europe/Lisbon',
       2
FROM institutions i
CROSS JOIN generate_series(0, 20) AS d(n)
CROSS JOIN generate_series(0, 15) AS t(n)
WHERE EXTRACT(ISODOW FROM (CURRENT_DATE + d.n)) < 6;

-- ------------------------------------------------------------ demo user
-- Login: demo@passgo.pt / demo1234
INSERT INTO users (id, name, email, password_hash) VALUES
 (1, 'Demo Citizen', 'demo@passgo.pt', '$2y$10$WESKjKaSFTRn4o2YD.Iiw.f55oRlFX2SQO9QywDNVwfvfiT1YquQO');

INSERT INTO user_document_checks (user_id, service_id, document_id) VALUES (1, 1, 1);

INSERT INTO user_step_progress (user_id, step_id)
SELECT 1, id FROM guide_steps WHERE service_id = 1 AND position <= 2;

INSERT INTO appointments (user_id, service_id, slot_id, ticket_code, status)
VALUES (1, 3,
        (SELECT id FROM time_slots
          WHERE institution_id = 2 AND starts_at > now() + INTERVAL '30 hours'
          ORDER BY starts_at LIMIT 1),
        'PG-DEMO01', 'booked');

-- --------------------------------------------- keep serial sequences in sync
SELECT setval(pg_get_serial_sequence('categories',   'id'), (SELECT max(id) FROM categories));
SELECT setval(pg_get_serial_sequence('institutions', 'id'), (SELECT max(id) FROM institutions));
SELECT setval(pg_get_serial_sequence('services',     'id'), (SELECT max(id) FROM services));
SELECT setval(pg_get_serial_sequence('documents',    'id'), (SELECT max(id) FROM documents));
SELECT setval(pg_get_serial_sequence('users',        'id'), (SELECT max(id) FROM users));
