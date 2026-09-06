-- MS Shanto Enterprise — Supplier module upgrade
--
-- Run this ONCE against an existing live database that was created before the
-- Supplier module was added. Import it in phpMyAdmin (Import tab, or paste it
-- into the SQL tab), or from a shell:
--     mysql -u YOUR_DB_USER -p YOUR_DB_NAME < upgrade-suppliers.sql
--
-- Safe to run twice: IF NOT EXISTS means a second run changes nothing.
-- A fresh import of sql/schema.sql already contains this table, so brand-new
-- installs can ignore this file.

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
