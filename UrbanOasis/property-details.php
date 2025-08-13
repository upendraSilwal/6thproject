<?php
session_start();
$pageTitle = "Property Details";
require_once 'includes/header.php';
require_once 'config/images.php';
require_once 'config/property_utils.php';

// Get property details
$propertyId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$propertyId) {
    header("Location: properties.php");
    exit();
}

// Get property details - only show active, non-expired, approved properties
$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ? AND approval_status = 'approved' AND is_active = 1 AND expires_at > NOW()");
$stmt->execute([$propertyId]);
$property = $stmt->fetch();

if (!$property) {
    header("Location: properties.php");
    exit();
}

// Get property features using utility function
$features = getPropertyFeatures($pdo, $propertyId);

// Calculate Quality Score using property-type-specific algorithm
$qualityScore = calculateQualityScore($features, $property['property_type']);

// Get user info if property has an owner
$owner = null;
if ($property['user_id']) {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$property['user_id']]);
    $owner = $stmt->fetch();
}

// Get multiple property images
$stmt = $pdo->prepare("SELECT image_url, image_type, display_order, is_primary FROM property_images WHERE property_id = ? ORDER BY display_order ASC, id ASC");
$stmt->execute([$propertyId]);
$propertyImages = $stmt->fetchAll();

// If no images in property_images table, fall back to single image
if (empty($propertyImages) && !empty($property['image_url'])) {
    $propertyImages = [[
        'image_url' => $property['image_url'],
        'image_type' => 'uploaded',
        'display_order' => 0,
        'is_primary' => 1
    ]];
}

// Use centralized functions
$categorizedFeatures = categorizeFeatures($features);
$propertyStats = calculatePropertyStats($property);
$imageUrl = getPropertyImageUrl($property);
?>

