-- CLEAN Demo Data for Urban Oasis - Property Listings
-- Updated: August 2025
-- Users: Upendra Silwal (ID: 28) and Dev Magar (ID: 29)  
-- Property Types: house, apartment, room ONLY

-- =============================================================================
-- PROPERTIES DATA
-- =============================================================================

-- Properties for Upendra Silwal (user_id = 28) - 5 properties
INSERT INTO properties (user_id, title, description, property_type, listing_type, price, location, city, bedrooms, bathrooms, area_sqft, image_url, parking_spaces, furnished, available_from, contact_phone, contact_email, approval_status, expires_at, created_at) VALUES

-- Upendra's Room Rentals (3 rooms)
(28, 'Cozy Furnished Room in Thamel', 'Well-furnished room perfect for students and young professionals. Located in the heart of Thamel with easy access to restaurants, cafes, and tourist attractions.', 'room', 'rent', 12000, 'Thamel', 'Kathmandu', NULL, 1, 250, 'https://images.unsplash.com/photo-1560185007-cde436f6a4d0?auto=format&fit=crop&w=800&q=80', 0, 1, '2025-08-10', '9860682982', 'kiranup18@gmail.com', 'approved', DATE_ADD(NOW(), INTERVAL 30 DAY), '2025-08-05 10:00:00'),

