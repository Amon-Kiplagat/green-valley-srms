# Green Valley Secondary School — Student Records Management System (SRMS)

A student records management system for Green Valley Secondary School, Murang'a County.
The static HTML pages have been converted into a fully functional web application using:

- **HTML** — page structure (your original layout, preserved)
- **CSS** — styling (`css/style.css`), including the responsive mobile layout
- **JavaScript** — client-side validation and the live grade preview (`js/script.js`),
  plus the Progressive Web App layer that makes the site installable on a phone and
  usable offline (`js/pwa.js` + `service-worker.js`)
- **PHP** — the backend / API layer (all database access happens here)
- **PostgreSQL** — the database
- **Python** — an optional, server-side exam-analytics tool (`tools/analytics.py`) that
  reads the existing tables and produces PDF + CSV reports

The browser never talks to PostgreSQL directly. Every page and form goes through PHP,
which uses **PDO prepared statements**. Database credentials live in a `.env` file on the
server and are never exposed to HTML or JavaScript.

---

## 1. File structure and where everything goes

The whole application already lives in this folder (the one you selected). When you run a
PHP server, point its **document root at this folder**. The layout is:

```
green_valley_srms_html_pages/   (this folder = your web root)
├── .env                     # your real DB credentials (NOT committed / NOT web-served content)
├── .env.example             # template to copy from
│
│   # --- old static mockups, kept only as forwarders ---
├── login.html               # each *.html now redirects to its working *.php page
├── dashboard.html           #   (plain redirect stubs — no data in them)
├── students.html            #   ...
│
├── config/
│   └── database.php         # PDO + PostgreSQL connection (reads .env)
│
├── includes/
│   ├── auth.php             # sessions, login state, role checks (staff + student)
│   ├── functions.php        # helpers: escaping, grade calc, CSRF, flash messages, logos
│   ├── header.php           # shared page head + topbar (opens .main)
│   ├── sidebar.php          # shared navigation menu
│   ├── footer.php           # shared page footer (loads script.js)
│   ├── report_header.php    # report card letterhead: crest | school name | second logo
│   ├── portal_header.php    # student portal page head, top bar and tabs (opens .main)
│   ├── portal_footer.php    # student portal page footer (loads script.js)
│   └── student_portal.php   # student login rules: lockout, password rules, slip passwords
│
├── actions/                 # form handlers (POST targets) — one per entity
│   ├── student_actions.php
│   ├── teacher_actions.php
│   ├── class_actions.php
│   ├── subject_actions.php
│   ├── exam_actions.php
│   ├── result_actions.php
│   └── user_actions.php     # approve / deactivate / delete login accounts (admin)
│
├── css/
│   └── style.css            # all styling
│
├── js/
│   ├── script.js            # client-side validation + grade preview
│   └── pwa.js               # PWA: service-worker registration, mobile drawer, install
│
├── sql/
│   ├── schema.sql           # creates the 7 tables, constraints and indexes
│   ├── seed.sql             # optional sample data (incl. login accounts)
│   ├── migration_2026_10_activity_log_and_phone.sql   # adds activity_log + phone columns
│   ├── migration_2026_10_teacher_signup.sql           # adds users.email / users.phone (teacher sign-up)
│   ├── migration_2026_10_student_remarks.sql          # adds students.class_teacher_remark / subject_teacher_remark
│   ├── migration_2026_10_student_portal.sql           # adds the student portal login columns
│   └── migration_2026_10_student_portal_security.sql  # adds lockout + "choose your own password" columns
│
├── images/
│   └── branding/            # drop school-logo.png (+ optional secondary-logo.png) here
│       └── README.txt       #   for the report card letterhead (see §10)
│
│   # --- Progressive Web App (mobile + offline) ---
├── manifest.webmanifest     # installable-app metadata (name, icons, colours)
├── service-worker.js        # offline layer — caches STATIC files only, never the DB
├── offline.html             # shown when a page is opened with no connection
├── icons/                   # app icons used by the manifest and browsers
│   ├── icon-192.png
│   ├── icon-512.png
│   ├── icon-maskable-512.png
│   ├── apple-touch-icon.png
│   └── favicon-32.png
│
│   # --- Python analytics (server-side, run from a terminal) ---
├── tools/
│   ├── analytics.py         # exam analytics → PDF + CSV (reads existing tables)
│   ├── report_card.py       # one student's report card → branded PDF (used by report_pdf.php)
│   └── requirements.txt     # Python dependencies (psycopg2 + reportlab)
├── exports/                 # generated reports land here (created automatically)
│
├── index.php                # entry point → redirects to dashboard or login
├── login.php                # login page  (replaces login.html)
├── signup.php               # teacher self-service sign-up (creates an account pending admin approval)
├── logout.php               # ends the session
├── dashboard.php            # dashboard  (replaces dashboard.html)
├── students.php             # students    (replaces students.html)
├── teachers.php             # teachers    (replaces teachers.html)
├── classes.php              # classes     (replaces classes.html)
├── subjects.php             # subjects    (replaces subjects.html)
├── exams.php                # exams       (replaces exams.html)
├── results.php              # marks entry (new — the results interface)
├── reports.php              # reports     (replaces reports.html)
├── report_pdf.php           # streams a student report card as a PDF (runs tools/report_card.py)
├── users.php                # Accounts: admin approves/manages login accounts
├── portal_accounts.php      # Student Portal: set up a class, print login slips, unlock (admin)
│
│   # --- Student portal (students see only their own results, see §13) ---
├── student_login.php        # student sign-in (separate from the staff login)
├── student_home.php         # "Overview": latest exam at a glance, recent exams
├── student_results.php      # "My Results": the student's own report + PDF download
├── student_profile.php      # "Profile": their class, teachers, guardian, account
├── student_password.php     # student changes their password (forced at first sign-in)
│
└── create_admin.php         # one-off utility to create/reset a login (DELETE after use)
```

