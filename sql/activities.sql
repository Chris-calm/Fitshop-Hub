-- Activity sessions for choreography and guides
CREATE TABLE IF NOT EXISTS activity_sessions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  activity_type ENUM('choreo','guide') NOT NULL,
  item_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME DEFAULT NULL,
  duration_sec INT DEFAULT 0,
  completed_steps INT DEFAULT 0,
  notes TEXT NULL,
  CONSTRAINT activity_sessions_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
