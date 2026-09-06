-- MS Shanto Enterprise - Inventory & Invoicing System
-- Import this file once via phpMyAdmin (or `mysql -u user -p dbname < schema.sql`)
-- after creating an empty database on your hosting control panel.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Editable from the in-app Settings page. Kept in the database rather than in
-- config/config.php so the app never has to rewrite the file holding your DB password.
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(50) NOT NULL PRIMARY KEY,
  setting_value TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  sku VARCHAR(50) DEFAULT NULL,
  category_id INT DEFAULT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sell_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0,
  image VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_products_name (name),
  INDEX idx_products_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customers_name (name),
  INDEX idx_customers_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Who you buy from. A plain contact book: no stock or money is posted against a
-- supplier, so a row can be removed at any time without touching other records.
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

CREATE TABLE IF NOT EXISTS invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(30) DEFAULT NULL,
  customer_id INT DEFAULT NULL,
  walkin_name VARCHAR(150) DEFAULT NULL,
  walkin_phone VARCHAR(30) DEFAULT NULL,
  invoice_date DATE NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('paid','partial','due') NOT NULL DEFAULT 'paid',
  voided TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  INDEX idx_invoices_no (invoice_no),
  INDEX idx_invoices_date (invoice_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  product_id INT DEFAULT NULL,
  product_name VARCHAR(150) NOT NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
  unit_price DECIMAL(12,2) NOT NULL,
  cost_price_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0,
  quantity DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  customer_id INT DEFAULT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_date DATE NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- Salary paid out. Kept as individual payments (not one row per month) so
-- advances and part-payments are recorded exactly as they happen.
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

CREATE TABLE IF NOT EXISTS stock_adjustments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  change_qty DECIMAL(12,2) NOT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  adjusted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A starter set of categories for a building-accessories shop. Edit/add more from the app.
INSERT IGNORE INTO categories (name) VALUES
('Locks'), ('Hinges'), ('Screws & Fasteners'), ('Paint'),
('Plumbing'), ('Electrical'), ('Tools'), ('Misc');

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

-- No admin user is seeded here on purpose (a hand-written password hash cannot be
-- verified as correct). After importing this file, open setup.php in your browser
-- once to create your admin login securely.
