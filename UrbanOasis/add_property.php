<?php
session_start();
require_once 'config/database.php';
require_once 'config/property_utils.php';
require_once 'config/user_utils.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user details first using centralized function
$user = getUserById($pdo, $user_id);

// Check if user can create a new listing
$uploadCheck = checkPropertyUploadLimit($pdo, $user_id, $user);

if (!$uploadCheck['allowed']) {
    if ($uploadCheck['reason'] === 'Phone verification required for free listings') {
        $_SESSION['info_message'] = 'Please verify your phone number to access your free listing.';
        header('Location: verify-phone.php');
        exit();
    } else {
        $_SESSION['error_message'] = 'You do not have enough credits to post a new property. Please buy more credits.';
        header('Location: pricing.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Re-check just in case of race conditions
    $uploadCheck = checkPropertyUploadLimit($pdo, $user_id, $user);
    if (!$uploadCheck['allowed']) {
        $error = 'You do not have enough credits. Please <a href="pricing.php">buy more</a>.';
    } else {
        // Collect form data
        $propertyData = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'property_type' => $_POST['property_type'] ?? '',
            'listing_type' => $_POST['listing_type'] ?? '',
            'price' => floatval($_POST['price'] ?? 0),
            'location' => trim($_POST['location'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'area_sqft' => floatval($_POST['area_sqft'] ?? 0),
            'bedrooms' => intval($_POST['bedrooms'] ?? 0),
            'bathrooms' => intval($_POST['bathrooms'] ?? 0),
            'parking_spaces' => intval($_POST['parking_spaces'] ?? 0),
            'furnished' => isset($_POST['furnished']) ? 1 : 0,
            'available_from' => $_POST['available_from'] ?? null,
            'construction_date' => $_POST['construction_date'] ?? null,
            'contact_phone' => trim($_POST['contact_phone'] ?? ''),
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'features' => $_POST['features'] ?? [],
            'selected_image' => $_POST['selected_image'] ?? '',
            'schedule_listing' => intval($_POST['schedule_listing'] ?? 0),
            'schedule_time' => $_POST['schedule_time'] ?? '08:00'
        ];
        
        // Use centralized validation
        $errors = validatePropertyData($propertyData, $propertyData['property_type']);
        
        if (empty($errors)) {
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // Handle multiple images upload and selection
                $selectedImages = [];
                if (isset($_POST['selected_images']) && !empty($_POST['selected_images'])) {
                    // Handle comma-separated string from hidden input
                    if (is_string($_POST['selected_images'])) {
                        $selectedImages = array_filter(explode(',', $_POST['selected_images']));
                    } else {
                        $selectedImages = $_POST['selected_images'];
                    }
                }
                $uploadedImages = [];
                
                // Handle file uploads
                if (isset($_FILES['property_images'])) {
                    $upload_dir = 'uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    foreach ($_FILES['property_images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['property_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_info = pathinfo($_FILES['property_images']['name'][$key]);
                            $file_extension = strtolower($file_info['extension']);
                            
                            if (in_array($file_extension, $allowed_types)) {
                                $new_filename = 'property_' . time() . '_' . $user_id . '_' . $key . '.' . $file_extension;
                                $upload_path = $upload_dir . $new_filename;
                                
                                if (move_uploaded_file($tmp_name, $upload_path)) {
                                    $uploadedImages[] = $new_filename;
                                }
                            }
                        }
                    }
                }
                
                // Combine uploaded and selected demo images
                $allImages = array_merge($uploadedImages, $selectedImages);
                
                // Use first image as primary for backward compatibility
                $image_url = !empty($allImages) ? $allImages[0] : null;
                
                // Insert property with 30-day expiry
                $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                $stmt = $pdo->prepare("INSERT INTO properties (title, description, property_type, listing_type, price, location, city, area_sqft, bedrooms, bathrooms, parking_spaces, furnished, available_from, construction_date, contact_phone, contact_email, image_url, user_id, expires_at, is_active, schedule_listing, schedule_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$propertyData['title'], $propertyData['description'], $propertyData['property_type'], $propertyData['listing_type'], $propertyData['price'], $propertyData['location'], $propertyData['city'], $propertyData['area_sqft'], $propertyData['bedrooms'], $propertyData['bathrooms'], $propertyData['parking_spaces'], $propertyData['furnished'], $propertyData['available_from'], $propertyData['construction_date'], $propertyData['contact_phone'], $propertyData['contact_email'], $image_url, $user_id, $expires_at, 1, $propertyData['schedule_listing'], $propertyData['schedule_time']]);
                
                $property_id = $pdo->lastInsertId();
                
                // Insert property images
                if (!empty($allImages)) {
                    $stmt = $pdo->prepare("INSERT INTO property_images (property_id, image_url, image_type, display_order, is_primary) VALUES (?, ?, ?, ?, ?)");
                    foreach ($allImages as $index => $imageUrl) {
                        $imageType = in_array($imageUrl, $uploadedImages) ? 'uploaded' : 'demo';
                        $isPrimary = ($index === 0) ? 1 : 0;
                        $stmt->execute([$property_id, $imageUrl, $imageType, $index, $isPrimary]);
                    }
                }
                
                // Insert features
                if (!empty($propertyData['features'])) {
                    $stmt = $pdo->prepare("INSERT INTO property_features (property_id, feature_name) VALUES (?, ?)");
                    foreach ($propertyData['features'] as $feature) {
                        if (!empty(trim($feature))) {
                            $stmt->execute([$property_id, trim($feature)]);
                        }
                    }
                }
                
                // Deduct credit or use free listing
                if ($uploadCheck['free_listings_remaining'] > 0) {
                    // Use a free listing
                    $stmt = $pdo->prepare("UPDATE users SET free_listings_used = free_listings_used + 1, total_listings_created = total_listings_created + 1 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $transaction_description = 'Used 1 free listing for property #'. $property_id;
                } else {
                    // Use a credit
                    $stmt = $pdo->prepare("UPDATE users SET listing_credits = listing_credits - 5, total_listings_created = total_listings_created + 1 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $transaction_description = 'Used 5 credits for property #'. $property_id;
                }
                
                // Record transaction
                $stmt = $pdo->prepare("INSERT INTO credit_transactions (user_id, transaction_type, credits, description, property_id) VALUES (?, 'usage', 5, ?, ?)");
                $stmt->execute([$user_id, $transaction_description, $property_id]);
                
                // Commit transaction
                $pdo->commit();
                
                // Redirect to my properties page
                header("Location: my-properties.php?success=1");
                exit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'An error occurred while saving the property. Please try again. Error: ' . $e->getMessage();
            }
        } else {
            $error = implode(' ', $errors);
        }
    }
}

// User details already loaded above

// Enhanced feature suggestions based on our algorithm categories
// Default to house, but JavaScript will update this dynamically
$featureSuggestions = getFeatureSuggestions('house');

// Unsplash demo images for different property types
$unsplashImages = [
    'house' => [
        'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=500&q=80'
    ],
    'apartment' => [
        'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1502005229762-cf1b2da7c5d6?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=500&q=80'
    ],
    'room' => [
        'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1507089947368-19c1da9775ae?auto=format&fit=crop&w=500&q=80',
        'https://images.unsplash.com/photo-1465101178521-c1a9136a3b99?auto=format&fit=crop&w=500&q=80'
    ]
];
?>

<?php
$pageTitle = "Add Property";
require_once 'includes/header.php';
?>

    <main class="container">
        <div class="add-property-container">
            <div class="add-property-header">
                <h1><i class="fas fa-plus-circle"></i> Add New Property</h1>
                <p>List your property and reach potential buyers/renters across Nepal</p>
                
                <div class="upload-status-info">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Cost:</strong> 5 Credits = 1 Property Listing | 
                        <strong>Available:</strong> <?php echo $user['listing_credits']; ?> credits + <?php echo max(0, 1 - $user['free_listings_used']); ?> free listing
                    </div>
                </div>
            </div>

            
            <?php if ($error): ?>
                <div id="form-error-alert" class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="add-property-form" enctype="multipart/form-data">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Property Title *</label>
                            <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" placeholder="e.g., Modern 3BHK Apartment in Kathmandu">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description *</label>
                            <textarea id="description" name="description" rows="4" required placeholder="Describe your property in detail: amenities, location benefits, nearby facilities, transportation access, neighborhood highlights, and any unique features that make this property special."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="property_type">Property Type *</label>
                            <select id="property_type" name="property_type" required>
                                <option value="">Select Property Type</option>
                                <option value="house" <?php echo ($_POST['property_type'] ?? '') === 'house' ? 'selected' : ''; ?>><?php echo getPropertyTypeDisplay('house'); ?></option>
                                <option value="apartment" <?php echo ($_POST['property_type'] ?? '') === 'apartment' ? 'selected' : ''; ?>><?php echo getPropertyTypeDisplay('apartment'); ?></option>
                                <option value="room" <?php echo ($_POST['property_type'] ?? '') === 'room' ? 'selected' : ''; ?>><?php echo getPropertyTypeDisplay('room'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="listing_type">Listing Type *</label>
                            <select id="listing_type" name="listing_type" required>
                                <option value="">Select Listing Type</option>
                                <option value="rent" <?php echo ($_POST['listing_type'] ?? '') === 'rent' ? 'selected' : ''; ?>><?php echo getListingTypeDisplay('rent'); ?></option>
                                <option value="sale" <?php echo ($_POST['listing_type'] ?? '') === 'sale' ? 'selected' : ''; ?>><?php echo getListingTypeDisplay('sale'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Price (NPR) *</label>
                            <input type="number" id="price" name="price" min="1" required value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" placeholder="e.g., 25000">
                        </div>
                        
                        <div class="form-group">
                            <label for="area_sqft">Area (sq ft) *</label>
                            <div class="input-with-index">
                                <input type="number" id="area_sqft" name="area_sqft" min="1" step="0.01" required value="<?php echo htmlspecialchars($_POST['area_sqft'] ?? ''); ?>" placeholder="e.g., 1200">
                                <span id="area-index" class="input-index"></span>
                            </div>
                            <small class="text-muted" id="area-hint"></small>
                        </div>
                    </div>

                    
                </div>

                <!-- Property Images -->
                <div class="form-section">
                    <h3><i class="fas fa-images"></i> Property Images</h3>
                    <p class="text-muted mb-3">Upload multiple images to showcase your property. You can select up to 10 images.</p>
                    
                    <div class="image-upload-container">
                        <div class="upload-options">
                            <div class="upload-option">
                                <h4><i class="fas fa-upload"></i> Upload Your Images</h4>
                                <div class="file-upload-wrapper">
                                    <input type="file" id="property_images" name="property_images[]" accept="image/*" multiple class="file-input">
                                    <label for="property_images" class="file-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Choose Files</span>
                                        <small>JPG, PNG, GIF, WebP (Max 5MB each, up to 10 images)</small>
                                    </label>
                                </div>
                                <div id="uploaded-previews" class="uploaded-previews"></div>
                            </div>
                            
                            <div class="upload-divider">
                                <span>AND/OR</span>
                            </div>
                            
                            <div class="upload-option">
                                <h4><i class="fas fa-images"></i> Choose Demo Images</h4>
                                <p class="text-muted">Select demo images based on your property type (click to select/deselect)</p>
                                <input type="hidden" id="selected_images" name="selected_images" value="">
                                <div id="demo-images" class="demo-images-grid">
                                    <p class="text-muted">Select a property type to see demo images</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Selected Images Summary -->
                        <div id="images-summary" class="images-summary mt-4" style="display: none;">
                            <h5><i class="fas fa-check-circle text-success"></i> Selected Images</h5>
                            <div id="summary-content" class="summary-content"></div>
                        </div>
                    </div>
                </div>

                <!-- Location Information -->
                <div class="form-section">
                    <h3><i class="fas fa-map-marker-alt"></i> Location Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City/District *</label>
                            <select id="city" name="city" required>
                                <option value="">Select City/District</option>
                                <?php foreach (getNepalDistricts() as $district): ?>
                                <option value="<?php echo htmlspecialchars($district); ?>" <?php echo (($_POST['city'] ?? '') === $district) ? 'selected' : ''; ?>><?php echo htmlspecialchars($district); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Location/Address *</label>
                            <input type="text" id="location" name="location" required value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" placeholder="e.g., Baneshwor, Near City Center">
                        </div>
                    </div>
                </div>

                <!-- Property Details -->
                <div class="form-section">
                    <h3><i class="fas fa-home"></i> Property Details</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bedrooms">Bedrooms</label>
                            <input type="number" id="bedrooms" name="bedrooms" min="1" max="10" value="<?php echo htmlspecialchars($_POST['bedrooms'] ?? ''); ?>" placeholder="e.g., 3">
                            <small class="text-muted">Not required for rooms</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="bathrooms">Bathrooms</label>
                            <input type="number" id="bathrooms" name="bathrooms" min="1" max="10" value="<?php echo htmlspecialchars($_POST['bathrooms'] ?? ''); ?>" placeholder="e.g., 2">
                            <small class="text-muted">Not required for rooms</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="parking_spaces">Parking Spaces</label>
                            <input type="number" id="parking_spaces" name="parking_spaces" min="0" max="10" value="<?php echo htmlspecialchars($_POST['parking_spaces'] ?? '0'); ?>" placeholder="e.g., 1">
                        </div>
                    </div>

                        <div class="form-row" id="floors-row" style="display:none;">
                            <div class="form-group">
                                <label for="floors">Number of Floors (storeys)</label>
                                <input type="number" id="floors" name="floors" min="1" max="6" value="<?php echo htmlspecialchars($_POST['floors'] ?? ''); ?>" placeholder="e.g., 3">
                                <small class="text-muted">Shown for houses only</small>
                            </div>
                        </div>

                    <div class="form-row full-width">
                        <div class="form-group full-width">
                            <label for="available_from">Property Availability Date</label>
                            <input type="date" id="available_from" name="available_from" 
                                   value="<?php echo htmlspecialchars($_POST['available_from'] ?? ''); ?>" 
                                   min="<?php echo date('Y-m-d'); ?>">
                            <small class="text-muted">When will this property be ready for occupancy? Leave empty if available immediately.</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group full-width">
                            <!-- Advanced Scheduling Options - Full Width -->
                            <div id="scheduling-options" class="scheduling-options full-width" style="display: none;">
                                <div class="scheduling-card">
                                    <div class="scheduling-header">
                                        <h6><i class="fas fa-clock"></i>Advanced Scheduling Options</h6>
                                    </div>
                                    <div class="scheduling-body">
                                        <div class="scheduling-button-wrapper">
                                            <input type="hidden" id="schedule_listing" name="schedule_listing" value="0">
                                            <button type="button" id="schedule_button" class="schedule-button">
                                                <div class="button-content">
                                                    <div class="button-title">Schedule Listing Publication</div>
                                                    <div class="button-subtitle">Hide this property from public view until the scheduled date/time</div>
                                                </div>
                                                <div class="button-indicator">
                                                    <i class="fas fa-clock"></i>
                                                </div>
                                            </button>
                                        </div>
                                        
                                        <div id="schedule-time-section" class="schedule-time-section" style="display: none;">
                                            <div class="time-settings-grid">
                                                <div class="time-input-group">
                                                    <label for="schedule_time" class="time-label">Publication Time</label>
                                                    <select id="schedule_time" name="schedule_time" class="time-select">
                                                        <option value="00:00">12:00 AM (Midnight)</option>
                                                        <option value="06:00">6:00 AM</option>
                                                        <option value="08:00" selected>8:00 AM</option>
                                                        <option value="10:00">10:00 AM</option>
                                                        <option value="12:00">12:00 PM (Noon)</option>
                                                        <option value="14:00">2:00 PM</option>
                                                        <option value="16:00">4:00 PM</option>
                                                        <option value="18:00">6:00 PM</option>
                                                        <option value="20:00">8:00 PM</option>
                                                    </select>
                                                </div>
                                                <div class="status-display-group">
                                                    <label class="status-label">Status</label>
                                                    <div class="status-indicator">
                                                        <i class="fas fa-eye-slash"></i>
                                                        <span>Hidden until scheduled time</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Dynamic Info Display -->
                            <div id="availability-info" class="availability-info mt-3" style="display: none;"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" id="construction-date-group" style="display: none;">
                            <label for="construction_date">Date of Construction/Completion</label>
                            <input type="date" id="construction_date" name="construction_date" value="<?php echo htmlspecialchars($_POST['construction_date'] ?? ''); ?>">
                            <small class="text-muted">When was this house built or completed?</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="furnished" value="1" <?php echo isset($_POST['furnished']) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Furnished
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="form-section">
                    <h3><i class="fas fa-phone"></i> Contact Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_phone">Contact Phone *</label>
                            <input type="tel" id="contact_phone" name="contact_phone" required value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>" placeholder="e.g., 9800000000">
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_email">Contact Email *</label>
                            <input type="email" id="contact_email" name="contact_email" required value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ''); ?>" placeholder="e.g., contact@example.com">
                        </div>
                    </div>
                </div>

                <!-- Enhanced Features & Amenities -->
                <div class="form-section">
                    <h3><i class="fas fa-thumbs-up"></i> Features & Amenities</h3>
                    
                    <div class="features-container">
                        <div class="feature-inputs">
                            <div class="feature-input">
                                <input type="text" name="features[]" placeholder="Add a feature (e.g., WiFi, Garden, Security, etc.)">
                                <button type="button" class="btn btn-sm btn-outline-primary add-feature">+</button>
                            </div>
                        </div>
                        <!--
                        <small>Click + to add more features. Our smart algorithm will automatically categorize your features for better display and user experience.</small>
                        -->
                        <!-- Feature Preview (shows how features will be categorized) -->
                        <div id="feature-preview" class="feature-preview mt-3" style="display: none;">
                            <h6><i class="fas fa-eye"></i> How your features will be displayed:</h6>
                            <div id="preview-categories" class="preview-categories"></div>
                        </div>
                        
                        <!-- Enhanced Feature Suggestions by Category -->
                        <div class="feature-suggestions mt-3">
                            <h6><i class="fas fa-lightbulb"></i> Suggested Features by Category:</h6>
                            <p class="text-muted small mb-2">Click any feature below to add it. Our algorithm will automatically organize them into categories for better presentation.</p>
                            
                            <?php foreach ($featureSuggestions as $category => $features): ?>
                            <div class="suggestion-category">
                                <h6 class="category-title">
                                    <?php
                                    $icons = [
                                        'essential' => 'fas fa-shield-alt',
                                        'comfort' => 'fas fa-couch',
                                        'luxury' => 'fas fa-crown',
                                        'convenience' => 'fas fa-map-marker-alt',
                                        'safety' => 'fas fa-shield-alt'
                                    ];
                                    $labels = [
                                        'essential' => 'Essential Amenities',
                                        'comfort' => 'Comfort Features',
                                        'luxury' => 'Luxury Amenities',
                                        'convenience' => 'Location Benefits',
                                        'safety' => 'Safety Features'
                                    ];
                                    ?>
                                    <i class="<?php echo $icons[$category]; ?>"></i>
                                    <?php echo $labels[$category]; ?>
                                </h6>
                                <div class="suggestion-tags">
                                    <?php foreach ($features as $feature): ?>
                                    <?php if (strtolower($feature) !== 'wifi'): ?>
                                    <span class="badge bg-light text-dark me-2 mb-2 suggestion-tag" data-feature="<?php echo htmlspecialchars($feature); ?>"><?php echo htmlspecialchars($feature); ?></span>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <!-- Price Suggestion Widget (moved to end, hidden until ready) -->
                <div id="price-suggestion-widget" class="form-section" style="display:none;">
                    <h3><i class="fas fa-magic"></i> Smart Price Suggestion</h3>
                    <div class="form-row full-width">
                        <div class="form-group full-width">
                            <div class="d-flex" style="gap: .5rem; flex-wrap: wrap; align-items: center;">
                                <button type="button" id="btn-fetch-suggested-price" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-wand-magic-sparkles"></i> Get Suggested Price
                                </button>
                                <button type="button" id="btn-use-suggested-price" class="btn btn-outline-success btn-sm" style="display:none;">
                                    Use suggested
                                </button>
                                <span id="price-suggestion-badge" class="badge bg-light text-dark" style="display:none;"></span>
                            </div>
                            <small class="text-muted" id="price-suggestion-note"></small>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">
                        <i class="fas fa-save"></i>
                        Submit Property
                    </button>
                    <a href="my-properties.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Unsplash demo images data
        const unsplashImages = <?php echo json_encode($unsplashImages); ?>;
        
        // Initialize Choices.js for select elements
        const selects = document.querySelectorAll('select');
        const choicesInstances = {};
        
        selects.forEach(select => {
            choicesInstances[select.id] = new Choices(select, {
                searchEnabled: false,
                itemSelectText: ''
            });
        });

        // Multiple images handling variables
        const fileInput = document.getElementById('property_images');
        const uploadedPreviewsContainer = document.getElementById('uploaded-previews');
        const selectedImagesInput = document.getElementById('selected_images');
        const demoImagesContainer = document.getElementById('demo-images');
        const imagesSummary = document.getElementById('images-summary');
        const summaryContent = document.getElementById('summary-content');
        
        let uploadedFiles = [];
        let selectedDemoImages = [];
        
        // Dynamic feature inputs and form logic
        const addFeatureBtn = document.querySelector('.add-feature');
        const featureInputs = document.querySelector('.feature-inputs');
        const propertyTypeSelect = document.getElementById('property_type');
        const listingTypeSelect = document.getElementById('listing_type');
        const bedroomsInput = document.getElementById('bedrooms');
        const bathroomsInput = document.getElementById('bathrooms');
        const floorsRow = document.getElementById('floors-row');
        const floorsInput = document.getElementById('floors');
        const citySelect = document.getElementById('city');
        const priceInput = document.getElementById('price');
        const btnFetch = document.getElementById('btn-fetch-suggested-price');
        const btnUse = document.getElementById('btn-use-suggested-price');
        const badge = document.getElementById('price-suggestion-badge');
        const note = document.getElementById('price-suggestion-note');
        const suggestionSection = document.getElementById('price-suggestion-widget');

        // Handle property type change
        propertyTypeSelect.addEventListener('change', function() {
            const selectedType = this.value;
            const constructionDateGroup = document.getElementById('construction-date-group');
            
            // Destroy existing Choices.js instance and recreate it
            if (choicesInstances['listing_type']) {
                choicesInstances['listing_type'].destroy();
            }
            
            // Clear the select element
            listingTypeSelect.innerHTML = '';
            
            if (selectedType === 'room') {
                // For rooms, only allow rent
                listingTypeSelect.innerHTML = `
                    <option value="" disabled>Select Listing Type</option>
                    <option value="rent" selected>For Rent</option>
                `;
                bedroomsInput.required = false;
                bathroomsInput.required = false;
                bedroomsInput.placeholder = 'Optional for rooms';
                bathroomsInput.placeholder = 'Optional for rooms';
                // Hide construction date field for rooms
                constructionDateGroup.style.display = 'none';
                // Hide floors for rooms
                if (floorsRow) floorsRow.style.display = 'none';
            } else if (selectedType === 'house') {
                // For houses, allow both rent and sale
                listingTypeSelect.innerHTML = `
                    <option value="" disabled>Select Listing Type</option>
                    <option value="rent">For Rent</option>
                    <option value="sale">For Sale</option>
                `;
                bedroomsInput.required = true;
                bathroomsInput.required = true;
                bedroomsInput.placeholder = 'e.g., 3';
                bathroomsInput.placeholder = 'e.g., 2';
                // Show construction date field for houses
                constructionDateGroup.style.display = 'block';
                // Show floors for houses
                if (floorsRow) floorsRow.style.display = 'grid';
            } else {
                // For apartments, allow both rent and sale
                listingTypeSelect.innerHTML = `
                    <option value="" disabled>Select Listing Type</option>
                    <option value="rent">For Rent</option>
                    <option value="sale">For Sale</option>
                `;
                bedroomsInput.required = true;
                bathroomsInput.required = true;
                bedroomsInput.placeholder = 'e.g., 3';
                bathroomsInput.placeholder = 'e.g., 2';
                // Hide construction date field for apartments
                constructionDateGroup.style.display = 'none';
                // Hide floors for apartments
                if (floorsRow) floorsRow.style.display = 'none';
            }
            
            // Recreate Choices.js instance
            choicesInstances['listing_type'] = new Choices(listingTypeSelect, {
                searchEnabled: false,
                itemSelectText: ''
            });
            
            // Load demo images for selected property type
            loadDemoImages(selectedType);
            
            // Load property-type-specific feature suggestions
            loadFeatureSuggestions(selectedType);
        });

        // Multiple file upload handling
        fileInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            const maxFiles = 10;
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            
            // Check total files limit
            if (uploadedFiles.length + files.length > maxFiles) {
                alert(`You can only upload up to ${maxFiles} images in total.`);
                return;
            }
            
            files.forEach((file, index) => {
                // Validate file size
                if (file.size > maxSize) {
                    alert(`File "${file.name}" is too large. Max size is 5MB.`);
                    return;
                }
                
                // Validate file type
                if (!allowedTypes.includes(file.type)) {
                    alert(`File "${file.name}" is not a valid image file.`);
                    return;
                }
                
                // Add to uploaded files array
                uploadedFiles.push(file);
                
                // Create preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    createUploadedImagePreview(file, e.target.result, uploadedFiles.length - 1);
                    updateImagesSummary();
                };
                reader.readAsDataURL(file);
            });
        });
        
        // Create uploaded image preview
        function createUploadedImagePreview(file, src, index) {
            const previewDiv = document.createElement('div');
            previewDiv.className = 'uploaded-image-preview';
            previewDiv.setAttribute('data-index', index);
            previewDiv.innerHTML = `
                <img src="${src}" alt="${file.name}">
                <div class="image-info">
                    <span class="image-name">${file.name}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-uploaded" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Add remove functionality
            previewDiv.querySelector('.remove-uploaded').addEventListener('click', function() {
                const idx = parseInt(this.getAttribute('data-index'));
                uploadedFiles.splice(idx, 1);
                previewDiv.remove();
                updateImagesSummary();
                // Update data-index attributes for remaining previews
                document.querySelectorAll('.uploaded-image-preview').forEach((preview, newIndex) => {
                    preview.setAttribute('data-index', newIndex);
                    preview.querySelector('.remove-uploaded').setAttribute('data-index', newIndex);
                });
            });
            
            uploadedPreviewsContainer.appendChild(previewDiv);
        }

        // Load demo images
        function loadDemoImages(propertyType) {
            if (!propertyType || !unsplashImages[propertyType]) {
                demoImagesContainer.innerHTML = '<p class="text-muted">Select a property type to see demo images</p>';
                return;
            }
            const images = unsplashImages[propertyType];
            demoImagesContainer.innerHTML = '';
            images.forEach((imageUrl, index) => {
                const imageDiv = document.createElement('div');
                imageDiv.className = 'demo-image-item';
                imageDiv.innerHTML = `
                    <img src="${imageUrl}" alt="Demo Image ${index + 1}" data-url="${imageUrl}">
                    <div class="image-overlay">
                        <button type="button" class="btn btn-sm btn-primary select-image">Select</button>
                    </div>
                `;
                // Add click event
                imageDiv.querySelector('.select-image').addEventListener('click', function() {
                    selectDemoImage(imageUrl, imageDiv);
                });
                demoImagesContainer.appendChild(imageDiv);
            });
        }

        // Select demo image (toggle functionality)
        function selectDemoImage(imageUrl, imageDiv) {
            if (selectedDemoImages.includes(imageUrl)) {
                // Deselect image
                selectedDemoImages = selectedDemoImages.filter(url => url !== imageUrl);
                imageDiv.classList.remove('selected');
                imageDiv.querySelector('.select-image').textContent = 'Select';
            } else {
                // Select image (max 10 total)
                if (uploadedFiles.length + selectedDemoImages.length >= 10) {
                    alert('You can only select up to 10 images in total.');
                    return;
                }
                selectedDemoImages.push(imageUrl);
                imageDiv.classList.add('selected');
                imageDiv.querySelector('.select-image').textContent = 'Selected';
            }
            // Update hidden input with selected demo images
            selectedImagesInput.value = selectedDemoImages.join(',');
            updateImagesSummary();
        }
        
        // Update images summary
        function updateImagesSummary() {
            const totalImages = uploadedFiles.length + selectedDemoImages.length;
            if (totalImages > 0) {
                imagesSummary.style.display = 'block';
                summaryContent.innerHTML = `
                    <div class="summary-stats">
                        <span class="summary-item"><i class="fas fa-upload"></i> ${uploadedFiles.length} uploaded</span>
                        <span class="summary-item"><i class="fas fa-images"></i> ${selectedDemoImages.length} demo images</span>
                        <span class="summary-item"><i class="fas fa-check-circle"></i> ${totalImages} total images</span>
                    </div>
                `;
            } else {
                imagesSummary.style.display = 'none';
            }
        }

        // Load property-type-specific feature suggestions
        function loadFeatureSuggestions(propertyType) {
            if (!propertyType) return;
            
            // Feature suggestions for different property types
            const featureSuggestionsByType = {
                'room': {
                    'essential': ['Water Supply', 'Electricity', 'Internet', 'Furnished', 'Clean'],
                    'comfort': ['Balcony', 'Sunlight', 'Ventilation', 'Quiet', 'Private Bathroom', 'Pre-painted', 'Air Conditioning', 'Attached Bathroom', 'Study Table'],
                    'convenience': ['Near Market', 'Near Hospital', 'Near School', 'Near Public Transport', 'Laundry Access', 'Pets Allowed'],
                    'safety': ['CCTV', 'Safe Neighborhood', 'Female Friendly']
                },
                'apartment': {
                    'essential': ['Security', 'Elevator', 'Water Supply', 'Electricity', 'Internet', 'Parking'],
                    'comfort': ['Air Conditioning', 'Furnished', 'Balcony', 'Terrace', 'Garden View', 'Modern Kitchen'],
                    'luxury': ['Swimming Pool', 'Gym', 'Spa', 'Concierge', 'Jacuzzi', 'Rooftop Access'],
                    'convenience': ['Near Market', 'Near Hospital', 'Near School', 'Near Public Transport', 'Near Bank', 'Shopping Mall'],
                    'safety': ['CCTV', 'Security Guard', 'Fire Safety', 'Emergency Exit', 'Intercom']
                },
                'house': {
                    'essential': ['Security', 'Parking', 'Water Supply', 'Electricity', 'Internet'],
                    'comfort': ['Air Conditioning', 'Furnished', 'Garden', 'Balcony', 'Terrace', 'Fireplace'],
                    'luxury': ['Swimming Pool', 'Gym', 'Spa', 'Tennis Court', 'Home Theater', 'Wine Cellar'],
                    'convenience': ['Near Market', 'Near Hospital', 'Near School', 'Near Public Transport', 'Near Bank', 'Near Post Office'],
                    'safety': ['CCTV', 'Security Guard', 'Fire Safety', 'Emergency', 'Gated Community']
                }
            };
            
            const featureSuggestions = featureSuggestionsByType[propertyType] || featureSuggestionsByType['house'];
            const featureSuggestionsContainer = document.querySelector('.feature-suggestions');
            
            if (!featureSuggestionsContainer) return;
            
            // Clear existing suggestions
            featureSuggestionsContainer.innerHTML = `
                <h6><i class="fas fa-lightbulb"></i> Suggested Features by Category:</h6>
                <p class="text-muted small mb-2">Click any feature below to add it. Our algorithm will automatically organize them into categories for better presentation.</p>
            `;
            
            const icons = {
                'essential': 'fas fa-shield-alt',
                'comfort': 'fas fa-couch',
                'luxury': 'fas fa-crown',
                'convenience': 'fas fa-map-marker-alt',
                'safety': 'fas fa-shield-alt'
            };
            
            const labels = {
                'essential': 'Essential Amenities',
                'comfort': 'Comfort Features',
                'luxury': 'Luxury Amenities',
                'convenience': 'Location Benefits',
                'safety': 'Safety Features'
            };
            
            Object.entries(featureSuggestions).forEach(([category, features]) => {
                const categoryDiv = document.createElement('div');
                categoryDiv.className = 'suggestion-category';
                categoryDiv.innerHTML = `
                    <h6 class="category-title">
                        <i class="${icons[category]}"></i>
                        ${labels[category]}
                    </h6>
                    <div class="suggestion-tags">
                        ${features.map(feature => `
                            <span class="badge bg-light text-dark me-2 mb-2 suggestion-tag" data-feature="${feature}">${feature}</span>
                        `).join('')}
                    </div>
                `;
                featureSuggestionsContainer.appendChild(categoryDiv);
            });
            
            // Re-attach event listeners to new suggestion tags
            featureSuggestionsContainer.querySelectorAll('.suggestion-tag').forEach(tag => {
                tag.addEventListener('click', function() {
                    const feature = this.getAttribute('data-feature');
                    
                    // Special handling for bathroom options - disable conflicting options
                    const isBathroomFeature = feature === 'Private Bathroom' || feature === 'Attached Bathroom';
                    if (isBathroomFeature) {
                        const conflictingFeature = feature === 'Private Bathroom' ? 'Attached Bathroom' : 'Private Bathroom';
                        const conflictingTag = document.querySelector(`.suggestion-tag[data-feature="${conflictingFeature}"]`);
                        
                        // If adding this bathroom feature, remove the conflicting one
                        if (!this.classList.contains('active')) {
                            // Remove conflicting feature if it exists
                            const featureInputsList = document.querySelectorAll('input[name="features[]"]');
                            featureInputsList.forEach(input => {
                                if (input.value.trim().toLowerCase() === conflictingFeature.toLowerCase()) {
                                    input.parentElement.remove();
                                }

            // Update area hint and constraints by type
            updateAreaConstraints(selectedType);
                            });

        // Set initial area constraints
        updateAreaConstraints(propertyTypeSelect.value || '');

        function updateAreaConstraints(type) {
            const areaInput = document.getElementById('area_sqft');
            const hint = document.getElementById('area-hint');
            const indexEl = document.getElementById('area-index');
            if (!areaInput || !hint) return;
            let min = 1, max = 100000, msg = '';
            if (type === 'room') {
                min = 60; max = 400; msg = 'Typical room: 60–400 sq ft';
            } else if (type === 'apartment') {
                min = 300; max = 3500; msg = 'Typical apartment: 300–3500 sq ft';
            } else if (type === 'house') {
                min = 600; max = 10000; msg = 'Typical house: 600–10000 sq ft';
            } else {
                msg = '';
            }
            areaInput.min = String(min);
            areaInput.max = String(max);
            areaInput.dataset.min = String(min);
            areaInput.dataset.max = String(max);
            hint.textContent = msg;
            if (indexEl) indexEl.textContent = (min && max) ? `${min}~${max}` : '';
            // ensure right padding so index doesn't overlap text
            areaInput.style.paddingRight = indexEl && indexEl.textContent ? '80px' : '';
            // Re-validate current value
            checkAreaValidity();
        }

        const areaInputEl = document.getElementById('area_sqft');
        function checkAreaValidity() {
            const min = parseFloat(areaInputEl.dataset.min || areaInputEl.min || '0');
            const max = parseFloat(areaInputEl.dataset.max || areaInputEl.max || '0');
            const val = parseFloat(areaInputEl.value || '0');
            if (!isNaN(val) && (val < min || val > max)) {
                areaInputEl.setCustomValidity(`Enter between ${min} and ${max}`);
                // hide server error alert if it's only the area issue the user is fixing
                const err = document.getElementById('form-error-alert');
                if (err) err.style.display = 'none';
            } else {
                areaInputEl.setCustomValidity('');
            }
        }
        areaInputEl.addEventListener('input', checkAreaValidity);
            areaInputEl.addEventListener('blur', function() {
            const min = parseFloat(areaInputEl.dataset.min || areaInputEl.min || '0');
            const max = parseFloat(areaInputEl.dataset.max || areaInputEl.max || '0');
            let val = parseFloat(areaInputEl.value || '');
            if (isNaN(val)) return;
            if (val < min) val = min;
            if (val > max) val = max;
            areaInputEl.value = val;
            checkAreaValidity();
                // hide alert if now valid
                const err = document.getElementById('form-error-alert');
                if (err && areaInputEl.validationMessage === '') err.style.display = 'none';
        });
                            // Remove active class from conflicting tag
                            if (conflictingTag) {
                                conflictingTag.classList.remove('active');
                            }
                        }
                    }
                    
                    // Check if feature already exists
                    const featureInputsList = document.querySelectorAll('input[name="features[]"]');
                    let found = null;
                    featureInputsList.forEach(input => {
                        if (input.value.trim().toLowerCase() === feature.toLowerCase()) {
                            found = input;
                        }
                    });
                    if (found) {
                        // Remove the feature input
                        found.parentElement.remove();
                        this.classList.remove('active');
                    } else {
                        // Add the feature input
                        const newFeature = document.createElement('div');
                        newFeature.className = 'feature-input';
                        newFeature.innerHTML = `
                            \u003cinput type="text" name="features[]" value="${feature}" placeholder="Add a feature (e.g., WiFi, Garden, Security, etc.)"\u003e
                            \u003cbutton type="button" class="btn btn-sm btn-outline-danger remove-feature"\u003e-\u003c/button\u003e
                        `;
                        featureInputs.appendChild(newFeature);
                        // Add remove functionality
                        newFeature.querySelector('.remove-feature').addEventListener('click', function() {
                            newFeature.remove();
                            window.updateFeaturePreview();
                            // Also un-highlight the tag if present
                            document.querySelectorAll('.suggestion-tag').forEach(t => {
                                if (t.getAttribute('data-feature').toLowerCase() === feature.toLowerCase()) {
                                    t.classList.remove('active');
                                }
                            });
                        });
                        // Update preview when feature is added
                        newFeature.querySelector('input').addEventListener('input', window.updateFeaturePreview);
                        this.classList.add('active');
                    }
                    window.updateFeaturePreview();
                    // Invalidate previous suggestion on feature change
                    clearSuggestion();
                });
            });
        }

        // Dynamic feature inputs
        addFeatureBtn.addEventListener('click', function() {
            const newFeature = document.createElement('div');
            newFeature.className = 'feature-input';
            newFeature.innerHTML = `
                <input type="text" name="features[]" placeholder="Add a feature (e.g., WiFi, Garden, Security, etc.)">
                <button type="button" class="btn btn-sm btn-outline-danger remove-feature">-</button>
            `;
            featureInputs.appendChild(newFeature);
            // Add remove functionality
            newFeature.querySelector('.remove-feature').addEventListener('click', function() {
                newFeature.remove();
                window.updateFeaturePreview();
                clearSuggestion();
            });
            // Update preview when feature is added
            newFeature.querySelector('input').addEventListener('input', window.updateFeaturePreview);
        });

        // Enhanced feature suggestion tags (toggle behavior)
        document.querySelectorAll('.suggestion-tag').forEach(tag => {
            tag.addEventListener('click', function() {
                const feature = this.getAttribute('data-feature');
                // Check if feature already exists
                const featureInputsList = document.querySelectorAll('input[name="features[]"]');
                let found = null;
                featureInputsList.forEach(input => {
                    if (input.value.trim().toLowerCase() === feature.toLowerCase()) {
                        found = input;
                    }
                });
                if (found) {
                    // Remove the feature input
                    found.parentElement.remove();
                    this.classList.remove('active');
                } else {
                    // Add the feature input
                    const newFeature = document.createElement('div');
                    newFeature.className = 'feature-input';
                    newFeature.innerHTML = `
                        <input type="text" name="features[]" value="${feature}" placeholder="Add a feature (e.g., WiFi, Garden, Security, etc.)">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-feature">-</button>
                    `;
                    featureInputs.appendChild(newFeature);
                    // Add remove functionality
                    newFeature.querySelector('.remove-feature').addEventListener('click', function() {
                        newFeature.remove();
                        window.updateFeaturePreview();
                        // Also un-highlight the tag if present
                        document.querySelectorAll('.suggestion-tag').forEach(t => {
                            if (t.getAttribute('data-feature').toLowerCase() === feature.toLowerCase()) {
                                t.classList.remove('active');
                            }
                        });
                        clearSuggestion();
                    });
                    // Update preview when feature is added
                    newFeature.querySelector('input').addEventListener('input', window.updateFeaturePreview);
                    this.classList.add('active');
                }
                window.updateFeaturePreview();
                clearSuggestion();
            });
        });

        // Feature preview functionality
        function updateFeaturePreview() {
            const featureInputsList = document.querySelectorAll('input[name="features[]"]');
            const features = Array.from(featureInputsList).map(input => input.value.trim()).filter(f => f);
            if (features.length === 0) {
                document.getElementById('feature-preview').style.display = 'none';
                return;
            }
            // Updated categorization logic
            const categories = {
                'Essential Amenities': [],
                'Comfort Features': [],
                'Luxury Amenities': [],
                'Location Benefits': [],
                'Safety Features': [],
                'Other': []
            };
            features.forEach(feature => {
                const lowerFeature = feature.toLowerCase();
                if ([
                    'internet', 'security', 'parking', 'water supply', 'electricity'
                ].some(keyword => lowerFeature.includes(keyword))) {
                    categories['Essential Amenities'].push(feature);
                } else if ([
                    'air conditioning', 'furnished', 'garden', 'balcony', 'terrace', 'fireplace'
                ].some(keyword => lowerFeature.includes(keyword))) {
                    categories['Comfort Features'].push(feature);
                } else if ([
                    'swimming pool', 'gym', 'spa', 'elevator', 'jacuzzi', 'sauna', 'tennis court'
                ].some(keyword => lowerFeature.includes(keyword))) {
                    categories['Luxury Amenities'].push(feature);
                } else if ([
                    'near', 'market', 'hospital', 'school', 'transport', 'restaurant', 'bank', 'post office'
                ].some(keyword => lowerFeature.includes(keyword))) {
                    categories['Location Benefits'].push(feature);
                } else if ([
                    'cctv', 'security guard', 'fire safety', 'emergency'
                ].some(keyword => lowerFeature.includes(keyword))) {
                    categories['Safety Features'].push(feature);
                } else {
                    categories['Other'].push(feature);
                }
            });
            // Display preview
            const previewContainer = document.getElementById('preview-categories');
            previewContainer.innerHTML = '';
            Object.entries(categories).forEach(([category, categoryFeatures]) => {
                if (categoryFeatures.length > 0) {
                    const categoryDiv = document.createElement('div');
                    categoryDiv.className = 'preview-category';
                    categoryDiv.innerHTML = `
                        <h6 class="preview-category-title">${category}</h6>
                        <div class="preview-features">
                            ${categoryFeatures.map(f => `<span class="badge bg-primary me-1 mb-1">${f}</span>`).join('')}
                        </div>
                    `;
                    previewContainer.appendChild(categoryDiv);
                }
            });
            document.getElementById('feature-preview').style.display = 'block';
        }
        window.updateFeaturePreview = updateFeaturePreview;

        // Add event listeners for feature input changes (for initial fields)
        document.querySelectorAll('input[name="features[]"]').forEach(input => {
            input.addEventListener('input', window.updateFeaturePreview);
            input.addEventListener('input', clearSuggestion);
        });

        // Load initial demo images if property type is selected
        if (propertyTypeSelect.value) {
            loadDemoImages(propertyTypeSelect.value);
        } else {
            demoImagesContainer.innerHTML = '<p class="text-muted">Select a property type to see demo images</p>';
        }
        
        // Initial feature preview
        window.updateFeaturePreview();
        
        // Advanced Availability date and scheduling handling
        const availableFromInput = document.getElementById('available_from');
        const availabilityInfo = document.getElementById('availability-info');
        const schedulingOptions = document.getElementById('scheduling-options');
        const scheduleListingCheckbox = document.getElementById('schedule_listing');
        const scheduleTimeSection = document.getElementById('schedule-time-section');
        const scheduleTimeSelect = document.getElementById('schedule_time');
        
        // Debug: Check if elements exist
        console.log('Debugging availability elements:');
        console.log('availableFromInput:', availableFromInput);
        console.log('schedulingOptions:', schedulingOptions);
        console.log('availabilityInfo:', availabilityInfo);
        
        // Handle availability date changes
        if (availableFromInput && schedulingOptions && availabilityInfo) {
            availableFromInput.addEventListener('change', function() {
                console.log('Date changed to:', this.value);
                const selectedDate = new Date(this.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                console.log('Selected date:', selectedDate);
                console.log('Today:', today);
                console.log('Date comparison:', selectedDate >= today);
                
                if (this.value && selectedDate >= today) {
                    // Show scheduling options for today and future dates
                    console.log('Showing scheduling options for today or future date');
                    schedulingOptions.style.display = 'block';
                    updateAvailabilityInfo();
                } else {
                    // Hide scheduling options and info for empty or past dates
                    console.log('Hiding scheduling options (empty or past date)');
                    schedulingOptions.style.display = 'none';
                    availabilityInfo.style.display = 'none';
                    scheduleListingCheckbox.checked = false;
                    scheduleTimeSection.style.display = 'none';
                }
            });
        } else {
            console.error('Missing required elements for scheduling functionality');
        }
        
        // Handle schedule listing button
        const scheduleButton = document.getElementById('schedule_button');
        let isSchedulingEnabled = false;
        
        if (scheduleButton) {
            scheduleButton.addEventListener('click', function() {
                isSchedulingEnabled = !isSchedulingEnabled;
                
                // Update hidden input value
                scheduleListingCheckbox.value = isSchedulingEnabled ? '1' : '0';
                
                // Add green animation effect
                if (isSchedulingEnabled) {
                    this.classList.add('active', 'animate-success');
                    scheduleTimeSection.style.display = 'block';
                    
                    // Remove animation class after animation completes
                    setTimeout(() => {
                        this.classList.remove('animate-success');
                    }, 600);
                } else {
                    this.classList.remove('active');
                    scheduleTimeSection.style.display = 'none';
                }
                
                updateAvailabilityInfo();
            });
        }
        
        // Handle schedule time changes
        scheduleTimeSelect.addEventListener('change', updateAvailabilityInfo);
        
        // Update availability info display
        function updateAvailabilityInfo() {
            if (!availableFromInput.value) {
                availabilityInfo.style.display = 'none';
                return;
            }
            
            const selectedDate = new Date(availableFromInput.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const isToday = selectedDate.getTime() === today.getTime();
            const isFuture = selectedDate > today;
            
            const dateOptions = { 
                weekday: 'long',
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const formattedDate = selectedDate.toLocaleDateString('en-US', dateOptions);
            
            let infoHTML = '';
            
            if (scheduleListingCheckbox.checked) {
                // Scheduled listing - hidden until date/time
                const timeValue = scheduleTimeSelect.value;
                const timeDisplay = convertTo12Hour(timeValue);
                
                infoHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-eye-slash me-2"></i>
                        <strong>Scheduled Publication:</strong><br>
                        <div class="mt-2">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-calendar-alt me-2 text-warning"></i>
                                <span><strong>Goes Live:</strong> ${formattedDate} at ${timeDisplay}</span>
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-home me-2 text-info"></i>
                                <span><strong>Available for Occupancy:</strong> ${formattedDate}</span>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Property will be hidden from public view until ${formattedDate} at ${timeDisplay}. 
                                Once published, users will see it as ready for occupancy.
                            </small>
                        </div>
                    </div>
                `;
            } else {
                // Not scheduled - visible immediately but availability shows future date
                if (isToday) {
                    infoHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Published Immediately & Available Today:</strong><br>
                            <div class="mt-2">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-globe me-2 text-success"></i>
                                    <span><strong>Visibility:</strong> Live on website immediately</span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-home me-2 text-success"></i>
                                    <span><strong>Occupancy:</strong> Available today</span>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (isFuture) {
                    const daysUntil = Math.ceil((selectedDate - today) / (1000 * 60 * 60 * 24));
                    infoHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-calendar-check me-2"></i>
                            <strong>Published Immediately, Available Later:</strong><br>
                            <div class="mt-2">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-globe me-2 text-success"></i>
                                    <span><strong>Visibility:</strong> Live on website immediately</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-home me-2 text-info"></i>
                                    <span><strong>Available for Occupancy:</strong> ${formattedDate}</span>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Users can view your property immediately but will see "Available from ${formattedDate}" 
                                    (${daysUntil} day${daysUntil > 1 ? 's' : ''} from now). On that date, it automatically changes to "Available Now".
                                </small>
                            </div>
                        </div>
                    `;
                }
            }
            
            if (infoHTML) {
                availabilityInfo.innerHTML = infoHTML;
                availabilityInfo.style.display = 'block';
            } else {
                availabilityInfo.style.display = 'none';
            }
        }
        
        // Convert 24-hour time to 12-hour format
        function convertTo12Hour(time24) {
            const [hours, minutes] = time24.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        }

        // Price suggestion logic
        function collectFormDataForSuggestion() {
            const features = Array.from(document.querySelectorAll('input[name="features[]"]'))
                .map(i => i.value.trim()).filter(Boolean);
            return {
                property_type: propertyTypeSelect.value || 'house',
                listing_type: listingTypeSelect.value || 'rent',
                city: citySelect.value || '',
                features: features,
                area_sqft: parseFloat(document.getElementById('area_sqft')?.value || '0') || undefined,
                floors: floorsInput && floorsRow && floorsRow.style.display !== 'none' ? (parseInt(floorsInput.value || '0', 10) || undefined) : undefined
            };
        }

        function clearSuggestion() {
            if (badge) {
                badge.style.display = 'none';
                badge.textContent = '';
            }
            if (note) {
                note.textContent = '';
            }
            if (btnUse) {
                btnUse.style.display = 'none';
                btnUse.dataset.value = '';
            }
        }

        async function fetchSuggestion() {
            try {
                window.showLoading && window.showLoading();
                clearSuggestion();
                const payload = collectFormDataForSuggestion();
                // Guard: only allow when minimally complete
                if (!payload.property_type || !payload.listing_type || !payload.city) {
                    if (note) note.textContent = 'Fill property type, listing type, and city to get a suggestion.';
                    return;
                }
                const res = await fetch('ajax/predict_price.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (!json.success) throw new Error(json.error || 'Failed');
                const data = json.data;
                if (badge) {
                    badge.textContent = `Suggested: ${data.formatted_price}`;
                    badge.style.display = 'inline-block';
                }
                if (note) {
                    note.textContent = `${data.explanation} (model ${data.model_version})`;
                }
                if (btnUse) {
                    btnUse.dataset.value = data.predicted_price;
                    btnUse.style.display = 'inline-block';
                }
            } catch (e) {
                if (note) note.textContent = 'Could not fetch suggestion. Please check required fields.';
            } finally {
                window.hideLoading && window.hideLoading();
            }
        }

        if (btnFetch) {
            btnFetch.addEventListener('click', fetchSuggestion);
        }
        if (btnUse) {
            btnUse.addEventListener('click', function() {
                const val = this.dataset.value ? parseInt(this.dataset.value, 10) : null;
                if (val && priceInput) {
                    priceInput.value = val;
                    // brief feedback
                    this.textContent = 'Applied';
                    setTimeout(() => { this.textContent = 'Use suggested'; }, 1000);
                }
            });
        }

        // Show/hide suggestion section only when form has required basics
        function evaluateSuggestionVisibility() {
            const hasBasics = (propertyTypeSelect.value && listingTypeSelect.value && citySelect.value);
            if (suggestionSection) {
                suggestionSection.style.display = hasBasics ? 'block' : 'none';
                if (!hasBasics) clearSuggestion();
            }
        }
        // Initialize visibility and wire change listeners
        evaluateSuggestionVisibility();
        // Ensure floors visibility reflects current type on load
        if (floorsRow) {
            floorsRow.style.display = propertyTypeSelect.value === 'house' ? 'grid' : 'none';
        }
        [propertyTypeSelect, listingTypeSelect, citySelect].forEach(el => {
            el && el.addEventListener('change', evaluateSuggestionVisibility);
        });

        // Concise feature selection: toggle tags, store as hidden inputs; hide preview block
        (function initConciseFeatureSelection(){
            const hiddenContainer = document.querySelector('.feature-inputs');
            const preview = document.getElementById('feature-preview');
            if (preview) preview.style.display = 'none';
            // Hide any visible text inputs UI; we'll use hidden inputs only
            if (hiddenContainer) hiddenContainer.querySelectorAll('.feature-input').forEach(el => { el.style.display = 'none'; });

            const suggestions = document.querySelector('.feature-suggestions');
            if (!suggestions || !hiddenContainer) return;

            function findHidden(feature){
                return hiddenContainer.querySelector(`input[type="hidden"][name="features[]"][value="${CSS.escape(feature)}"]`);
            }
            function addHidden(feature){
                const h = document.createElement('input');
                h.type = 'hidden'; h.name = 'features[]'; h.value = feature; h.setAttribute('data-selected','1');
                hiddenContainer.appendChild(h);
            }
            function removeHidden(el){ if (el) el.remove(); }

            // Capture phase to override older handlers
            suggestions.addEventListener('click', function(e){
                const tag = e.target.closest('.suggestion-tag');
                if (!tag) return;
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                const feature = tag.getAttribute('data-feature');

                // conflict handling
                if ((feature === 'Private Bathroom' || feature === 'Attached Bathroom') && !tag.classList.contains('active')) {
                    const other = feature === 'Private Bathroom' ? 'Attached Bathroom' : 'Private Bathroom';
                    const otherTag = suggestions.querySelector(`.suggestion-tag[data-feature="${other}"]`);
                    removeHidden(findHidden(other));
                    if (otherTag) otherTag.classList.remove('active');
                }

                const existing = findHidden(feature);
                if (existing) { removeHidden(existing); tag.classList.remove('active'); }
                else { addHidden(feature); tag.classList.add('active'); }
                clearSuggestion();
            }, true);
        })();
    });
</script>

    <style>
        .input-with-index { position: relative; }
        .input-with-index .input-index {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 0.85rem;
            pointer-events: none;
        }
        .add-property-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .add-property-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .add-property-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.8rem;
        }

        .add-property-header p {
            margin: 0;
            opacity: 0.9;
        }

        .add-property-form {
            padding: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .form-section h3 {
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group.full-width,
        .form-row.full-width {
            grid-column: 1 / -1;
        }
        
        .form-row.full-width .form-group {
            grid-column: 1 / -1;
        }
        
        .scheduling-options.full-width {
            width: 100%;
            margin: 1rem 0;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #667eea;
            outline: none;
        }

        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #6c757d;
            font-size: 0.875rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 1.5rem;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: normal;
        }

        .checkbox-label input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
        }

        .features-container {
            margin-top: 1rem;
        }

        .feature-inputs {
            margin-bottom: 0.5rem;
        }

        .feature-input {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .feature-input input {
            flex: 1;
        }

        .feature-suggestions {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
        }

        .suggestion-category {
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-radius: 6px;
            background: white;
        }

        .category-title {
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .category-title i {
            margin-right: 0.5rem;
            color: #667eea;
        }

        .suggestion-tags .badge {
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.8rem;
        }

        .suggestion-tags .badge:hover {
            background: #667eea !important;
            color: white !important;
            transform: translateY(-1px);
        }

        /* Image Upload Styles */
        .image-upload-container {
            margin-top: 1rem;
        }

        .upload-options {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .upload-option {
            padding: 1.5rem;
            border: 2px dashed #e9ecef;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s;
        }

        .upload-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .upload-option h4 {
            margin-bottom: 1rem;
            color: #333;
        }

        .upload-divider {
            text-align: center;
            position: relative;
            margin: 1rem 0;
        }

        .upload-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
        }

        .upload-divider span {
            background: white;
            padding: 0 1rem;
            color: #6c757d;
            font-weight: 600;
        }

        .file-upload-wrapper {
            position: relative;
            display: inline-block;
        }

        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-label {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
            border: 2px dashed #667eea;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-label:hover {
            background: #f8f9ff;
            border-color: #5a6fd8;
        }

        .file-label i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        .file-label span {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .file-label small {
            color: #6c757d;
        }

        .file-preview {
            margin-top: 1rem;
            text-align: center;
        }

        .file-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .demo-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .demo-image-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
        }

        .demo-image-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .demo-image-item.selected {
            border: 3px solid #667eea;
        }

        .demo-image-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .demo-image-item:hover .image-overlay {
            opacity: 1;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn-large {
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
        }

        /* Uploaded images preview styles */
        .uploaded-previews {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .uploaded-image-preview {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }

        .uploaded-image-preview img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }

        .uploaded-image-preview .image-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .uploaded-image-preview .image-name {
            font-size: 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .uploaded-image-preview .remove-uploaded {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }

        .uploaded-image-preview .remove-uploaded:hover {
            background: #dc3545;
            border-color: #dc3545;
        }

        /* Images summary styles */
        .images-summary {
            background: #e8f5e8;
            border: 1px solid #c3e6c3;
            border-radius: 8px;
            padding: 1rem;
        }

        .images-summary h5 {
            margin-bottom: 0.5rem;
            color: #155724;
        }

        .summary-stats {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .summary-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #155724;
        }

        .summary-item i {
            color: #28a745;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .demo-images-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
            
            .uploaded-previews {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
            
            .summary-stats {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        /* Feature Preview Styles */
        .feature-preview {
            background: #e8f4fd;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        /* Concise features: hide preview and manual inputs UI */
        #feature-preview { display: none !important; }
        .feature-inputs .feature-input { display: none !important; }

        .preview-categories {
            margin-top: 0.5rem;
        }

        .preview-category {
            margin-bottom: 0.75rem;
            padding: 0.5rem;
            background: white;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        .preview-category-title {
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .preview-features .badge {
            font-size: 0.75rem;
            background: #667eea !important;
        }

        /* Highlight active suggestion tag */
        .suggestion-tag.active {
            background: #667eea !important;
            color: #fff !important;
            border: 1px solid #667eea;
        }
        
        /* Free User Limit Reached Styles */
        .limit-reached-container {
            max-width: 800px;
            margin: 2rem auto;
        }
        
        .limit-stat {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #f39c12;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .limit-options {
            margin-top: 2rem;
        }
        
        .option-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            text-align: center;
            height: 100%;
            transition: all 0.3s ease;
        }
        
        .option-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .option-card h6 {
            color: #2c3e50;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }
        
        .option-card p {
            color: #6c757d;
            margin-bottom: 0;
        }
        
        /* Advanced Scheduling Styles */
        .scheduling-options {
            margin-top: 1.5rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .scheduling-options:hover {
            border-color: #667eea;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.15);
        }
        
        .scheduling-card {
            width: 100%;
            border: none;
            background: transparent;
        }
        
        .scheduling-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            margin: 0;
            border: none;
        }
        
        .scheduling-header h6 {
            margin: 0;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .scheduling-header i {
            font-size: 1rem;
            color: white;
        }
        
        .scheduling-body {
            padding: 1.5rem;
            background: white;
        }
        
        .scheduling-checkbox-wrapper {
            margin-bottom: 1.5rem;
        }
        
        .custom-checkbox {
            position: relative;
            display: block;
        }
        
        .custom-checkbox-input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .custom-checkbox-label {
            display: flex;
            align-items: flex-start;
            cursor: pointer;
            padding: 1.25rem;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s ease;
            gap: 1rem;
            min-height: auto;
        }
        
        .custom-checkbox-label:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f4fd 100%);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
        }
        
        .custom-checkbox-input:checked + .custom-checkbox-label {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f4fd 100%);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .checkbox-indicator {
            width: 22px;
            height: 22px;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            position: relative;
            transition: all 0.3s ease;
            flex-shrink: 0;
            margin-top: 1px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .custom-checkbox-input:checked + .custom-checkbox-label .checkbox-indicator {
            background: #667eea;
            border-color: #667eea;
            transform: scale(1.1);
        }
        
        .custom-checkbox-input:checked + .custom-checkbox-label .checkbox-indicator::after {
            content: '✓';
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 14px;
            font-weight: bold;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        .checkbox-content {
            flex: 1;
            min-width: 0;
        }
        
        .checkbox-title {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            line-height: 1.3;
            letter-spacing: -0.5px;
        }
        
        .checkbox-subtitle {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 0;
            font-weight: 400;
        }
        
        .schedule-time-section {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #e9ecef;
            border-radius: 12px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .time-settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            align-items: start;
        }
        
        .time-input-group {
            display: flex;
            flex-direction: column;
        }
        
        .time-label {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.75rem;
            font-size: 1rem;
            letter-spacing: -0.5px;
        }
        
        .time-select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
            font-weight: 500;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .time-select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
            transform: translateY(-1px);
        }
        
        .status-display-group {
            display: flex;
            flex-direction: column;
        }
        
        .status-label {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.75rem;
            font-size: 1rem;
            letter-spacing: -0.5px;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #ffc107;
            border-radius: 10px;
            color: #856404;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.2);
            transition: all 0.3s ease;
        }
        
        .status-indicator:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }
        
        .status-indicator i {
            font-size: 1rem;
            color: #856404;
            flex-shrink: 0;
        }
        
        .status-indicator span {
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: -0.3px;
        }
        
        /* Mobile responsiveness for scheduling options */
        @media (max-width: 768px) {
            .time-settings-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .scheduling-body {
                padding: 1rem;
            }
            
            .custom-checkbox-label {
                padding: 1rem;
                gap: 0.75rem;
            }
            
            .checkbox-title {
                font-size: 1rem;
            }
            
            .checkbox-subtitle {
                font-size: 0.85rem;
            }
        }
        
        .availability-info {
            margin-top: 1rem;
        }
        
        .availability-info .alert {
            margin-bottom: 0;
            border-radius: 8px;
        }
        
        .availability-info .alert .d-flex {
            align-items: center;
        }
        
        .availability-info .alert i {
            flex-shrink: 0;
        }
        
        /* Schedule Button Styles */
        .scheduling-button-wrapper {
            margin-bottom: 1.5rem;
        }
        
        .schedule-button {
            width: 100%;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
            position: relative;
            overflow: hidden;
        }
        
        .schedule-button:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f4fd 100%);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
        }
        
        .schedule-button.active {
            border-color: #28a745;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }
        
        .schedule-button.animate-success {
            animation: successPulse 0.6s ease-out;
        }
        
        @keyframes successPulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4);
            }
            50% {
                transform: scale(1.02);
                box-shadow: 0 0 0 10px rgba(40, 167, 69, 0.1);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 20px rgba(40, 167, 69, 0);
            }
        }
        
        .button-content {
            flex: 1;
            min-width: 0;
        }
        
        .button-title {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            line-height: 1.3;
            letter-spacing: -0.5px;
        }
        
        .button-subtitle {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 0;
            font-weight: 400;
        }
        
        .button-indicator {
            width: 32px;
            height: 32px;
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .schedule-button.active .button-indicator {
            background: #28a745;
            border-color: #28a745;
            color: white;
            transform: scale(1.1);
        }
        
        .button-indicator i {
            font-size: 14px;
            color: #6c757d;
            transition: all 0.3s ease;
        }
        
        .schedule-button.active .button-indicator i {
            color: white;
            transform: scale(1.2);
        }
        
        @media (max-width: 768px) {
            .limit-reached-container {
                margin: 1rem;
            }
            
            .option-card {
                margin-bottom: 1rem;
            }
            
            .scheduling-options .card-body {
                padding: 1rem;
            }
            
            .schedule-time-section .row {
                margin: 0;
            }
            
            .schedule-time-section .col-md-6 {
                padding: 0.5rem 0;
            }
            
            .schedule-button {
                padding: 1rem;
            }
            
            .button-title {
                font-size: 1rem;
            }
            
            .button-subtitle {
                font-size: 0.85rem;
            }
            
            .button-indicator {
                width: 28px;
                height: 28px;
            }
            
            .button-indicator i {
                font-size: 12px;
            }
        }
    </style>

<?php
// Use shared footer scripts for Choices.js
$includeChoicesJS = true;
require_once 'includes/shared-footer-scripts.php';
require_once 'includes/footer.php';
?>