<!-- Property Details Section -->
<section class="py-5">
    <div class="container">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['inquiry_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['inquiry_success']; unset($_SESSION['inquiry_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['inquiry_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['inquiry_error']; unset($_SESSION['inquiry_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Property Images -->
            <div class="col-lg-8">
                <div class="property-gallery-container mb-4">
                    <?php if (!empty($propertyImages)): ?>
                        <!-- Professional Image Gallery -->
                        <div class="property-gallery">
                            <!-- Main Image Display -->
                            <div class="main-image-section">
                                <div class="main-image-wrapper">
                                    <div class="image-slider" id="imageSlider">
                                        <?php foreach ($propertyImages as $index => $image): ?>
                                        <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>">
                                            <img src="<?php echo getImageUrl($image['image_url'], $image['image_type']); ?>" 
                                                 class="slide-image" 
                                                 alt="<?php echo htmlspecialchars($property['title']); ?> - Image <?php echo $index + 1; ?>"
                                                 onerror="this.src='<?php echo getRandomPropertyImage($property['property_type']); ?>'">
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Navigation Controls -->
                                    <?php if (count($propertyImages) > 1): ?>
                                    <button class="gallery-nav nav-prev" onclick="navigateSlide(-1)" id="prevBtn">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <button class="gallery-nav nav-next" onclick="navigateSlide(1)" id="nextBtn">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <!-- Image Counter -->
                                    <?php if (count($propertyImages) > 1): ?>
                                    <div class="image-counter">
                                        <span id="currentSlide">1</span> / <?php echo count($propertyImages); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Fullscreen Button -->
                                    <button class="fullscreen-btn" onclick="openLightbox(0)" title="View Fullscreen">
                                        <i class="fas fa-expand"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Thumbnail Gallery -->
                            <?php if (count($propertyImages) > 1): ?>
                            <div class="thumbnail-gallery">
                                <div class="thumbnail-track" id="thumbnailTrack">
                                    <?php foreach ($propertyImages as $index => $image): ?>
                                    <div class="thumbnail-slide <?php echo $index === 0 ? 'active' : ''; ?>" 
                                         data-index="<?php echo $index; ?>"
                                         onclick="goToSlide(<?php echo $index; ?>)">
                                        <img src="<?php echo getImageUrl($image['image_url'], $image['image_type']); ?>" 
                                             alt="Thumbnail <?php echo $index + 1; ?>"
                                             onerror="this.src='<?php echo getRandomPropertyImage($property['property_type']); ?>'">
                                        <div class="thumbnail-overlay"></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Thumbnail Navigation -->
                                <?php if (count($propertyImages) > 5): ?>
                                <button class="thumbnail-nav nav-left" onclick="scrollThumbnails('left')" id="thumbNavLeft">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button class="thumbnail-nav nav-right" onclick="scrollThumbnails('right')" id="thumbNavRight">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Fallback Single Image -->
                        <div class="property-gallery">
                            <div class="main-image-section">
                                <div class="main-image-wrapper">
                                    <div class="image-slider">
                                        <div class="slide active">
                                            <img src="<?php echo $imageUrl; ?>" 
                                                 class="slide-image" 
                                                 alt="<?php echo htmlspecialchars($property['title']); ?>"
                                                 onerror="this.src='<?php echo getRandomPropertyImage($property['property_type']); ?>'">
                                        </div>
                                    </div>
                                    <button class="fullscreen-btn" onclick="openLightbox(0)" title="View Fullscreen">
                                        <i class="fas fa-expand"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Property Information -->
                <div class="property-info">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h1 class="h2 mb-2"><?php echo htmlspecialchars($property['title']); ?></h1>
                            <p class="text-muted mb-0">
                                <i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($property['location']); ?>, <?php echo htmlspecialchars($property['city']); ?>
                            </p>
                        </div>
                        <div class="text-end">
                            <div class="property-price h3 text-primary mb-1">
                                <?php echo formatPropertyPrice($property['price'], 'NPR ', $property['property_type'], $property['listing_type']); ?>
                                <small class="text-muted d-block"><?php echo getListingTypeDisplay($property['listing_type']); ?></small>
                            </div>
                            <div class="badge bg-primary me-2"><?php echo getPropertyTypeDisplay($property['property_type']); ?></div>
                        </div>
                    </div>

<!-- Inquiry Count -->
                    <div class="mb-3">
                        <span class="badge bg-info">
                            <?php echo $property['inquiry_count']; ?> people interested
                        </span>
                    </div>

                    <!-- Property Stats -->
                    <div class="property-stats mb-4">
                        <div class="row text-center">
                            <?php if ($property['property_type'] !== 'room'): ?>
                            <div class="col-3">
                                <div class="stat-item">
                                    <i class="fas fa-bed fa-2x text-primary mb-2"></i>
                                    <div class="stat-value"><?php echo $property['bedrooms']; ?></div>
                                    <div class="stat-label">Bedrooms</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="stat-item">
                                    <i class="fas fa-bath fa-2x text-primary mb-2"></i>
                                    <div class="stat-value"><?php echo $property['bathrooms']; ?></div>
                                    <div class="stat-label">Bathrooms</div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="col-<?php echo $property['property_type'] === 'room' ? '6' : '3'; ?>">
                                <div class="stat-item">
                                    <i class="fas fa-ruler-combined fa-2x text-primary mb-2"></i>
                                    <div class="stat-value"><?php echo isset($property['area_sqft']) ? $property['area_sqft'] : 'N/A'; ?></div>
                                    <div class="stat-label">Sq Ft</div>
                                </div>
                            </div>
                            <div class="col-<?php echo $property['property_type'] === 'room' ? '6' : '3'; ?>">
                                <div class="stat-item">
                                    <i class="fas fa-car fa-2x text-primary mb-2"></i>
                                    <div class="stat-value"><?php echo isset($property['parking_spaces']) ? $property['parking_spaces'] : 'N/A'; ?></div>
                                    <div class="stat-label">Parking</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Property Description -->
                    <div class="property-description mb-4">
                        <h4><i class="fas fa-info-circle me-2"></i>Description</h4>
                        <p><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
                    </div>

                    <!-- Property Features & Amenities -->
                    <?php if (!empty($features)): ?>
                    <div class="property-features mb-4">
                        <h4><i class="fas fa-thumbs-up me-2"></i>Features & Amenities (<?php echo count($features); ?> total)</h4>
                        
                        <?php foreach ($categorizedFeatures as $category => $categoryFeatures): ?>
                            <?php if (!empty($categoryFeatures)): ?>
                            <div class="feature-category mb-3">
                                <h6 class="text-primary mb-2">
                                    <?php 
                                    switch($category) {
                                        case 'essential': echo '<i class="fas fa-shield-alt me-1"></i>Essential Amenities'; break;
                                        case 'comfort': echo '<i class="fas fa-couch me-1"></i>Comfort Features'; break;
                                        case 'luxury': echo '<i class="fas fa-crown me-1"></i>Luxury Amenities'; break;
                                        case 'convenience': echo '<i class="fas fa-map-marker-alt me-1"></i>Location Benefits'; break;
                                        case 'safety': echo '<i class="fas fa-shield-alt me-1"></i>Safety Features'; break;
                                        case 'other': echo '<i class="fas fa-plus me-1"></i>Additional Features'; break;
                                    }
                                    ?>
                                    <span class="badge bg-secondary ms-2"><?php echo count($categoryFeatures); ?></span>
                                </h6>
                                <div class="feature-tags">
                                    <?php foreach ($categoryFeatures as $feature): ?>
                                    <span class="badge bg-light text-dark me-2 mb-2"><?php echo htmlspecialchars($feature); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Property Details -->
                    <div class="property-details mb-4">
                        <h4><i class="fas fa-home me-2"></i>Property Details</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Property Type:</strong></td>
                                        <td><?php echo getPropertyTypeDisplay($property['property_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Listing Type:</strong></td>
                                        <td><?php echo getListingTypeDisplay($property['listing_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>City:</strong></td>
                                        <td><?php echo htmlspecialchars($property['city']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Furnished:</strong></td>
                                        <td>
                                            <?php if (isset($property['furnished'])): ?>
                                                <span class="badge <?php echo $property['furnished'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $property['furnished'] ? 'Yes' : 'No'; ?>
                                                </span>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if (!empty($property['available_from'])): ?>
                                    <tr>
                                        <td><strong>Available From:</strong></td>
                                        <td><?php echo date('M d, Y', strtotime($property['available_from'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($property['construction_date']) && $property['property_type'] !== 'room' && $property['construction_date'] !== '0000-00-00'): ?>
                                    <tr>
                                        <td><strong>Construction Date:</strong></td>
                                        <td><?php echo date('M d, Y', strtotime($property['construction_date'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Listed On:</strong></td>
                                        <td><?php echo date('M d, Y', strtotime($property['created_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Last Updated:</strong></td>
                                        <td><?php echo date('M d, Y', strtotime($property['updated_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Features:</strong></td>
                                        <td><?php echo count($features); ?> amenities</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Availability Calendar -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Availability</h5>
                    </div>
                    <div class="card-body">
                        <div class="availability-section">
                            <?php 
                            $available_date = $property['available_from'];
                            
                            // Get today's date at midnight for proper comparison
                            $today_start = strtotime('today');
                            $available_timestamp = !empty($available_date) ? strtotime($available_date) : null;
                            
                            // Determine availability status
                            $is_available_now = empty($available_date) || $available_timestamp <= $today_start;
                            $is_future_date = !empty($available_date) && $available_timestamp > $today_start;
                            ?>
                            
                            <?php if ($is_available_now): ?>
                                <div class="availability-status available">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Available Now</strong>
                                    <?php if (!empty($available_date)): ?>
                                        <small class="d-block text-muted">Since <?php echo date('M d, Y', strtotime($available_date)); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($is_future_date): ?>
                                <div class="availability-status scheduled">
                                    <i class="fas fa-clock me-2"></i>
                                    <strong>Available From <?php echo date('M d, Y', strtotime($available_date)); ?></strong>
                                </div>
                            <?php else: ?>
                                <div class="availability-status pending">
                                    <i class="fas fa-question-circle me-2"></i>
                                    <strong>Availability TBD</strong>
                                    <small class="d-block text-muted">Contact owner for availability details</small>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Property Availability Info -->
                            <?php if ($is_future_date): ?>
                                <div class="availability-info mt-3">
                                    <h6><i class="fas fa-info-circle me-2"></i>Availability Information</h6>
                                    <div class="info-container">
                                        <div class="availability-details">
                                            <p class="mb-2"><strong>This property will be available from:</strong></p>
                                            <div class="availability-date-display">
                                                <i class="fas fa-calendar-alt me-2"></i>
                                                <?php echo date('l, F j, Y', strtotime($available_date)); ?>
                                            </div>
                                            <small class="text-muted d-block mt-2">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php 
                                                $days_until = floor(($available_timestamp - $today_start) / (60*60*24));
                                                if ($days_until > 0) {
                                                    echo $days_until . ' days from now';
                                                } else {
                                                    echo 'Available today';
                                                }
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!$isLoggedIn || $_SESSION['user_id'] != $property['user_id']): ?>
                <!-- Contact Owner -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Contact Owner</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($owner): ?>
                            <p><strong>Listed by:</strong><br>
                            <?php echo htmlspecialchars($owner['first_name'] . ' ' . $owner['last_name']); ?></p>
                        <?php endif; ?>
				<?php if ($isLoggedIn): ?>
					<button type="button" class="btn btn-primary w-100" onclick="console.log('Button clicked!'); openInquiryModal();">
						<i class="fas fa-paper-plane me-2"></i>Send Inquiry
					</button>
				<?php else: ?>
					<button type="button" class="btn btn-primary w-100" onclick="redirectToLogin();">
						<i class="fas fa-sign-in-alt me-2"></i>Login to Send Inquiry
					</button>
				<?php endif; ?>
                        <small class="d-block text-center mt-2 text-muted">Contact details are shared securely after you send an inquiry.</small>
                    </div>
                </div>
                <?php else: ?>
                <!-- Owner's Own Property -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-check me-2"></i>Your Property</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                            <i class="fas fa-home fa-3x text-primary mb-3"></i>
                            <h6 class="mb-2">This is your property listing</h6>
                            <p class="text-muted mb-3">You can manage this property from your dashboard.</p>
                            <a href="my-properties.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-cog me-2"></i>Manage Property
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Property Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="quick-stats">
                            <div class="stat-row d-flex justify-content-between mb-2">
                                <span>Price per sq ft:</span>
                                <strong>Rs. <?php echo $propertyStats['price_per_sqft']; ?></strong>
                            </div>
                            <div class="stat-row d-flex justify-content-between mb-2">
                                <span>Quality Score:</span>
                                <div class="text-end">
                                    <?php echo generateQualityScoreHTML($qualityScore, 'medium'); ?>
                                </div>
                            </div>
                            <div class="stat-row d-flex justify-content-between">
                                <span>Days listed:</span>
                                <strong><?php echo $propertyStats['days_listed']; ?> days</strong>
                            </div>
                            <div class="stat-row d-flex justify-content-between">
                                <span>AI Predicted Price:</span>
                                <strong id="predicted-price"></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    /* Professional Property Gallery Styles */
    .property-gallery-container {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
    
    .property-gallery {
        position: relative;
    }
    
    /* Main Image Section */
    .main-image-section {
        position: relative;
        background: #f8f9fa;
    }
    
    .main-image-wrapper {
        position: relative;
        width: 100%;
        height: 500px;
        overflow: hidden;
        background: #000;
    }
    
    .image-slider {
        position: relative;
        width: 100%;
        height: 100%;
    }
    
    .slide {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        transform: translateX(30px);
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1;
    }
    
    .slide.active {
        opacity: 1;
        transform: translateX(0);
        z-index: 2;
    }
    
    .slide-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        user-select: none;
        pointer-events: none;
    }
    
    /* Navigation Controls */
    .gallery-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 48px;
        height: 48px;
        background: rgba(255, 255, 255, 0.95);
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 10;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        backdrop-filter: blur(10px);
    }
    
    .gallery-nav:hover {
        background: rgba(255, 255, 255, 1);
        transform: translateY(-50%) scale(1.1);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
    }
    
    .gallery-nav i {
        color: #333;
        font-size: 18px;
    }
    
    .nav-prev {
        left: 20px;
    }
    
    .nav-next {
        right: 20px;
    }
    
    /* Image Counter */
    .image-counter {
        position: absolute;
        bottom: 20px;
        right: 20px;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 500;
        backdrop-filter: blur(10px);
        z-index: 10;
    }
    
    /* Fullscreen Button */
    .fullscreen-btn {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 40px;
        height: 40px;
        background: rgba(0, 0, 0, 0.6);
        border: none;
        border-radius: 8px;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 10;
        backdrop-filter: blur(10px);
    }
    
    .fullscreen-btn:hover {
        background: rgba(0, 0, 0, 0.8);
        transform: scale(1.05);
    }
    
    /* Thumbnail Gallery */
    .thumbnail-gallery {
        position: relative;
        padding: 20px;
        background: #fff;
        border-top: 1px solid #e9ecef;
    }
    
    .thumbnail-track {
        display: flex;
        gap: 12px;
        overflow-x: auto;
        padding: 4px;
        scroll-behavior: smooth;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    
    .thumbnail-track::-webkit-scrollbar {
        display: none;
    }
    
    .thumbnail-slide {
        position: relative;
        flex-shrink: 0;
        width: 80px;
        height: 60px;
        border-radius: 8px;
        overflow: hidden;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    
    .thumbnail-slide:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }
    
    .thumbnail-slide.active {
        border-color: #007bff;
        box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
    }
    
    .thumbnail-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        transition: transform 0.3s ease;
    }
    
    .thumbnail-slide:hover img {
        transform: scale(1.05);
    }
    
    .thumbnail-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0);
        transition: background 0.3s ease;
    }
    
    .thumbnail-slide:hover .thumbnail-overlay {
        background: rgba(0, 0, 0, 0.1);
    }
    
    /* Thumbnail Navigation */
    .thumbnail-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 32px;
        height: 32px;
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid #e9ecef;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 5;
    }
    
    .thumbnail-nav:hover {
        background: #007bff;
        color: white;
        border-color: #007bff;
    }
    
    .nav-left {
        left: 4px;
    }
    
    .nav-right {
        right: 4px;
    }
    
    /* Lightbox Modal */
    .lightbox-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.95);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    
    .lightbox-modal.show {
        display: flex;
    }
    
    .lightbox-content {
        position: relative;
        max-width: 90vw;
        max-height: 90vh;
    }
    
    .lightbox-image {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    .lightbox-close {
        position: absolute;
        top: -50px;
        right: 0;
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 10px;
    }
    
    /* Property Information Styles */
    .property-price {
        font-weight: bold;
        color: #007bff;
    }
    
    .stat-item {
        padding: 20px;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        background: #fff;
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    
    .stat-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        border-color: #007bff;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 500;
    }
    
    .feature-category {
        background: linear-gradient(135deg, #f8f9ff, #fff);
        padding: 20px;
        border-radius: 12px;
        border-left: 4px solid #007bff;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .feature-tags .badge {
        font-size: 0.85rem;
        padding: 8px 14px;
        border-radius: 20px;
        font-weight: 500;
    }
    
    .quick-stats .stat-row {
        padding: 12px 0;
        border-bottom: 1px solid #e9ecef;
        font-size: 0.95rem;
    }
    
    .quick-stats .stat-row:last-child {
        border-bottom: none;
    }
    
    /* Availability Calendar Styles */
    .availability-section {
        padding: 15px 0;
    }
    
    .availability-status {
        display: flex;
        align-items: center;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 15px;
        font-size: 16px;
    }
    
    .availability-status.available {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        border-left: 4px solid #28a745;
        color: #155724;
    }
    
    .availability-status.scheduled {
        background: linear-gradient(135deg, #fff3cd, #ffeaa7);
        border-left: 4px solid #ffc107;
        color: #856404;
    }
    
    .availability-status.pending {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        border-left: 4px solid #dc3545;
        color: #721c24;
    }
    
    .availability-date {
        font-size: 18px;
        font-weight: 700;
        margin: 5px 0;
        color: inherit;
    }
    
    .schedule-viewing {
        border-top: 1px solid #e9ecef;
        padding-top: 15px;
    }
    
    .schedule-viewing h6 {
        color: #495057;
        font-weight: 600;
        margin-bottom: 15px;
    }
    
    .viewing-calendar-container {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }
    
    .viewing-calendar-container input[type="date"],
    .viewing-calendar-container select {
        border: 1px solid #ced4da;
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 14px;
        transition: all 0.3s ease;
    }
    
    .viewing-calendar-container input[type="date"]:focus,
    .viewing-calendar-container select:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        outline: none;
    }
    
    .viewing-calendar-container button {
        font-weight: 500;
        padding: 10px;
        border-radius: 6px;
        transition: all 0.3s ease;
    }
    
    .viewing-calendar-container button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
    }
    
    /* Viewing Status Messages */
    .viewing-alert {
        margin-top: 15px;
        padding: 12px 15px;
        border-radius: 8px;
        font-size: 14px;
        display: none;
    }
    
    .viewing-alert.success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }
    
    .viewing-alert.error {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }
    
    .viewing-alert.info {
        background: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
    }
    
    /* Remove Bootstrap validation icons from form fields */
    .form-control.is-valid,
    .form-control:valid {
        background-image: none !important;
        border-color: #ced4da !important;
        padding-right: 0.75rem !important;
    }
    
    .form-control[readonly] {
        background-image: none !important;
        border-color: #ced4da !important;
        padding-right: 0.75rem !important;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .main-image-wrapper {
            height: 350px;
        }
        
        .gallery-nav {
            width: 40px;
            height: 40px;
        }
        
        .nav-prev {
            left: 10px;
        }
        
        .nav-next {
            right: 10px;
        }
        
        .thumbnail-slide {
            width: 60px;
            height: 45px;
        }
        
        .thumbnail-gallery {
            padding: 15px;
        }
        
        .availability-status {
            font-size: 14px;
            padding: 12px;
        }
        
        .availability-date {
            font-size: 16px;
        }
        
        .viewing-calendar-container {
            padding: 12px;
        }
    }
    
    @media (max-width: 576px) {
        .main-image-wrapper {
            height: 280px;
        }
        
        .thumbnail-slide {
            width: 50px;
            height: 38px;
        }
        
        .availability-status {
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
        }
        
        .availability-status i {
            margin-bottom: 5px;
        }
    }
</style>

<!-- Lightbox Modal -->
<div class="lightbox-modal" id="lightboxModal">
    <div class="lightbox-content">
        <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
        <img class="lightbox-image" id="lightboxImage" src="" alt="">
    </div>
</div>

<script>
    // Professional Property Gallery JavaScript
    class PropertyGallery {
        constructor() {
            this.currentIndex = 0;
            this.images = [];
            this.totalImages = 0;
            this.autoSlideInterval = null;
            this.isLightboxOpen = false;
            
            this.init();
        }
        
        init() {
            // Get image data from PHP
            <?php if (!empty($propertyImages)): ?>
            this.images = <?php echo json_encode(array_map(function($img) {
                return [
                    'url' => getImageUrl($img['image_url'], $img['image_type']),
                    'alt' => 'Property Image'
                ];
            }, $propertyImages)); ?>;
            <?php endif; ?>
            
            this.totalImages = this.images.length;
            
            if (this.totalImages > 0) {
                this.setupEventListeners();
                this.updateCounter();
                this.preloadImages();
            }
        }
        
        setupEventListeners() {
            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (this.isLightboxOpen) {
                    if (e.key === 'Escape') this.closeLightbox();
                    if (e.key === 'ArrowLeft') this.navigate(-1);
                    if (e.key === 'ArrowRight') this.navigate(1);
                } else {
                    if (e.key === 'ArrowLeft') this.navigate(-1);
                    if (e.key === 'ArrowRight') this.navigate(1);
                }
            });
            
            // Touch/swipe support for mobile
            this.setupSwipeNavigation();
        }
        
        setupSwipeNavigation() {
            const slider = document.getElementById('imageSlider');
            if (!slider) return;
            
            let startX = 0;
            let startY = 0;
            let moveX = 0;
            let moveY = 0;
            
            slider.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            }, { passive: true });
            
            slider.addEventListener('touchmove', (e) => {
                moveX = e.touches[0].clientX;
                moveY = e.touches[0].clientY;
            }, { passive: true });
            
            slider.addEventListener('touchend', () => {
                const deltaX = startX - moveX;
                const deltaY = startY - moveY;
                
                // Only trigger if horizontal swipe is more significant than vertical
                if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
                    if (deltaX > 0) {
                        this.navigate(1); // Swipe left - next image
                    } else {
                        this.navigate(-1); // Swipe right - previous image
                    }
                }
            }, { passive: true });
        }
        
        navigate(direction) {
            if (this.totalImages <= 1) return;
            
            this.currentIndex += direction;
            
            if (this.currentIndex >= this.totalImages) {
                this.currentIndex = 0;
            } else if (this.currentIndex < 0) {
                this.currentIndex = this.totalImages - 1;
            }
            
            this.showSlide(this.currentIndex);
        }
        
        goToSlide(index) {
            if (index < 0 || index >= this.totalImages) return;
            
            this.currentIndex = index;
            this.showSlide(this.currentIndex);
        }
        
        showSlide(index) {
            // Update main slides
            const slides = document.querySelectorAll('.slide');
            slides.forEach((slide, i) => {
                slide.classList.toggle('active', i === index);
            });
            
            // Update thumbnails
            const thumbnails = document.querySelectorAll('.thumbnail-slide');
            thumbnails.forEach((thumb, i) => {
                thumb.classList.toggle('active', i === index);
            });
            
            // Update counter
            this.updateCounter();
            
            // Auto-scroll thumbnail into view
            this.scrollThumbnailIntoView(index);
        }
        
        updateCounter() {
            const counter = document.getElementById('currentSlide');
            if (counter) {
                counter.textContent = this.currentIndex + 1;
            }
        }
        
        scrollThumbnailIntoView(index) {
            const thumbnailTrack = document.getElementById('thumbnailTrack');
            const thumbnail = document.querySelector(`[data-index="${index}"]`);
            
            if (thumbnailTrack && thumbnail) {
                const trackWidth = thumbnailTrack.offsetWidth;
                const thumbLeft = thumbnail.offsetLeft;
                const thumbWidth = thumbnail.offsetWidth;
                const currentScroll = thumbnailTrack.scrollLeft;
                
                // Check if thumbnail is not fully visible
                if (thumbLeft < currentScroll || thumbLeft + thumbWidth > currentScroll + trackWidth) {
                    thumbnail.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest',
                        inline: 'center'
                    });
                }
            }
        }
        
        scrollThumbnails(direction) {
            const track = document.getElementById('thumbnailTrack');
            if (!track) return;
            
            const scrollAmount = 200;
            const currentScroll = track.scrollLeft;
            
            if (direction === 'left') {
                track.scrollTo({
                    left: currentScroll - scrollAmount,
                    behavior: 'smooth'
                });
            } else {
                track.scrollTo({
                    left: currentScroll + scrollAmount,
                    behavior: 'smooth'
                });
            }
        }
        
        openLightbox(index = null) {
            const lightbox = document.getElementById('lightboxModal');
            const lightboxImage = document.getElementById('lightboxImage');
            
            if (!lightbox || !lightboxImage) return;
            
            const imageIndex = index !== null ? index : this.currentIndex;
            const image = this.images[imageIndex];
            
            if (image) {
                lightboxImage.src = image.url;
                lightboxImage.alt = image.alt;
                lightbox.classList.add('show');
                this.isLightboxOpen = true;
                document.body.style.overflow = 'hidden';
            }
        }
        
        closeLightbox() {
            const lightbox = document.getElementById('lightboxModal');
            if (lightbox) {
                lightbox.classList.remove('show');
                this.isLightboxOpen = false;
                document.body.style.overflow = '';
            }
        }
        
        preloadImages() {
            // Preload next few images for better performance
            this.images.forEach((image, index) => {
                if (index <= this.currentIndex + 2) {
                    const img = new Image();
                    img.src = image.url;
                }
            });
        }
    }
    
    // Initialize gallery when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        window.propertyGallery = new PropertyGallery();
    });
    
    // Global functions for HTML onclick handlers
    function navigateSlide(direction) {
        if (window.propertyGallery) {
            window.propertyGallery.navigate(direction);
        }
    }
    
    function goToSlide(index) {
        if (window.propertyGallery) {
            window.propertyGallery.goToSlide(index);
        }
    }
    
    function scrollThumbnails(direction) {
        if (window.propertyGallery) {
            window.propertyGallery.scrollThumbnails(direction);
        }
    }
    
    function openLightbox(index) {
        if (window.propertyGallery) {
            window.propertyGallery.openLightbox(index);
        }
    }
    
    function closeLightbox() {
        if (window.propertyGallery) {
            window.propertyGallery.closeLightbox();
        }
    }
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const propertyId = <?php echo json_encode($propertyId); ?>;
    const propertyData = <?php echo json_encode([
        'property_type' => $property['property_type'],
        'listing_type' => $property['listing_type'],
        'city' => $property['city'],
        'bedrooms' => $property['bedrooms'],
        'bathrooms' => $property['bathrooms'],
        'area_sqft' => $property['area_sqft'],
        'furnished' => $property['furnished'],
        'features' => array_values($features), // Send features as a simple array of names
    ]); ?>;

    fetch('ajax/predict_price.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(propertyData)
    })
    .then(response => response.json())
    .then(data => {
        const predictedPriceElement = document.getElementById('predicted-price');
        if (data.status === 'success' && data.predicted_price !== null) {
            predictedPriceElement.textContent = `Rs. ${parseFloat(data.predicted_price).toLocaleString('en-NP')}`;
        } else {
            predictedPriceElement.textContent = 'N/A'; // Or an error message
        }
    })
    .catch(error => {
        console.error('Error fetching predicted price:', error);
        document.getElementById('predicted-price').textContent = 'Error';
    });
});
</script>

