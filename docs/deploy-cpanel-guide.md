# Put Menu on cPanel hosting: step by step

This is how the live site **https://menu.kulmiss.com** was set up, in the order we did it.
Do the same for a new server. After the first setup you only need **Part 3** (one click in VS Code).

More detail on every setting: [deploy.md](deploy.md).

| What | Ours |
|---|---|
| Server (SSH) | `kulmisscom@tonic.hostns.io`, port 22 |
| PHP | `/opt/alt/php84/usr/bin/php` (PHP 8.4) |
| Composer | `/home/kulmisscom/bin/composer` |
| Git copy of the code | `~/menu-app` |
| Website folder | `~/menu.kulmiss.com` → a link to `~/menu-app/backend/public` |

In the commands below, `PHP` means `/opt/alt/php84/usr/bin/php`.
Make a short name for it once in each terminal:

```bash
alias PHP=/opt/alt/php84/usr/bin/php
```

---

## Part 1. Once, on your computer (Windows)

### 1.1 Make an SSH key

In PowerShell:

```powershell
ssh-keygen -t ed25519
```

Press Enter for every question.

### 1.2 Give the key to cPanel

1. Open `C:\Users\YOU\.ssh\id_ed25519.pub` in Notepad and copy everything.
2. cPanel → **SSH Access** → **Manage SSH Keys** → **Import Key**. Paste it and save.
3. Click **Manage** next to the key → **Authorize**.
4. Test it. This must log in **without** a password:

   ```powershell
   ssh kulmisscom@tonic.hostns.io
   ```

### 1.3 The deploy settings file

In the project folder, copy `deploy.config.example.json` to `deploy.config.json` and fill it in.
Git never commits this file.

```json
{
  "host": "tonic.hostns.io",
  "user": "kulmisscom",
  "port": 22,
  "path": "~/menu-app",
  "branch": "main",
  "php": "/opt/alt/php84/usr/bin/php",
  "composer": "/opt/alt/php84/usr/bin/php /home/kulmisscom/bin/composer"
}
```

### 1.4 The buttons in VS Code

VS Code suggests the **Task Buttons** extension. Install it.
You will see **🚀 Deploy** and **🧪 Deploy (dry run)** at the bottom of the window.
No extension? Use **Terminal → Run Task… → Deploy live site** instead.

---

## Part 2. Once, on the server

Log in with `ssh kulmisscom@tonic.hostns.io` (or use cPanel → **Terminal**).

### 2.1 Make the database

cPanel → **MySQL Databases**:

1. Make a database, for example `kulmisscom_menu`.
2. Make a user with a strong password.
3. Add the user to the database with **All privileges**.

Write down the database name, user name and password.

### 2.2 Get Composer (if `composer` is not found)

```bash
mkdir -p ~/bin && cd ~/bin
curl -sS https://getcomposer.org/installer | /opt/alt/php84/usr/bin/php
mv composer.phar composer
```

### 2.3 Get the code

```bash
git clone https://github.com/kulmiss/menu.git ~/menu-app
cd ~/menu-app && git checkout main
```

### 2.4 The `.env` file

```bash
cd ~/menu-app/backend
cp .env.example .env
nano .env
```

Set at least these lines (save with Ctrl+O, Enter, then Ctrl+X):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://menu.kulmiss.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=kulmisscom_menu
DB_USERNAME=...
DB_PASSWORD=...

