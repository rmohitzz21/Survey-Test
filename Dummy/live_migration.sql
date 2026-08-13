-- Run this in cPanel phpMyAdmin → survevap_inquiry database → SQL tab
-- Safe to run multiple times (IF NOT EXISTS / IF NOT EXISTS index checks not needed — just run once)

-- 1. New columns on inquiries
ALTER TABLE inquiries
  ADD COLUMN IF NOT EXISTS outcome_reason  VARCHAR(255) NULL AFTER outcome,
  ADD COLUMN IF NOT EXISTS secondary_email VARCHAR(150) NULL AFTER email;

-- 2. New columns on stage_history
ALTER TABLE stage_history
  ADD COLUMN IF NOT EXISTS outcome_reason VARCHAR(255) NULL AFTER outcome,
  ADD COLUMN IF NOT EXISTS final_remark   TEXT         NULL AFTER remark;

-- 3. Performance indexes
CREATE INDEX IF NOT EXISTS idx_inq_created_by    ON inquiries(created_by);
CREATE INDEX IF NOT EXISTS idx_inq_current_owner ON inquiries(current_owner);
CREATE INDEX IF NOT EXISTS idx_inq_created_at    ON inquiries(created_at);
CREATE INDEX IF NOT EXISTS idx_steps_assigned_to ON inquiry_steps(assigned_to);
CREATE INDEX IF NOT EXISTS idx_steps_assigned_by ON inquiry_steps(assigned_by);