<!-- Inquiry Modal -->
<div class="modal fade" id="inquiryModal" tabindex="-1" aria-labelledby="inquiryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inquiryModalLabel">Send Inquiry for "<?php echo htmlspecialchars($property['title']); ?>"</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="send_inquiry.php" method="POST" id="inquiryForm">
                    <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                    <input type="hidden" name="owner_id" value="<?php echo $property['user_id']; ?>">
                    <div class="mb-3">
                        <label for="sender_name" class="form-label">Your Name</label>
                        <?php if ($isLoggedIn): ?>
                            <input type="text" class="form-control" id="sender_name" name="sender_name" 
                                   value="<?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>" 
                                   readonly required>
                            <small class="form-text text-muted">Name is auto-filled from your profile and cannot be edited.</small>
                        <?php else: ?>
                            <input type="text" class="form-control" id="sender_name" name="sender_name" required>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="sender_email" class="form-label">Your Email</label>
                        <?php if ($isLoggedIn): ?>
                            <input type="email" class="form-control" id="sender_email" name="sender_email" 
                                   value="<?php echo htmlspecialchars($currentUser['email']); ?>" 
                                   readonly required>
                            <small class="form-text text-muted">Email is auto-filled from your profile and cannot be edited.</small>
                        <?php else: ?>
                            <input type="email" class="form-control" id="sender_email" name="sender_email" required>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="sender_phone" class="form-label">Your Phone (Optional)</label>
                        <input type="tel" class="form-control" id="sender_phone" name="sender_phone">
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required>I'm interested in this property and would like to know more. Please contact me.</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Inquiry</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Additional Bootstrap JavaScript for dropdowns -->
<script>
// Ensure Bootstrap dropdowns and modals work properly
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all Bootstrap dropdowns
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });
    
    // Initialize Bootstrap modals
    var modalElementList = [].slice.call(document.querySelectorAll('.modal'));
    var modalList = modalElementList.map(function (modalEl) {
        return new bootstrap.Modal(modalEl);
    });
    
    // Handle inquiry modal trigger button
    const inquiryButton = document.querySelector('[data-bs-target="#inquiryModal"]');
    const inquiryModal = document.getElementById('inquiryModal');
    
    if (inquiryButton && inquiryModal) {
        inquiryButton.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Inquiry button clicked');
            
            // Create or get existing modal instance
            let modalInstance = bootstrap.Modal.getInstance(inquiryModal);
            if (!modalInstance) {
                modalInstance = new bootstrap.Modal(inquiryModal);
            }
            
            // Show the modal
            modalInstance.show();
            console.log('Modal should be shown now');
        });
    }
    
    // Let Bootstrap handle dropdowns automatically - no custom interference needed
    
    // Handle inquiry form submission
    const inquiryForm = document.getElementById('inquiryForm');
    if (inquiryForm) {
        inquiryForm.addEventListener('submit', function(e) {
            console.log('Form submission started');
            
            // Get form data
            const formData = new FormData(this);
            const name = formData.get('sender_name');
            const email = formData.get('sender_email');
            const message = formData.get('message');
            
            // Basic client-side validation
            if (!name || !email || !message) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            if (!email.includes('@')) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            submitBtn.disabled = true;
            
            console.log('Form validation passed, submitting...');
            
            // Let form submit normally - no preventDefault
            return true;
        });
    }
});

