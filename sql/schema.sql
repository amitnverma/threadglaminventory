CREATE DATABASE IF NOT EXISTS threadglam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE threadglam;

CREATE TABLE IF NOT EXISTS settings (
  id INT PRIMARY KEY DEFAULT 1,
  company_name VARCHAR(255) NOT NULL DEFAULT 'ThreadGlam Events',
  company_address TEXT,
  company_phone VARCHAR(50),
  company_email VARCHAR(255),
  logo_path VARCHAR(500),
  default_tax_percent DECIMAL(5,2) DEFAULT 0,
  currency VARCHAR(10) DEFAULT 'USD',
  contract_footer TEXT,
  pdf_header TEXT,
  ceremony_types TEXT,
  ai_settings LONGTEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  phone VARCHAR(50),
  address TEXT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS inventory_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT,
  name VARCHAR(255) NOT NULL,
  sku VARCHAR(100),
  description TEXT,
  quantity_on_hand INT DEFAULT 0,
  unit_cost DECIMAL(12,2) DEFAULT 0,
  rental_price DECIMAL(12,2) DEFAULT 0,
  sale_price DECIMAL(12,2) DEFAULT 0,
  condition_status VARCHAR(50) DEFAULT 'good',
  reorder_level INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  FOREIGN KEY (category_id) REFERENCES inventory_categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS inventory_adjustments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  inventory_item_id INT NOT NULL,
  adjustment_type ENUM('add', 'remove', 'set') NOT NULL,
  quantity INT NOT NULL,
  reason VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attachable_type VARCHAR(50) NOT NULL,
  attachable_id INT NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  thumbnail_path VARCHAR(500),
  caption VARCHAR(255),
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attachable (attachable_type, attachable_id)
);

CREATE TABLE IF NOT EXISTS events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  ceremony_type VARCHAR(100),
  event_date DATE,
  end_date DATE,
  venue VARCHAR(500),
  status ENUM('inquiry', 'estimated', 'confirmed', 'completed', 'cancelled') DEFAULT 'inquiry',
  internal_notes TEXT,
  archived TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS estimates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  event_id INT,
  parent_estimate_id INT,
  title VARCHAR(255) NOT NULL,
  status ENUM('draft', 'sent', 'approved', 'rejected') DEFAULT 'draft',
  version INT DEFAULT 1,
  is_template TINYINT(1) DEFAULT 0,
  subtotal DECIMAL(12,2) DEFAULT 0,
  tax_percent DECIMAL(5,2) DEFAULT 0,
  tax_amount DECIMAL(12,2) DEFAULT 0,
  discount_type ENUM('percent', 'flat') DEFAULT 'percent',
  discount_value DECIMAL(12,2) DEFAULT 0,
  discount_amount DECIMAL(12,2) DEFAULT 0,
  total DECIMAL(12,2) DEFAULT 0,
  valid_until DATE,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
  FOREIGN KEY (parent_estimate_id) REFERENCES estimates(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS estimate_line_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  estimate_id INT NOT NULL,
  line_type ENUM('inventory', 'custom', 'labor', 'discount') DEFAULT 'custom',
  inventory_item_id INT,
  label VARCHAR(255) NOT NULL,
  description TEXT,
  quantity DECIMAL(10,2) DEFAULT 1,
  unit_price DECIMAL(12,2) DEFAULT 0,
  unit_cost DECIMAL(12,2) DEFAULT 0,
  sort_order INT DEFAULT 0,
  notes TEXT,
  FOREIGN KEY (estimate_id) REFERENCES estimates(id) ON DELETE CASCADE,
  FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS partners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(50),
  email VARCHAR(255),
  default_split_percent DECIMAL(5,2) DEFAULT 0,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS partner_expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  partner_id INT NOT NULL,
  event_id INT,
  category VARCHAR(100),
  description TEXT,
  amount DECIMAL(12,2) NOT NULL,
  expense_date DATE NOT NULL,
  receipt_path VARCHAR(500),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS purchases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier VARCHAR(255),
  purchase_date DATE NOT NULL,
  total DECIMAL(12,2) DEFAULT 0,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS purchase_line_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT NOT NULL,
  inventory_item_id INT,
  label VARCHAR(255) NOT NULL,
  quantity INT DEFAULT 1,
  unit_cost DECIMAL(12,2) DEFAULT 0,
  line_total DECIMAL(12,2) DEFAULT 0,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT,
  event_id INT,
  estimate_id INT,
  sale_date DATE NOT NULL,
  total DECIMAL(12,2) DEFAULT 0,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
  FOREIGN KEY (estimate_id) REFERENCES estimates(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sale_line_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  inventory_item_id INT,
  label VARCHAR(255) NOT NULL,
  quantity INT DEFAULT 1,
  unit_price DECIMAL(12,2) DEFAULT 0,
  line_total DECIMAL(12,2) DEFAULT 0,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS budgets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT,
  category VARCHAR(100) NOT NULL,
  allocated_amount DECIMAL(12,2) DEFAULT 0,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contract_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  content LONGTEXT NOT NULL,
  is_default TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contracts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  event_id INT,
  estimate_id INT,
  template_id INT,
  title VARCHAR(255) NOT NULL,
  content LONGTEXT NOT NULL,
  status ENUM('draft', 'sent', 'signed', 'cancelled') DEFAULT 'draft',
  signed_at TIMESTAMP NULL,
  signed_document_path VARCHAR(500),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
  FOREIGN KEY (estimate_id) REFERENCES estimates(id) ON DELETE SET NULL,
  FOREIGN KEY (template_id) REFERENCES contract_templates(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_username (username)
);

CREATE TABLE IF NOT EXISTS comm_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ceremony_type VARCHAR(100) NOT NULL DEFAULT '',
  name VARCHAR(255) NOT NULL,
  questions_json LONGTEXT NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ceremony (ceremony_type)
);

CREATE TABLE IF NOT EXISTS comm_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  customer_id INT NOT NULL,
  session_type ENUM('initial_meeting','follow_up','design_review','approval','other') NOT NULL DEFAULT 'initial_meeting',
  title VARCHAR(255) NOT NULL,
  status ENUM('draft','in_progress','summarized','closed') NOT NULL DEFAULT 'draft',
  held_at DATETIME NULL,
  summary_text TEXT NULL,
  summary_json LONGTEXT NULL,
  summarized_at DATETIME NULL,
  summary_model VARCHAR(120) NULL,
  album_id INT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_event (event_id),
  INDEX idx_customer (customer_id)
);

CREATE TABLE IF NOT EXISTS comm_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  question_key VARCHAR(80) NOT NULL,
  question_text VARCHAR(500) NOT NULL,
  answer_text TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  source ENUM('template','manual','ai') NOT NULL DEFAULT 'template',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_session (session_id),
  FOREIGN KEY (session_id) REFERENCES comm_sessions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comm_recordings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  duration_sec INT NULL,
  transcript_text LONGTEXT NULL,
  transcribe_status ENUM('none','pending','done','failed') NOT NULL DEFAULT 'none',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_session (session_id),
  FOREIGN KEY (session_id) REFERENCES comm_sessions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comm_decisions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  session_id INT NULL,
  decision_text VARCHAR(500) NOT NULL,
  status ENUM('proposed','approved','rejected') NOT NULL DEFAULT 'proposed',
  related_album_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_event (event_id),
  INDEX idx_session (session_id)
);
