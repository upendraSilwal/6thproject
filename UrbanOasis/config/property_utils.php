<?php
/**
 * @param array $features Array of feature names
 * @param string $propertyType Property type (room, apartment, house)
 * @return array Categorized features
 */
function categorizeFeatures($features, $propertyType = 'house') {
    // Get property-type-specific feature categories
    $categories = getPropertyTypeFeatureCategories($propertyType);
    
    // Initialize categorized array based on property type
    $categorized = [
        'essential' => [],
        'comfort' => [],
        'convenience' => [],
        'safety' => [],
        'other' => []
    ];
    
    // Add luxury category only for non-room properties
    if ($propertyType !== 'room') {
        $categorized['luxury'] = [];
    }
    
    foreach ($features as $feature) {
        $feature_lower = strtolower(trim($feature));
        $categorized_flag = false;
        
        foreach ($categories as $category => $keywords) {
            // Skip luxury category for rooms
            if ($propertyType === 'room' && $category === 'luxury') {
                continue;
            }
            
            foreach ($keywords as $keyword) {
                if (strpos($feature_lower, strtolower($keyword)) !== false) {
                    $categorized[$category][] = $feature;
                    $categorized_flag = true;
                    break 2;
                }
            }
        }
        
        if (!$categorized_flag) {
            $categorized['other'][] = $feature;
        }
    }
    
    return $categorized;
}

/**
 * Get property-type-specific feature categories
 * Different property types have different feature expectations
 * 
 * @param string $propertyType Property type (room, apartment, house)
 * @return array Feature categories for the property type
 */
function getPropertyTypeFeatureCategories($propertyType) {
    switch ($propertyType) {
        case 'room':
            return [
                'essential' => ['Water Supply', 'Electricity', 'Internet', 'Furnished', 'Clean'],
                'comfort' => ['Balcony', 'Sunlight', 'Ventilation', 'Quiet', 'Private Bathroom', 'Pre-painted', 'Painted', 'Air Conditioning', 'Attached Bathroom', 'Study Table', 'Pets Allowed'],
                'convenience' => ['Near Market', 'Near Hospital', 'Near School', 'Near Public Transport', 'Near Restaurant', 'Laundry Access'],
                'safety' => ['CCTV', 'Safe Neighborhood', 'Female Friendly'],
                'other' => []
            ];
            
        case 'apartment':
            return [
                'essential' => ['Security', 'Elevator', 'Water Supply', 'Electricity', 'Internet', 'Parking'],
                'comfort' => ['Air Conditioning', 'Furnished', 'Balcony', 'Terrace', 'Garden View', 'Modern Kitchen'],
                'luxury' => ['Swimming Pool', 'Gym', 'Spa', 'Concierge', 'Jacuzzi', 'Rooftop Access'],
                'convenience' => ['Near Market', 'Near Hospital', 'Near School', 'Near Public Transport', 'Near Restaurant', 'Near Bank'],
                'safety' => ['CCTV', 'Security Guard', 'Fire Safety', 'Emergency Exit', 'Intercom'],
                'other' => []
            ];
            
        case 'house':
        default:
            return [
                'essential' => ['Security', 'Parking', 'Water Supply', 'Electricity', 'Internet'],
                'comfort' => ['Air Conditioning', 'Furnished', 'Garden', 'Balcony', 'Terrace', 'Fireplace'],
                'luxury' => ['Swimming Pool', 'Gym', 'Spa', 'Tennis Court', 'Home Theater', 'Wine Cellar'],
                'convenience' => ['Near Market', 'Near Hospital', 'Near School', 'Near Public Transport', 'Near Restaurant', 'Near Bank'],
                'safety' => ['CCTV', 'Security Guard', 'Fire Safety', 'Emergency', 'Gated Community'],
                'other' => []
            ];
    }
}

/**
 * Get property image URL with fallback handling
 * Centralizes image logic to reduce redundancy
 * 
 * @param array $property Property data array
 * @param string $uploadPath Path to uploads directory
 * @return string Image URL
 */
function getPropertyImageUrl($property, $uploadPath = 'uploads/') {
    if (!empty($property['image_url'])) {
        // Check if it's an Unsplash URL or uploaded file
        if (strpos($property['image_url'], 'http') === 0) {
            return $property['image_url'];
        } else {
            return $uploadPath . $property['image_url'];
        }
    }
    
    // Fallback to Unsplash demo image
    return getRandomPropertyImage($property['property_type'] ?? 'house');
}

/**
 * Calculate property statistics
 * Provides useful calculations for property analysis
 * 
 * @param array $property Property data array
 * @return array Statistics
 */
function calculatePropertyStats($property) {
    $stats = [
        'price_per_sqft' => 'N/A',
        'price_per_bedroom' => 'N/A',
        'days_listed' => 0,
        'feature_count' => 0
    ];
    
    // Price per sq ft
    if (isset($property['area_sqft']) && $property['area_sqft'] > 0) {
        $stats['price_per_sqft'] = number_format($property['price'] / $property['area_sqft'], 2);
    }
    
    // Price per bedroom (only for non-room properties)
    if ($property['property_type'] !== 'room' && isset($property['bedrooms']) && $property['bedrooms'] > 0) {
        $stats['price_per_bedroom'] = number_format($property['price'] / $property['bedrooms']);
    }
    
    // Days listed
    if (isset($property['created_at'])) {
        $days_diff = floor((time() - strtotime($property['created_at'])) / (60 * 60 * 24));
        $stats['days_listed'] = max(0, $days_diff); // Ensure it's never negative
    }
    
    return $stats;
}