// Simple modal opening function
function openInquiryModal() {
    console.log('openInquiryModal called');
    const modal = document.getElementById('inquiryModal');
    if (!modal) {
        console.error('Modal not found!');
        return;
    }
    
    // Check if Bootstrap is available
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        console.log('Using Bootstrap modal');
        let modalInstance = bootstrap.Modal.getInstance(modal);
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(modal);
        }
        modalInstance.show();
    } else {
        console.log('Bootstrap not available, using fallback');
        // Fallback: show modal manually
        modal.style.display = 'block';
        modal.classList.add('show');
        document.body.classList.add('modal-open');
        
        // Add backdrop
        if (!document.querySelector('.modal-backdrop')) {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
        
        // Handle close button
        const closeBtn = modal.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.onclick = function() {
                closeInquiryModal();
            };
        }
        
        // Handle backdrop click
        modal.onclick = function(e) {
            if (e.target === modal) {
                closeInquiryModal();
            }
        };
    }
}

function closeInquiryModal() {
    const modal = document.getElementById('inquiryModal');
    if (!modal) return;
    
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) {
            modalInstance.hide();
        }
    } else {
        // Fallback: hide modal manually
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.classList.remove('modal-open');
        
        // Remove backdrop
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
    }
}

// Function to redirect guests to login page
function redirectToLogin() {
    const currentUrl = window.location.href;
    const loginUrl = 'login.php?redirect=' + encodeURIComponent(currentUrl);
    window.location.href = loginUrl;
}
</script>

<?php require_once 'includes/footer.php'; ?>
