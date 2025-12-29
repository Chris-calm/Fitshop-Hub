-- Run this after initial schema to enable steps tracking

-- Add per-user steps goal (skip if it already exists)
ALTER TABLE users ADD COLUMN steps_goal INT DEFAULT 10000;

-- Steps logs table (daily aggregation)
CREATE TABLE IF NOT EXISTS steps_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  step_date DATE NOT NULL,
  steps INT NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_user_date (user_id, step_date),
  CONSTRAINT steps_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
