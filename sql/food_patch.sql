-- Backfill null macros to 0 and set defaults to 0 going forward

-- Backfill existing rows
UPDATE food_logs SET calories = 0 WHERE calories IS NULL;
UPDATE food_logs SET protein_g = 0 WHERE protein_g IS NULL;
UPDATE food_logs SET carbs_g = 0 WHERE carbs_g IS NULL;
UPDATE food_logs SET fat_g = 0 WHERE fat_g IS NULL;

-- Set column defaults to 0 for future inserts
ALTER TABLE food_logs 
  MODIFY calories INT NOT NULL DEFAULT 0,
  MODIFY protein_g DECIMAL(6,2) NOT NULL DEFAULT 0,
  MODIFY carbs_g DECIMAL(6,2) NOT NULL DEFAULT 0,
  MODIFY fat_g DECIMAL(6,2) NOT NULL DEFAULT 0;
