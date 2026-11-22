# Deploying PASS GO for free (Render + Neon)

Total time: about 15 minutes. Cost: 0 EUR. You need a GitHub account.
Result: a public `https://passgo-xxxx.onrender.com` address to submit on Moodle.

## 1. Create the database (Neon)
1. Go to https://neon.tech and sign up (GitHub login works).
2. Create a project named `passgo` (region: Europe, e.g. Frankfurt).
3. On the project dashboard click **Connect** and copy the connection string. It looks like
   `postgresql://user:password@ep-xxxx.eu-central-1.aws.neon.tech/neondb?sslmode=require`.

You do NOT need to run any SQL: the app creates the tables and demo data on its first start.

## 2. Put the code on GitHub
```
cd passgo
git init && git add . && git commit -m "PASS GO MVP"
# create an empty repository on github.com (e.g. passgo), then:
git remote add origin https://github.com/<you>/passgo.git
git branch -M main && git push -u origin main
```
Keep the repository **public** or invite the teachers: the brief asks for evidence of the LLM good practices (SPEC.md, CLAUDE.md, tests) "visible in the repository".

## 3. Create the web service (Render)
1. https://render.com -> sign up -> **New +** -> **Web Service** -> connect your GitHub repo.
2. Runtime: **Docker** (detected from the `Dockerfile`). Instance type: **Free**.
3. Environment variable: `DATABASE_URL` = the Neon string from step 1.
4. **Create Web Service**. The first build takes a few minutes.
5. Open the URL Render shows. Log in with the demo account: `demo@passgo.pt` / `demo1234`.

(`render.yaml` in the repo describes the same service if you prefer *New + -> Blueprint*.)

## 4. Before the demo
- The free Render service **sleeps after ~15 min without traffic** and takes ~30-60 s to wake up. Open the URL a few minutes before your slot so it is warm.
- Each start tops up time slots for the next 3 weeks, so booking always works.
- To reset the demo data: Render dashboard -> Shell -> `php bin/setup_db.php --reset`
  (if the Shell is not available on the free plan: in the Neon SQL editor drop the tables, then redeploy).

## Run locally
```
export DATABASE_URL=postgresql://user:pass@localhost:5432/passgo
php bin/setup_db.php
php -S localhost:8080 -t public
```

## Run the tests
```
createdb passgo_test      # the name must contain "test": the runner wipes this database
export PASSGO_DB_HOST=localhost PASSGO_DB_NAME=passgo_test PASSGO_DB_USER=... PASSGO_DB_PASSWORD=...
php tests/run.php
python3 tests/ux/screens_check.py http://localhost:8080 /tmp/shots   # needs Playwright
```

## Alternative: FEUP disk server
Copy the folder, point the web root to `public/`, copy `config/config.example.php` to `config/config.php` with your FEUP PostgreSQL details (and `schema`), then run `php bin/setup_db.php`.
