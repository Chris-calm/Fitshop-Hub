    -- Food logs for nutrition tracking
    CREATE TABLE IF NOT EXISTS food_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(160) NOT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    calories INT DEFAULT NULL,
    protein_g DECIMAL(6,2) DEFAULT NULL,
    carbs_g DECIMAL(6,2) DEFAULT NULL,
    fat_g DECIMAL(6,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT food_logs_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
