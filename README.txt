SYNTHSEO — LOGIN/DASHBOARD FOUNDATION
======================================

WHAT'S IN THIS ZIP
This zip is structured to match laravel12/ exactly. Extract it and
upload/merge the app/, resources/, and routes/ folders straight into
laravel12/, keeping the same folder layout. It will add new files
and only touch routes/web.php (which gets replaced — see below).

STEP 1 — Database
Open phpMyAdmin for the synthseo database → SQL tab → run:

CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(100) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

STEP 2 — .env
Open laravel12/.env and set (add or change these lines):

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

Also confirm APP_KEY=... is NOT blank. If it is, stop and flag it
before continuing — sessions/login won't work without it.

STEP 3 — Upload
Extract this zip and upload its app/, resources/, and routes/
folders into laravel12/, merging with what's already there.
routes/web.php in this zip REPLACES the existing one — it keeps
your current homepage route and adds login/register/dashboard
routes on top of it.

STEP 4 — Test
Visit synthseo.co.uk/register, create an account, confirm you land
on /dashboard logged in, then test /logout and /login.

STEP 5 — Once confirmed working
The /register route has no protection — anyone who finds it can
create an account. Fine for now while testing, but remove or lock
it down before this goes near real clients.
