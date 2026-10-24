# Deploy to production

Production is a cPanel account **without a terminal and without Composer**. The deploy is a `git pull` made by the
cPanel API; everything a terminal would do afterwards (migrations, one-time commands, Laravel caches) the app does by
itself on the first visits after the pull (`app/Services/DeployTasksService.php`).

- Trigger: a push to **`main`** (the merge of `pre-main`), or *Actions → Deploy to production → Run workflow*.
- Workflow: `.github/workflows/deploy.yml` — same approach as icd-laravel.
- Libraries the server cannot install are kept in `lib/` (`lib/webauthn`, `lib/pdfparser`); the built CSS/JS is in
  `public/build` (committed). `vendor/` on the server stays as it is: no other Composer package changed.

The domain may point to the repository folder itself (`/home/igrejai1/my-finances`): the root `.htaccess` then
serves everything from `public/`, so `storage/` (logs, old backups), `vendor/`, `.env` and the rest are never
reachable, and old `/public/...` addresses redirect. Pointing the domain's document root to `.../public` (cPanel →
*Domains*) is still the cleanest; the root `.htaccess` is then simply not used.

## One-time setup

1. **Back up the database** first: cPanel → phpMyAdmin → database → *Export* (SQL). The first deploy runs the
   migrations and `balances:rebuild` (balances fully derived from movements; idempotent, in a transaction).
2. **The site folder must be a clone of the repository** (cPanel → *Git Version Control*):
   - If the site is already listed there, use its *Repository Path*, with the branch `main` checked out.
   - If not: *Create* → clone `https://github.com/BrunoCheble/my-finances.git` (the repository is public) into a new
     folder, e.g. `/home/<user>/escudo`. Copy into it, from the current site folder, `.env`, `vendor/` and
     `storage/app` (and `storage/bkps`, the old backups). Then point the domain's document root to
     `/home/<user>/escudo/public` (cPanel → *Domains*). Keep the old folder until the new one is checked.
3. **API token**: cPanel → *Security → Manage API Tokens* → *Create* (name `github-deploy`). Copy it.
4. **GitHub** → https://github.com/BrunoCheble/my-finances/settings/secrets/actions
   - *Secrets* tab → *New repository secret*:
     | Name               | Value                                                        |
     |--------------------|--------------------------------------------------------------|
     | `CPANEL_HOST`      | the cPanel host, without `https://` or port (e.g. `server.host.com`) |
     | `CPANEL_USER`      | the cPanel user name                                         |
     | `CPANEL_TOKEN`     | the token of step 3                                          |
     | `CPANEL_REPO_ROOT` | the repository path of step 2 (e.g. `/home/<user>/escudo`)   |
   - *Variables* tab → *New repository variable*: `APP_URL` = the site address, e.g. `https://escudo.example.com`
     (no `/` at the end).
5. **`.env` on the server** (cPanel → File Manager), at least:
   ```
   APP_NAME=Escudo
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://<the site address>
   ```
   Fingerprint / Face ID only works over **HTTPS** and is bound to that domain.

## Checking a deploy

`https://<site>/up` answers with the header `X-App-Deploy: tasks=ok; config=1; routes=1`.

- `tasks=pending:…` — the pull is still settling (90 s); the workflow waits for it.
- `tasks=failed:<step>:…` — `migrate`, `balances-rebuild` or `optimize` failed: the site keeps working without the
  caches; the error is in `storage/logs/laravel.log`. Fix, push again (or touch `.env`) and it runs again.

A one-time command runs once per server (`storage/framework/deploy-tasks.once-*` records it). New ones go in
`DeployTasksService::ONCE`.
