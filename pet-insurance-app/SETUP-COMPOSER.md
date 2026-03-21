# PHP dependencies (Stripe checkout)

Checkout and webhooks need the **Stripe PHP SDK**, installed with **Composer**.

## One-time setup

1. Install [Composer](https://getcomposer.org/download/) if you don’t have it.
2. In a terminal, go to this folder (`pet-insurance-app`).
3. Run:

   ```bash
   composer install
   ```

That creates `vendor/autoload.php` (this folder is in `.gitignore`, so each machine needs its own `composer install`).

---

## Windows: `composer` is not recognized

PowerShell shows **`CommandNotFoundException`** when Composer isn’t installed or isn’t on your `PATH`.

### Option A — Composer-Setup (easiest)

1. Download **Composer-Setup.exe** from: https://getcomposer.org/Composer-Setup.exe  
2. Run the installer and choose your **`php.exe`** when asked (XAMPP, Laragon, or standalone PHP).
3. **Close and reopen** PowerShell, then:

   ```powershell
   cd "C:\Users\lideo\Web Systems and Technologies\INF1005_Project\pet-insurance-app"
   composer install
   ```

### Option B — PHP works, but no `composer` command

1. Check: `php -v`
2. Download **composer.phar** from https://getcomposer.org/download/ (manual install instructions on that page).
3. From the `pet-insurance-app` folder:

   ```powershell
   php composer.phar install
   ```

If `php` is also not recognized, install PHP and add it to PATH (e.g. [windows.php.net](https://windows.php.net/download/), or Laragon / XAMPP).

## After that

Configure Stripe keys in your environment or `config` as documented for the project, then **Confirm and Pay** should work without the “Autoload file missing” error.
