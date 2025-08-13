<?php
require_once 'config/database.php';
require_once 'config/property_utils.php';

echo "=== UrbanOasis Bulk Property Insert Script ===\n\n";

// Get Upendra Silwal's user account
$stmt = $pdo->prepare("SELECT id, first_name, last_name, listing_credits FROM users WHERE first_name = 'Upendra' AND last_name = 'Silwal' LIMIT 1");
$stmt->execute();
$user = $stmt->fetch();

if (!$user) {
    die("Error: Upendra Silwal not found in database. Please check if the user exists.\n");
}

echo "Found user: {$user['first_name']} {$user['last_name']} (ID: {$user['id']}, Credits: {$user['listing_credits']})\n\n";
$user_id = $user['id'];

// Unsplash images for different property types
$unsplashImages = [
    'house' => [
        'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1507089947368-19c1da9775ae?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1460518451285-97b6aa326961?auto=format&fit=crop&w=500&q=80'
    ],
    'apartment' => [
        'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1502005229762-cf1b2da7c5d6?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1519125323398-675f0ddb6308?auto=format&fit=crop&w=500&q=80'
    ],
    'room' => [
        'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1507089947368-19c1da9775ae?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1465101178521-c1a9136a3b99?auto=format&fit=crop&w=500&q=80'
    ]
];

// Cities in Nepal
$cities = ['Kathmandu', 'Pokhara', 'Lalitpur', 'Bhaktapur', 'Biratnagar', 'Bharatpur', 'Butwal', 'Dharan', 'Hetauda', 'Itahari', 'Janakpur', 'Nepalgunj'];

// Locations within cities
$locations = [
    'Kathmandu' => ['Baneshwor', 'Lazimpat', 'Durbarmarg', 'Thamel', 'New Baneshwor', 'Maharajgunj', 'Anamnagar', 'Putalisadak'],
    'Pokhara' => ['Lakeside', 'Mahendrapul', 'Prithvi Chowk', 'New Road', 'Chipledhunga', 'Bagar'],
    'Lalitpur' => ['Pulchowk', 'Jawalakhel', 'Kupondole', 'Sanepa', 'Ekantakuna'],
    'Bhaktapur' => ['Durbar Square', 'Suryabinayak', 'Changunarayan', 'Thimi'],
    'Biratnagar' => ['Main Road', 'Traffic Chowk', 'Bhanu Chowk', 'Rani'],
    'Bharatpur' => ['Narayangadh', 'Pulchowk', 'Hospital Road'],
    'Butwal' => ['Traffic Chowk', 'Golpark', 'Kalikanagar'],
    'Dharan' => ['Pindeshwor', 'Bhanu Chowk', 'Chatta Dhunga'],
    'Hetauda' => ['Main Bazaar', 'Hospital Road', 'Bus Park'],
    'Itahari' => ['Main Road', 'Kanchanbari', 'Traffic Chowk'],
    'Janakpur' => ['Ram Mandir', 'Station Road', 'Murli Chowk'],
    'Nepalgunj' => ['Medical College', 'Tribhuvan Chowk', 'Dhamboji']
];

// Property features by category
$featuresByCategory = [
    'essential' => ['Security', 'Parking', 'Water Supply', 'Electricity', 'Internet'],
    'comfort' => ['Air Conditioning', 'Furnished', 'Garden', 'Balcony', 'Terrace', 'Fireplace'],
    'luxury' => ['Swimming Pool', 'Gym', 'Spa', 'Elevator', 'Jacuzzi'],
    'convenience' => ['Near Market', 'Near Hospital', 'Near School', 'Near Public Transport', 'Near Restaurant'],
    'safety' => ['CCTV', 'Security Guard', 'Fire Safety']
];

