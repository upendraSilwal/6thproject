-- Demo data for Urban Oasis - Property Listings (CLEAN VERSION)
-- Using existing real users: Upendra Silwal (user_id=28) and Dev Magar (user_id=29)
-- Only supports: house, apartment, room (NO land or commercial)

-- Adding 6 properties for Upendra Silwal (user_id=28)
INSERT INTO properties (user_id, title, description, property_type, listing_type, price, location, city, bedrooms, bathrooms, area_sqft, image_url, parking_spaces, furnished, available_from, contact_phone, contact_email, approval_status, created_at) VALUES
(28, 'Cozy Room in Thamel', 'Well-furnished room perfect for students and professionals.', 'room', 'rent', 12000, 'Thamel', 'Kathmandu', NULL, 1, 250, 'https://images.unsplash.com/photo-1560185127-5ffea2da3f7b?auto=format&fit=crop&w=500&q=80', 0, 1, '2025-08-10', '9860682982', 'kiranup18@gmail.com', 'approved', '2025-08-05 10:00:00'),
(28, 'Budget Room near Patan', 'Affordable room with essential amenities near Patan Durbar Square.', 'room', 'rent', 10000, 'Patan', 'Lalitpur', NULL, 1, 200, 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&w=500&q=80', 0, 0, '2025-08-12', '9860682982', 'kiranup18@gmail.com', 'approved', '2025-08-05 11:00:00'),
(28, 'Spacious Room in Baneshwor', 'Spacious room with all essential comforts and good connectivity.', 'room', 'rent', 15000, 'Baneshwor', 'Kathmandu', NULL, 1, 300, 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=500&q=80', 0, 1, '2025-08-15', '9860682982', 'kiranup18@gmail.com', 'approved', '2025-08-05 12:00:00'),
(28, 'Modern Apartment in Kathmandu', 'Beautiful 2BHK apartment with modern amenities in central Kathmandu.', 'apartment', 'rent', 35000, 'New Baneshwor', 'Kathmandu', 2, 2, 800, 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=500&q=80', 1, 1, '2025-08-20', '9860682982', 'kiranup18@gmail.com', 'approved', '2025-08-05 13:00:00'),
(28, 'House for Sale in Bhaktapur', 'Traditional style house with modern facilities in historic Bhaktapur.', 'house', 'sale', 8500000, 'Dudhpati', 'Bhaktapur', 4, 3, 2500, 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=500&q=80', 2, 1, '2025-08-25', '9860682982', 'kiranup18@gmail.com', 'approved', '2025-08-05 14:00:00');

-- Adding 5 properties for Dev Magar (user_id=29)
INSERT INTO properties (user_id, title, description, property_type, listing_type, price, location, city, bedrooms, bathrooms, area_sqft, image_url, parking_spaces, furnished, available_from, contact_phone, contact_email, approval_status, created_at) VALUES
(29, 'Luxury Room in Pokhara', 'Luxurious room with lake view and modern amenities in Pokhara.', 'room', 'rent', 20000, 'Lakeside', 'Pokhara', NULL, 1, 350, 'https://images.unsplash.com/photo-1549187774-b4e9b0445b71?auto=format&fit=crop&w=500&q=80', 0, 1, '2025-08-08', '9866449860', 'dev@gmail.com', 'approved', '2025-08-05 15:00:00'),
(29, 'Affordable Room in Chitwan', 'Simple room with shared facilities in peaceful Chitwan.', 'room', 'rent', 9000, 'Narayangarh', 'Chitwan', NULL, 1, 150, 'https://images.unsplash.com/photo-1586182984158-288486cf7025?auto=format&fit=crop&w=500&q=80', 0, 0, '2025-08-11', '9866449860', 'dev@gmail.com', 'approved', '2025-08-05 16:00:00'),
(29, 'Studio Apartment in Pokhara', 'Cozy studio apartment perfect for young professionals or students.', 'apartment', 'rent', 18000, 'Chipledhunga', 'Pokhara', 1, 1, 400, 'https://images.unsplash.com/photo-1560448075-bb485b067938?auto=format&fit=crop&w=500&q=80', 1, 1, '2025-08-22', '9866449860', 'dev@gmail.com', 'approved', '2025-08-05 19:00:00');

-- Adding some sample contact messages
INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES
('Raj Sharma', 'raj@example.com', 'Property Inquiry', 'I am interested in the properties listed in Kathmandu area. Can you provide more details?', '2025-08-05 09:00:00'),
('Sita Gurung', 'sita@example.com', 'General Question', 'What are your commission rates for property listings?', '2025-08-05 10:30:00'),
('Ram Thapa', 'ram@example.com', 'Technical Support', 'I am having trouble uploading property images. Can you help?', '2025-08-05 12:15:00'),
('Maya Shrestha', 'maya@example.com', 'Partnership Inquiry', 'I represent a real estate company and would like to discuss partnership opportunities.', '2025-08-05 14:45:00'),
('Bikash Tamang', 'bikash@example.com', 'Feedback', 'Great website! Very user-friendly and helpful for finding properties.', '2025-08-05 16:20:00');

-- Add some property features for the properties
INSERT INTO property_features (property_id, feature_name) VALUES
-- Features for Upendra's properties (assuming IDs will be sequential)
((SELECT MAX(id) FROM properties WHERE user_id = 28 AND title = 'Modern Apartment in Kathmandu'), 'Internet'),
((SELECT MAX(id) FROM properties WHERE user_id = 28 AND title = 'Modern Apartment in Kathmandu'), 'Parking'),
((SELECT MAX(id) FROM properties WHERE user_id = 28 AND title = 'Modern Apartment in Kathmandu'), 'Air Conditioning'),
((SELECT MAX(id) FROM properties WHERE user_id = 28 AND title = 'Modern Apartment in Kathmandu'), 'Security'),
((SELECT MAX(id) FROM properties WHERE user_id = 28 AND title = 'House for Sale in Bhaktapur'), 'Garden'),
((SELECT MAX(id) FROM properties WHERE user_id = 28 AND title = 'House for Sale in Bhaktapur'), 'Parking'),
-- Features for Dev's properties
((SELECT MAX(id) FROM properties WHERE user_id = 29 AND title = 'Luxury Room in Pokhara'), 'Lake View'),
((SELECT MAX(id) FROM properties WHERE user_id = 29 AND title = 'Luxury Room in Pokhara'), 'Internet'),
((SELECT MAX(id) FROM properties WHERE user_id = 29 AND title = 'Studio Apartment in Pokhara'), 'Furnished'),
((SELECT MAX(id) FROM properties WHERE user_id = 29 AND title = 'Studio Apartment in Pokhara'), 'Parking');
