# Green Valley Secondary School — Student Records Management System (SRMS)

A student records management system for Green Valley Secondary School, Murang'a County.
The static HTML pages have been converted into a fully functional web application using:

- **HTML** — page structure (your original layout, preserved)
- **CSS** — styling (`css/style.css`, extracted from your original inline styles)
- **JavaScript** — client-side validation and the live grade preview (`js/script.js`)
- **PHP** — the backend / API layer (all database access happens here)
- **PostgreSQL** — the database

The browser never talks to PostgreSQL directly. Every page and form goes through PHP,
which uses **PDO prepared statements**. Database credentials live in a `.env` file on the
server and are never exposed to HTML or JavaScript.

---

## 1. File structure and where everything goes

Copy the entire `green_valley_srms/` folder into your web root (e.g. `htdocs/`, `www/`,
or wherever you point the PHP server). The layout is:

```
green_valley_srms/
├── .env                     # your real DB credentials (NOT committed / NOT web-served content)
├── .env.example             # template to copy from
│
├── config/
│   └── database.php         # PDO + PostgreSQL connection (reads .env)
│
├── includes/
│   ├── auth.php             # sessions, login state, role checks
│   ├── functions.php        # helpers: escaping, grade calc, CSRF, flash messages
│   ├── header.php           # shared page head + topbar (opens .main)
│   ├── sidebar.php          # shared navigation menu
│   └── footer.php           # shared page footer (loads script.js)
│
├── actions/                 # form handlers (POST targets) — one per entity
│   ├── student_actions.php
│   ├── teacher_actions.php
│   ├── class_actions.php
│   ├── subject_actions.php
│   ├── exam_actions.php
│   └── result_actions.php
│
├── css/
│   └── style.css            # all styling
│
├── js/
│   └── script.js            # client-side validation + grade preview
│
├── sql/
│   ├── schema.sql           # creates the 7 tables, constraints and indexes
│   └── seed.sql             # optional sample data (incl. login accounts)
│
├── index.php                # entry point → redirects to dashboard or login
├── login.php                # login page  (replaces login.html)
├── logout.php               # ends the session
├── dashboard.php            # dashboard  (replaces dashboard.html)
├── students.php             # students    (replaces students.html)
├── teachers.php             # teachers    (replaces teachers.html)
├── classes.php              # classes     (replaces classes.html)
├── subjects.php             # subjects    (replaces subjects.html)
├── exams.php                # exams       (replaces exams.html)
├── results.php              # marks entry (new — the results interface)
├── reports.php              # reports     (replaces reports.html)
└── create_admin.php         # one-off utility to create/reset a login (DELETE after use)
```

Each old `*.html` file is replaced by the matching `*.php` file. The forms now submit to
the handlers in `actions/`, which validate the input and talk to PostgreSQL.

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

`schema.sql` builds seven tables with the relationships your spec requires:

| Table      | Key columns | Relationships |
|------------|-------------|---------------|
| `teachers` | `teacher_id` (PK), `staff_no` (unique) | — |
| `users`    | `user_id` (PK), `username` (unique) | `teacher_id → teachers` (SET NULL) |
| `classes`  | `class_id` (PK), `class_name` (unique) | `teacher_id → teachers` (SET NULL) |
| `students` | `student_id` (PK), `admission_no` (unique) | `class_id → classes` (SET NULL) |
| `subjects` | `subject_id` (PK), `subject_code` (unique) | `teacher_id → teachers` (SET NULL) |
| `exams`    | `exam_id` (PK) | unique on (`exam_name`, `term`, `year`) |
| `results`  | `result_id` (PK) | `student_id`, `subject_id`, `exam_id` (all CASCADE) |

Duplicate-prevention is enforced by the database itself, not just by PHP:

- `students.admission_no` is **UNIQUE** — no two students share an admission number.
- `results` has a **UNIQUE (student_id, subject_id, exam_id)** constraint — a student
  cannot have two marks for the same subject in the same exam.
- `exams` is **UNIQUE (exam_name, term, year)**.
- `teachers.staff_no`, `subjects.subject_code`, `classes.class_name`, `users.username`
  are all UNIQUE.

Indexes are added on the foreign-key and search columns for performance.

Reports are **generated on the fly** by SQL joins across `students`, `subjects`, `exams`
and `results` — no report data is duplicated or stored anywhere.

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
```

`config/database.php` loads this file and creates a single PDO connection configured for:
prepared statements only (no emulation), exceptions on error, and associative-array
fetches. Set `APP_ENV=production` on a live server to hide detailed error messages.

> **Keep `.env` private.** It holds your database password. Do not put it in a public
> repository, and make sure your web server does not serve dot-files.

---

## 4. Run it locally

The simplest way is PHP's built-in web server. From inside the `green_valley_srms/`
folder:

```bash
php -S localhost:8000
```

Then open <http://localhost:8000/> in your browser. You will be redirected to the login
page. (You need PHP 8.0+ with the `pdo_pgsql` extension enabled — check with
`php -m | grep pgsql`.)

If you prefer Apache/XAMPP, drop the folder into your web root and browse to it, e.g.
<http://localhost/green_valley_srms/>.

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

---

## 6. Who can do what (roles)

The system has two roles, checked on every page and every action:

- **Admin** — full access: add/edit/delete students, teachers, classes, subjects, exams;
  enter and edit marks; view all reports; delete records.
- **Teacher** — can view all records, manage students, and enter/edit marks. Managing
  teachers, classes, subjects and exams, and *all* delete operations, are restricted to
  admins.

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
  pages and actions.
- **Credentials in `.env` only** — never in HTML, JavaScript, or committed source.

---

## 8. Design

Your original look and navigation are preserved. The inline styles from the old HTML
pages were moved verbatim into `css/style.css` (the navy sidebar, stat cards, tables and
form grid are unchanged); only small additive rules were introduced — for the active menu
item, flash/alert messages, the grade badge, search bars, and print styling for reports.
The static sample rows in the old tables are now replaced by live PostgreSQL data.

---

## 9. Troubleshooting

- **"could not find driver"** — the `pdo_pgsql` PHP extension is not enabled. Enable it in
  `php.ini` and restart.
- **Connection refused / authentication failed** — check the values in `.env` and that
  PostgreSQL is running and accepting connections for user `mole`.
- **Blank page** — set `APP_ENV=development` in `.env` to see the error, and check your
  PHP error log.
- **Login always fails** — make sure `seed.sql` ran (so the `users` table has accounts),
  or create one with `create_admin.php`.
