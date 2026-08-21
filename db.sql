-- Survey Pacific Work Journey — MySQL Schema
-- Fresh install: mysql -u root -p < db.sql
-- Last updated: 2026-08-04

CREATE DATABASE IF NOT EXISTS survevap_inquiry CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE survevap_inquiry;

-- ─── Accounts ────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS accounts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  role          VARCHAR(50)   NOT NULL DEFAULT 'Member',
  status        ENUM('approved','pending','rejected','blocked') NOT NULL DEFAULT 'pending',
  requested_at  DATE          NOT NULL,
  last_seen_at  TIMESTAMP     NULL,
  INDEX idx_name (name)
);

-- ─── Inquiries ───────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS inquiries (
  id             VARCHAR(20)  PRIMARY KEY,
  date           VARCHAR(20)  NOT NULL,
  inquiry_type   VARCHAR(20)  NOT NULL DEFAULT 'Client Project',
  client         VARCHAR(150) NOT NULL,
  company        VARCHAR(150) NOT NULL,
  country        VARCHAR(100) NOT NULL DEFAULT 'India',
  is_new         TINYINT(1)   NOT NULL DEFAULT 0,
  client_type    VARCHAR(50)  NOT NULL DEFAULT 'New',
  requirement    TEXT         NOT NULL,
  created_by     VARCHAR(100) NOT NULL,
  current_owner  VARCHAR(100) NOT NULL,
  due_date       VARCHAR(20)  NOT NULL DEFAULT 'TBD',
  stage          VARCHAR(100) NOT NULL DEFAULT 'Inquiry Received',
  outcome        VARCHAR(100) NOT NULL DEFAULT 'In Progress',
  outcome_reason VARCHAR(255),
  proposal_value VARCHAR(30),
  delivery_date  VARCHAR(20),
  follow_up_date VARCHAR(20),
  final_value    VARCHAR(30),
  email          VARCHAR(150),
  secondary_email VARCHAR(150),
  phone          VARCHAR(30),
  website        VARCHAR(255),
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ─── Stage History ───────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS stage_history (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  inquiry_id     VARCHAR(20)  NOT NULL,
  stage          VARCHAR(100) NOT NULL,
  outcome        VARCHAR(100) NOT NULL,
  outcome_reason VARCHAR(255),
  by_user        VARCHAR(100) NOT NULL,
  date           VARCHAR(50)  NOT NULL,
  remark         TEXT,
  final_remark   TEXT,
  FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
);

-- ─── Inquiry Steps ───────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS inquiry_steps (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  inquiry_id  VARCHAR(20)  NOT NULL,
  assigned_by VARCHAR(100) NOT NULL,
  assigned_to VARCHAR(100) NOT NULL,
  instruction TEXT         NOT NULL,
  remark      TEXT,
  status      VARCHAR(50)  NOT NULL DEFAULT 'New',
  due         VARCHAR(20)  NOT NULL DEFAULT 'TBD',
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
);

-- ─── Step Attachments ────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS step_attachments (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  step_id  INT          NOT NULL,
  filename VARCHAR(255) NOT NULL,
  FOREIGN KEY (step_id) REFERENCES inquiry_steps(id) ON DELETE CASCADE
);

-- ─── Inquiry Attachments ─────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS inquiry_attachments (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  inquiry_id  VARCHAR(20)  NOT NULL,
  filename    VARCHAR(255) NOT NULL,
  uploaded_by VARCHAR(100) NOT NULL,
  uploaded_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
);

-- ─── Notifications ───────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT          NOT NULL,
  title      VARCHAR(255) NOT NULL,
  body       TEXT         NOT NULL,
  is_read    TINYINT(1)   NOT NULL DEFAULT 0,
  inquiry_id VARCHAR(20),
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
);

-- ─── Messages ────────────────────────────────────────────────────────────────
-- Direct messages between any account and a team member (Admin/Member/Master Admin).

CREATE TABLE IF NOT EXISTS messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  sender_id   INT       NOT NULL,
  receiver_id INT       NOT NULL,
  body        TEXT      NOT NULL,
  attachment  VARCHAR(255) NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (receiver_id) REFERENCES accounts(id) ON DELETE CASCADE,
  INDEX idx_conversation (sender_id, receiver_id, created_at)
);

-- ─── Announcements ───────────────────────────────────────────────────────────
-- Broadcast messages: only Admin/Master Admin post, all approved accounts can read.

