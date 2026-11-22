# PASS GO — MVP Specification (SPEC.md)

> Your personal admin assistant. Public services, simplified.
> Derived from Assignment 1 (PASS GO product specification deck). This file is the contract the LLM agent builds against.

## 1. Product in one paragraph

PASS GO helps people deal with Portuguese public services without wasting time. A user can **find** the right service, see **exactly which documents to bring**, follow a **step-by-step guide**, and **book a digital queue ticket**. The MVP implements these four pillars end to end with real data stored in PostgreSQL.

## 2. Users

| Persona | Need (from Assignment 1) |
|---|---|
| Student | "I just want to know exactly what to do." |
| Young professional | "I don't want to waste time in queues." |
| Family | "I need a simple way to keep track of everything." |
| Senior citizen | "I need clear information and simple steps." (drives large text, high contrast, simple flows) |

Roles in the MVP: **visitor** (not logged in, can browse) and **citizen** (logged in, can track progress and book). No admin UI in v1.

## 3. Scope

### In scope (v1 / MVP)
1. Services directory with search and category filter
2. Required-documents list per service, with a personal "ready" checklist
3. Step-by-step guides with personal progress tracking
4. Appointments (digital queue tickets): book, view, cancel
5. Account: register, log in, log out, edit name

### Out of scope (v1)
Real integration with government systems, payments, document upload, push/SMS notifications, admin back office, multi-language, native apps, real-time queue position, QR check-in.

## 4. User stories, priority and acceptance criteria

Priority: **M** = must (MVP), **S** = should (if time allows), **C** = could (not in v1).

### Epic A — Find services

**US-01 (M)** As a visitor, I want to search for a service by keyword so that I can quickly find what I need.
- Search matches service name, summary and institution name, case-insensitive.
- Results show service name, institution, category and estimated time.
- An empty result shows a friendly "no services found" message.
- Works without logging in.

**US-02 (M)** As a visitor, I want to filter services by category so that I can browse when I do not know the exact name.
- Categories are shown as selectable chips; the active one is visibly highlighted.
- Filter and keyword search can be combined.

**US-03 (M)** As a visitor, I want to see an institution's address, opening hours and contacts on a service page so that I know where and when to go.
- Service page shows institution name, address, city, opening hours, phone.

### Epic B — Prepare documents

**US-04 (M)** As a visitor, I want to see the list of required documents for a service so that I arrive prepared.
- Each document is marked **required** or **if applicable**.
- Notes (e.g. "issued in the last 3 months") are shown when present.

**US-05 (M)** As a logged-in citizen, I want to tick the documents I already have so that I know what is still missing.
- Ticks persist between sessions.
- A counter shows "X of Y required documents ready".
- A visitor sees the list read-only with a prompt to log in to track it.

### Epic C — Follow steps

**US-06 (M)** As a visitor, I want to read an ordered step-by-step guide for a service so that I know the whole process.
- Steps are numbered and ordered; each has a title and description.
- The guide page shows the estimated total time.

**US-07 (M)** As a logged-in citizen, I want to mark steps as done so that I can resume later where I stopped.
- Progress persists; a progress bar shows "X of Y steps".
- A completed step can be un-marked.
- The first not-done step is highlighted as "Next".

### Epic D — Queue tickets

**US-08 (M)** As a logged-in citizen, I want to see available time slots for a service so that I can choose a convenient time.
- Slots are listed by day for the next 14 days, only for the institution that offers the service.
- Past slots and full slots are not selectable (full ones are shown as "Full").
- Services that do not require appointments show a "no appointment needed" notice instead.

**US-09 (M)** As a logged-in citizen, I want to book a slot and receive a ticket so that I do not wait in line.
- A ticket has a unique code (format `PG-XXXXXX`), service, institution, date and time.
- A slot cannot be overbooked (capacity enforced, also under concurrent requests).
- A user cannot hold two active appointments for the same service.
- A user cannot book the same slot twice.
- After booking, the user sees the ticket and a reminder of documents still missing.

**US-10 (M)** As a logged-in citizen, I want to see my appointments so that I can manage them.
- Upcoming and past appointments are shown in separate sections.
- Each shows status: booked, cancelled or completed.

**US-11 (M)** As a logged-in citizen, I want to cancel an upcoming appointment so that the slot is released for others.
- Only own, booked, future appointments can be cancelled.
- A cancelled appointment frees one place in the slot.
- Cancellation asks for confirmation.

### Epic E — Account

**US-12 (M)** As a new user, I want to register with name, email and password.
- Email must be valid and unique; password at least 8 characters.
- Password is stored hashed (bcrypt via `password_hash`), never in plain text.
- Validation errors are shown next to the form, and entered values are kept.

**US-13 (M)** As a registered user, I want to log in and out.
- Wrong credentials show a generic error (no hint whether the email exists).
- Protected pages redirect to login and return the user to the page they wanted.

**US-14 (S)** As a logged-in user, I want to edit my name on a profile page.

### Could (not in v1)
**US-15 (C)** Reminders by email/SMS before an appointment. **US-16 (C)** Admin back office to manage services. **US-17 (C)** Family profiles (manage dependants).

## 5. Non-functional requirements

| Area | Requirement |
|---|---|
| Security | Prepared statements only; CSRF token on every POST; passwords hashed; session cookie HttpOnly + SameSite=Lax; output escaped; session id regenerated on login |
| Accessibility | WCAG AA text contrast; visible focus; labels on every field; touch targets at least 44px; semantic HTML; works with keyboard only |
| Responsive | Mobile-first; usable at 360px width; bottom tab bar on phones |
| Performance | Each page under 1 s on the FEUP server with seed data |
| Hosting | PHP (Apache) + PostgreSQL; reachable on the internet at a stable address |
| Data honesty | Seed data is illustrative demo data and must be checked against official sources before real use |

## 6. Data model (summary — see `database/schema.sql`)

`users`, `categories`, `institutions`, `services`, `documents`, `service_documents`, `guide_steps`, `user_document_checks`, `user_step_progress`, `time_slots`, `appointments`.

## 7. Screens

| # | Screen | File | Stories |
|---|---|---|---|
| 1 | Home | `public/index.php` | US-01, US-10 |
| 2 | Services / search | `public/services.php` | US-01, US-02 |
| 3 | Service detail | `public/service.php` | US-03, US-04, US-05 |
| 4 | Step-by-step guide | `public/guide.php` | US-06, US-07 |
| 5 | Book appointment | `public/book.php` | US-08, US-09 |
| 6 | My appointments | `public/appointments.php` | US-10, US-11 |
| 7 | Ticket | `public/appointment.php` | US-09 |
| 8 | Login / Register | `public/login.php`, `public/register.php` | US-12, US-13 |
| 9 | Profile | `public/profile.php` | US-14 |

## 8. Definition of done (per story)

1. Acceptance criteria demonstrably met in the browser.
2. Automated test covers the business rule or flow (`php tests/run.php` passes).
3. Page checked by hand at phone width (360px) and desktop.
4. No SQL string concatenation with user input; every POST has a CSRF check.
