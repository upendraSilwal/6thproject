<?php
session_start();
require_once 'config/database.php';
require_once 'config/property_utils.php';

// Check if user is logged in (admin or property owner)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$property_id = intval($_GET['id'] ?? 0);
if (!$property_id) {
    header('Location: index.php');
    exit();
}

// Get property details
$stmt = $pdo->prepare("
    SELECT p.*, u.first_name, u.last_name, u.email as user_email
    FROM properties p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$property_id]);
$property = $stmt->fetch();

if (!$property) {
    header('Location: index.php');
    exit();
}

// Check if user has permission to edit this property
$isAdmin = isset($_SESSION['admin_id']);
$isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $property['user_id'];

if (!$isAdmin && !$isOwner) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'features' => $_POST['features'] ?? []
    ];
    
    // Use centralized validation
    $errors = validatePropertyData($propertyData, $propertyData['property_type']);
    
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update property
            $stmt = $pdo->prepare("
                UPDATE properties SET 
                title = ?, description = ?, property_type = ?, listing_type = ?, 
                price = ?, location = ?, city = ?, area_sqft = ?, bedrooms = ?, 
                bathrooms = ?, parking_spaces = ?, furnished = ?, available_from = ?, 
                contact_phone = ?, contact_email = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $propertyData['title'], $propertyData['description'], $propertyData['property_type'],
                $propertyData['listing_type'], $propertyData['price'], $propertyData['location'],
                $propertyData['city'], $propertyData['area_sqft'], $propertyData['bedrooms'],
                $propertyData['bathrooms'], $propertyData['parking_spaces'], $propertyData['furnished'],
                $propertyData['available_from'], $propertyData['contact_phone'], $propertyData['contact_email'],
                $property_id
            ]);
            
            // Update features
            $stmt = $pdo->prepare("DELETE FROM property_features WHERE property_id = ?");
            $stmt->execute([$property_id]);
            
            if (!empty($propertyData['features'])) {
                $stmt = $pdo->prepare("INSERT INTO property_features (property_id, feature_name) VALUES (?, ?)");
                foreach ($propertyData['features'] as $feature) {
                    if (!empty(trim($feature))) {
                        $stmt->execute([$property_id, trim($feature)]);
                    }
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            $success = 'Property updated successfully!';
            
            // Refresh property data
            $stmt = $pdo->prepare("
                SELECT p.*, u.first_name, u.last_name, u.email as user_email
                FROM properties p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$property_id]);
            $property = $stmt->fetch();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'An error occurred while updating the property. Please try again. Error: ' . $e->getMessage();
        }
    } else {
        $error = implode(' ', $errors);
    }
}

// Get current features
$features = getPropertyFeatures($pdo, $property_id);

