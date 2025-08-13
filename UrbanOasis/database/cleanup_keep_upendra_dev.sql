-- Cleanup script for Urban Oasis
-- This script removes demo data while preserving real users: Upendra Silwal and Dev Magar

-- First, identify the user IDs for Upendra and Dev (in case they change)
SET @upendra_id = (SELECT id FROM users WHERE first_name = 'Upendra' AND last_name = 'Silwal' LIMIT 1);
SET @dev_id = (SELECT id FROM users WHERE first_name = 'Dev' AND last_name = 'magar' LIMIT 1);

-- Remove all properties NOT belonging to Upendra or Dev
-- This also removes related property_features due to CASCADE
DELETE FROM properties 
WHERE user_id NOT IN (@upendra_id, @dev_id);

-- Remove all users except Upendra and Dev  
-- This also removes related credit_transactions due to CASCADE
DELETE FROM users 
WHERE id NOT IN (@upendra_id, @dev_id);

-- Remove demo contact messages (optional - keeps real inquiries)
DELETE FROM contact_messages 
WHERE name IN ('Alice Johnson', 'Bob Williams', 'Carol Davis', 'Edward Miller', 'Fiona Garcia', 
               'Jane Smith', 'Mike Wilson', 'John Doe', 'Sarah Brown', 'David Jones',
               'Raj Sharma', 'Sita Gurung', 'Ram Thapa', 'Maya Shrestha', 'Bikash Tamang');

-- Display summary of remaining data
SELECT 'Users Summary' as table_name, COUNT(*) as count FROM users
UNION ALL
SELECT 'Properties Summary', COUNT(*) FROM properties  
UNION ALL
SELECT 'Contact Messages Summary', COUNT(*) FROM contact_messages
UNION ALL
SELECT 'Credit Transactions Summary', COUNT(*) FROM credit_transactions;

-- Show remaining users
SELECT 'Remaining Users:' as info, id, first_name, last_name, email FROM users;
