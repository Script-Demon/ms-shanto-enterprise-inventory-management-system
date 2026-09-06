# MS Shanto Enterprise — Inventory & Invoicing System

A small, self-contained inventory + invoicing web app for a building-accessories shop.
Plain PHP + MySQL — no framework, no build step, no Composer needed. Works on any
standard shared hosting plan with PHP 8+ and MySQL.

## Upgrading an existing install
For the Transportation module, run this once against your live database:
```sql
CREATE TABLE IF NOT EXISTS transport_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_date DATE NOT NULL,
  car_number VARCHAR(50) NOT NULL,
  driver_name VARCHAR(150) DEFAULT NULL,
  driver_phone VARCHAR(30) DEFAULT NULL,
  description VARCHAR(255) DEFAULT NULL,
  charge_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_transport_date (entry_date),
  INDEX idx_transport_car (car_number),
  INDEX idx_transport_driver (driver_name),
  INDEX idx_transport_phone (driver_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transport_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_date DATE NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (entry_id) REFERENCES transport_entries(id),
  INDEX idx_transport_pay_date (payment_date),
  INDEX idx_transport_pay_entry (entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

For the Supplier module, run this once against your live database:
```sql
CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  company VARCHAR(150) DEFAULT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_suppliers_name (name),
  INDEX idx_suppliers_company (company),
  INDEX idx_suppliers_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
For product images, run this once against your live database:
```sql
ALTER TABLE products ADD COLUMN image VARCHAR(100) DEFAULT NULL AFTER reorder_level;
```
Also upload the `assets/uploads/products/` folder (including its `.htaccess`) and
make it writable (chmod 755, or 775 if your host needs it). If it is missing the
app tries to create it on the first upload, which only works when
`assets/uploads/` is itself writable.

For the Salary module, run this once against your live database:
```sql
CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  designation VARCHAR(100) DEFAULT NULL,
  monthly_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_employees_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS salary_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_date DATE NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employee_id) REFERENCES employees(id),
  INDEX idx_salary_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```


