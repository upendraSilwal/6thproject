-- Demo data for Urban Oasis - Property Listings

-- Using existing users: Jane Smith (user_id=2) and Mike Wilson (user_id=3)

-- Adding 5 listings for Jane Smith (user_id=2)
INSERT INTO properties (user_id, title, description, property_type, listing_type, price, location, city, bedrooms, bathrooms, area_sqft, image_url, parking_spaces, furnished, available_from, contact_phone, contact_email, approval_status, created_at) VALUES
(2, 'Cozy Room in Thamel', 'Well-furnished room perfect for students.', 'room', 'rent', 12000, 'Thamel', 'Kathmandu', NULL, 1, 250, 'https://images.unsplash.com/photo-1560185127-5ffea2da3f7b?auto=format&fit=crop&w=500&q=80', 0, 1, '2025-08-01', '9851234568', 'jane@example.com', 'approved', '2025-07-28 10:00:00'),
(2, 'Budget Room near Patan', 'Affordable room with essential amenities.', 'room', 'rent', 10000, 'Patan', 'Lalitpur', NULL, 1, 200, 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&w=500&q=80', 0, 0, '2025-08-02', '9851234568', 'jane@example.com', 'approved', '2025-07-28 11:00:00'),
(2, 'Spacious Room in Baneshwor', 'Spacious room with all essential comforts.', 'room', 'rent', 15000, 'Baneshwor', 'Kathmandu', NULL, 1, 300, 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=500&q=80', 0, 1, '2025-08-03', '9851234568', 'jane@example.com', 'approved', '2025-07-28 12:00:00'),
(2, 'Luxury Room in Pokhara', 'Luxurious room with lake view and modern amenities.', 'room', 'rent', 20000, 'Lakeside', 'Pokhara', NULL, 1, 350, 'https://images.unsplash.com/photo-1549187774-b4e9b0445b71?auto=format&fit=crop&w=500&q=80', 0, 1, '2025-08-04', '9851234568', 'jane@example.com', 'approved', '2025-07-28 13:00:00'),
(2, 'Shared Room in Bhaktapur', 'Economical option for students and bachelors.', 'room', 'rent', 8000, 'Suryabinayak', 'Bhaktapur', NULL, 1, 200, 'https://images.unsplash.com/photo-1519974719765-e6559eac2575?auto=format&fit=crop&w=500&q=80', 0, 0, '2025-08-05', '9851234568', 'jane@example.com', 'approved', '2025-07-28 14:00:00');
-- Additional listings can be added here for Jane Smith...

-- Adding 5 listings for Mike Wilson (user_id=3)
INSERT INTO properties (user_id, title, description, property_type, listing_type, price, location, city, bedrooms, bathrooms, area_sqft, image_url, parking_spaces, furnished, available_from, contact_phone, contact_email, approval_status, created_at) VALUES
(3, 'Affordable Room in Chitwan', 'Simple room with shared facilities.', 'room', 'rent', 9000, 'Narayangarh', 'Chitwan', NULL, 1, 150, 'https://images.unsplash.com/photo-1586182984158-288486cf7025?auto=format&fit=crop&w=500&q=80', 0, 0, '2025-08-01', '9851234569', 'mike@example.com', 'approved', '2025-07-28 15:00:00'),
(3, 'Peaceful Room near Lakeside', 'Quiet and peaceful room with garden view.', 'room', 'rent', 14000, 'Lakeside', 'Pokhara', NULL, 1, 250, 'https://images.unsplash.com/photo-1574169208507-843761648bb2?auto=format&fit=crop&w=500&q=80', 0, 1, '2025-08-02', '9851234569', 'mike@example.com', 'approved', '2025-07-28 16:00:00'),
(3, 'Modern Room in New Baneshwor', 'Modern amenities ensure a comfortable stay.', 'room', 'rent', 18000, 'New Baneshwor', 'Kathmandu', NULL, 1, 270, 'https://images.unsplash.com/photo-1554995207-c18c203602cb?auto=format&fit=crop&w=500&q=80', 0, 1, '2025-08-03', '9851234569', 'mike@example.com', 'approved', '2025-07-28 17:00:00'),
(3, 'Central Location Room in Lalitpur', 'Close to all major amenities and public transport.', 'room', 'rent', 16000, 'Kupondole', 'Lalitpur', NULL, 1, 260, 'https://images.unsplash.com/photo-1581132515280-c4bdbb1d2f29?auto=format&fit=crop&w=500&q=80', 0, 1, '2025-08-04', '9851234569', 'mike@example.com', 'approved', '2025-07-28 18:00:00'),
(3, 'Economical Room in Kirtipur', 'Suitable for budget-conscious individuals.', 'room', 'rent', 7000, 'Kirtipur', 'Kathmandu', NULL, 1, 180, 'https://images.unsplash.com/photo-1566127992457-aa6b5f680f5c?auto=format&fit=crop&w=500&q=80', 0, 0, '2025-08-05', '9851234569', 'mike@example.com', 'approved', '2025-07-28 19:00:00');
-- Additional listings can be added here for Mike Wilson...

