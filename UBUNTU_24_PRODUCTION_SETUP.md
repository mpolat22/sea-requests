# Ubuntu 24.04 Production Setup

This playbook is for the first live setup of Sea Requests on a fresh Ubuntu 24.04 LTS VPS.

It is written to be applied before or during the first deployment day.

## 1. Target Stack

- Ubuntu 24.04 LTS
- Nginx
- PHP 8.3 FPM
- MySQL
- Redis
- Supervisor
- Node.js 22 LTS
- Git + GitHub deploy flow

Why Node 22:

- this project currently uses Vite 7
- local package metadata shows Vite requires Node `^20.19.0 || >=22.12.0`
- Node 22 LTS is the safest long-term choice for production builds

Why PHP 8.3:

- project composer requirement is `^8.2`
- Ubuntu 24.04 works well with PHP 8.3

## 2. Information To Have Ready

Before the real setup starts, keep these ready:

- server IP
- root or initial SSH user
- final domain name
- your local SSH public key
- GitHub repository URL
- production database name, user, and password
- production mail settings
- app name and live URL

## 3. First Login

Use the login user provided by the VPS provider.

Examples:

```bash
ssh root@YOUR_SERVER_IP
```

or

```bash
ssh ubuntu@YOUR_SERVER_IP
```

After login:

```bash
apt update && apt upgrade -y
timedatectl set-timezone Europe/Istanbul
hostnamectl set-hostname searequests
```

Optional but recommended after the first update:

```bash
reboot
```

## 4. Create A Real Admin / Deploy User

Do not keep daily work under root.

Recommended username:

```text
deploy
```

Create it:

```bash
adduser deploy
usermod -aG sudo deploy
```

Copy your SSH key to that user:

```bash
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys
```

Test it in a new terminal before hardening SSH:

```bash
ssh deploy@YOUR_SERVER_IP
```

## 5. SSH Hardening

Only do this after confirming the `deploy` user works.

Edit:

```bash
nano /etc/ssh/sshd_config
```

Recommended values:

```text
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
ChallengeResponseAuthentication no
UsePAM yes
```

Then restart SSH:

```bash
systemctl restart ssh
```

## 6. Firewall And Basic Security

Install and configure UFW:

```bash
apt install -y ufw
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable
ufw status
```

Install Fail2ban:

```bash
apt install -y fail2ban
systemctl enable fail2ban
systemctl start fail2ban
systemctl status fail2ban
```

## 7. Swap

If the VPS does not already have swap, add one.

Safe default:

- 4 GB swap for a healthy mid-size production VPS

Commands:

```bash
fallocate -l 4G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' | tee -a /etc/fstab
sysctl vm.swappiness=10
echo 'vm.swappiness=10' | tee -a /etc/sysctl.conf
swapon --show
```

## 8. Base Packages

Install general tools:

```bash
apt install -y software-properties-common unzip curl git ca-certificates supervisor
```

## 9. Install PHP 8.3 And Extensions

Install PHP and the required extensions for Laravel, MySQL, spreadsheets, uploads, and app runtime:

```bash
apt install -y \
php8.3-fpm \
php8.3-cli \
php8.3-common \
php8.3-mysql \
php8.3-curl \
php8.3-xml \
php8.3-mbstring \
php8.3-zip \
php8.3-bcmath \
php8.3-intl \
php8.3-gd \
php8.3-soap \
php8.3-redis
```

Check versions:

```bash
php -v
php-fpm8.3 -v
```

## 10. Install Composer

Ubuntu package is acceptable for a first deployment:

```bash
apt install -y composer
composer --version
```

## 11. Install Nginx

```bash
apt install -y nginx
systemctl enable nginx
systemctl start nginx
systemctl status nginx
```

## 12. Install MySQL

```bash
apt install -y mysql-server
systemctl enable mysql
systemctl start mysql
systemctl status mysql
```

Run the security wizard:

```bash
mysql_secure_installation
```

Create the production database:

```bash
mysql -u root -p
```

