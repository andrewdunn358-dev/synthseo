# SynthSEO — deploy notes

## Where it lives
20i shared hosting, `~/public_html/laravel12`. Document root is set in
the 20i panel (Manage Domains → Document Root) to
`public_html/laravel12/public`.

A previous attempt moved the app to `~/app` and symlinked
`public_html -> app/public`, which 500'd every request including static
files. The error log showed `AH00124: Request exceeded the limit of 10
internal redirects` — a redirect loop, NOT a refused symlink. The cause
was that the panel docroot was already `public_html/laravel12/public`,
so once the app moved, that path resolved to
`app/public/laravel12/public`, which does not exist. Whether 20i follows
symlinked docroots is still untested. **Set the docroot in the panel and
leave the filesystem alone** — it works and there is no reason to find
out.

## PHP
Both CLI and web are on 8.4. The web version is set by `~/.htaccess`,
which 20i's control panel regenerates — if you change PHP version in the
panel, it rewrites that file and drops any aliases added to
`~/.bash_profile`.

Always call the versioned binary, or use the aliases:

    /usr/php84/usr/bin/php artisan ...

## Pull and deploy

    cd ~/public_html/laravel12
    git pull
    php artisan migrate
    php artisan config:clear

`composer install` is only needed when `composer.lock` changes. Add
`-d memory_limit=-1` to composer commands — the default limit on shared
hosting kills dependency resolution partway.

## Queue — REQUIRED for audits to run

There is no supervisor here, so audits only run if the scheduler does.
Add one cron entry in the 20i panel, every minute:

    /usr/php84/usr/bin/php /home/sites/41a/c/ce356e13a1/public_html/laravel12/artisan schedule:run

Without this, every audit sits in `queued` forever and the UI will
honestly say so — but nothing will happen.

## Making yourself staff

Registration always creates a `client` user with its own account, never
staff — a public form must not be able to grant cross-tenant access.
Promote deliberately:

    php artisan tinker --execute="App\Models\User::where('email','you@example.com')->update(['role'=>'staff']);"

Staff bypass the tenant scope and see every account.

## PageSpeed API key (optional but wanted)

PSI answers without a key and rate-limits hard. Get one from the Google
Cloud console with the PageSpeed Insights API enabled, then add to
`.env`:

    PAGESPEED_API_KEY=...

No key means audits still work - the Lighthouse panel just says Google
rate-limited the request.

## Tests

    php tests/audit-engine-test.php
    php tests/pagespeed-test.php
    php tests/blade-balance-test.php

Standalone, no database or framework boot needed. Covers the parsing and
scoring, which is where the bugs are. The HTTP fetch is not covered —
that needs a live request.

## Gotchas found the hard way

- `.env` values containing `#` are silently truncated at the `#` unless
  quoted. This cost an hour: MySQL reported `using password: NO` while
  the password looked correct in the file.
- On 20i the MySQL database name and username are the same string, and
  the host is `sdb-NN.hosting.stackcp.net`, never `localhost`.
- SSH sessions drop shortly after a PHP version change in the panel.
  Non-interactive commands (`ssh user@host "command"`) keep working, so
  the whole git setup can be done that way if needed.
- **Non-interactive SSH does not load `.bash_profile`**, so bare `php`
  is 8.0.30 there and Laravel 12 refuses to boot. Always use
  `/usr/php84/usr/bin/php` in `ssh host "..."` commands and in cron.
- `git init` created a `master` branch while the remote uses `main`;
  `git pull` then fails with "no tracking information". Fixed with
  `git branch -m main && git branch --set-upstream-to=origin/main main`.
