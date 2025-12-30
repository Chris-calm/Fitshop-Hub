-- Gym workouts tables

-- Sessions table: one row per workout session
CREATE TABLE IF NOT EXISTS workout_sessions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  program_id INT NOT NULL,
  program_title VARCHAR(200) NOT NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME DEFAULT NULL,
  total_duration_sec INT DEFAULT 0,
  total_volume DECIMAL(12,2) DEFAULT 0,
  notes TEXT NULL,
  CONSTRAINT workout_sessions_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sets table: one row per performed set
CREATE TABLE IF NOT EXISTS workout_sets (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT NOT NULL,
  exercise_order INT NOT NULL,
  exercise_name VARCHAR(160) NOT NULL,
  set_number INT NOT NULL,
  target_reps INT DEFAULT NULL,
  performed_reps INT DEFAULT NULL,
  weight_kg DECIMAL(6,2) DEFAULT NULL,
  rpe DECIMAL(3,1) DEFAULT NULL,
  rest_sec INT DEFAULT NULL,
  CONSTRAINT workout_sets_session_fk FOREIGN KEY (session_id) REFERENCES workout_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
