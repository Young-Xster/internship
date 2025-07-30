-- Check materiel_history table structure
SHOW CREATE TABLE materiel_history;

-- Check for nulls in previous_owner and new_owner
SELECT numserie, previous_owner, new_owner, date_change FROM materiel_history ORDER BY date_change DESC LIMIT 10;
