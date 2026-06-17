# Sea Requests Launch Checklist

## 0. Release Control

- Use commit messages in `type: short summary` format.
- Use release tags in `YYYYMMDD-XXX` format.
- Push `main` first, then create and push the release tag.
- Deploy production from the release tag, not from an untagged local state.
- Record the previous stable tag before every live deploy.

## 1. Brand And Metadata

- Run `php scripts/generate_brand_assets.php` if any favicon, logo, or OG image is updated.
- Verify `/favicon.ico`, `/apple-touch-icon.png`, `/android-chrome-192x192.png`, and `/brand/sea-requests-og.png` load correctly.
- Open `/robots.txt` and `/sitemap.xml` on the final domain and confirm the live domain is used.
- Confirm social previews use the latest OG image on homepage and service pages.

## 2. Environment

- Set `APP_ENV=production`.
- Set `APP_DEBUG=false`.
- Set `APP_URL` to the final HTTPS domain.
- Confirm `MAIL_*` values use the live mailbox and sender identity.
- Confirm `QUEUE_CONNECTION`, `CACHE_STORE`, and `SESSION_DRIVER` are set for production.

## 3. Database And Storage

- Run `php artisan migrate --force`.
- Run `php artisan storage:link`.
- Confirm `storage/app/public` attachments, invoice files, payment proof files, and messenger attachments open correctly in-browser.
- Smoke test buyer, supplier, and admin accounts after migration.

## 4. Build And Cache

- Remove any leftover `public/hot` file before deploy.
- Run `composer install --no-dev --prefer-dist --optimize-autoloader`.
- Run `npm ci`.
- Run `npm run build`.
- Run `php artisan optimize:clear`.
- Run `php artisan config:cache`.
- Run `php artisan route:cache`.
- Run `php artisan view:cache`.
- If route/config caching is used in your server flow, confirm dynamic `robots.txt` and `sitemap.xml` still respond correctly after cache warmup.

## 5. Background Processes

- Start the queue worker for notifications, RFQ mails, and other async jobs.
- Verify the process manager restarts workers after deploy.
- Confirm failed jobs are monitored and retried.
- Run `php artisan queue:restart` after each deployment.

## 6. SEO And Crawl Checks

- Confirm public request pages that should be indexable are reachable and render correct meta tags.
- Confirm dashboard, admin, notifications, login, and register pages are not intended for indexing.
- Validate the sitemap in Google Search Console after DNS and HTTPS are final.

## 7. Product Smoke Tests

- Buyer: create RFQ, edit draft, submit, compare offers, confirm awards, fill order information, upload payment proof.
- Supplier: receive RFQ, submit offer, receive award, add invoice, confirm payment receipt, send messenger message.
- Admin: review RFQs, orders, users, supplier companies, and notifications.
- File preview: image and PDF attachments should open inside the app modal flows.

## 8. Final Go-Live Review

- Check mobile layout on home, services, requests, buyer dashboard, supplier dashboard, admin dashboard, and messenger drawer.
- Confirm the footer, navbar badges, unread messenger counts, and order workflow statuses still render correctly.
- Keep one buyer, one supplier, and one admin demo account ready for final smoke testing on the live server.
- Keep the previous stable release tag ready for rollback until the new release is confirmed healthy.
