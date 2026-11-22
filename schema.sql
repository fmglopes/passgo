-- PASS GO — PostgreSQL schema (MVP)
-- Run: psql "$DSN" -f database/schema.sql
-- Re-running drops and recreates everything (demo/dev convenience).

SET client_min_messages TO WARNING;

DROP TABLE IF EXISTS appointments        CASCADE;
DROP TABLE IF EXISTS time_slots          CASCADE;
DROP TABLE IF EXISTS user_step_progress  CASCADE;
DROP TABLE IF EXISTS user_document_checks CASCADE;
DROP TABLE IF EXISTS guide_steps         CASCADE;
DROP TABLE IF EXISTS service_documents   CASCADE;
DROP TABLE IF EXISTS documents           CASCADE;
DROP TABLE IF EXISTS services            CASCADE;
DROP TABLE IF EXISTS institutions        CASCADE;
DROP TABLE IF EXISTS categories          CASCADE;
DROP TABLE IF EXISTS users               CASCADE;

-- ---------------------------------------------------------------- users
CREATE TABLE users (
    id            SERIAL PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now()
);
-- e-mail is unique regardless of upper/lower case
CREATE UNIQUE INDEX users_email_lower_uq ON users (lower(email));

-- ----------------------------------------------------------- categories
CREATE TABLE categories (
    id    SERIAL PRIMARY KEY,
    name  VARCHAR(80) NOT NULL UNIQUE,
    slug  VARCHAR(80) NOT NULL UNIQUE
);

-- --------------------------------------------------------- institutions
CREATE TABLE institutions (
    id            SERIAL PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    address       VARCHAR(200) NOT NULL,
    city          VARCHAR(80)  NOT NULL,
    phone         VARCHAR(40),
    opening_hours VARCHAR(200) NOT NULL,
    website       VARCHAR(200)
);

-- ------------------------------------------------------------- services
CREATE TABLE services (
    id                   SERIAL PRIMARY KEY,
    institution_id       INTEGER      NOT NULL REFERENCES institutions(id) ON DELETE RESTRICT,
    category_id          INTEGER      NOT NULL REFERENCES categories(id)   ON DELETE RESTRICT,
    name                 VARCHAR(150) NOT NULL,
    summary              VARCHAR(300) NOT NULL,
    description          TEXT         NOT NULL,
    estimated_minutes    INTEGER      NOT NULL CHECK (estimated_minutes > 0),
    requires_appointment BOOLEAN      NOT NULL DEFAULT TRUE
);
CREATE INDEX services_category_idx    ON services (category_id);
CREATE INDEX services_institution_idx ON services (institution_id);

-- ------------------------------------------------------------ documents
CREATE TABLE documents (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(150) NOT NULL UNIQUE,
    description VARCHAR(300)
);

-- Which documents a service needs (many-to-many with attributes)
CREATE TABLE service_documents (
    service_id  INTEGER NOT NULL REFERENCES services(id)  ON DELETE CASCADE,
    document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE RESTRICT,
    is_required BOOLEAN NOT NULL DEFAULT TRUE,
    note        VARCHAR(250),
    PRIMARY KEY (service_id, document_id)
);

-- ---------------------------------------------------------- guide steps
CREATE TABLE guide_steps (
    id          SERIAL PRIMARY KEY,
    service_id  INTEGER      NOT NULL REFERENCES services(id) ON DELETE CASCADE,
    position    INTEGER      NOT NULL CHECK (position > 0),
    title       VARCHAR(150) NOT NULL,
    description TEXT         NOT NULL,
    UNIQUE (service_id, position)
);

-- ------------------------------------------- per-user checklist/progress
CREATE TABLE user_document_checks (
    user_id     INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    service_id  INTEGER     NOT NULL,
    document_id INTEGER     NOT NULL,
    checked_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, service_id, document_id),
    FOREIGN KEY (service_id, document_id)
        REFERENCES service_documents (service_id, document_id) ON DELETE CASCADE
);

CREATE TABLE user_step_progress (
    user_id      INTEGER     NOT NULL REFERENCES users(id)       ON DELETE CASCADE,
    step_id      INTEGER     NOT NULL REFERENCES guide_steps(id) ON DELETE CASCADE,
    completed_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, step_id)
);

-- ----------------------------------------------------------- time slots
-- One row = one time window at one institution, with a number of places.
CREATE TABLE time_slots (
    id             SERIAL PRIMARY KEY,
    institution_id INTEGER     NOT NULL REFERENCES institutions(id) ON DELETE CASCADE,
    starts_at      TIMESTAMPTZ NOT NULL,
    capacity       INTEGER     NOT NULL DEFAULT 2 CHECK (capacity > 0),
    UNIQUE (institution_id, starts_at)
);
CREATE INDEX time_slots_starts_idx ON time_slots (starts_at);

-- --------------------------------------------------------- appointments
CREATE TABLE appointments (
    id           SERIAL PRIMARY KEY,
    user_id      INTEGER     NOT NULL REFERENCES users(id)      ON DELETE CASCADE,
    service_id   INTEGER     NOT NULL REFERENCES services(id)   ON DELETE RESTRICT,
    slot_id      INTEGER     NOT NULL REFERENCES time_slots(id) ON DELETE RESTRICT,
    ticket_code  VARCHAR(12) NOT NULL UNIQUE,
    status       VARCHAR(12) NOT NULL DEFAULT 'booked'
                 CHECK (status IN ('booked', 'cancelled', 'completed')),
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    cancelled_at TIMESTAMPTZ
);
CREATE INDEX appointments_user_idx ON appointments (user_id);
CREATE INDEX appointments_slot_idx ON appointments (slot_id);
-- Business rules enforced by the database as a safety net:
-- a user has at most one active appointment per service, and per slot.
CREATE UNIQUE INDEX appointments_one_active_per_service
    ON appointments (user_id, service_id) WHERE status = 'booked';
CREATE UNIQUE INDEX appointments_one_active_per_slot
    ON appointments (user_id, slot_id) WHERE status = 'booked';
