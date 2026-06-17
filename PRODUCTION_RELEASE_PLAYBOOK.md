# Production Release Playbook

Use this file on every release day. It is the shortest path from local change to live deployment.

## 1. Local Pre-Release Check

Before tagging a release, confirm:

- the feature or fix is completed
- local smoke testing is done
- `.env`, uploads, logs, and build artifacts are not being committed
- `git status` is clean after the final commit

## 2. Local Release Commands

Example release for the first deploy of 15 June 2026:

```bash
git add .
git commit -m "type: short description"
git push origin main

git tag -a 20260615-001 -m "Release 20260615-001"
git push origin 20260615-001
```

If a second live release goes out on the same day:

```bash
git tag -a 20260615-002 -m "Release 20260615-002"
git push origin 20260615-002
```

## 3. First Server Setup Notes

Before the first deploy, the server should already have:

- the project directory created, for example `/var/www/searequests`
- PHP 8.2+
- Composer
- Node.js and npm
- Git
- MySQL
- Nginx
- queue process management
- the production `.env` file

## 4. Standard Deploy Commands

Deploy the exact release tag, not a moving branch head:

```bash
cd /var/www/searequests

git fetch --all --tags
git checkout 20260615-001

composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build

php artisan migrate --force

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan queue:restart
```

Run these once if the server is brand new:

```bash
php artisan key:generate
php artisan storage:link
```

## 5. Post-Deploy Smoke Test

After the deploy completes:

1. open home page
2. test login
3. test buyer dashboard
4. test supplier dashboard
5. test admin dashboard
6. open a buyer RFQ detail
7. open a supplier order detail
8. open messenger
9. preview one image file
10. preview one PDF file

## 6. Rollback Commands

If the release is bad, return to the previous stable tag immediately.

Example rollback:

```bash
cd /var/www/searequests

git fetch --all --tags
git checkout 20260615-001

composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan queue:restart
```

## 7. Release Log Rule

For every live release, record:

- tag name
- deploy date and time
- short release note
- whether migrations ran
- whether rollback was needed

Suggested format:

```text
20260615-001 | First live deployment | migrations: yes | rollback: no
20260615-002 | Fix payment proof modal issue | migrations: no | rollback: no
```

## 8. Non-Negotiable Rules

- do not deploy uncommitted code
- do not deploy without a pushed tag
- do not skip smoke testing
- do not make destructive migrations casually
- if production breaks, rollback first