SUPER_ADMIN_PATH=...   # your own secret word, e.g. office-1a2b3c (letters, digits, -)
```

Never put the real passwords or the real Super Admin address in a file that goes to GitHub.

### 2.5 Install and make the tables

```bash
cd ~/menu-app/backend
PHP ~/bin/composer install --no-dev --optimize-autoloader
PHP artisan key:generate --force
PHP artisan migrate --force
PHP artisan platform:aggregate
```

### 2.6 Point the website at the right folder

The website must show **only** `backend/public`. Our host did not let us change the folder in cPanel,
so we moved the code next to it and made the website folder a link:

```bash
cd ~
mv ~/menu.kulmiss.com ~/menu-app-old-site   # only if the folder already exists; keep it as a backup
ln -s ~/menu-app/backend/public ~/menu.kulmiss.com
```

Check it. Each line below must show what it says:

| Open | You should see |
|---|---|
| `https://menu.kulmiss.com/up` | "Application up" |
| `https://menu.kulmiss.com/.env` | 404 or 403 (never the file) |
| `https://menu.kulmiss.com/composer.json` | 404 |

### 2.7 First deploy from VS Code

On your computer: press **🧪 Deploy (dry run)**. If it says OK, press **🚀 Deploy**.
This builds the admin app and puts it on the server.

### 2.8 Cron (the clock that runs jobs)

cPanel → **Cron Jobs** → **Every minute**. The command:

```bash
cd /home/kulmisscom/menu-app/backend && /opt/alt/php84/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

It sends e-mails, makes the nightly backup and keeps the menus fresh.

### 2.9 PHP settings

cPanel → **MultiPHP INI Editor** → the domain:

| Setting | Value |
|---|---|
| `upload_max_filesize` | `10M` |
| `post_max_size` | `12M` |
| `memory_limit` | `256M` |

### 2.10 Backup key

```bash
cd ~/menu-app/backend
PHP artisan backup:key --write
PHP artisan optimize
```

The first command adds a `BACKUP_KEY=…` line to `.env` and prints it.
**Keep a copy of that line somewhere safe** (not on the server). Without it, the backups cannot be opened.

### 2.11 The Super Admin

```bash
PHP artisan super:create
```

Type your name, e-mail and a password (at least 10 characters).
Then open `https://menu.kulmiss.com/<SUPER_ADMIN_PATH>/sign-in` and sign in.

1. Turn on **two-factor sign-in**: scan the QR code with an authenticator app, type the code, keep the recovery codes.
2. The setup wizard opens. Go through it.
3. **Settings → Email**: fill in the SMTP details and send a test e-mail.
4. **Backups → Back up now**: check that it works.

### 2.12 Demo restaurants (optional)

The home page links to a demo menu for each style. Make them:

```bash
PHP artisan demo:live
```

Answer `yes`. Nobody can sign in to them. Super Admin can delete them later.

---

## Part 3. Every update (one click)

1. On GitHub, merge the release pull request into **main**.
2. In VS Code:

   ```bash
   git checkout main
   git pull
   ```

3. Press **🚀 Deploy**.
4. Open the site and check it.

The button stops if you are not on `main`, if you have changes that are not committed, or if `main` is not the same as on GitHub.
On the server it runs: `git pull` → maintenance mode → `composer install` → admin app → `migrate` → `optimize` → rebuild the menu pages → site live again.
If a step fails, the site comes back up and the button tells you which step.

---

## When something goes wrong

| Problem | Fix |
|---|---|
| `ssh` asks for a password | The key is not authorized. Do 1.2 again. |
| The page shows 404 from LiteSpeed | The website folder is wrong. Check 2.6: `ls -la ~/menu.kulmiss.com` must point to `menu-app/backend/public`. |
| "500 Server Error" | Look at the newest file in `~/menu-app/backend/storage/logs/`. |
| Photos do not upload | Check 2.9 (upload size) and that these folders are writable: `storage`, `bootstrap/cache`, `public/media`, `public/menu-cache`, `public/menu-assets`. |
| A change does not show | `cd ~/menu-app/backend && PHP artisan optimize:clear && PHP artisan optimize && PHP artisan menu:refresh-pages --all` |
| "Command … is not defined" | The server has old code. Pull `main` in VS Code and press **🚀 Deploy**. |
| Locked out of Super Admin | On the server: `PHP artisan super:access` shows the address. `PHP artisan super:access --clear-ips` removes the IP allow-list. |