Each old `*.html` file has been replaced by the matching `*.php` file. The `.html` files
that remain are tiny redirects that forward to the working `.php` pages, so old links keep
working; the working `.php` pages are the single source of truth. The forms
now submit to the handlers in `actions/`, which validate the input and talk to PostgreSQL.

> **Important:** always open the app through the running PHP server (e.g.
> `http://localhost:8000/`), **not** by double-clicking an `.html` or `.php` file in your
> file manager. PHP only runs when served by a server; opening files directly shows the old
> static version or raw code.

---

## 2. Set up the PostgreSQL database

You already run PostgreSQL 18 locally with a database called `green_valley` (user `mole`).
The commands below create the tables and load the sample data.

**a. Create the database** (skip if `green_valley` already exists):

```bash
createdb -U mole green_valley
```

or from inside `psql`:

```sql
CREATE DATABASE green_valley;
```

**b. Create the tables, constraints and indexes:**

```bash
psql -U mole -d green_valley -f sql/schema.sql
```

**c. (Optional) Load sample data, including login accounts:**

```bash
psql -U mole -d green_valley -f sql/seed.sql
```

> `schema.sql` drops and recreates the tables each time it runs, so it is safe to re-run
> during development — but it **erases existing data**. Do not run it on a database that
> already holds real records you want to keep.

### What the SQL creates

`schema.sql` builds eight tables with the relationships your spec requires:

| Table          | Key columns | Relationships |
|----------------|-------------|---------------|
| `teachers`     | `teacher_id` (PK), `staff_no` (unique) | — |
| `users`        | `user_id` (PK), `username` (unique) | `teacher_id → teachers` (SET NULL) |
| `classes`      | `class_id` (PK), `class_name` (unique) | `teacher_id → teachers` (SET NULL) |
| `students`     | `student_id` (PK), `admission_no` (unique) | `class_id → classes` (SET NULL) |
| `subjects`     | `subject_id` (PK), `subject_code` (unique) | `teacher_id → teachers` (SET NULL) |
| `exams`        | `exam_id` (PK) | unique on (`exam_name`, `term`, `year`) |
| `results`      | `result_id` (PK) | `student_id`, `subject_id`, `exam_id` (all CASCADE) |
| `activity_log` | `log_id` (PK) | `user_id → users` (SET NULL) |

Duplicate-prevention is enforced by the database itself, not just by PHP:

- `students.admission_no` is **UNIQUE** — no two students share an admission number.
- `results` has a **UNIQUE (student_id, subject_id, exam_id)** constraint — a student
  cannot have two marks for the same subject in the same exam.
- `exams` is **UNIQUE (exam_name, term, year)**.
- `teachers.staff_no`, `subjects.subject_code`, `classes.class_name`, `users.username`
  are all UNIQUE.

Phone numbers are constrained too. Both `students.guardian_phone` and `teachers.phone`
carry a `CHECK` that only accepts **digits** in the canonical Kenyan local form — ten
digits beginning `07` or `01`, e.g. `0712345678`. See section 12 for the full rule.