// Property titles by type
$propertyTitles = [
    'house' => [
        'Luxurious 4BHK Villa with Garden',
        'Modern Family House with Parking',
        'Spacious 3BHK House Near School',
        'Beautiful Villa with Mountain View',
        'Traditional Nepali House',
        'Contemporary 5BHK Mansion',
        'Cozy Family Home',
        'Elegant Two-Story House',
        'House with Private Garden',
        'Newly Built Modern House'
    ],
    'apartment' => [
        'Modern 3BHK Apartment',
        'Luxury 2BHK Flat with Balcony',
        'Spacious Apartment Near City Center',
        'Furnished 1BHK Apartment',
        'Premium Apartment with Elevator',
        'Cozy 2BHK Flat',
        'High-Rise Apartment with View',
        'Well-Ventilated 3BHK Flat',
        'Apartment with Modern Amenities',
        'Comfortable Family Apartment'
    ],
    'room' => [
        'Single Room for Students',
        'Comfortable Room with Attached Bath',
        'Furnished Room Near College',
        'Spacious Room for Working Professional',
        'Budget-Friendly Single Room',
        'Room with Balcony Access',
        'Quiet Room in Residential Area',
        'Room with Kitchen Access',
        'Well-Lit Study Room',
        'Private Room with WiFi'
    ]
];

// Generate comprehensive property data
$properties = [];
$propertyCount = 0;

// Generate properties for each city and type combination
foreach ($cities as $city) {
    foreach (['house', 'apartment', 'room'] as $propertyType) {
        // Generate 4-6 properties of each type in each city
        $propertiesInCityType = rand(4, 6);
        
        for ($i = 0; $i < $propertiesInCityType; $i++) {
            $location = $locations[$city][array_rand($locations[$city])];
            $title = $propertyTitles[$propertyType][array_rand($propertyTitles[$propertyType])];
            $image = $unsplashImages[$propertyType][array_rand($unsplashImages[$propertyType])];
            
            // Generate realistic prices based on city and type (Nepal market prices)
            $priceRanges = [
                'Kathmandu' => [
                    'house' => ['rent' => [40000, 150000], 'sale' => [50000000, 500000000]], // 5 crore to 50 crore
                    'apartment' => ['rent' => [20000, 35000], 'sale' => [8000000, 80000000]], // 80 lakh to 8 crore
                    'room' => ['rent' => [6000, 12000], 'sale' => null] // Only rent
                ],
                'Pokhara' => [
                    'house' => ['rent' => [25000, 80000], 'sale' => [30000000, 300000000]], // 3 crore to 30 crore
                    'apartment' => ['rent' => [15000, 25000], 'sale' => [5000000, 50000000]], // 50 lakh to 5 crore
                    'room' => ['rent' => [4000, 8000], 'sale' => null]
                ],
                'Lalitpur' => [
                    'house' => ['rent' => [35000, 120000], 'sale' => [40000000, 400000000]], // 4 crore to 40 crore
                    'apartment' => ['rent' => [18000, 30000], 'sale' => [6000000, 60000000]], // 60 lakh to 6 crore
                    'room' => ['rent' => [5500, 10000], 'sale' => null]
                ],
                'Bhaktapur' => [
                    'house' => ['rent' => [20000, 60000], 'sale' => [25000000, 200000000]], // 2.5 crore to 20 crore
                    'apartment' => ['rent' => [12000, 20000], 'sale' => [4000000, 40000000]], // 40 lakh to 4 crore
                    'room' => ['rent' => [4000, 7000], 'sale' => null]
                ],
                'default' => [
                    'house' => ['rent' => [15000, 50000], 'sale' => [20000000, 150000000]], // 2 crore to 15 crore
                    'apartment' => ['rent' => [10000, 18000], 'sale' => [3000000, 30000000]], // 30 lakh to 3 crore
                    'room' => ['rent' => [3000, 6000], 'sale' => null]
                ]
            ];
            
            $cityRange = $priceRanges[$city] ?? $priceRanges['default'];
            $typeRange = $cityRange[$propertyType];
            
            // Determine listing type (rooms are only for rent)
            $listingType = ($propertyType === 'room') ? 'rent' : (rand(0, 1) ? 'rent' : 'sale');
            
            // Get price based on listing type
            if ($listingType === 'rent') {
                $price = rand($typeRange['rent'][0], $typeRange['rent'][1]);
            } else {
                $price = rand($typeRange['sale'][0], $typeRange['sale'][1]);
            }
            
            // Generate property specifications
            $bedrooms = ($propertyType === 'room') ? 1 : rand(1, 5);
            $bathrooms = ($propertyType === 'room') ? 1 : rand(1, min(3, $bedrooms));
            $area = ($propertyType === 'room') ? rand(100, 300) : 
                   (($propertyType === 'apartment') ? rand(500, 2000) : rand(1000, 5000));
            
            // Generate random features
            $propertyFeatures = [];
            foreach ($featuresByCategory as $category => $features) {
                // Add 1-3 features from each category randomly
                $featuresToAdd = rand(1, 3);
                $selectedFeatures = array_rand(array_flip($features), min($featuresToAdd, count($features)));
                if (!is_array($selectedFeatures)) $selectedFeatures = [$selectedFeatures];
                $propertyFeatures = array_merge($propertyFeatures, $selectedFeatures);
            }
            
            // Create property description
            $description = "Beautiful {$propertyType} located in {$location}, {$city}. ";
            $description .= ($propertyType === 'room') ? 
                "Perfect for students and working professionals. " :
                "Ideal for families looking for comfortable living. ";
            $description .= "Features: " . implode(', ', array_slice($propertyFeatures, 0, 5)) . ". ";
            $description .= "Contact for viewing and more details.";
            
            $property = [
                'title' => $title . " in " . $city,
                'description' => $description,
                'property_type' => $propertyType,
                'listing_type' => $listingType,
                'price' => $price,
                'location' => $location,
                'city' => $city,
                'area_sqft' => $area,
                'bedrooms' => $bedrooms,
                'bathrooms' => $bathrooms,
                'parking_spaces' => rand(0, 2),
                'furnished' => rand(0, 1),
                'available_from' => date('Y-m-d', strtotime('+' . rand(1, 90) . ' days')),
                'contact_phone' => '98' . rand(10000000, 99999999),
                'contact_email' => 'upendra@urbanoasis.com',
                'image_url' => $image,
                'features' => $propertyFeatures
            ];
            
            $properties[] = $property;
            $propertyCount++;
        }
    }
}

