-- GuardLink database (created automatically the first time you open the app).
CREATE DATABASE IF NOT EXISTS guardlink;
USE guardlink;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,          -- hashed, never plain text
  role ENUM('resident','guard','management') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS invitations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resident_id INT NOT NULL,
  visitor_name VARCHAR(100) NOT NULL,
  visitor_phone VARCHAR(20) NOT NULL,
  visitor_id_no VARCHAR(30) NULL,
  valid_from DATETIME NOT NULL,
  valid_to DATETIME NOT NULL,
  token VARCHAR(32) NOT NULL UNIQUE,            -- in the registration link
  code CHAR(6) NOT NULL UNIQUE,                 -- entry code / QR content
  status ENUM('pending','registered','used','expired','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (resident_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS visit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invitation_id INT NOT NULL,
  guard_id INT NOT NULL,
  check_in DATETIME NOT NULL,
  check_out DATETIME NULL,
  FOREIGN KEY (invitation_id) REFERENCES invitations(id),
  FOREIGN KEY (guard_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  details VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (      -- SMS outbox
  id INT AUTO_INCREMENT PRIMARY KEY,
  to_phone VARCHAR(20) NOT NULL,
  message VARCHAR(300) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