Indexes are added on the foreign-key and search columns for performance.

Reports are **generated on the fly** by SQL joins across `students`, `subjects`, `exams`
and `results` — no report data is duplicated or stored anywhere.

### Upgrading an existing database

`schema.sql` **drops and recreates** every table, so running it would delete records you
already have. To add the newer pieces — the `activity_log` table and the phone-number
constraints — to a database that already holds real data, run the migration instead:

```bash
psql -h localhost -U mole -d green_valley -f sql/migration_2026_10_activity_log_and_phone.sql
```

It is safe to run more than once. It creates `activity_log` only if it is missing, tidies
any existing phone numbers into the canonical form (stripping spaces and dashes,
converting `+254…` to `0…`), then adds the `CHECK` constraints as `NOT VALID` so an
unusual legacy number cannot make the migration fail. The comments at the bottom of that
file show how to list any leftovers and finish validating once they are fixed.

To enable **teacher self-service sign-up** (see §5) on an existing database, also run:

```bash
psql -h localhost -U mole -d green_valley -f sql/migration_2026_10_teacher_signup.sql
```

This adds `users.email` and `users.phone` (so a teacher's own contact details, captured at
sign-up, are visible to the admin at approval time) plus a small index for fast
"pending approval" look-ups. It is additive, changes no existing data, and is safe to
re-run.

To enable **teacher remarks on report cards** (see §10) on an existing database, also run:

```bash
psql -h localhost -U mole -d green_valley -f sql/migration_2026_10_student_remarks.sql
```

This adds two free-text columns, `students.class_teacher_remark` and
`students.subject_teacher_remark`, which hold the overall (per-student) comments shown on
every report card. It is additive, changes no existing data, and is safe to re-run.

To enable the **student portal** (see §13) on an existing database, run these two, **in
this order**:

```bash
psql -h localhost -U mole -d green_valley -f sql/migration_2026_10_student_portal.sql
psql -h localhost -U mole -d green_valley -f sql/migration_2026_10_student_portal_security.sql
```

The first adds the login columns (`portal_username`, `portal_password_hash`,
`portal_enabled`, plus a case-insensitive unique index on the username). The second adds
`portal_must_change`, `portal_failed_attempts`, `portal_locked_until` and
`portal_last_login`, which drive the "choose your own password" step and the sign-in
lockout. Both are additive, change no existing data, and are safe to re-run. Run them
**before** you start using the updated pages — the Students page, the student sign-in and
the Student Portal page all read these columns.

---

## 3. Configure the connection

Credentials are read from a `.env` file so they never appear in HTML, JavaScript, or the
PHP source. Copy the template and fill in your real password:

```bash
cp .env.example .env
```

Then edit `.env`:

```ini
DB_HOST=localhost
DB_PORT=5432
DB_NAME=green_valley
DB_USER=mole
DB_PASS=your_real_password_here
APP_ENV=development

# Optional: interpreter used to generate PDF report cards (see §10).
# Defaults to "python3"; set this if your reportlab/psycopg2 live in a venv.
# PYTHON_BIN=/path/to/venv/bin/python

# Optional: the student sign-in address printed on login slips (see §13).
# Left unset, the slips show the address the admin opened the page on.
# PORTAL_URL=https://records.greenvalley.sc.ke/student_login.php
```

`config/database.php` loads this file and creates a single PDO connection configured for:
prepared statements only (no emulation), exceptions on error, and associative-array
fetches. Set `APP_ENV=production` on a live server to hide detailed error messages.

> **Keep `.env` private.** It holds your database password. Do not put it in a public
> repository, and make sure your web server does not serve dot-files.

---

## 4. Run it locally

The simplest way is PHP's built-in web server. Open a terminal **in this folder** (the one
you selected) and run:

```bash
php -S localhost:8000
```

Then open <http://localhost:8000/> in your browser. You will be redirected to the login
page. (You need PHP 8.0+ with the `pdo_pgsql` extension enabled — check with
`php -m | grep pgsql`.)

Inside a JetBrains IDE (PhpStorm / IntelliJ IDEA Ultimate) you can instead use
**Run → Edit Configurations → + → PHP Built-in Web Server**, with host `localhost`, port
`8000`, and **document root set to this folder**.

If you prefer Apache/XAMPP, place this folder in your web root and browse to it.

---

## 5. Logging in

If you loaded `seed.sql`, two accounts exist:

| Role    | Username  | Password     | Can do |
|---------|-----------|--------------|--------|
| Admin   | `admin`   | `admin123`   | Everything |
| Teacher | `teacher` | `teacher123` | View all records, manage students, enter/edit marks |

**Change these passwords immediately** in a real deployment.

### Creating or resetting an account

`create_admin.php` is a small utility for making a login without touching SQL by hand.
Passwords are stored as bcrypt hashes (`password_hash()`), never in plain text.

From the command line:

```bash
php create_admin.php <username> <password> "Full Name" <admin|teacher>
```

Or in a browser, visit `create_admin.php?setup=1` once to create the default `admin`
account. **Delete `create_admin.php` from the server as soon as you are done** — leaving
it in place is a security risk.

### Teacher self-service sign-up (needs admin approval)

Teachers can request their own account from the login page via the **"Create an account"**
link, which opens `signup.php`. They enter their name, a username and password, and
optionally an email, phone and the staff number the school issued them (if the staff number
matches an unlinked `teachers` record, the login is linked to it automatically).

The key safeguard: a new sign-up is created with **`is_active = FALSE`, so it cannot sign in
yet**. Because these accounts can see student records, an administrator must approve each
one first. Approvals happen on the new **Accounts** page (`users.php`, admin-only, under
*Administration* in the sidebar):

- **Pending approval & inactive** lists new sign-ups. Click **Approve** to activate the
  account (the teacher can then log in), or **Delete** to reject it.
- **Active accounts** lets an admin **Deactivate** (revoke sign-in) or **Delete** an account.

To prevent anyone locking the whole school out, you cannot deactivate or delete your own
account, nor the last remaining active administrator. Every sign-up, approval,
deactivation and deletion is written to the activity log (§11). Requires the
`migration_2026_10_teacher_signup.sql` migration (§2).

---

## 6. Who can do what (roles)

The system has two roles, checked on every page and every action:

- **Admin** — full access: add/edit/delete students, teachers, classes, subjects, exams;
  enter and edit marks; view all reports; delete records. Admins also review teacher
  sign-ups and manage login accounts on the **Accounts** page (`users.php`).
- **Teacher** — can view all records, manage students, and enter/edit marks. Managing
  teachers, classes, subjects and exams, and *all* delete operations, are restricted to
  admins.
- **Student** — signs in on the separate student portal (§13) and can only ever see their
  **own** results and report card. A student session can never open a staff page.

Anyone who is not logged in is redirected to the login page — no page can be reached by
typing its URL directly without a session.

---

## 7. Security summary

- **PDO prepared statements everywhere** — protects against SQL injection. Values are
  always bound, never concatenated into query strings.
- **Passwords hashed** with `password_hash()` / verified with `password_verify()`
  (bcrypt). Plain-text passwords are never stored. Hashes are upgraded automatically on
  login if the algorithm changes (`password_needs_rehash()`).
- **PHP sessions** with hardened cookies (HttpOnly, SameSite=Lax, Secure over HTTPS).
  The session ID is regenerated on login to prevent fixation.
- **CSRF tokens** on every state-changing form and on delete links, checked with
  `hash_equals()`.
- **Input validation** on both the client (JavaScript) and the server (PHP). Client-side
  checks are for convenience only; the PHP checks are authoritative.
- **Output escaping** — all database values are printed through `htmlspecialchars()`
  (the `e()` helper) to prevent cross-site scripting.
- **Role-based authorization** — `require_login()` and `require_role('admin')` guard the
  pages and actions; `require_student()` guards the student portal.
- **Student portal lockout** — 5 wrong passwords in a row lock a student login for 15
  minutes, and a password the school set must be replaced at the first sign-in (§13).
- **Credentials in `.env` only** — never in HTML, JavaScript, or committed source.

---

## 8. Design

Your original look and navigation are preserved. The inline styles from the old HTML
pages were moved verbatim into `css/style.css` (the navy sidebar, stat cards, tables and
form grid are unchanged); only small additive rules were introduced — for the active menu
item, flash/alert messages, the grade badge, search bars, and print styling for reports.
The static sample rows in the old tables are now replaced by live PostgreSQL data.

---

## 9. Install it on a phone + use it offline (PWA)

The app is now a **Progressive Web App (PWA)**: on top of the normal website, it can be
installed to a phone's home screen and keeps working when the connection drops. This is
pure front-end JavaScript — it never touches the database or your credentials.

**What was added**