echo "Generated {$propertyCount} properties across " . count($cities) . " cities\n";
echo "Starting database insertion...\n\n";

try {
    $pdo->beginTransaction();
    
    // Prepare statements
    $propertyStmt = $pdo->prepare("
        INSERT INTO properties (
            title, description, property_type, listing_type, price, location, city, 
            area_sqft, bedrooms, bathrooms, parking_spaces, furnished, available_from, 
            contact_phone, contact_email, image_url, user_id, approval_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')
    ");
    
    $featureStmt = $pdo->prepare("INSERT INTO property_features (property_id, feature_name) VALUES (?, ?)");
    
    $insertedCount = 0;
    
    foreach ($properties as $property) {
        // Insert property
        $propertyStmt->execute([
            $property['title'],
            $property['description'],
            $property['property_type'],
            $property['listing_type'],
            $property['price'],
            $property['location'],
            $property['city'],
            $property['area_sqft'],
            $property['bedrooms'],
            $property['bathrooms'],
            $property['parking_spaces'],
            $property['furnished'],
            $property['available_from'],
            $property['contact_phone'],
            $property['contact_email'],
            $property['image_url'],
            $user_id
        ]);
        
        $property_id = $pdo->lastInsertId();
        
        // Insert features
        foreach ($property['features'] as $feature) {
            $featureStmt->execute([$property_id, $feature]);
        }
        
        $insertedCount++;
        
        // Show progress
        if ($insertedCount % 10 == 0) {
            echo "Inserted {$insertedCount} properties...\n";
        }
    }
    
    $pdo->commit();
    
    echo "\n=== SUCCESS! ===\n";
    echo "Successfully inserted {$insertedCount} properties for {$user['first_name']} {$user['last_name']}\n";
    echo "Properties are spread across " . count($cities) . " cities with realistic data\n";
    echo "All properties are auto-approved and ready for testing\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n=== ERROR! ===\n";
    echo "Failed to insert properties: " . $e->getMessage() . "\n";
    echo "Transaction rolled back - no data was inserted\n";
}
?>