(28, 'Budget Room near Patan Durbar Square', 'Affordable room with essential amenities. Great location near Patan Durbar Square, perfect for cultural enthusiasts and budget-conscious individuals.', 'room', 'rent', 10000, 'Patan', 'Lalitpur', NULL, 1, 200, 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=800&q=80', 0, 0, '2025-08-12', '9860682982', 'kiranup18@gmail.com', 'approved', DATE_ADD(NOW(), INTERVAL 30 DAY), '2025-08-05 11:00:00'),

(28, 'Spacious Room in Baneshwor', 'Large, comfortable room in a quiet residential area of Baneshwor. Good connectivity to major parts of Kathmandu with all essential comforts.', 'room', 'rent', 15000, 'Baneshwor', 'Kathmandu', NULL, 1, 300, 'https://images.unsplash.com/photo-1631679706909-1844bbd07221?auto=format&fit=crop&w=800&q=80', 0, 1, '2025-08-15', '9860682982', 'kiranup18@gmail.com', 'approved', DATE_ADD(NOW(), INTERVAL 30 DAY), '2025-08-05 12:00:00'),

-- Upendra's Apartment Rental (1 apartment)
(28, 'Modern 2BHK Apartment in New Baneshwor', 'Beautiful fully-furnished 2-bedroom apartment with modern amenities. Perfect for small families or working professionals. Includes parking space and 24/7 security.', 'apartment', 'rent', 35000, 'New Baneshwor', 'Kathmandu', 2, 2, 800, 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800&q=80', 1, 1, '2025-08-20', '9860682982', 'kiranup18@gmail.com', 'approved', DATE_ADD(NOW(), INTERVAL 30 DAY), '2025-08-05 13:00:00'),

-- Upendra's House for Sale (1 house)
(28, 'Traditional House for Sale in Bhaktapur', 'Beautiful traditional-style house with modern facilities in historic Bhaktapur. Features 4 bedrooms, 3 bathrooms, garden area, and 2-car parking. Perfect for families who want to live in a cultural heritage area.', 'house', 'sale', 8500000, 'Dudhpati', 'Bhaktapur', 4, 3, 2500, 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&w=800&q=80', 2, 1, '2025-08-25', '9860682982', 'kiranup18@gmail.com', 'approved', DATE_ADD(NOW(), INTERVAL 30 DAY), '2025-08-05 14:00:00');

-- Properties for Dev Magar (user_id = 29) - 5 properties  
INSERT INTO properties (user_id, title, description, property_type, listing_type, price, location, city, bedrooms, bathrooms, area_sqft, image_url, parking_spaces, furnished, available_from, contact_phone, contact_email, approval_status, expires_at, created_at) VALUES

-- Dev's Room Rentals (2 rooms)
(29, 'Luxury Room with Lake View in Pokhara', 'Premium furnished room with stunning Phewa Lake view. Perfect for professionals or tourists looking for a comfortable long-term stay in Pokhara. All modern amenities included.', 'room', 'rent', 20000, 'Lakeside', 'Pokhara', NULL, 1, 350, 'https://images.unsplash.com/photo-1540932239986-30128078f3c5?auto=format&fit=crop&w=800&q=80', 0, 1, '2025-08-08', '9866449860', 'dev@gmail.com', 'approved', DATE_ADD(NOW(), INTERVAL 30 DAY), '2025-08-05 15:00:00'),

(29, 'Simple Room in Chitwan', 'Clean, affordable room with shared facilities in peaceful Chitwan. Great for nature lovers and budget travelers. Close to Chitwan National Park.', 'room', 'rent', 9000, 'Narayangarh', 'Chitwan', NULL, 1, 150, 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=800&q=80', 0, 0, '2025-08-11', '9866449860', 'dev@gmail.com', 'approved', DATE_ADD(NOW(), INTERVAL 30 DAY), '2025-08-05 16:00:00'),

-- Dev's Apartment Rentals (2 apartments)
(29, 'Studio Apartment in Pokhara', 'Cozy studio apartment perfect for young professionals, students, or couples. Fully furnished with modern kitchen, comfortable living space, and mountain views.', 'apartment', 'rent', 18000, 'Chipledhunga', 'Pokhara', 1, 1, 400, 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=800&q=80', 1, 1, '2025-08-22', '9866449860', 'dev@gmail.com', 'approved', DATE_ADD(NOW(), INTERVAL 30 DAY), '2025-08-05 17:00:00'),

(29, 'Luxury 2BHK Apartment in Pokhara Lakeside', 'Premium apartment with breathtaking lake and mountain views. Fully furnished with high-end amenities, perfect for families or professionals seeking luxury living.', 'apartment', 'rent', 28000, 'Lakeside', 'Pokhara', 2, 2, 900, 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=800&q=80', 1, 1, '2025-08-18', '9866449860', 'dev@gmail.com', 'approved', DATE_ADD(NOW(), INTERVAL 30 DAY), '2025-08-05 18:00:00'),

-- Dev's House for Sale (1 house)
(29, 'Modern Family House in Pokhara', 'Beautiful 3-bedroom house with garden in a quiet residential area of Pokhara. Features modern design, ample parking, and stunning mountain views. Perfect for families.', 'house', 'sale', 6500000, 'Baidam', 'Pokhara', 3, 2, 1800, 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=800&q=80', 2, 1, '2025-08-28', '9866449860', 'dev@gmail.com', 'approved', DATE_ADD(NOW(), INTERVAL 30 DAY), '2025-08-05 19:00:00');

-- =============================================================================
-- PROPERTY FEATURES DATA  
-- =============================================================================

INSERT INTO property_features (property_id, feature_name) VALUES
-- Features for Upendra's properties
(1, 'Internet'), (1, 'Furnished'), (1, 'Near Public Transport'),
(2, 'Water Supply'), (2, 'Electricity'), (2, 'Near Market'),
(3, 'Internet'), (3, 'Furnished'), (3, 'Quiet'), (3, 'Parking'),
(4, 'Internet'), (4, 'Parking'), (4, 'Air Conditioning'), (4, 'Security'), (4, 'Furnished'), (4, 'Elevator'),
(5, 'Garden'), (5, 'Parking'), (5, 'Security'), (5, 'Traditional Design'), (5, 'Near School'),

-- Features for Dev's properties  
(6, 'Lake View'), (6, 'Internet'), (6, 'Furnished'), (6, 'Mountain View'),
(7, 'Water Supply'), (7, 'Electricity'), (7, 'Near National Park'),
(8, 'Furnished'), (8, 'Modern Kitchen'), (8, 'Mountain View'), (8, 'Parking'),
(9, 'Lake View'), (9, 'Mountain View'), (9, 'Furnished'), (9, 'Parking'), (9, 'Luxury'), (9, 'Modern Kitchen'),
(10, 'Garden'), (10, 'Mountain View'), (10, 'Parking'), (10, 'Modern Design'), (10, 'Quiet');

-- =============================================================================
-- SAMPLE CONTACT MESSAGES
-- =============================================================================

INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES
('Raj Sharma', 'raj.sharma@example.com', 'Property Inquiry - Kathmandu', 'Hello, I am interested in the room properties listed in Kathmandu area. Could you provide more details about availability and visiting arrangements?', '2025-08-05 09:00:00'),
('Sita Gurung', 'sita.gurung@example.com', 'Commission Rates Inquiry', 'Hi, I am planning to list my property on your platform. What are your commission rates for property listings? Do you provide any marketing support?', '2025-08-05 10:30:00'),
('Ram Thapa', 'ram.thapa@example.com', 'Technical Support Request', 'I am having trouble uploading property images through the website. The upload seems to fail every time. Can you help me resolve this issue?', '2025-08-05 12:15:00'),
('Maya Shrestha', 'maya.shrestha@example.com', 'Partnership Opportunity', 'Greetings! I represent a real estate company in Pokhara and would like to discuss potential partnership opportunities with Urban Oasis. Please let me know if you are interested.', '2025-08-05 14:45:00'),
('Bikash Tamang', 'bikash.tamang@example.com', 'Website Feedback', 'Great website! Very user-friendly and helpful for finding properties in Nepal. The search filters work perfectly and the property details are comprehensive. Keep up the good work!', '2025-08-05 16:20:00'),
('Anita Rai', 'anita.rai@example.com', 'Apartment Inquiry - Pokhara', 'I am looking for a 2BHK apartment in Pokhara for my family. Saw some great options on your site. When can I schedule a viewing?', '2025-08-05 18:30:00');

-- =============================================================================
-- SUMMARY QUERY
-- =============================================================================
SELECT 'DEMO DATA IMPORTED SUCCESSFULLY' as status;
SELECT 
    'Properties by Type' as summary,
    property_type,
    COUNT(*) as count,
    CONCAT('Rs. ', FORMAT(MIN(price), 0), ' - Rs. ', FORMAT(MAX(price), 0)) as price_range
FROM properties 
GROUP BY property_type
UNION ALL
SELECT 'TOTAL', 'ALL TYPES', COUNT(*), CONCAT('Rs. ', FORMAT(MIN(price), 0), ' - Rs. ', FORMAT(MAX(price), 0))
FROM properties;
