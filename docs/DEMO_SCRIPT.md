# PASS GO — 5-minute live demo script

Open the app 5 minutes before (free hosting sleeps). Be logged OUT at the start. Demo account: demo@passgo.pt / demo1234. Have the repository open in a second tab.

| Time | What you do | What you say |
|---|---|---|
| 0:00 | Home page on a phone-width window | "PASS GO is a personal admin assistant for public services. Problem: scattered information, unclear documents, long queues. I built the MVP from my Assignment 1 spec." |
| 0:30 | Type "passport" in search; then clear and use a category chip | "Visitors can search and filter without an account: US-01, US-02." |
| 1:00 | Open **Renew your Citizen Card** | "Where and when, the documents to bring, marked required or if applicable." |
| 1:30 | Click **Log in** from the checklist prompt, log in as the demo user (wrong password first: generic error) | "Generic error: no hint if the email exists. After login I return to the page I wanted." |
| 2:00 | Tick a document -> counter changes; reload | "Saved in PostgreSQL per user, X of Y ready." |
| 2:30 | **Start guide** -> mark a step done, show progress bar and "Next" | "I can resume where I stopped." |
| 3:00 | **Book an appointment** -> pick a day, a time, confirm | "Slots come from the database; capacity is enforced inside a transaction so two people cannot take the last place." |
| 3:45 | Ticket page (code PG-XXXXXX) -> **My appointments** -> cancel one | "Status is kept (booked / cancelled), slot is released." |
| 4:15 | Switch to the repository: `SPEC.md`, `CLAUDE.md`, `docs/ARCHITECTURE.md`, `tests/` | "Before the first prompt I wrote the architecture, SPEC from my user stories and project rules. 83 automated tests, plus a browser UX check at phone width." |
| 4:45 | Show terminal `php tests/run.php` result (or screenshot) | "Every change was verified by running the tests and checking the page by hand." |

Backup if the site is slow: say the free tier was asleep, refresh once; the local copy runs with `php -S localhost:8080 -t public`.

Likely questions: *Why PHP/PostgreSQL?* (course stack, portable to FEUP). *How do you prevent double booking?* (`SELECT ... FOR UPDATE` on the slot + unique indexes). *What did the LLM get wrong / what did you change?* (prepare one real example).