Then:

```sql
CREATE DATABASE searequests CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'searequests'@'localhost' IDENTIFIED BY 'CHANGE_THIS_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON searequests.* TO 'searequests'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 13. Install Redis

```bash
apt install -y redis-server
systemctl enable redis-server
systemctl start redis-server
systemctl status redis-server
redis-cli ping
```

Expected response:

```text
PONG
```

## 14. Install Node.js 22 LTS

Use Node 22 for production builds:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs
node -v
npm -v
```

## 15. Prepare The App Directory

Create the live project path:

```bash
mkdir -p /var/www/searequests
chown -R deploy:www-data /var/www/searequests
chmod -R 775 /var/www/searequests
```

Switch to the deploy user for application work:

```bash
su - deploy
```

## 16. Clone The Repository

As the `deploy` user:

```bash
git clone https://github.com/mpolat22/sea-requests.git /var/www/searequests
cd /var/www/searequests
git fetch --all --tags
```

## 17. Production Environment File

Create the production environment file:

```bash
cp .env.example .env
nano .env
```

Set at minimum:

```text
APP_NAME="Sea Requests"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://YOUR_DOMAIN

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=searequests
DB_USERNAME=searequests
DB_PASSWORD=CHANGE_THIS_STRONG_PASSWORD

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Also fill:

- mail settings
- session domain if needed
- any production notification settings

Then generate the app key:

```bash
php artisan key:generate
```

## 18. First Deploy From Tag

Use the release tag, not only branch head:

```bash
cd /var/www/searequests
git fetch --all --tags
git checkout 20260615-001

composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan storage:link

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Fix writable directories if needed:

```bash
chown -R deploy:www-data /var/www/searequests
find /var/www/searequests/storage -type d -exec chmod 775 {} \;
find /var/www/searequests/storage -type f -exec chmod 664 {} \;
find /var/www/searequests/bootstrap/cache -type d -exec chmod 775 {} \;
find /var/www/searequests/bootstrap/cache -type f -exec chmod 664 {} \;
```

## 19. Nginx Server Block

Create:

```bash
sudo nano /etc/nginx/sites-available/searequests
```

Suggested config:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name YOUR_DOMAIN www.YOUR_DOMAIN;

    root /var/www/searequests/public;
    index index.php index.html;

    charset utf-8;

    access_log /var/log/nginx/searequests.access.log;
    error_log /var/log/nginx/searequests.error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable it:

```bash
ln -s /etc/nginx/sites-available/searequests /etc/nginx/sites-enabled/searequests
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
```

## 20. HTTPS

Install Certbot:

```bash
apt install -y certbot python3-certbot-nginx
```

Issue the certificate:

```bash
certbot --nginx -d YOUR_DOMAIN -d www.YOUR_DOMAIN
```

Test renewals:

```bash
certbot renew --dry-run
```

## 21. Queue Worker With Supervisor

Create:

```bash
sudo nano /etc/supervisor/conf.d/searequests-worker.conf
```

Suggested config:

```ini
[program:searequests-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/searequests/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/searequests/storage/logs/worker.log
stopwaitsecs=3600
```

Apply it:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start searequests-worker:*
supervisorctl status
```

## 22. Scheduler

Add cron:

```bash
crontab -e
```

Use:

```cron
* * * * * cd /var/www/searequests && php artisan schedule:run >> /dev/null 2>&1
```

## 23. Final Smoke Test

After setup, test:

1. home page loads
2. login works
3. buyer dashboard opens
4. supplier dashboard opens
5. admin dashboard opens
6. create RFQ page opens
7. orders pages open
8. messenger works
9. image previews work
10. PDF previews work

## 24. What I Need From You When The Server Is Ready

When the VPS is active, send these and I can adapt the setup exactly:

- server IP
- first login user (`root` or `ubuntu`)
- final domain
- whether you already added your SSH public key
- whether GitHub clone will use HTTPS or SSH
- the first release tag to deploy

At that point we can move from generic setup to exact live commands.