/**
 * Validate property data consistency
 * Ensures all required fields are present and valid
 * 
 * @param array $data Property data
 * @param string $propertyType Property type
 * @return array Validation errors
 */
function validatePropertyData($data, $propertyType = null) {
    $errors = [];
    
    // Required fields
    $required = ['title', 'description', 'property_type', 'listing_type', 'price', 'location', 'city', 'area_sqft', 'contact_phone', 'contact_email'];
    
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    
    // Property type specific validation
    if ($propertyType === 'room') {
        if ($data['listing_type'] === 'sale') {
            $errors[] = 'Rooms can only be listed for rent, not for sale.';
        }
    } else {
        if (empty($data['bedrooms']) || $data['bedrooms'] <= 0) {
            $errors[] = 'Please specify the number of bedrooms.';
        }
        if (empty($data['bathrooms']) || $data['bathrooms'] <= 0) {
            $errors[] = 'Please specify the number of bathrooms.';
        }
    }
    
    // Email validation
    if (!empty($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    // Price validation
    if (!empty($data['price']) && $data['price'] <= 0) {
        $errors[] = 'Price must be greater than 0.';
    }

    // Area validation by property type (to prevent unrealistic inputs)
    $area = isset($data['area_sqft']) ? (float)$data['area_sqft'] : 0.0;
    $type = strtolower((string)($propertyType ?? $data['property_type'] ?? ''));
    if ($type === 'room') {
        if ($area < 60 || $area > 400) {
            $errors[] = 'For rooms, area should be between 60 and 400 sq ft.';
        }
    } elseif ($type === 'apartment') {
        if ($area < 300 || $area > 3500) {
            $errors[] = 'For apartments, area should be between 300 and 3500 sq ft.';
        }
    } elseif ($type === 'house') {
        if ($area < 600 || $area > 10000) {
            $errors[] = 'For houses, area should be between 600 and 10000 sq ft.';
        }
    }
    
    return $errors;
}

/**
 * Get property features for display
 * Fetches and formats property features for consistent display
 * 
 * @param PDO $pdo Database connection
 * @param int $propertyId Property ID
 * @return array Features array
 */
function getPropertyFeatures($pdo, $propertyId) {
    $stmt = $pdo->prepare("SELECT feature_name FROM property_features WHERE property_id = ? ORDER BY feature_name ASC");
    $stmt->execute([$propertyId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Centralized method to get properties with various filters
 * @param PDO $pdo Database connection
 * @param string $whereClause SQL WHERE clause
 * @param array $params Parameters for prepared statement
 * @return array Resulting properties
 */
function getProperties($pdo, $whereClause = '', $params = []) {
    $query = "SELECT * FROM properties WHERE $whereClause";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Format property price with currency
 * Consistent price formatting across the application
 * 
 * @param float $price Property price
 * @param string $currency Currency symbol
 * @param string $propertyType Property type (optional)
 * @param string $listingType Listing type (optional)
 * @return string Formatted price
 */
function formatPropertyPrice($price, $currency = 'NPR ', $propertyType = null, $listingType = null) {
    $formattedPrice = $currency . number_format($price);
    
    // Add "/month" for room rentals
    if ($propertyType === 'room' && $listingType === 'rent') {
        $formattedPrice .= '/month';
    }
    
    return $formattedPrice;
}

/**
 * Get property type display name
 * Consistent property type formatting
 * 
 * @param string $type Property type
 * @return string Formatted type name
 */
function getPropertyTypeDisplay($type) {
    $types = [
        'house' => 'House',
        'apartment' => 'Apartment', 
        'room' => 'Room',
        'land' => 'Land',
        'commercial' => 'Commercial'
    ];
    
    return $types[$type] ?? ucfirst($type);
}

/**
 * Get listing type display name
 * Consistent listing type formatting
 * 
 * @param string $type Listing type
 * @return string Formatted type name
 */
function getListingTypeDisplay($type) {
    $types = [
        'sale' => 'For Sale',
        'rent' => 'For Rent'
    ];
    
    return $types[$type] ?? ucfirst($type);
}

/**
 * Get list of all 77 districts of Nepal
 * Standardized names as commonly used post-federal restructuring
 * @return array
 */
function getNepalDistricts() {
    return [
        'Achham', 'Arghakhanchi', 'Baglung', 'Baitadi', 'Bajhang', 'Bajura', 'Banke', 'Bara', 'Bardiya', 'Bhaktapur',
        'Bhojpur', 'Chitwan', 'Dadeldhura', 'Dailekh', 'Dang', 'Darchula', 'Dhading', 'Dhankuta', 'Dhanusha', 'Dolakha',
        'Dolpa', 'Doti', 'Gorkha', 'Gulmi', 'Humla', 'Ilam', 'Jajarkot', 'Jhapa', 'Jumla', 'Kailali',
        'Kalikot', 'Kanchanpur', 'Kapilvastu', 'Kaski', 'Kathmandu', 'Kavrepalanchok', 'Khotang', 'Lalitpur', 'Lamjung', 'Mahottari',
        'Makwanpur', 'Manang', 'Morang', 'Mugu', 'Mustang', 'Myagdi', 'Nawalparasi (East) Nawalpur', 'Nawalparasi (West) Parasi', 'Okhaldhunga', 'Palpa',
        'Panchthar', 'Parbat', 'Parsa', 'Pyuthan', 'Ramechhap', 'Rasuwa', 'Rautahat', 'Rolpa', 'Rukum (East)', 'Rukum (West)',
        'Rupandehi', 'Salyan', 'Sankhuwasabha', 'Saptari', 'Sarlahi', 'Sindhuli', 'Sindhupalchok', 'Siraha', 'Solukhumbu', 'Sunsari',
        'Surkhet', 'Syangja', 'Tanahun', 'Taplejung', 'Tehrathum', 'Udayapur'
    ];
}

/**
 * Canonical list of major cities in Nepal for pricing and UX logic
 * Note: These are municipal urban centers, not districts
 * @return array
 */
function getNepalMajorCities() {
    return [
        'Kathmandu',
        'Lalitpur',
        'Bhaktapur',
        'Pokhara',
        'Biratnagar',
        'Birgunj',
        'Bharatpur',
        'Butwal',
        'Dharan',
        'Hetauda',
        'Itahari',
        'Nepalgunj',
        'Dhangadhi',
        'Janakpur'
    ];
}

/**
 * Non-major districts (77-district list minus the 3 valley districts that are also cities)
 * Useful when form input contains district names instead of specific cities
 * @return array
 */
function getNepalNonMajorDistricts() {
    $districts = getNepalDistricts();
    // District names that double as cities are the valley trio
    $valleyDistrictsThatAreCities = ['Kathmandu', 'Lalitpur', 'Bhaktapur'];
    return array_values(array_diff($districts, $valleyDistrictsThatAreCities));
}

/**
 * Classify a provided location (city or district) for pricing logic
 * Returns one of: major_city | district | other
 * @param string $location
 * @return string
 */
function getLocationCategoryForPricing($location) {
    $name = trim((string)$location);
    if ($name === '') return 'other';
    $major = getNepalMajorCities();
    if (in_array($name, $major, true)) return 'major_city';
    $districts = getNepalDistricts();
    if (in_array($name, $districts, true)) return 'district';
    return 'other';
}

/**
 * Calculate Quality Score for a property based on property type
 * Different property types have different scoring criteria
 * @param array $features Array of feature names
 * @param string $propertyType Property type (room, apartment, house)
 * @return int Quality Score (0-100)
 */
function calculateQualityScore($features, $propertyType = 'house') {
    $categorized = categorizeFeatures($features, $propertyType);
    
    // Get property-type-specific scoring parameters
    $scoringParams = getPropertyTypeScoringParams($propertyType);
    $weights = $scoringParams['weights'];
    $maxCounts = $scoringParams['max_counts'];
    
    $score = 0;
    $maxScore = 0;
    
    foreach ($weights as $category => $weight) {
        $count = isset($categorized[$category]) ? count($categorized[$category]) : 0;
        $categoryMax = $maxCounts[$category];

        // Apply diminishing returns: first 2 items full weight, beyond that half-weight
        $fullWeightItems = min($count, 2);
        $halfWeightItems = max(0, min($count - 2, $categoryMax - 2));

        $categoryScore = ($fullWeightItems * $weight) + ($halfWeightItems * ($weight * 0.5));

        // Cap by category max regardless
        $categoryScore = min($categoryScore, $categoryMax * $weight);

        $score += $categoryScore;
        $maxScore += $categoryMax * $weight;
    }
    
    // Normalize to 100
    if ($maxScore == 0) return 0;
    return min(100, round(($score / $maxScore) * 100));
}

/**
 * Get property-type-specific scoring parameters
 * Different property types have different expectations and weightings
 * @param string $propertyType Property type (room, apartment, house)
 * @return array Scoring parameters with weights and max counts
 */
function getPropertyTypeScoringParams($propertyType) {
    switch ($propertyType) {
        case 'room':
            return [
                'weights' => [
                    // Re-tuned for rooms to avoid over-scoring common amenities
                    'essential' => 8,
                    'comfort' => 6,
                    'convenience' => 5,
                    'safety' => 7,
                    'other' => 2
                ],
                'max_counts' => [
                    'essential' => 3,
                    'comfort' => 3,
                    'convenience' => 3,
                    'safety' => 2,
                    'other' => 2
                ]
            ];
            
        case 'apartment':
            return [
                'weights' => [
                    'essential' => 6,    // Important (Security, Elevator, Parking)
                    'comfort' => 5,      // Important (AC, Furnished, Balcony)
                    'luxury' => 4,       // Moderate importance (Pool, Gym)
                    'convenience' => 3,  // Moderate importance (nearby facilities)
                    'safety' => 5,       // Important (CCTV, Security Guard)
                    'other' => 1
                ],
                'max_counts' => [
                    'essential' => 4,    // Security, Elevator, Parking, Internet
                    'comfort' => 5,      // AC, Furnished, Balcony, Modern Kitchen, etc.
                    'luxury' => 4,       // Pool, Gym, Spa, Concierge
                    'convenience' => 4,  // Near Market, Hospital, School, Transport
                    'safety' => 3,       // CCTV, Security Guard, Fire Safety
                    'other' => 3
                ]
            ];
            
        case 'house':
        default:
            return [
                'weights' => [
                    'essential' => 5,    // Important but houses expected to have basics
                    'comfort' => 4,      // Important (Garden, AC, Furnished)
                    'luxury' => 5,       // Higher importance for houses (Pool, Gym, etc.)
                    'convenience' => 3,  // Moderate importance
                    'safety' => 4,       // Important (Security, CCTV)
                    'other' => 1
                ],
                'max_counts' => [
                    'essential' => 5,    // Security, Parking, Water, Electricity, Internet
                    'comfort' => 6,      // AC, Garden, Balcony, Terrace, Fireplace, etc.
                    'luxury' => 5,       // Pool, Gym, Spa, Tennis Court, Home Theater
                    'convenience' => 4,  // Near Market, Hospital, School, Transport
                    'safety' => 4,       // CCTV, Security Guard, Fire Safety, Gated Community
                    'other' => 4
                ]
            ];
    }
}

/**
 * Get centralized feature suggestions for property forms
 * Now supports property-type-specific suggestions
 * @param string $propertyType Property type (room, apartment, house)
 * @return array
 */
function getFeatureSuggestions($propertyType = 'house') {
    switch ($propertyType) {
        case 'room':
            return [
                'essential' => [
                    'Water Supply', 'Electricity', 'Internet', 'Furnished', 'Clean'
                ],
                'comfort' => [
                    'Balcony', 'Sunlight', 'Ventilation', 'Quiet', 'Private Bathroom', 'Pre-painted', 'Air Conditioning', 'Attached Bathroom', 'Study Table', 'Pets Allowed'
                ],
                'convenience' => [
                    'Near Market', 'Near Hospital', 'Near School', 'Near Public Transport', 'Laundry Access'
                ],
                'safety' => [
                    'CCTV', 'Safe Neighborhood', 'Female Friendly'
                ]
            ];
            
        case 'apartment':
            return [
                'essential' => [
                    'Security', 'Elevator', 'Water Supply', 'Electricity', 'Internet', 'Parking'
                ],
                'comfort' => [
                    'Air Conditioning', 'Furnished', 'Balcony', 'Terrace', 'Garden View', 'Modern Kitchen'
                ],
                'luxury' => [
                    'Swimming Pool', 'Gym', 'Spa', 'Concierge', 'Jacuzzi', 'Rooftop Access'
                ],
                'convenience' => [
                    'Near Market', 'Near Hospital', 'Near School', 'Near Public Transport', 'Near Bank', 'Shopping Mall'
                ],
                'safety' => [
                    'CCTV', 'Security Guard', 'Fire Safety', 'Emergency Exit', 'Intercom'
                ]
            ];
            
        case 'house':
        default:
            return [
                'essential' => [
                    'Security', 'Parking', 'Water Supply', 'Electricity', 'Internet'
                ],
                'comfort' => [
                    'Air Conditioning', 'Furnished', 'Garden', 'Balcony', 'Terrace', 'Fireplace'
                ],
                'luxury' => [
                    'Swimming Pool', 'Gym', 'Spa', 'Tennis Court', 'Home Theater', 'Wine Cellar'
                ],
                'convenience' => [
                    'Near Market', 'Near Hospital', 'Near School', 'Near Public Transport', 'Near Bank', 'Near Post Office'
                ],
                'safety' => [
                    'CCTV', 'Security Guard', 'Fire Safety', 'Emergency', 'Gated Community'
                ]
            ];
    }
}

/**
 * Get Quality Score category and styling
 * @param int $score Quality Score (0-100)
 * @return array Array with category, class, and color info
 */
function getQualityScoreCategory($score) {
    if ($score >= 75) {
        return [
            'category' => 'excellent',
            'class' => 'excellent',
            'color' => '#28a745',
            'label' => 'Excellent',
            'icon' => 'fas fa-thumbs-up'
        ];
    } elseif ($score >= 55) {
        return [
            'category' => 'good',
            'class' => 'good',
            'color' => '#ffc107',
            'label' => 'Good',
            'icon' => 'fas fa-thumbs-up'
        ];
    } else {
        return [
            'category' => 'fair',
            'class' => 'fair',
            'color' => '#dc3545',
            'label' => 'Fair',
            'icon' => 'fas fa-thumbs-up'
        ];
    }
}

/**
 * Generate Quality Score HTML visualization
 * @param int $score Quality Score (0-100)
 * @param string $size Size class (small, medium, large)
 * @param bool $showTooltip Whether to show tooltip
 * @return string HTML for Quality Score visualization
 */
function generateQualityScoreHTML($score, $size = 'medium', $showTooltip = true) {
    $category = getQualityScoreCategory($score);
    $tooltipClass = $showTooltip ? 'quality-score-tooltip' : '';
    $tooltipData = $showTooltip ? 'data-tooltip="Quality Score: ' . $score . '/100 - ' . $category['label'] . ' - Based on essential, comfort, luxury, convenience, and safety features"' : '';
    
    $html = '<div class="quality-score-container ' . $size . '">';
    $html .= '<div class="quality-score-progress">';
    $html .= '<div class="quality-score-fill quality-score-' . $category['class'] . '" style="width: ' . $score . '%"></div>';
    $html .= '</div>';
    $html .= '<div class="quality-score-badge ' . $category['class'] . ' ' . $tooltipClass . '" ' . $tooltipData . '>';
    $html .= '<i class="' . $category['icon'] . '"></i>';
    $html .= '<span class="quality-score-value">' . $score . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Check if user can create a new property listing based on credit system
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param array $user User data (optional)
 * @return array Array with 'allowed' boolean and detailed information
 */
function checkPropertyUploadLimit($pdo, $userId, $user = null) {
    // Get user data if not provided
    if ($user === null) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
    
    if (!$user) {
        return [
            'allowed' => false,
            'reason' => 'User not found',
            'free_listings_remaining' => 0,
            'credits_available' => 0,
            'phone_verified' => false
        ];
    }
    
    $freeListingsUsed = $user['free_listings_used'];
    $creditsAvailable = $user['listing_credits'];
    $freeListingsRemaining = max(0, 1 - $freeListingsUsed);
    $phoneVerified = isset($user['phone_verified']) ? (bool)$user['phone_verified'] : false;
    
    // Check if user can create a listing
    $canCreateListing = false;
    $reason = 'OK';
    
    if ($freeListingsRemaining > 0 && !$phoneVerified) {
        $canCreateListing = false;
        $reason = 'Phone verification required for free listings';
    } elseif ($freeListingsRemaining > 0 && $phoneVerified) {
        $canCreateListing = true;
    } elseif ($creditsAvailable >= 5) {
        $canCreateListing = true;
    } else {
        $reason = 'No free listings or credits available';
    }
    
    return [
        'allowed' => $canCreateListing,
        'free_listings_remaining' => $freeListingsRemaining,
        'credits_available' => $creditsAvailable,
        'phone_verified' => $phoneVerified,
        'reason' => $reason
    ];
}

/**
 * Price Model: Coefficients and helpers
 * Linear model per segment: price = base_price + quality_slope * quality_score
 * Segments defined by property_type, listing_type, and location_category
 */

/**
 * Get current price model version string
 * @return string
 */
function getPriceModelVersion() {
    return 'v1.2-high-baselines-houses-apartments-2025-08-13';
}

/**
 * Coefficients for price prediction per segment
 * Keys: property_type ∈ {house, apartment, room}
 *       listing_type ∈ {rent, sale}
 *       location_category ∈ {major_city, district, other}
 * Values: base_price, quality_slope, min_price, max_price
 * Note: Values are illustrative defaults; tune later and/or move to DB.
 * @return array
 */
function getPriceModelCoefficients() {
    return [
        'house' => [
            'rent' => [
                // Kathmandu and other major cities often exceed 1 lakh/month for full houses
                'major_city' => ['base_price' => 80000, 'quality_slope' => 1000, 'min_price' => 60000, 'max_price' => 400000],
                'district' =>   ['base_price' => 50000, 'quality_slope' => 700,  'min_price' => 35000, 'max_price' => 250000],
                'other' =>      ['base_price' => 35000, 'quality_slope' => 500,  'min_price' => 25000, 'max_price' => 180000],
            ],
            'sale' => [
                // Sale prices commonly in crores in major cities
                'major_city' => ['base_price' => 20000000, 'quality_slope' => 300000, 'min_price' => 10000000, 'max_price' => 200000000],
                'district' =>   ['base_price' => 12000000, 'quality_slope' => 200000, 'min_price' => 6000000,  'max_price' => 150000000],
                'other' =>      ['base_price' => 8000000,  'quality_slope' => 150000, 'min_price' => 4000000,  'max_price' => 120000000],
            ],
        ],
        'apartment' => [
            'rent' => [
                'major_city' => ['base_price' => 45000, 'quality_slope' => 800,  'min_price' => 30000, 'max_price' => 250000],
                'district' =>   ['base_price' => 30000, 'quality_slope' => 550,  'min_price' => 20000, 'max_price' => 180000],
                'other' =>      ['base_price' => 22000, 'quality_slope' => 420,  'min_price' => 15000, 'max_price' => 150000],
            ],
            'sale' => [
                'major_city' => ['base_price' => 12000000, 'quality_slope' => 200000, 'min_price' => 6000000,  'max_price' => 150000000],
                'district' =>   ['base_price' => 8000000,  'quality_slope' => 150000, 'min_price' => 4000000,  'max_price' => 100000000],
                'other' =>      ['base_price' => 6000000,  'quality_slope' => 120000, 'min_price' => 3000000,  'max_price' => 80000000],
            ],
        ],
        'room' => [
            'rent' => [
                // Tuned to target ~NPR 8,000 for a typical room with common amenities in major cities
                'major_city' => ['base_price' => 6000, 'quality_slope' => 40, 'min_price' => 5000, 'max_price' => 15000],
                'district' =>   ['base_price' => 5000, 'quality_slope' => 30, 'min_price' => 4000, 'max_price' => 12000],
                'other' =>      ['base_price' => 4000, 'quality_slope' => 25, 'min_price' => 3000, 'max_price' => 10000],
            ],
            // Rooms are not sold typically; keep sale mapping same as apartment sale as a fallback, but unused in UI
            'sale' => [
                'major_city' => ['base_price' => 800000, 'quality_slope' => 12000, 'min_price' => 500000, 'max_price' => 20000000],
                'district' =>   ['base_price' => 500000, 'quality_slope' => 9000,  'min_price' => 300000, 'max_price' => 15000000],
                'other' =>      ['base_price' => 350000, 'quality_slope' => 7000,  'min_price' => 200000, 'max_price' => 12000000],
            ],
        ],
    ];
}

/**
 * Resolve coefficients for a given segment
 * @param string $propertyType
 * @param string $listingType
 * @param string $locationCategory
 * @return array|null
 */
function getPriceModelSegmentCoefficients($propertyType, $listingType, $locationCategory) {
    $coeffs = getPriceModelCoefficients();
    $pt = strtolower((string)$propertyType);
    $lt = strtolower((string)$listingType);
    $lc = strtolower((string)$locationCategory);
    if (!isset($coeffs[$pt][$lt][$lc])) {
        return null;
    }
    return $coeffs[$pt][$lt][$lc];
}

/**
 * Compute a mild area-based adjustment factor by property type and listing type.
 * Keeps predictions stable by clamping within reasonable bounds.
 * @return array{factor: float, baseline: float}
 */
function getAreaAdjustmentFactor($propertyType, $listingType, $areaSqft) {
    $type = strtolower((string)$propertyType);
    $lt = strtolower((string)$listingType);
    $area = is_numeric($areaSqft) ? (float)$areaSqft : 0.0;
    if ($area <= 0) {
        return ['factor' => 1.0, 'baseline' => 0.0];
    }

    // Baselines (approximate): tuned for gentle adjustments
    // Rooms: ~120; Apartments: ~900; Houses: ~1500 sq ft
    $baseline = 0.0;
    $minFactor = 0.9; $maxFactor = 1.3; $alpha = 0.2; // defaults

    if ($type === 'room') {
        $baseline = 120.0;
        $minFactor = 0.85; $maxFactor = 1.25; $alpha = 0.25;
    } elseif ($type === 'apartment') {
        $baseline = 900.0;
        $minFactor = 0.9; $maxFactor = 1.3; $alpha = 0.2;
    } else { // house
        $baseline = 1500.0;
        $minFactor = 0.9; $maxFactor = 1.3; $alpha = 0.2;
    }

    // Slightly allow stronger effect for sales than rents
    if ($lt === 'sale') {
        $minFactor -= 0.05; // e.g., 0.85
        $maxFactor += 0.05; // e.g., 1.35
        $alpha += 0.05;     // stronger slope
    }

    $ratio = $area / $baseline; // 1.0 at baseline
    $factor = (1.0 - $alpha) + ($alpha * $ratio);
    $factor = max($minFactor, min($maxFactor, $factor));

    return ['factor' => $factor, 'baseline' => $baseline];
}

/**
 * Storey (floors) adjustment for houses.
 * Baseline floors = 2. Gentle multiplier, slightly stronger for sale than rent.
 * @return float factor
 */
function getStoreyAdjustmentFactor($propertyType, $listingType, $floors) {
    $type = strtolower((string)$propertyType);
    if ($type !== 'house') return 1.0;
    $lt = strtolower((string)$listingType);
    $f = is_numeric($floors) ? (int)$floors : 0;
    if ($f <= 0) return 1.0;
    $baselineFloors = 2;
    $delta = $f - $baselineFloors;
    // per-floor impact
    $perFloor = ($lt === 'sale') ? 0.10 : 0.08; // 10% sale, 8% rent per floor relative to baseline
    $factor = 1.0 + ($perFloor * $delta);
    // clamp to reasonable bounds
    $minFactor = 0.85;
    $maxFactor = ($lt === 'sale') ? 1.6 : 1.5;
    return max($minFactor, min($maxFactor, $factor));
}

/**
 * Bedrooms/Bathrooms adjustment for house/apartment.
 * Baselines: apartment 2BR/1BA, house 3BR/2BA.
 */
function getBedroomBathroomAdjustmentFactor($propertyType, $listingType, $bedrooms, $bathrooms) {
    $type = strtolower((string)$propertyType);
    if ($type !== 'house' && $type !== 'apartment') return 1.0;
    $lt = strtolower((string)$listingType);
    $br = is_numeric($bedrooms) ? (int)$bedrooms : 0;
    $ba = is_numeric($bathrooms) ? (int)$bathrooms : 0;

    $baselineBr = ($type === 'house') ? 3 : 2;
    $baselineBa = ($type === 'house') ? 2 : 1;

    $deltaBr = $br > 0 ? ($br - $baselineBr) : 0;
    $deltaBa = $ba > 0 ? ($ba - $baselineBa) : 0;

    $perBr = ($lt === 'sale') ? 0.03 : 0.05; // sale smaller, rent larger
    $perBa = ($lt === 'sale') ? 0.03 : 0.04;

    $factor = 1.0 + ($deltaBr * $perBr) + ($deltaBa * $perBa);
    // clamp to avoid extremes
    $minFactor = 0.85;
    $maxFactor = 1.5;
    return max($minFactor, min($maxFactor, $factor));
}

/**
 * Parking adjustment (both house/apartment). Mild uplift.
 */
function getParkingAdjustmentFactor($listingType, $parkingSpaces) {
    $spaces = is_numeric($parkingSpaces) ? (int)$parkingSpaces : 0;
    if ($spaces <= 0) return 1.0;
    $lt = strtolower((string)$listingType);
    $perSpace = ($lt === 'sale') ? 0.025 : 0.03;
    $effectiveSpaces = min(2, max(0, $spaces));
    $factor = 1.0 + ($effectiveSpaces * $perSpace);
    return min($factor, 1.1);
}

/**
 * Furnished adjustment. Stronger for rent, small for sale.
 */
function getFurnishedAdjustmentFactor($propertyType, $listingType, $isFurnished) {
    $lt = strtolower((string)$listingType);
    $type = strtolower((string)$propertyType);
    $f = (bool)$isFurnished;
    if (!$f) return 1.0;
    if ($lt === 'rent') {
        // apartments generally command higher furnished premium
        return ($type === 'apartment') ? 1.10 : 1.08;
    }
    // sale: minimal impact
    return 1.02;
}

/**
 * Age adjustment from construction_date. Newer gets mild premium; older mild discount.
 */
function getAgeAdjustmentFactor($listingType, $constructionDate) {
    if (empty($constructionDate)) return 1.0;
    $timestamp = strtotime($constructionDate);
    if ($timestamp === false) return 1.0;
    $years = max(0, (int)floor((time() - $timestamp) / (365 * 24 * 60 * 60)));
    $lt = strtolower((string)$listingType);
    // Neutral at ~10 years
    $deltaYears = 10 - $years; // positive if newer than 10y
    if ($lt === 'rent') {
        $k = 0.01; // 1% per year away from 10, capped
        $adj = max(-0.10, min(0.10, $k * $deltaYears));
        return 1.0 + $adj;
    } else {
        $k = 0.005; // sale smaller effect
        $adj = max(-0.06, min(0.06, $k * $deltaYears));
        return 1.0 + $adj;
    }
}

/**
 * Micro-location boost from location string keywords (hotspots).
 */
function getMicroLocationAdjustmentFactor($city, $location) {
    $loc = strtolower((string)$location);
    $cityNorm = strtolower((string)$city);
    if ($loc === '') return 1.0;
    // Basic hotspots (can be moved to config/db later)
    $hotspots = [
        // Kathmandu Valley
        'baneshwor' => 0.08,
        'new baneshwor' => 0.10,
        'tinkune' => 0.07,
        'sinamangal' => 0.06,
        'maitidevi' => 0.07,
        'jhamsikhel' => 0.10,
        'lazimpat' => 0.10,
        'baluwatar' => 0.10,
        'thamel' => 0.08,
        'durbarmarg' => 0.12,
        'naxal' => 0.10,
        'banepa' => 0.04,
        // Pokhara
        'lakeside' => 0.10,
    ];
    $boost = 0.0;
    foreach ($hotspots as $key => $val) {
        if (strpos($loc, $key) !== false) {
            $boost = max($boost, $val);
        }
    }
    if ($boost <= 0) return 1.0;
    return 1.0 + $boost;
}

/**
 * Predict property price using linear model and clamp to [min_price, max_price]
 * Expects $property to contain: property_type, listing_type, city, and either
 *  - quality_score (0-100) or
 *  - features (array of strings) to compute quality score
 * @param array $property
 * @return float
 */
function predictPropertyPrice($property) {
    $propertyType = strtolower($property['property_type'] ?? 'house');
    $listingType = strtolower($property['listing_type'] ?? 'rent');
    $city = $property['city'] ?? '';
    $locationCategory = getLocationCategoryForPricing($city);

    $qualityScore = null;
    if (isset($property['quality_score']) && is_numeric($property['quality_score'])) {
        $qualityScore = (int)max(0, min(100, $property['quality_score']));
    } elseif (!empty($property['features']) && is_array($property['features'])) {
        $qualityScore = (int)max(0, min(100, calculateQualityScore($property['features'], $propertyType)));
    } else {
        $qualityScore = 50; // fallback neutral score
    }

    $segment = getPriceModelSegmentCoefficients($propertyType, $listingType, $locationCategory);
    if ($segment === null) {
        // Fallback to a safe default if segment missing
        $segment = ['base_price' => 10000, 'quality_slope' => 200, 'min_price' => 3000, 'max_price' => 100000000];
    }

    $pred = (float)$segment['base_price'] + (float)$segment['quality_slope'] * (float)$qualityScore;

    // Area adjustment for all types (gentle, type-specific baselines)
    $areaSqft = isset($property['area_sqft']) && is_numeric($property['area_sqft']) ? (float)$property['area_sqft'] : 0.0;
    if ($areaSqft > 0) {
        $adj = getAreaAdjustmentFactor($propertyType, $listingType, $areaSqft);
        $pred *= $adj['factor'];
    }

    // Storey adjustment for houses
    $floors = isset($property['floors']) && is_numeric($property['floors']) ? (int)$property['floors'] : null;
    if ($floors !== null) {
        $pred *= getStoreyAdjustmentFactor($propertyType, $listingType, $floors);
    }

    // Bedrooms/Bathrooms (house/apartment)
    $bedrooms = isset($property['bedrooms']) ? $property['bedrooms'] : null;
    $bathrooms = isset($property['bathrooms']) ? $property['bathrooms'] : null;
    if ($bedrooms !== null || $bathrooms !== null) {
        $pred *= getBedroomBathroomAdjustmentFactor($propertyType, $listingType, $bedrooms, $bathrooms);
    }

    // Parking
    if (isset($property['parking_spaces'])) {
        $pred *= getParkingAdjustmentFactor($listingType, $property['parking_spaces']);
    }

    // Furnishing
    if (isset($property['furnished'])) {
        $pred *= getFurnishedAdjustmentFactor($propertyType, $listingType, (bool)$property['furnished']);
    }

    // Property age (construction_date)
    if (isset($property['construction_date'])) {
        $pred *= getAgeAdjustmentFactor($listingType, $property['construction_date']);
    }

    // Micro-location via location string
    if (isset($property['location'])) {
        $pred *= getMicroLocationAdjustmentFactor($city, $property['location']);
    }

    // Clamp and ensure non-negative
    $pred = max(0, min($pred, (float)$segment['max_price']));
    $pred = max($pred, (float)$segment['min_price']);

    return round($pred);
}

/**
 * Convenience API for forms: returns prediction and rationale
 * @param PDO $pdo
 * @param array $formData
 * @return array
 */
function suggestPriceForForm($pdo, $formData) {
    $propertyType = strtolower($formData['property_type'] ?? 'house');
    $listingType = strtolower($formData['listing_type'] ?? 'rent');
    $city = $formData['city'] ?? '';
    $locationCategory = getLocationCategoryForPricing($city);
    $features = $formData['features'] ?? [];
    $qualityScore = isset($formData['quality_score']) && is_numeric($formData['quality_score'])
        ? (int)max(0, min(100, $formData['quality_score']))
        : (is_array($features) ? (int)max(0, min(100, calculateQualityScore($features, $propertyType))) : 50);

    $segmentCoeffs = getPriceModelSegmentCoefficients($propertyType, $listingType, $locationCategory);
    if ($segmentCoeffs === null) {
        $segmentCoeffs = ['base_price' => 10000, 'quality_slope' => 200, 'min_price' => 3000, 'max_price' => 100000000];
    }

    $pred = (float)$segmentCoeffs['base_price'] + (float)$segmentCoeffs['quality_slope'] * (float)$qualityScore;

    // Area adjustment (all types)
    $areaSqft = isset($formData['area_sqft']) && is_numeric($formData['area_sqft']) ? (float)$formData['area_sqft'] : null;
    $areaFactor = 1.0; $areaBaseline = null;
    if ($areaSqft && $areaSqft > 0) {
        $adj = getAreaAdjustmentFactor($propertyType, $listingType, $areaSqft);
        $areaFactor = $adj['factor'];
        $areaBaseline = $adj['baseline'];
        $pred *= $areaFactor;
    }

    // Storey adjustment (houses)
    $floors = isset($formData['floors']) && is_numeric($formData['floors']) ? (int)$formData['floors'] : null;
    $floorsFactor = 1.0;
    if ($floors !== null) {
        $floorsFactor = getStoreyAdjustmentFactor($propertyType, $listingType, $floors);
        $pred *= $floorsFactor;
    }

    // Bedrooms/Bathrooms
    $bbFactor = getBedroomBathroomAdjustmentFactor(
        $propertyType,
        $listingType,
        $formData['bedrooms'] ?? null,
        $formData['bathrooms'] ?? null
    );
    $pred *= $bbFactor;

    // Parking
    $pred *= getParkingAdjustmentFactor($listingType, $formData['parking_spaces'] ?? null);

    // Furnished
    $pred *= getFurnishedAdjustmentFactor($propertyType, $listingType, !empty($formData['furnished']));

    // Age
    $pred *= getAgeAdjustmentFactor($listingType, $formData['construction_date'] ?? null);

    // Micro-location
    $pred *= getMicroLocationAdjustmentFactor($city, $formData['location'] ?? '');

    $pred = max(0, min($pred, (float)$segmentCoeffs['max_price']));
    $pred = max($pred, (float)$segmentCoeffs['min_price']);
    $pred = round($pred);

    $coeffSummary = sprintf('base=%s, slope=%s, min=%s, max=%s',
        number_format($segmentCoeffs['base_price']),
        number_format($segmentCoeffs['quality_slope']),
        number_format($segmentCoeffs['min_price']),
        number_format($segmentCoeffs['max_price'])
    );

    $explanationParts = [];
    $explanationParts[] = sprintf('quality score %d/100', $qualityScore);
    $explanationParts[] = sprintf('%s location', str_replace('_', ' ', $locationCategory));
    if ($areaSqft && $areaSqft > 0) {
        $explanationParts[] = sprintf('area %.0f sq ft (×%.2f)', $areaSqft, $areaFactor);
    }
    if ($floors !== null) {
        $explanationParts[] = sprintf('%d floor%s (×%.2f)', $floors, $floors === 1 ? '' : 's', $floorsFactor);
    }
    if (!empty($formData['bedrooms']) || !empty($formData['bathrooms'])) {
        $explanationParts[] = sprintf('BR/BA (%s/%s)', $formData['bedrooms'] ?? '-', $formData['bathrooms'] ?? '-');
    }
    if (isset($formData['parking_spaces'])) {
        $explanationParts[] = sprintf('parking %s', (string)$formData['parking_spaces']);
    }
    if (!empty($formData['furnished'])) {
        $explanationParts[] = 'furnished';
    }
    if (!empty($formData['construction_date'])) {
        $explanationParts[] = sprintf('age adj from %s', $formData['construction_date']);
    }
    if (!empty($formData['location'])) {
        $explanationParts[] = sprintf('micro-location "%s"', $formData['location']);
    }
    $explanation = sprintf(
        'Suggested %s price based on %s. Coefficients: %s.',
        ($listingType === 'rent' ? 'rent' : 'sale'),
        implode(', ', $explanationParts),
        $coeffSummary
    );

    return [
        'predicted_price' => $pred,
        'formatted_price' => formatPropertyPrice($pred, 'NPR ', $propertyType, $listingType),
        'quality_score' => $qualityScore,
        'area_sqft' => $areaSqft,
        'floors' => $floors,
        'bedrooms' => $formData['bedrooms'] ?? null,
        'bathrooms' => $formData['bathrooms'] ?? null,
        'parking_spaces' => $formData['parking_spaces'] ?? null,
        'furnished' => !empty($formData['furnished']),
        'property_type' => $propertyType,
        'listing_type' => $listingType,
        'city' => $city,
        'location_category' => $locationCategory,
        'model_version' => getPriceModelVersion(),
        'coefficients' => $segmentCoeffs,
        'explanation' => $explanation
    ];
}
?> 