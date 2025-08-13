-- Remove unsupported property types (land, commercial) from Urban Oasis
-- This script updates the database to only support house, apartment, and room

-- First, ensure no data exists for these types (already done above)
DELETE FROM properties WHERE property_type IN ('land', 'commercial');

-- Update the ENUM column to remove land and commercial
-- Note: This requires recreating the column with the new ENUM values
ALTER TABLE properties MODIFY COLUMN property_type ENUM('house','apartment','room') NOT NULL;

-- Verify the change
DESCRIBE properties;