// Enhanced feature suggestions based on our algorithm categories - use property-specific suggestions
$featureSuggestions = getFeatureSuggestions($property['property_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Property - Urban Oasis</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container">
        <div class="edit-property-container">
            <div class="edit-property-header">
                <h1><i class="fas fa-edit"></i> Edit Property</h1>
                <p>Update property details and features</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="edit-property-form">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Property Title *</label>
                            <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($property['title']); ?>" placeholder="e.g., Modern 3BHK Apartment in Kathmandu">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description *</label>
                            <textarea id="description" name="description" rows="4" required placeholder="Describe your property in detail: amenities, location benefits, nearby facilities, transportation access, neighborhood highlights, and any unique features that make this property special."><?php echo htmlspecialchars($property['description']); ?></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="property_type">Property Type *</label>
                            <select id="property_type" name="property_type" required>
                                <option value="">Select Property Type</option>
                                <option value="house" <?php echo $property['property_type'] === 'house' ? 'selected' : ''; ?>><?php echo getPropertyTypeDisplay('house'); ?></option>
                                <option value="apartment" <?php echo $property['property_type'] === 'apartment' ? 'selected' : ''; ?>><?php echo getPropertyTypeDisplay('apartment'); ?></option>
                                <option value="room" <?php echo $property['property_type'] === 'room' ? 'selected' : ''; ?>><?php echo getPropertyTypeDisplay('room'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="listing_type">Listing Type *</label>
                            <select id="listing_type" name="listing_type" required>
                                <option value="">Select Listing Type</option>
                                <option value="rent" <?php echo $property['listing_type'] === 'rent' ? 'selected' : ''; ?>><?php echo getListingTypeDisplay('rent'); ?></option>
                                <option value="sale" <?php echo $property['listing_type'] === 'sale' ? 'selected' : ''; ?>><?php echo getListingTypeDisplay('sale'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Price (NPR) *</label>
                            <input type="number" id="price" name="price" min="1" required value="<?php echo htmlspecialchars($property['price']); ?>" placeholder="e.g., 25000">
                        </div>
                        
                        <div class="form-group">
                            <label for="area_sqft">Area (sq ft) *</label>
                            <div class="input-with-index">
                                <input type="number" id="area_sqft" name="area_sqft" min="1" step="0.01" required value="<?php echo htmlspecialchars($property['area_sqft']); ?>" placeholder="e.g., 1200">
                                <span id="area-index" class="input-index"></span>
                            </div>
                            <small class="text-muted" id="area-hint"></small>
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
                                <option value="<?php echo htmlspecialchars($district); ?>" <?php echo $property['city'] === $district ? 'selected' : ''; ?>><?php echo htmlspecialchars($district); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Location/Address *</label>
                            <input type="text" id="location" name="location" required value="<?php echo htmlspecialchars($property['location']); ?>" placeholder="e.g., Baneshwor, Near City Center">
                        </div>
                    </div>
                </div>

                <!-- Property Details -->
                <div class="form-section">
                    <h3><i class="fas fa-home"></i> Property Details</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bedrooms">Bedrooms</label>
                            <input type="number" id="bedrooms" name="bedrooms" min="1" max="10" value="<?php echo htmlspecialchars($property['bedrooms']); ?>" placeholder="e.g., 3">
                            <small class="text-muted">Not required for rooms</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="bathrooms">Bathrooms</label>
                            <input type="number" id="bathrooms" name="bathrooms" min="1" max="10" value="<?php echo htmlspecialchars($property['bathrooms']); ?>" placeholder="e.g., 2">
                            <small class="text-muted">Not required for rooms</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="parking_spaces">Parking Spaces</label>
                            <input type="number" id="parking_spaces" name="parking_spaces" min="0" max="10" value="<?php echo htmlspecialchars($property['parking_spaces']); ?>" placeholder="e.g., 1">
                        </div>
                    </div>

                    <div class="form-row" id="floors-row" style="display: <?php echo $property['property_type']==='house' ? 'grid' : 'none'; ?>;">
                        <div class="form-group">
                            <label for="floors">Number of Floors (storeys)</label>
                            <input type="number" id="floors" name="floors" min="1" max="6" value="<?php echo htmlspecialchars($property['floors'] ?? ''); ?>" placeholder="e.g., 3">
                            <small class="text-muted">Shown for houses only</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="available_from">Available From</label>
                            <input type="date" id="available_from" name="available_from" value="<?php echo htmlspecialchars($property['available_from']); ?>">
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="furnished" value="1" <?php echo $property['furnished'] ? 'checked' : ''; ?>>
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
                            <input type="tel" id="contact_phone" name="contact_phone" required value="<?php echo htmlspecialchars($property['contact_phone']); ?>" placeholder="e.g., 9800000000">
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_email">Contact Email *</label>
                            <input type="email" id="contact_email" name="contact_email" required value="<?php echo htmlspecialchars($property['contact_email']); ?>" placeholder="e.g., contact@example.com">
                        </div>
                    </div>
                </div>

                <!-- Enhanced Features & Amenities -->
                <div class="form-section">
                    <h3><i class="fas fa-thumbs-up"></i> Features & Amenities</h3>
                    
                    <div class="features-container">
                        <div class="feature-inputs">
                            <?php foreach ($features as $index => $feature): ?>
                            <div class="feature-input">
                                <input type="text" name="features[]" value="<?php echo htmlspecialchars($feature); ?>" placeholder="Add a feature (e.g., WiFi, Garden, Security, etc.)">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-feature">-</button>
                            </div>
                            <?php endforeach; ?>
                            <div class="feature-input">
                                <input type="text" name="features[]" placeholder="Add a feature (e.g., WiFi, Garden, Security, etc.)">
                                <button type="button" class="btn btn-sm btn-outline-primary add-feature">+</button>
                            </div>
                        </div>
                        <small>Click + to add more features. Our smart algorithm will automatically categorize your features for better display and user experience.</small>
                        
                        <!-- Feature Preview (shows how features will be categorized) -->
                        <div id="feature-preview" class="feature-preview mt-3" style="display: none;">
                            <h6><i class="fas fa-eye"></i> How your features will be displayed:</h6>
                            <div id="preview-categories" class="preview-categories"></div>
                        </div>
                        
                        <!-- Enhanced Feature Suggestions by Category -->
                        <div class="feature-suggestions mt-3">
                            <h6><i class="fas fa-lightbulb"></i> Suggested Features by Category:</h6>
                            <p class="text-muted small mb-2">Click any feature below to add it. Our algorithm will automatically organize them into categories for better presentation.</p>
                            
                            <?php foreach ($featureSuggestions as $category => $categoryFeatures): ?>
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
                                    <?php foreach ($categoryFeatures as $feature): ?>
                                    <span class="badge bg-light text-dark me-2 mb-2 suggestion-tag" data-feature="<?php echo htmlspecialchars($feature); ?>"><?php echo htmlspecialchars($feature); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">
                        <i class="fas fa-save"></i>
                        Update Property
                    </button>
                    <a href="property-details.php?id=<?php echo $property_id; ?>" class="btn btn-secondary">View Property</a>
                    <?php if ($isAdmin): ?>
                        <a href="admin/properties.php" class="btn btn-outline-secondary">Back to Admin</a>
                    <?php else: ?>
                        <a href="my-properties.php" class="btn btn-outline-secondary">Back to My Properties</a>
                    <?php endif; ?>
                </div>

                <!-- Price Suggestion Widget (moved to end, hidden until ready) -->
                <div id="price-suggestion-widget" class="form-section" style="display:none;">
                    <h3><i class="fas fa-magic"></i> Smart Price Suggestion</h3>
                    <div class="form-row">
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
            </form>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script>
        // Initialize Choices.js for select elements
        const selects = document.querySelectorAll('select');
        const choicesInstances = {};
        
        selects.forEach(select => {
            choicesInstances[select.id] = new Choices(select, {
                searchEnabled: false,
                itemSelectText: ''
            });
        });

        // Dynamic feature inputs and form logic
        document.addEventListener('DOMContentLoaded', function() {
            const addFeatureBtn = document.querySelector('.add-feature');
            const featureInputs = document.querySelector('.feature-inputs');
            const propertyTypeSelect = document.getElementById('property_type');
            const listingTypeSelect = document.getElementById('listing_type');
            const bedroomsInput = document.getElementById('bedrooms');
            const bathroomsInput = document.getElementById('bathrooms');
            const citySelect = document.getElementById('city');
            const priceInput = document.getElementById('price');
            const floorsRow = document.getElementById('floors-row');
            const floorsInput = document.getElementById('floors');
            const btnFetch = document.getElementById('btn-fetch-suggested-price');
            const btnUse = document.getElementById('btn-use-suggested-price');
            const badge = document.getElementById('price-suggestion-badge');
            const note = document.getElementById('price-suggestion-note');
            const suggestionSection = document.getElementById('price-suggestion-widget');

            // Handle property type change
            propertyTypeSelect.addEventListener('change', function() {
                const selectedType = this.value;
                const saleOption = Array.from(listingTypeSelect.options).find(opt => opt.value === 'sale');
                
                if (selectedType === 'room') {
                    // For rooms, only allow rent
                    listingTypeSelect.value = 'rent';
                    if (saleOption) saleOption.style.display = 'none';
                    listingTypeSelect.disabled = false;
                    bedroomsInput.required = false;
                    bathroomsInput.required = false;
                    bedroomsInput.placeholder = 'Optional for rooms';
                    bathroomsInput.placeholder = 'Optional for rooms';
                    if (floorsRow) floorsRow.style.display = 'none';
                } else {
                    // For houses and apartments, allow both rent and sale
                    if (saleOption) saleOption.style.display = '';
                    listingTypeSelect.disabled = false;
                    bedroomsInput.required = true;
                    bathroomsInput.required = true;
                    bedroomsInput.placeholder = 'e.g., 3';
                    bathroomsInput.placeholder = 'e.g., 2';
                    if (floorsRow) floorsRow.style.display = selectedType === 'house' ? 'grid' : 'none';
                }

                // Update area constraints hint
                updateAreaConstraints(selectedType);
            });

            // Initialize area constraints hint
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
                areaInput.style.paddingRight = indexEl && indexEl.textContent ? '80px' : '';
                checkAreaValidity();
            }

            const areaInputEl = document.getElementById('area_sqft');
            function checkAreaValidity() {
                const min = parseFloat(areaInputEl.dataset.min || areaInputEl.min || '0');
                const max = parseFloat(areaInputEl.dataset.max || areaInputEl.max || '0');
                const val = parseFloat(areaInputEl.value || '0');
                if (!isNaN(val) && (val < min || val > max)) {
                    areaInputEl.setCustomValidity(`Enter between ${min} and ${max}`);
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
            });

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
                });

                // Update preview
                window.updateFeaturePreview();
            });

            // Remove feature functionality for existing features
            document.querySelectorAll('.remove-feature').forEach(btn => {
                btn.addEventListener('click', function() {
                    this.parentElement.remove();
                    window.updateFeaturePreview();
                });
            });

            // Enhanced feature suggestion tags
            document.querySelectorAll('.suggestion-tag').forEach(tag => {
                tag.addEventListener('click', function() {
                    const feature = this.getAttribute('data-feature');
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
                        clearSuggestion();
                    });

                    // Update preview
                    window.updateFeaturePreview();
                });
            });

            // Add event listeners for feature input changes
            document.addEventListener('input', function(e) {
                if (e.target.name === 'features[]') {
                    window.updateFeaturePreview();
                    clearSuggestion();
                }
            });

            // Initialize feature preview
            window.updateFeaturePreview();

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
            evaluateSuggestionVisibility();
            // Ensure floors visibility reflects current type on load
            if (floorsRow) {
                floorsRow.style.display = propertyTypeSelect.value === 'house' ? 'grid' : 'none';
            }
            [propertyTypeSelect, listingTypeSelect, citySelect].forEach(el => {
                el && el.addEventListener('change', evaluateSuggestionVisibility);
            });
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
        .edit-property-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .edit-property-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .edit-property-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.8rem;
        }

        .edit-property-header p {
            margin: 0;
            opacity: 0.9;
        }

        .edit-property-form {
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

        /* Feature Preview Styles */
        .feature-preview {
            background: #e8f4fd;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

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

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html> 