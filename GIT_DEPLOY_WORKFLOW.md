# Git Deploy Workflow

This project is prepared for a simple and controlled Git-based production flow.

## 1. Release Model

This repository uses a single main development branch:

- local work happens in this workspace
- approved changes are committed to `main`
- `main` is pushed to GitHub
- production is deployed from annotated release tags, not from an untracked local state

This keeps the live server traceable and rollback-friendly.

## 2. Commit Message Standard

Commit messages should describe the actual change. Do not put release dates inside commit messages because release identity belongs to tags.

Use this format:

```bash
type: short summary
```

Recommended types:

- `feat:` new feature
- `fix:` bug fix
- `refactor:` code cleanup without changing behavior
- `perf:` performance improvement
- `docs:` documentation change
- `chore:` maintenance or infrastructure work

Examples:

```bash
feat: add supplier order chat panel
fix: resolve buyer payment proof layout
perf: lazy load order action payloads
docs: update production release workflow
```

## 3. Release Tag Format

Production releases use date-based tags:

```bash
YYYYMMDD-XXX
```

Examples:

```bash
20260615-001
20260615-002
20260616-001
```

Rules:

- `YYYYMMDD` is the release date
- `XXX` starts from `001` each day
- the same tag format is used for every live release
- deploy only tagged releases to production

## 4. What Must Be Tracked In Git

Track source and deploy-critical files:

- `app/`
- `bootstrap/`
- `config/`
- `database/`
- `public/`
- `resources/`
- `routes/`
- `scripts/`
- `tests/`
- `tools/`
- `artisan`
- `composer.json`
- `composer.lock`
- `package.json`
- `package-lock.json`
- `vite.config.js`
- `README.md`
- `GIT_DEPLOY_WORKFLOW.md`
- `PRODUCTION_RELEASE_PLAYBOOK.md`
- `UBUNTU_24_PRODUCTION_SETUP.md`
- `LAUNCH_CHECKLIST.md`

Do not track local or runtime-only files:

- `.env`
- `node_modules/`
- `vendor/`
- `outputs/`
- `public/build/`
- `public/storage/`
- logs
- caches
- temporary OCR or import files
- uploaded RFQ, offer, invoice, verification, payment, or chat files under `storage/`

## 5. Normal Daily Workflow

For normal non-release updates:

```bash
git add .
git commit -m "fix: short description"
git push origin main
```

This updates GitHub but does not automatically mean production should change.

## 6. Production Release Workflow

When a change is ready for live deployment:

```bash
git add .
git commit -m "type: short description"
git push origin main

git tag -a 20260615-001 -m "Release 20260615-001"
git push origin 20260615-001
```

Recommended release rule:

1. finish the change locally
2. test locally
3. commit the change
4. push `main`
5. create the next date-based release tag
6. push the tag
7. deploy that exact tag on the server
8. smoke test live

## 7. Production Deploy Commands

On the production server:

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

Run this once on the first live setup if needed:

```bash
php artisan storage:link
```

If the application key does not exist yet:

```bash
php artisan key:generate
```

## 8. Rollback Workflow

Rollback means deploying the previous known-good release tag.

Example:

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

Important note:

- if a bad release includes destructive or incompatible database migrations, Git rollback alone may not fully restore behavior
- risky migrations must be written to be backward-compatible whenever possible
- destructive schema or data changes should always have a separate recovery plan

## 9. Release Discipline Rules

These rules should be followed every time:

1. never deploy uncommitted local work
2. never deploy by memory alone; always deploy a visible tag
3. always know the previous stable tag before starting a new release
4. always smoke test the live application after deploy
5. if production breaks, rollback first and investigate second

## 10. Minimum Smoke Test After Deploy

At minimum, verify:

1. homepage loads
2. login works
3. buyer dashboard opens
4. supplier dashboard opens
5. admin dashboard opens
6. RFQ create page opens
7. orders pages open
8. messenger loads
9. notifications load
10. image and PDF previews still work

## 11. Current Repository State

This workspace currently has:

- local Git repository initialized
- `main` branch initialized
- `origin` connected to GitHub
- first project import committed and pushed
- `.gitignore` updated for runtime uploads and local artifacts

Next focus:

- prepare the production server
- apply the first live environment configuration
- follow the release playbook for the first deployment