CREATE TABLE IF NOT EXISTS announcements (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  created_by VARCHAR(100) NOT NULL,
  body       TEXT         NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- One row per account — last time they opened the Announcements page.
CREATE TABLE IF NOT EXISTS announcement_reads (
  account_id   INT       PRIMARY KEY,
  last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
);

-- ─── Leave requests ──────────────────────────────────────────────────────────
-- Members submit leave; Admin / Master Admin approve or decline.

CREATE TABLE IF NOT EXISTS leave_requests (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  account_id  INT         NOT NULL,
  leave_type  VARCHAR(40) NOT NULL DEFAULT 'Casual Leave',
  start_date  DATE        NOT NULL,
  end_date    DATE        NOT NULL,
  days        DECIMAL(4,1) NOT NULL,
  reason      TEXT        NOT NULL,
  status      ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  reviewed_by VARCHAR(100) NULL,
  reviewed_at TIMESTAMP   NULL,
  review_note VARCHAR(255) NULL,
  created_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  INDEX idx_leave_account (account_id, status),
  INDEX idx_leave_status (status)
);

-- ─── Seed Accounts ───────────────────────────────────────────────────────────
-- One per role, all approved so they can log in immediately.
-- To regenerate a hash: php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"

INSERT INTO accounts (name, email, password_hash, role, status, requested_at) VALUES
('Admin',       'admin@surveypacific.com',  '$2y$10$WhuFm.9AH94Bl9yMoXJ29OJMRA.r3eYPDSCoeD.kK0PhPwPcf8zui', 'Master Admin', 'approved', CURDATE()),
-- Password: Admin@123
('Ops Admin',   'opsadmin@surveypacific.com', '$2y$10$43fVBhVN3VD8z8qtZK.4W.PbDsbS8OvXmj2R85nDQVxbQV2GTciNW', 'Admin', 'approved', CURDATE()),
-- Password: Member@123
('Team Member', 'member@surveypacific.com', '$2y$10$9JDBUrcQTGcUy0spi65R9OCfsf6jaoq4Bvs9..DWIppbckzxT1p.C', 'Member', 'approved', CURDATE()),
-- Password: Client@123
('Test Client', 'client@surveypacific.com', '$2y$10$6LzDAkGlunHJItsH4DjT/uornKCVw/QoxmVes0nd5T0O4jcM5MzRO', 'Client', 'approved', CURDATE());
-- Password: Survey@404 (Master Admin)

-- ─── Existing DB migrations (run once if upgrading) ──────────────────────────
-- ALTER TABLE inquiries ADD COLUMN website VARCHAR(255) AFTER phone;
-- ALTER TABLE inquiries MODIFY client_type VARCHAR(50) NOT NULL DEFAULT 'New';
-- 2026-08-04
-- ALTER TABLE inquiries ADD COLUMN secondary_email VARCHAR(150) NULL AFTER email;
-- ALTER TABLE inquiries ADD COLUMN outcome_reason VARCHAR(255) NULL AFTER outcome;
-- ALTER TABLE inquiries ADD COLUMN admin_remark TEXT NULL AFTER outcome_reason;
-- ALTER TABLE stage_history ADD COLUMN outcome_reason VARCHAR(255) NULL AFTER outcome;
-- ALTER TABLE stage_history ADD COLUMN final_remark TEXT NULL AFTER remark;
-- 2026-08-12
-- ALTER TABLE inquiries ADD COLUMN inquiry_type VARCHAR(20) NOT NULL DEFAULT 'Client Project' AFTER date;
-- 2026-08-17
-- CREATE TABLE messages (see above) — run the block above directly on existing DBs.
-- 2026-08-21
-- ALTER TABLE inquiries ADD COLUMN team_remark TEXT NULL AFTER admin_remark;
-- ALTER TABLE inquiries ADD COLUMN team_remark_by VARCHAR(100) NULL AFTER team_remark;
-- CREATE TABLE announcements, announcement_reads (see above) — same.
-- ALTER TABLE messages ADD COLUMN attachment VARCHAR(255) NULL AFTER body;
-- ALTER TABLE accounts ADD COLUMN last_seen_at TIMESTAMP NULL AFTER requested_at;
-- 2026-08-19
-- CREATE TABLE leave_requests (see above) — run the block above directly on existing DBs.
-- ALTER TABLE leave_requests ADD COLUMN leave_type VARCHAR(40) NOT NULL DEFAULT 'Casual Leave' AFTER account_id;
-- ALTER TABLE leave_requests MODIFY days DECIMAL(4,1) NOT NULL;
-- leave_type values: Casual Leave, Half Day, Sick Leave, Emergency Leave, WFH