- `manifest.webmanifest` — tells the browser the app's name, colours and icons so it can
  be installed like a native app. Linked from `includes/header.php`.
- `service-worker.js` — a small background script the browser runs. It caches the **static
  files only** (CSS, JavaScript, icons, the offline page) and serves pages network-first,
  falling back to a cached copy and then to `offline.html` when there is no signal. It
  never connects to PostgreSQL.
- `offline.html` — a friendly "you're offline" page.
- `js/pwa.js` — registers the service worker, runs the mobile menu drawer (the ☰ button in
  the top bar), and shows an **Install app** button when the browser offers one.

**Installing on a phone**

1. Serve the app somewhere the phone can reach it over **HTTPS**, or use it on
   **`localhost`** (service workers are disabled on plain `http://` to other devices — see
   the note below).
2. Open the site in Chrome (Android) or Safari (iPhone).
3. Android/Chrome: tap the **Install app** button, or browser menu → *Install app* /
   *Add to Home screen*. iPhone/Safari: **Share → Add to Home Screen**.
4. The app opens in its own window with the Green Valley icon and no address bar.

**What works offline**

Opening the app with no connection shows `offline.html`, and any page you *already visited
in that session* can still be re-opened from the cache. Live data (adding or editing
records) always needs the server, because PHP + PostgreSQL run there — so the app tells you
clearly when you're offline instead of showing stale or broken forms.

**Shared-device privacy.** On sign-out, `js/pwa.js` asks the service worker to wipe its
cached pages, so student records don't linger in the cache for the next person on a shared
school computer or phone.

> **The `localhost` / HTTPS rule.** Browsers only allow service workers on `https://` or on
> `http://localhost`. So the install/offline features work when you run
> `php -S localhost:8000` on the same machine, or once the app is deployed behind HTTPS. If
> you open the app from another phone via a plain `http://<your-ip>:8000` address, it still
> works as a normal website, but install/offline stay disabled until you add HTTPS. The app
> is fully functional either way — the PWA layer is a progressive enhancement.

---

## 10. Exam analytics reports (Python)

`tools/analytics.py` is an optional command-line tool that turns the marks already in the
database into a polished **PDF** report plus a **CSV**. It is written in Python and run from
a terminal by staff — never from the browser. It **reads** the existing `students`,
`subjects`, `classes`, `exams` and `results` tables and **stores nothing** back; every
figure is computed live, so no report data is duplicated in the database.

It reuses the **same `.env`** the PHP app uses, so the database password stays in one place
and is never copied into the script.

**Install the dependencies once:**

```bash
pip install -r tools/requirements.txt
```

(or on Arch, if you prefer system packages: `sudo pacman -S python-psycopg2 python-reportlab`)

**Run it from the project root:**

```bash
python3 tools/analytics.py                 # all examinations, top 10 students
python3 tools/analytics.py --list-exams    # list examinations with their ids
python3 tools/analytics.py --exam-id 3     # limit to one examination
python3 tools/analytics.py --top 20        # show the top 20 students
python3 tools/analytics.py --format pdf    # pdf | csv | both  (default: both)
python3 tools/analytics.py --self-test     # sample data, no database (preview the layout)
```

Reports are written to the `exports/` folder, e.g. `exports/analytics_all_20261002.pdf`.

**What's in the report**

- Overview counts (students, subjects, exams, marks recorded)
- A grade-distribution bar chart (KCSE 12-point A…E)
- Per-exam summary: entries, mean, lowest, highest, mean grade
- Subject performance and class performance, ranked by mean mark
- The top students by average mark

Grades use exactly the same bands as the app (`includes/functions.php`), so the Python
report and the on-screen report cards always agree.

### Downloadable student report cards (PDF, from the browser)

Unlike the analytics tool above, this one is wired straight into the web app. On the
**Reports** page, after you generate a student's report, a **Download PDF** button sits
next to **Print Report**. It produces a branded, pine-and-gold report card as a proper PDF —
the same student header, subject marks and grades, total, average, mean grade and position
in class that the page shows.

How it fits together:

- `tools/report_card.py` is the generator. It reuses the shared helpers in `analytics.py`
  (grade bands, `.env` loading, brand colours), computes one student's card live from the
  database, and renders it with reportlab. It can also run standalone:

  ```bash
  python3 tools/report_card.py --student 1 --exam 2    # writes exports/report_card_…pdf
  python3 tools/report_card.py --student 1             # all examinations
  python3 tools/report_card.py --self-test             # sample card, no database
  ```

