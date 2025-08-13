-- Check if users exist in the database
SELECT id, email, first_name, last_name 
FROM users 
WHERE id IN (2, 3);

-- Show all users for reference
SELECT id, email, first_name, last_name 
FROM users 
ORDER BY id;
