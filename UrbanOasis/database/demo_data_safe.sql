-- Safe Demo data for Urban Oasis - Property Listings
-- This version uses the first two available users in the database

-- First, let's add properties for the first available user (usually ID 1)
INSERT INTO properties (user_id, title, description, property_type, listing_type, price, location, city, bedrooms, bathrooms, area_sqft, image_url, parking_spaces, furnished, available_from, contact_phone, contact_email, approval_status, created_at) VALUES
(1, 'Cozy Room in Thamel', 'Well-furnished room perfect for students.', 'room', 'rent', 12000, 'Thamel', 'Kathmandu', NULL, 1, 250, 'https://images.unsplash.com/photo-1560185127-5ffea2da3f7b?auto=format&fit=crop&w=500&q=80', 0, 1, '2025-08-01', '9851234567', 'john@example.com', 'approved', '2025-07-28 10:00:00'),
(1, 'Budget Room near Patan', 'Affordable room with essential amenities.', 'room', 'rent', 10000, 'Patan', 'Lalitpur', NULL, 1, 200, 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&w=500&q=80', 0, 0, '2025-08-02', '9851234567', 'john@example.com', 'approved', '2025-07-28 11:00:00'),
(1, 'Spacious Room in Baneshwor', 'Spacious room with all essential comforts.', 'room', 'rent', 15000, 'Baneshwor', 'Kathmandu', NULL, 1, 300, 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=500&q=80', 0, 1, '2025-08-03', '9851234567', 'john@example.com', 'approved', '2025-07-28 12:00:00');

-- Add properties for the second user (usually ID 2)  
INSERT INTO properties (user_id, title, description, property_type, listing_type, price, location, city, bedrooms, bathrooms, area_sqft, image_url, parking_spaces, furnished, available_from, contact_phone, contact_email, approval_status, created_at) VALUES
(2, 'Luxury Room in Pokhara', 'Luxurious room with lake view and modern amenities.', 'room', 'rent', 20000, 'Lakeside', 'Pokhara', NULL, 1, 350, 'https://images.unsplash.com/photo-1549187774-b4e9b0445b71?auto=format&fit=crop&w=500&q=80', 0, 1, '2025-08-04', '9851234568', 'jane@example.com', 'approved', '2025-07-28 13:00:00'),
(2, 'Shared Room in Bhaktapur', 'Economical option for students and bachelors.', 'room', 'rent', 8000, 'Suryabinayak', 'Bhaktapur', NULL, 1, 200, 'https://images.unsplash.com/photo-1519974719765-e6559eac2575?auto=format&fit=crop&w=500&q=80', 0, 0, '2025-08-05', '9851234568', 'jane@example.com', 'approved', '2025-07-28 14:00:00');