- `report_pdf.php` is the browser endpoint. It checks the user is logged in, then runs the
  Python generator with `--stdout` using `proc_open()` with an **argument array** (no shell,
  so the ids can't be used for injection) and streams the PDF straight to the browser as a
  download. The interpreter is `python3` by default, or whatever you set as `PYTHON_BIN` in
  `.env` (handy if reportlab/psycopg2 live in a virtualenv). Each download is recorded in the
  activity log (§11).

Dependencies are the same `tools/requirements.txt` as above (`psycopg2` + `reportlab`), so
if the analytics tool runs, report-card downloads will too.

### School logos on the report card

Every report card has a letterhead: the **school crest** on the left, the school name in
the middle, and an optional **second logo** on the right (for example the Ministry of
Education, or a sponsor). It appears in all three views — on screen (Reports page and the
student portal), in print, and in the PDF.

To use your own logos, drop image files into `images/branding/`:

| File                 | Shown          | If the file is missing |
|----------------------|----------------|------------------------|
| `school-logo.png`    | top-left       | the app's GV mark (`icons/icon-512.png`) is used |
| `secondary-logo.png` | top-right      | the slot stays empty; the school name stays centred |

`.jpg` / `.jpeg` work too (e.g. `school-logo.jpg`). A roughly square image of 400–800 px
with a transparent background looks best; a non-square logo is scaled to fit, never
stretched, and large files are shrunk for the PDF automatically. The crest is also printed
on the student portal login slips (§13).

No restart or code change is needed. Logo addresses carry a version stamp taken from the
file's modification time, so a replaced logo shows up on the next page load — even in the
installed app, whose offline cache would otherwise keep the old picture. PHP
(`report_logos()` in `includes/functions.php`) and Python (`report_logos()` in
`tools/report_card.py`) look for the same file names; keep them in step if you change
them. An unreadable image never breaks a download: the PDF is built without it and a
warning is written to the PHP error log.

### Teacher remarks on the report card

Each student record carries two optional, overall (per-student) remarks — a **Class
Teacher's remark** and a **Subject Teacher's remark** — entered on the student's add/edit
form (`students.php`). They are not tied to a single exam; they are the general comments
that belong on the report card.

Once saved, they appear in all three views of the report, which always stay in step because
they read the same two columns:

- **on screen** — a *Remarks* block under the marks table on the Reports page (`reports.php`);
- **in print** — the same block is part of the printable report (Print Report);
- **in the PDF** — a *Remarks* section on the downloaded card (`tools/report_card.py`);
- **in the student portal** — the same block under the marks on *My Results*
  (`student_results.php`), so the student reads what their teachers wrote.

When a remark is blank, the report prints a **ruled line** in its place, so the class or
subject teacher can fill it in by hand on a printed copy. The fields are free text, capped
at 500 characters on save to keep the card tidy, and stored in `TEXT` columns added by
`sql/migration_2026_10_student_remarks.sql` (see §2). Teachers as well as admins can edit
them, since both roles manage student records.

---

## 11. System activity log (who was last in, and what they did)

The dashboard carries a **System Activity** card. At the top it answers the question
"who was last in the system?" — the most recent person, their role, what they did, and how
long ago — and below it a short table lists the latest actions across the whole app.

Every action is recorded in a dedicated `activity_log` table:

- **Sessions** — successful sign-ins, sign-outs, and *failed* sign-in attempts (the
  username that was tried is recorded even though no session was created).
- **Records** — every add, edit and delete of a student, teacher, class, subject, exam or
  set of marks. Deletes capture the record's name *before* it is removed, so the trail
  still reads "Deleted Student — Mary Wanjiku (GV001)" rather than a blank.

Each row stores *who* (user id, username, full name and role), *what* (a short action verb,
the entity type, and a human-readable label), *when* (`created_at`), and the caller's IP
address. The who/what columns are **denormalised on purpose** — the username, full name and
role are copied into the row at the time of the action, so the history still reads correctly
even if that user account is later renamed or deleted.

Logging is best-effort and never gets in the user's way: `log_activity()` in
`includes/functions.php` wraps the whole insert in a `try/catch`, so if the audit write ever
fails (for example, the table has not been created yet) the user's save still succeeds and
the app simply carries on without that one trail entry.

> **Upgrading?** The `activity_log` table ships in `schema.sql`, but if you already have a
> live database, add it with the migration in section 2
> (`sql/migration_2026_10_activity_log_and_phone.sql`). Until then the dashboard shows a
> short note telling you to run it, rather than erroring.

---

## 12. Phone numbers: digits only

Both the **parent/guardian phone** (students) and the **teacher phone** fields accept a
phone number and **nothing else** — no names, notes, or stray letters can be saved in them.
The field is optional, so it may be left blank, but if something is entered it must be a
valid Kenyan mobile number.

While typing you may use the familiar separators — spaces, dashes, dots and brackets — and
any of the usual ways of writing the country code. Whatever you enter is **normalised to one
canonical 10-digit local form** before it is stored:

| You type | What is stored |
|----------|----------------|
| `0712345678`        | `0712345678` |
| `0712 345 678`      | `0712345678` |
| `+254 712 345 678`  | `0712345678` |
| `254712345678`      | `0712345678` |
| `00254712345678`    | `0712345678` |
| `712345678`         | `0712345678` |

Valid numbers are ten digits beginning `07` or `01` (the Safaricom/Airtel `7xx` and the newer
`1xx` ranges). Anything else — letters, a `+` in the wrong place, a wrong country code, too
many or too few digits — is rejected with a clear message, and the record is not saved.

The rule is enforced in **three layers**, each a safety net for the one before it:

1. **JavaScript** (`js/script.js`) cleans the field as you type and flags a bad number before
   the form is submitted — convenience only.
2. **PHP** (`normalize_phone()` in `includes/functions.php`) is the authoritative check: it
   runs on the server for every save and decides the exact value that gets stored. The
   client-side check can be bypassed; this one cannot.
3. **PostgreSQL** (`CHECK (… ~ '^0[71][0-9]{8}$')` on `students.guardian_phone` and
   `teachers.phone`) is the last line of defence, so the database itself refuses to hold a
   malformed number no matter how a write reaches it.

Because PHP always normalises to the same `0…` shape the database expects, anything the app
accepts the database accepts too — the three layers can never disagree.

---

## 13. Student portal (students see their own results)

Students have their own sign-in page, **`student_login.php`** (linked from the staff login
as *"Student? View your results"*). Once signed in they land on the portal, which has three
tabs along the top, on the same page on a phone as on a desktop:

- **Overview** (`student_home.php`) — their latest examination at a glance: average, mean
  grade and position in class, how that average compares with the examination before it,
  and a short list of recent examinations, each linking to its marks. It ends with their
  portal account (sign-in name, last sign-in).
- **My Results** (`student_results.php`) — the full report: their own marks, total,
  average, mean grade, position in class and teacher remarks, with an examination filter
  and **Download PDF** and **Print Report**. This is the same report card the staff Reports
  page produces, letterhead included.
- **Profile** (`student_profile.php`) — read-only: name, admission number, gender, date of
  birth, class and class teacher, the subjects they take with each subject's teacher, their
  parent/guardian's name and phone, and their portal account.

Every query is built from the student id held in the session, so editing the URL can never
reveal another student's results, and a student session can never open a staff page. The
shared top bar (`includes/portal_header.php`) offers **Change password** and **Sign out**
on every tab, and re-checks the student's access on each page: an admin switching a login
off ends the session at the student's next click, wherever they are.

Students cannot create their own accounts; an admin sets them up.

Needs both student portal migrations (§2).

### Setting up a whole class (login slips)

**Administration → Student Portal** (`portal_accounts.php`, admins only):

1. Choose the class and click **Create missing logins**.
2. Every student in the class without a login gets one: the **username is their admission
   number** (characters other than letters, digits, `.`, `-` and `_` become `-`, so
   `GV/2024/001` becomes `GV-2024-001`), and the password is a random 8-character one
   with no look-alike characters (no `0`/`O`, `1`/`l`).
3. The next page shows one **login slip** per student — crest, name, admission number,
   class, username, password and the sign-in address. Click **Print slips**, cut along
   the dashed lines and hand them out.

The passwords are shown **only once**. Only their hashes are stored, they never appear in a
URL, and the page is sent with `Cache-Control: no-store`, so neither the browser nor the
offline cache keeps a copy. Reloading or going back cannot show them again (the form uses a
one-time token, so it also cannot reset the class twice by accident). If slips are lost,
choose the class again and use **Reset all passwords**, which gives everyone in the class a
new password and slip — or reset a single student from the Students page. Either way the
new password must be at least 8 characters, the same minimum the student meets when they
choose their own.

Students who already have a login are left alone by *Create missing logins*. Students whose
access an admin has switched off stay off, even with *Reset all passwords*. A student is
skipped, with the reason listed, if their admission number is too short to use or the
username is already taken by someone else; set those up one at a time on the Students page.
Students with no class are set up there too.

The address on the slips is the one the admin opened the page with. If students reach the
server by a different name (for example, the admin uses `localhost`), set `PORTAL_URL` in
`.env` (§3).

### First sign-in: students choose their own password

A password set by the school — on a slip, or typed by an admin on the Students page — only
works until the student's first sign-in. They are taken straight to
**`student_password.php`** and cannot see their results until they have chosen their own
(at least 8 characters, and different from the one on the slip). So a slip that has been
used is worthless to anyone who finds it. Students can change their password again at any
time with **Change password** in the portal top bar; they must enter their current one
first.

When an admin sets a password on the Students page, the box *"Ask the student to choose
their own password at next sign-in"* is ticked by default. Untick it only if the student
chose the password themselves.

### Lockout after wrong passwords

**5 wrong passwords in a row lock a student login for 15 minutes.** While it is locked the
password is not even checked, so guessing gets nowhere, and the student is told how many
minutes are left. Each lockout is written to the activity log (§11) as *Locked — Student
portal*, so admins can see that someone was guessing. Wrong *current* passwords on the
change-password page count too, and reaching the limit there signs the student out.

To let a student back in early, either click **Unlock** under *Locked accounts* on the
Student Portal page, or save their portal login on the Students page (saving always clears
a lockout). If someone else was guessing, reset the student's password instead. The
Students list marks locked logins with a red **Locked** pill, and the student's edit page
shows their last sign-in and whether they still have to choose a password.

Because usernames are admission numbers, the lockout message does reveal that a username
exists. That is deliberate: admission numbers are not secret in a school, and telling the
student why they cannot sign in saves a trip to the office. The limits live at the top of
`includes/student_portal.php` (`PORTAL_MAX_ATTEMPTS`, `PORTAL_LOCK_MINUTES`).

---

## 14. Troubleshooting

- **"could not find driver"** — the `pdo_pgsql` PHP extension is not enabled. Enable it in
  `php.ini` and restart.
- **Connection refused / authentication failed** — check the values in `.env` and that
  PostgreSQL is running and accepting connections for user `mole`.
- **Blank page** — set `APP_ENV=development` in `.env` to see the error, and check your
  PHP error log.
- **Login always fails** — make sure `seed.sql` ran (so the `users` table has accounts),
  or create one with `create_admin.php`.
- **The "Install app" button never appears / offline doesn't work** — you are probably on a
  plain `http://` address to another device. Service workers need `https://` or
  `http://localhost` (see section 9). Also check the browser console for a service-worker
  error, and note iPhone installs via **Share → Add to Home Screen** rather than a button.
- **`python3 tools/analytics.py` says "No PostgreSQL driver found"** — install the
  dependencies first with `pip install -r tools/requirements.txt` (see section 10).
- **The dashboard says the activity log table has not been created** — you are on an older
  database. Run `sql/migration_2026_10_activity_log_and_phone.sql` (see section 2) to add
  the `activity_log` table; the card starts filling in from the next sign-in.
- **"Phone number may contain digits only…" when saving a student or teacher** — the field
  takes a Kenyan mobile number and nothing else: ten digits starting `07` or `01`, or the
  `+254` form (see section 12). Remove any letters or notes, or leave it blank. If an *old*
  record refuses to save after upgrading, its stored number predates the rule — re-enter it
  in the valid form.
- **"column portal_must_change does not exist"** (or another `portal_…` column), or the
  Students page / student sign-in shows a database error after upgrading — the student
  portal migrations have not been run. Run both, in order (see section 2).
- **A student says their slip password does not work** — it only works until their first
  sign-in, after which they must use the password they chose. If they have forgotten it,
  set a new one on the Students page (or *Reset all passwords* for the class). If they see
  "locked for N more minutes", wait or click **Unlock** on the Student Portal page.
- **The sign-in address on the slips is wrong** (e.g. shows `localhost`) — set
  `PORTAL_URL` in `.env` to the address students should use (see sections 3 and 13).
- **A new logo does not show** — check the exact file name and folder
  (`images/branding/school-logo.png`), then reload the page. If the PDF still shows the GV
  mark, look in the PHP error log for a `skipping logo` warning — the file is probably not
  a real PNG/JPEG (e.g. a renamed `.webp` or `.svg`).
