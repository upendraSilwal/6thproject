<?php
$pageTitle = "Properties";
require_once 'includes/header.php';
require_once 'config/property_utils.php';

// Get search parameters
$propertyType = isset($_GET['property_type']) ? $_GET['property_type'] : '';
$location = isset($_GET['location']) ? $_GET['location'] : '';
$purpose = isset($_GET['purpose']) ? $_GET['purpose'] : '';
$priceRange = isset($_GET['price_range']) ? $_GET['price_range'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$scoreFilter = isset($_GET['score_filter']) ? $_GET['score_filter'] : 'all';
$scoreMin = isset($_GET['score_min']) ? intval($_GET['score_min']) : 0;
$userId = isset($_GET['user']) ? intval($_GET['user']) : 0; // Filter by specific user

// Fetch distinct cities for dropdown
$cityStmt = $pdo->query("SELECT DISTINCT city FROM properties WHERE approval_status = 'approved' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY city ASC");
$cities = $cityStmt->fetchAll(PDO::FETCH_COLUMN);
// All districts of Nepal (centralized)
$districts = function_exists('getNepalDistricts') ? getNepalDistricts() : [];

// Price range options
$priceRanges = [
    '' => 'Any Price',
    '0-20000' => 'Under 20,000',
    '20000-50000' => '20,000 - 50,000',
    '50000-100000' => '50,000 - 1,00,000',
    '100000-500000' => '1,00,000 - 5,00,000',
    '500000-1000000' => '5,00,000 - 10,00,000',
    '1000000-999999999' => 'Above 10,00,000',
];

// Build WHERE clause
$whereClause = "approval_status = 'approved' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())";
$params = [];

// Handle user filtering
if ($userId > 0) {
    // Show specific user's properties (for admin view)
    $whereClause .= " AND user_id = ?";
    $params[] = $userId;
} elseif (isset($_SESSION['user_id'])) {
    // Exclude current user's own properties if logged in (normal browsing)
    $whereClause .= " AND user_id != ?";
    $params[] = $_SESSION['user_id'];
}

if (!empty($propertyType)) {
    $whereClause .= " AND property_type = ?";
    $params[] = $propertyType;
}
if (!empty($location)) {
    $whereClause .= " AND city = ?";
    $params[] = $location;
}
if (!empty($purpose)) {
    if ($purpose == 'rent') {
        $whereClause .= " AND listing_type = 'rent'";
    } elseif ($purpose == 'buy') {
        $whereClause .= " AND listing_type = 'sale'";
    }
}
if (!empty($priceRange)) {
    if (strpos($priceRange, '+') !== false) {
        $minPrice = (int)str_replace('+', '', $priceRange);
        $whereClause .= " AND price >= ?";
        $params[] = $minPrice;
    } else {
        list($minPrice, $maxPrice) = explode('-', $priceRange);
        $whereClause .= " AND price >= ? AND price <= ?";
        $params[] = $minPrice;
        $params[] = $maxPrice;
    }
}

// For quality score filtering, we need to get ALL properties first, then filter
if ($scoreMin > 0) {
    // Get all properties without pagination to apply quality score filter
    $allPropertiesQuery = "SELECT * FROM properties WHERE $whereClause ORDER BY created_at DESC";
    $allPropertiesStmt = $pdo->prepare($allPropertiesQuery);
    $allPropertiesStmt->execute($params);
    $allProperties = $allPropertiesStmt->fetchAll();
    
    // Get all property IDs for features
    $allPropertyIds = array_column($allProperties, 'id');
    $allFeatures = [];
    if (!empty($allPropertyIds)) {
        $placeholders = implode(',', array_fill(0, count($allPropertyIds), '?'));
        $featureStmt = $pdo->prepare("SELECT property_id, feature_name FROM property_features WHERE property_id IN ($placeholders)");
        $featureStmt->execute($allPropertyIds);
        $featureResults = $featureStmt->fetchAll();
        foreach ($featureResults as $feature) {
            $allFeatures[$feature['property_id']][] = $feature['feature_name'];
        }
    }
    
    // Calculate quality scores and filter
    $filteredProperties = [];
    foreach ($allProperties as $property) {
        $propertyFeatures = isset($allFeatures[$property['id']]) ? $allFeatures[$property['id']] : [];
        $property['quality_score'] = calculateQualityScore($propertyFeatures, $property['property_type']);
        if ($property['quality_score'] >= $scoreMin) {
            $filteredProperties[] = $property;
        }
    }
    
    // Set total count for pagination based on filtered results
    $totalProperties = count($filteredProperties);
    
    // Apply pagination to filtered results
    $propertiesPerPage = 9;
    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $totalPages = ceil($totalProperties / $propertiesPerPage);
    $offset = ($currentPage - 1) * $propertiesPerPage;
    
    // Get properties for current page
    $properties = array_slice($filteredProperties, $offset, $propertiesPerPage);
    
    // Features are already loaded for filtered properties
    $features = $allFeatures;
    
} else {
    // No quality score filtering - use normal pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE $whereClause");
    $countStmt->execute($params);
    $totalProperties = $countStmt->fetchColumn();
    
    // Pagination
    $propertiesPerPage = 9;
    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $totalPages = ceil($totalProperties / $propertiesPerPage);
    $offset = ($currentPage - 1) * $propertiesPerPage;
    
    // Get properties for current page
    $query = "SELECT * FROM properties WHERE $whereClause ORDER BY created_at DESC LIMIT $propertiesPerPage OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $properties = $stmt->fetchAll();
    
    // Fetch features for current page properties
    $propertyIds = array_column($properties, 'id');
    $features = [];
    if (!empty($propertyIds)) {
        $placeholders = implode(',', array_fill(0, count($propertyIds), '?'));
        $featureStmt = $pdo->prepare("SELECT property_id, feature_name FROM property_features WHERE property_id IN ($placeholders)");
        $featureStmt->execute($propertyIds);
        $featureResults = $featureStmt->fetchAll();
        foreach ($featureResults as $feature) {
            $features[$feature['property_id']][] = $feature['feature_name'];
        }
    }
    
    // Calculate quality scores for current page properties
    foreach ($properties as &$property) {
        $propertyFeatures = isset($features[$property['id']]) ? $features[$property['id']] : [];
        $property['quality_score'] = calculateQualityScore($propertyFeatures, $property['property_type']);
    }
    unset($property);
}

// Sort properties based on sort parameter
switch ($sortBy) {
    case 'amenity_high':
        usort($properties, function($a, $b) {
            return $b['quality_score'] - $a['quality_score'];
        });
        break;
    case 'amenity_low':
        usort($properties, function($a, $b) {
            return $a['quality_score'] - $b['quality_score'];
        });
        break;
    case 'price_high':
        usort($properties, function($a, $b) {
            return $b['price'] - $a['price'];
        });
        break;
    case 'price_low':
        usort($properties, function($a, $b) {
            return $a['price'] - $b['price'];
        });
        break;
    case 'oldest':
        usort($properties, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        break;
    default: // newest
        usort($properties, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        break;
}

// Unsplash images for demo
$unsplashImages = [
    'house' => [
        'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1507089947368-19c1da9775ae?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1460518451285-97b6aa326961?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1464983953574-0892a716854b?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1512918728675-ed5a9ecdebfd?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1430285561322-7808604715df?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1465101046530-73398c7f28ca?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1519974719765-e6559eac2575?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1463693396721-7bfa6b4b8c81?auto=format&fit=crop&w=500&q=80'
    ],
    'apartment' => [
        'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1464983953574-0892a716854b?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1512918728675-ed5a9ecdebfd?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1465101046530-73398c7f28ca?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1519974719765-e6559eac2575?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1463693396721-7bfa6b4b8c81?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1460518451285-97b6aa326961?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1507089947368-19c1da9775ae?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1519125323398-675f0ddb6308?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1464983953574-0892a716854b?auto=format&fit=crop&w=500&q=80'
    ],
    'room' => [
        'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1507089947368-19c1da9775ae?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1465101178521-c1a9136a3b99?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1519125323398-675f0ddb6308?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1465101046530-73398c7f28ca?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1463693396721-7bfa6b4b8c81?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1512918728675-ed5a9ecdebfd?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1460518451285-97b6aa326961?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1519974719765-e6559eac2575?auto=format&fit=crop&w=500&q=80'
    ]
];

// Popular cities in Nepal for the location dropdown
$popularCities = [
    'Kathmandu', 'Pokhara', 'Lalitpur', 'Bhaktapur', 'Biratnagar', 'Birgunj',
    'Dharan', 'Butwal', 'Nepalgunj', 'Hetauda', 'Dhangadhi', 'Itahari', 'Bharatpur', 'Janakpur'
];

// Use total properties count (already accounts for filtering)
$filteredCount = $totalProperties;

// Get user information if filtering by user
$userInfo = null;
if ($userId > 0) {
    $userStmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!-- Hero Section -->
<section class="properties-hero">
    <div class="container">
        <div class="row text-center">
            <div class="col-lg-8 mx-auto">
                <h1 class="display-4 fw-bold mb-4">Find Your Perfect Property</h1>
                <p class="lead mb-0">Discover houses, apartments, and rooms across Nepal</p>
            </div>
        </div>
    </div>
</section>

<?php
// Use shared footer scripts for Choices.js
$includeChoicesJS = true;
?>

<!-- Search Filters -->
<section class="container">
    <div class="search-filters">
        <form method="GET" id="propertySearchForm" action="properties.php">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" for="property_type">Property Type</label>
                    <select class="form-select" name="property_type" id="property_type">
                        <option value="">All Types</option>
                        <option value="house" <?php echo $propertyType == 'house' ? 'selected' : ''; ?>>House</option>
                        <option value="apartment" <?php echo $propertyType == 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                        <option value="room" <?php echo $propertyType == 'room' ? 'selected' : ''; ?>>Room</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="location">City/District</label>
                    <select class="form-select" name="location" id="location">
                        <option value="">All Cities/Districts</option>
                        <?php 
                        // Merge full district list with popular and actual cities from database
                        $allCities = array_unique(array_merge($districts, $popularCities, $cities));
                        sort($allCities);
                        foreach ($allCities as $city): 
                        ?>
                        <option value="<?php echo htmlspecialchars($city); ?>" <?php echo $location == $city ? 'selected' : ''; ?>><?php echo htmlspecialchars($city); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3" id="purposeCol" style="display:none; opacity:0; transition:opacity 0.3s;">
                    <label class="form-label" for="purpose">Purpose</label>
                    <select class="form-select" name="purpose" id="purpose">
                        <option value="">All Purposes</option>
                        <option value="rent" <?php echo $purpose == 'rent' ? 'selected' : ''; ?>>Rent</option>
                        <option value="buy" <?php echo $purpose == 'buy' ? 'selected' : ''; ?>>Buy</option>
                    </select>
                </div>
                <div class="col-md-3" id="priceRangeCol" style="display:none; opacity:0; transition:opacity 0.3s;">
                    <label class="form-label" for="price_range">Price Range</label>
                    <select class="form-select" name="price_range" id="price_range">
                        <option value="">Any Price</option>
                        <!-- Dynamic options will be populated by JavaScript -->
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="sort">Sort By</label>
                    <select class="form-select" name="sort" id="sort">
                        <option value="newest" <?php echo $sortBy == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="amenity_high" <?php echo $sortBy == 'amenity_high' ? 'selected' : ''; ?>>Quality Score (High to Low)</option>
                        <option value="amenity_low" <?php echo $sortBy == 'amenity_low' ? 'selected' : ''; ?>>Quality Score (Low to High)</option>
                        <option value="price_low" <?php echo $sortBy == 'price_low' ? 'selected' : ''; ?>>Price (Low to High)</option>
                        <option value="price_high" <?php echo $sortBy == 'price_high' ? 'selected' : ''; ?>>Price (High to Low)</option>
                        <option value="oldest" <?php echo $sortBy == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label d-block">Min Quality Score: <span id="scoreMinValue"><?php echo $scoreMin; ?></span></label>
                    <input type="range" class="form-range" min="0" max="100" step="1" id="score_min" name="score_min" value="<?php echo $scoreMin; ?>" oninput="document.getElementById('scoreMinValue').textContent = this.value;">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12 text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-search me-2"></i>Search Properties
                    </button>
                    <a href="properties.php" class="btn btn-outline-secondary btn-lg ms-2" id="resetFiltersBtn">
                        <i class="fas fa-undo me-2"></i>Reset Filters
                    </a>
                </div>
            </div>
        </form>
    </div>
</section>
<script>
let priceRangeChoices;
let purposeChoices;
function updatePurposeVisibility() {
    const propertyType = document.getElementById('property_type').value;
    const purposeCol = document.getElementById('purposeCol');
    if (propertyType === 'house' || propertyType === 'apartment') {
        purposeCol.style.display = 'block';
        setTimeout(() => { purposeCol.style.opacity = 1; }, 10);
    } else {
        purposeCol.style.opacity = 0;
        setTimeout(() => { purposeCol.style.display = 'none'; }, 300);
        document.getElementById('purpose').value = '';
    }
}
function updatePriceRangeVisibility() {
    const propertyType = document.getElementById('property_type').value;
    const purpose = document.getElementById('purpose').value;
    const priceRangeCol = document.getElementById('priceRangeCol');
    let show = false;
    if (propertyType === 'house' || propertyType === 'apartment') {
        if (purpose) show = true;
    } else if (propertyType === 'room') {
        show = true;
    }
    if (show) {
        priceRangeCol.style.display = 'block';
        setTimeout(() => { priceRangeCol.style.opacity = 1; }, 10);
    } else {
        priceRangeCol.style.opacity = 0;
        setTimeout(() => { priceRangeCol.style.display = 'none'; }, 300);
        document.getElementById('price_range').value = '';
        if (priceRangeChoices) priceRangeChoices.setChoiceByValue('');
    }
}
const priceRanges = {
    house: {
        rent: [
            { value: "20000-40000", text: "NPR 20,000 - 40,000" },
            { value: "40000-70000", text: "NPR 40,000 - 70,000" },
            { value: "70000+", text: "Above NPR 70,000" }
        ],
        buy: [
            { value: "10000000-20000000", text: "NPR 1,00,00,000 - 2,00,00,000" },
            { value: "20000000-40000000", text: "NPR 2,00,00,000 - 4,00,00,000" },
            { value: "40000000+", text: "Above NPR 4,00,00,000" }
        ]
    },
    apartment: {
        rent: [
            { value: "10000-20000", text: "NPR 10,000 - 20,000" },
            { value: "20000-35000", text: "NPR 20,000 - 35,000" },
            { value: "35000+", text: "Above NPR 35,000" }
        ],
        buy: [
            { value: "4000000-8000000", text: "NPR 40,00,000 - 80,00,000" },
            { value: "8000000-15000000", text: "NPR 80,00,000 - 1,50,00,000" },
            { value: "15000000+", text: "Above NPR 1,50,00,000" }
        ]
    },
    room: {
        rent: [
            { value: "3000-6000", text: "NPR 3,000 - 6,000" },
            { value: "6000-10000", text: "NPR 6,000 - 10,000" },
            { value: "10000+", text: "Above NPR 10,000" }
        ]
    }
};
function updatePriceRange() {
    const propertyType = document.getElementById('property_type').value;
    const purpose = document.getElementById('purpose').value;
    let choicesList = [{ value: '', label: 'Any Price', selected: true, disabled: false }];
    if (propertyType && priceRanges[propertyType]) {
        if ((propertyType === 'house' || propertyType === 'apartment') && purpose) {
            priceRanges[propertyType][purpose].forEach(range => {
                choicesList.push({ value: range.value, label: range.text });
            });
        } else if (propertyType === 'room') {
            priceRanges[propertyType].rent.forEach(range => {
                choicesList.push({ value: range.value, label: range.text });
            });
        }
    }
    if (priceRangeChoices) {
        priceRangeChoices.clearChoices();
        priceRangeChoices.setChoices(choicesList, 'value', 'label', true);
    }
}
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Choices.js for all .form-select elements
    document.querySelectorAll('.form-select').forEach(function(sel) {
        // Avoid double-initializing price_range and purpose (handled below)
        if (sel.id !== 'price_range' && sel.id !== 'purpose') {
            new Choices(sel, {
                searchEnabled: false,
                shouldSort: false,
                position: 'bottom',
            });
        }
    });
    // Dedicated Choices.js for price_range and purpose
    priceRangeChoices = new Choices('#price_range', {
        searchEnabled: false,
        shouldSort: false,
        position: 'bottom',
    });
    purposeChoices = new Choices('#purpose', {
        searchEnabled: false,
        shouldSort: false,
        position: 'bottom',
    });
    // Initialize sort dropdown
    new Choices('#sort', {
        searchEnabled: false,
        shouldSort: false,
        position: 'bottom',
    });
    const propertyTypeSelect = document.getElementById('property_type');
    const purposeSelect = document.getElementById('purpose');
    propertyTypeSelect.addEventListener('change', function() {
        updatePurposeVisibility();
        updatePriceRange();
        updatePriceRangeVisibility();
    });
    purposeSelect.addEventListener('change', function() {
        updatePriceRange();
        updatePriceRangeVisibility();
    });
    updatePurposeVisibility();
    updatePriceRange();
    updatePriceRangeVisibility();
});
</script>

<!-- Properties Section -->
<section class="py-5">
    <div class="container">
        <!-- User Filter Header -->
        <?php if ($userInfo): ?>
        <div class="alert alert-info mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-user me-3"></i>
                <div>
                    <h6 class="mb-1">Viewing Properties by User</h6>
                    <p class="mb-0">
                        <strong><?php echo htmlspecialchars($userInfo['first_name'] . ' ' . $userInfo['last_name']); ?></strong>
                        <span class="text-muted">(<?php echo htmlspecialchars($userInfo['email']); ?>)</span>
                    </p>
                </div>
                <div class="ms-auto">
                    <a href="properties.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear Filter
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Results Header -->
        <div class="row align-items-center mb-4">
            <div class="col-md-6">
                <h4 class="mb-0"><?php echo $filteredCount; ?> Properties Found</h4>
                <?php if ($sortBy != 'newest'): ?>
                <small class="text-muted">
                    Sorted by: 
                    <?php
                    switch($sortBy) {
                        case 'amenity_high': echo 'Quality Score (High to Low)'; break;
                        case 'amenity_low': echo 'Quality Score (Low to High)'; break;
                        case 'price_low': echo 'Price (Low to High)'; break;
                        case 'price_high': echo 'Price (High to Low)'; break;
                        case 'oldest': echo 'Oldest First'; break;
                        default: echo 'Newest First'; break;
                    }
                    ?>
                </small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Properties Grid -->
        <?php if (empty($properties)): ?>
        <div class="text-center py-5">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h5>No properties found</h5>
            <p class="text-muted">Try adjusting your search criteria (e.g., Property Type, Location, Purpose, or Price Range).</p>
            <a href="properties.php" class="btn btn-primary">Clear Filters</a>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($properties as $property): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card property-card h-100">
                    <?php
                    // Check if property has a custom uploaded image
                    if (!empty($property['image_url'])) {
                        // Check if it's an Unsplash URL or uploaded file
                        if (strpos($property['image_url'], 'http') === 0) {
                            // It's an Unsplash URL
                            $imgUrl = $property['image_url'];
                        } else {
                            // It's an uploaded file
                            $imgUrl = 'uploads/' . $property['image_url'];
                        }
                    } else {
                        // Fallback to Unsplash demo image
                        $type = $property['property_type'];
                        $imgUrl = isset($unsplashImages[$type]) ? $unsplashImages[$type][array_rand($unsplashImages[$type])] : $unsplashImages['house'][0];
                    }
                    ?>
                    <img src="<?php echo $imgUrl; ?>" class="card-img-top property-image" alt="<?php echo htmlspecialchars($property['title']); ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-primary"><?php echo ucfirst($property['property_type']); ?></span>
                            <span class="badge bg-success"><?php echo ucfirst($property['listing_type']); ?></span>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($property['title']); ?></h5>
                        <p class="card-text text-muted">
                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($property['city'] . ', ' . $property['location']); ?>
                        </p>
                        <div class="property-features mb-3">
                            <?php if ($property['property_type'] !== 'room'): ?>
                            <span><i class="fas fa-bed"></i> <?php echo $property['bedrooms']; ?> Beds</span>
                            <span><i class="fas fa-bath"></i> <?php echo $property['bathrooms']; ?> Baths</span>
                            <?php endif; ?>
                            <span><i class="fas fa-ruler-combined"></i> <?php echo isset($property['area_sqft']) ? $property['area_sqft'] : 'N/A'; ?> sq ft</span>
                            <?php 
                                $propertyFeatures = isset($features[$property['id']]) ? $features[$property['id']] : [];
                                $amenityScore = calculateQualityScore($propertyFeatures, $property['property_type']);
                            ?>
                            <?php echo generateQualityScoreHTML($amenityScore, 'small'); ?>
                        </div>
                        
                        <?php if (isset($features[$property['id']])): ?>
                        <div class="property-tags mb-3">
                            <?php foreach (array_slice($features[$property['id']], 0, 3) as $feature): ?>
                            <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($feature); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="property-price"><?php echo formatPropertyPrice($property['price'], 'Rs. ', $property['property_type'], $property['listing_type']); ?></span>
                            <div class="btn-group">
                                <a href="property-details.php?id=<?php echo $property['id']; ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Properties pagination" class="mt-5">
            <ul class="pagination justify-content-center">
                <?php if ($currentPage > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($currentPage < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<style>
    .properties-hero {
        background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), 
                    url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
        background-size: cover;
        background-position: center;
        padding: 120px 0 60px;
        color: white;
    }
    
    .search-filters {
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        padding: 30px;
        margin-top: -50px;
        position: relative;
        z-index: 10;
    }
    
    .property-card {
        border: none;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        height: 100%;
    }
    
    .property-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    }
    
    .property-image {
        height: 250px;
        object-fit: cover;
        width: 100%;
    }
    
    .property-price {
        font-size: 1.5rem;
        font-weight: bold;
        color: #3498db;
    }
    
    .property-features {
        display: flex;
        gap: 15px;
        margin: 15px 0;
        font-size: 0.9rem;
        color: #6c757d;
    }
    
    .property-features i {
        margin-right: 5px;
        color: #3498db;
    }
    
    .property-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    
    .pagination .page-link {
        border-radius: 50%;
        margin: 0 5px;
        border: none;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .pagination .page-item.active .page-link {
        background: #3498db;
        border-color: #3498db;
    }
</style>

<?php
require_once 'includes/shared-footer-scripts.php';
require_once 'includes/footer.php';
?>
