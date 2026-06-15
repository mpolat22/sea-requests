# Git Deploy Workflow

This project is prepared to move to a Git-based deployment flow.

## 1. Goal

The target workflow is:

1. We make changes locally in this workspace.
2. Changes are committed to Git.
3. Changes are pushed to a private GitHub repository.
4. The production server updates from that repository.

This means future release language can be standardized as:

- `commit`
- `tag`
- `push`
- `deploy`

## 2. What Should Be In Git

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
- `LAUNCH_CHECKLIST.md`

Do not track local or server-specific runtime files:

- `.env`
- `node_modules/`
- `vendor/`
- `outputs/`
- `public/build/`
- `public/storage/`
- log files
- cache files
- temporary import or OCR files
- uploaded RFQ / offer / invoice / chat files under `storage/`

## 3. Initial GitHub Setup

After creating a private repository on GitHub, connect it like this:

```bash
git remote add origin YOUR_GITHUB_REPO_URL
git add .
git commit -m "Initial project import"
git push -u origin main
```

Example remote formats:

```bash
git remote add origin git@github.com:YOUR_ORG_OR_USER/spareparts.git
```

or

```bash
git remote add origin https://github.com/YOUR_ORG_OR_USER/spareparts.git
```

## 4. Daily Release Workflow

For normal updates:

```bash
git add .
git commit -m "Describe the change"
git push origin main
```

For versioned releases:

```bash
git add .
git commit -m "Release changes"
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin main
git push origin v1.0.0
```

## 5. Recommended Production Deploy Flow

On the production server:

```bash
cd /var/www/searequests
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
php artisan schedule:interrupt
```

If storage symlink is missing:

```bash
php artisan storage:link
```

## 6. Better Release Model Later

Once the server is live and stable, one of these should be added:

### Option A: Manual deploy

After push, log into the server and run the deploy commands manually.

Pros:

- simplest
- easiest to debug
- best for early live period

### Option B: Tag-based deploy

The server only deploys tagged releases such as `v1.0.1`.

Pros:

- safer
- cleaner release history
- easier rollback discipline

### Option C: Push-based auto deploy

GitHub webhook or CI pipeline triggers deploy automatically on `main`.

Pros:

- fastest workflow

Cons:

- should only be enabled after the manual release flow is stable

## 7. Recommended Team Rule

For this project, the safest sequence is:

1. finish the change locally
2. test locally
3. commit
4. optional release tag
5. push
6. deploy on server
7. smoke test live

## 8. Smoke Test After Deploy

After each production deploy, check at minimum:

1. home page loads
2. login works
3. buyer dashboard opens
4. supplier dashboard opens
5. admin dashboard opens
6. RFQ create page opens
7. orders page opens
8. notifications load
9. messenger loads
10. uploaded files preview correctly

## 9. Current Local State

This workspace now has:

- local Git repository initialized
- main branch initialized
- improved `.gitignore` for local artifacts

Next step:

- create the private GitHub repository
- add `origin`
- make the first commit
- push the project