If you already imported `sql/schema.sql` before the Settings page existed, run this
once to add the settings table, then create the uploads folder:
```sql
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(50) NOT NULL PRIMARY KEY,
  setting_value TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
Upload the `assets/uploads/` folder (including its `.htaccess`) and make it writable
(chmod 755, or 775 if your host needs it) so logo uploads can be saved.


If you already imported `sql/schema.sql` before 2026-09-05, run this once against your
live database (phpMyAdmin → SQL tab, or `mysql -u user -p dbname`) to add the unit
column used on invoice line items:
```sql
ALTER TABLE invoice_items ADD COLUMN unit VARCHAR(20) NOT NULL DEFAULT 'pcs' AFTER product_name;
UPDATE invoice_items ii JOIN products p ON p.id = ii.product_id SET ii.unit = p.unit WHERE ii.product_id IS NOT NULL;
```
A fresh import of `sql/schema.sql` already includes this column, so new installs can skip this.

## Installing it as an app (phone home screen / desktop)
Settings → "Install as an app" adds the app to your phone's home screen or your
computer's desktop, using your shop name and uploaded logo as the app name and icon.

**This requires HTTPS on your domain** — browsers only allow installation on a
secure address (`https://`). Enable the free SSL certificate in cPanel
(SSL/TLS → Let's Encrypt) and reload the page; the Install button then becomes
active in Chrome/Edge. On iPhone use Safari → Share → "Add to Home Screen".

Icons are generated from your logo by `icon.php`. If your host has the PHP **GD**
extension (most do) the logo is resized to the exact sizes launchers expect;
without GD the logo file is served as-is, which still works but looks best if you
upload a square image.

## Features
- Suppliers — a contact book of who you buy from: name, company, phone and a
  free-text note, with one search box that matches any of the name, company
  or phone
- Salary management — employees with their agreed monthly salary, individual
  salary payments (full, part or advance), per-employee totals, and month/year/
  employee filters showing how much was paid in any period
- Delete old data (Settings → Delete Old Data) — purge invoices, stock
  adjustments, salary payments and transportation records older than a
  chosen date range, so the database stays small. Shows a row-by-row
  preview first, never touches products/customers/employees/settings,
  never changes current stock, and keeps unpaid invoices and unsettled
  trips unless you explicitly include them
- Settings page — change your login username/password, the shop name, address,
  phone, currency symbol, and upload a logo (shown in the sidebar, on the login
  screen and on printed invoices). Branding is stored in the database, so the app
  never rewrites `config/config.php` (the file holding your DB password).
- Bilingual UI — বাংলা (default) and English, switchable anytime from the top-right
  corner; the choice is remembered per browser session
- Transportation ledger — per-trip car number, driver name and phone, the
  agreed charge, payments against it (full or part) and the outstanding
  balance, with month/year/exact-date filters, a search by car number, driver
  or phone, and monthly and yearly totals
- Products with categories, cost/sell price, unit, stock quantity, and an
  optional product photo shown on the product list
- Low-stock alerts (per-product reorder level)
- Auditable manual stock add/reduce (Adjust Stock page, with a history log)
- Invoice creation: search products, auto-calculated line/sub/total, discount,
  partial payment, automatic stock deduction
- Printable invoices (browser Print → "Save as PDF" — works everywhere, no PDF
  library dependency)
- Customer records with a due/credit ledger and payment history
- Dashboard: today's/monthly sales, low stock, outstanding dues, recent invoices
- Sales report with date range, sales total, estimated profit, and stock valuation

## Deploying to shared hosting (cPanel-style)

1. **Create a MySQL database.** In cPanel: MySQL Databases → create a database and
   a database user, then add that user to the database with **All Privileges**.
   Note the database name, username, and password (cPanel usually prefixes them
   with your account name, e.g. `myuser_shop`).

2. **Import the schema.** Open phpMyAdmin → select your new database → Import →
   choose `sql/schema.sql` from this project → Go. This creates all tables and a
   starter set of product categories.

3. **Upload the files.** Upload the entire contents of this folder to your hosting
   account via FTP or the cPanel File Manager — typically into `public_html/` (for
   the domain root) or a subfolder of it (e.g. `public_html/inventory/`) if you
   want the app at `yourdomain.com/inventory/`.

4. **Configure database credentials.** In `config/`, copy `config.php.example` to
   `config.php` and fill in the database host (usually `localhost`), name, user,
   and password from step 1. If you installed into a subfolder, set `base_path`
   to that subfolder (e.g. `/inventory`); leave it as `''` if installed at the
   domain root. You can also set your shop name/address/phone and currency symbol
   here — they appear on printed invoices.

5. **Create your admin login.** Visit `https://yourdomain.com/setup.php` (or
   `https://yourdomain.com/inventory/setup.php` if in a subfolder) once. Fill in
   your name, a username, and a password. This page disables itself automatically
   once an account exists.

6. **Log in** at `login.php` and start adding products.

### Security notes
- Leave `'debug' => false` in `config/config.php` on the live site. With it off,
  an unexpected error shows a plain "something went wrong" page and the details go
  to your host's error log; with it on, the real message and file path are printed
  in the browser, which is only safe while developing.
- `config/config.php` holds your real database password — never share it, and the
  included `.htaccess` files already block direct web access to the `config/` and
  `sql/` folders on Apache hosting (which is what almost all shared hosts run).
- Use HTTPS on your domain (most hosts offer free Let's Encrypt certificates via
  cPanel → SSL/TLS) since login credentials and invoice data travel over the
  connection.
- Change your admin password if you ever suspect it's been shared or guessed —
  there's no separate change-password screen yet, so for now that means deleting
  your row from the `users` table via phpMyAdmin and running `setup.php` again.

## How the core flows work
- **Selling something**: Invoices → New Invoice. Pick a customer (or leave it as
  walk-in), search and add products, adjust quantity/price/discount as needed,
  enter how much the customer is paying now, and save. Stock is deducted
  automatically; if they still owe money, it's tracked as a due balance against
  that invoice and (if a customer was selected) their ledger.
- **Restocking / correcting inventory**: Adjust Stock page — enter a positive
  number to add stock (e.g. after buying more from your supplier) or a negative
  number to reduce it (e.g. damaged goods), with a reason. Every change is logged.
- **Collecting a due payment later**: open the invoice (or the customer's ledger)
  and use "Record Payment".
- **Printing an invoice**: open the invoice → "Print / Save PDF" → your browser's
  print dialog lets you print to paper or choose "Save as PDF" as the destination.

## Project structure
```
config/        Database connection + your local config.php (not committed)
includes/      Shared PHP: auth guard, helper functions, header/footer
lang/          bn.php (default) and en.php translation dictionaries
assets/        CSS and JS
products/      Product & category CRUD
customers/     Customer CRUD + due ledger
suppliers/     Supplier contact book (name, company, phone, note) + search
invoices/      Create/list/view/print invoices, record payments
stock/         Manual stock adjustments
reports/       Sales report
api/           JSON endpoints used by the invoice-creation screen
sql/schema.sql Database schema + starter categories
setup.php      One-time admin account creation
```
