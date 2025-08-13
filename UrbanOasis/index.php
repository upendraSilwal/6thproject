<?php
$pageTitle = "Home";
require_once 'includes/header.php';
require_once 'config/images.php';
require_once 'config/property_utils.php';

// Get latest properties for homepage
$latestQuery = "SELECT * FROM properties WHERE approval_status = 'approved' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())";
$latestParams = [];

// Exclude current user's own properties if logged in
if (isset($_SESSION['user_id'])) {
    $latestQuery .= " AND user_id != ?";
    $latestParams[] = $_SESSION['user_id'];
}

$latestQuery .= " ORDER BY created_at DESC LIMIT 6";
$stmt = $pdo->prepare($latestQuery);
$stmt->execute($latestParams);
$latestProperties = $stmt->fetchAll();
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-overlay">
        <div class="container">
            <div class="row min-vh-100 align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold text-white mb-4">
                        Find Your Dream Home in Nepal
                    </h1>
                    <p class="lead text-white mb-4">
                        Discover the perfect house, apartment, or room for rent, buy, or sell anywhere in Nepal.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="properties.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-search me-2"></i>Browse Properties
                        </a>
                        <?php if (!$isLoggedIn): ?>
                        <a href="register.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-user-plus me-2"></i>List Your Property
                        </a>
                        <?php else: ?>
                        <a href="add_property.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-plus me-2"></i>Add Property
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-lg-8 mx-auto">
                <h2 class="display-5 fw-bold">Why Choose Urban Oasis?</h2>
                <p class="lead text-muted">Your trusted partner in real estate across Nepal</p>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-home fa-3x text-primary"></i>
                        </div>
                        <h5 class="card-title">Houses</h5>
                        <p class="card-text">Find beautiful houses for rent or purchase in prime locations across Nepal.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-building fa-3x text-primary"></i>
                        </div>
                        <h5 class="card-title">Apartments</h5>
                        <p class="card-text">Modern apartments with all facilities across Nepal.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon mb-3">
                            <i class="fas fa-door-open fa-3x text-primary"></i>
                        </div>
                        <h5 class="card-title">Rooms</h5>
                        <p class="card-text">Rooms for students and professionals in convenient locations.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Latest Properties Section -->
<?php if (!empty($latestProperties)): ?>
<section class="py-5">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-lg-8 mx-auto">
                <h2 class="display-5 fw-bold">Latest Properties</h2>
                <p class="lead text-muted">Recently added properties</p>
            </div>
        </div>
        <div class="row g-4">
            <?php foreach ($latestProperties as $property): ?>
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
                        $imgUrl = getRandomPropertyImage($property['property_type']);
                    }
                    ?>
                    <img src="<?php echo $imgUrl; ?>" 
                         class="card-img-top property-image" 
                         alt="<?php echo htmlspecialchars($property['title']); ?>"
                         loading="lazy"
                         onerror="this.src='<?php echo getRandomPropertyImage('house'); ?>'">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-primary"><?php echo ucfirst($property['property_type']); ?></span>
                            <span class="badge bg-success"><?php echo ucfirst($property['listing_type']); ?></span>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($property['title']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($property['location']); ?></p>
                        <div class="property-features mb-3">
                            <?php if ($property['property_type'] !== 'room'): ?>
                            <span><i class="fas fa-bed"></i> <?php echo $property['bedrooms']; ?> Beds</span>
                            <span><i class="fas fa-bath"></i> <?php echo $property['bathrooms']; ?> Baths</span>
                            <?php endif; ?>
                            <span><i class="fas fa-ruler-combined"></i> <?php echo isset($property['area_sqft']) ? $property['area_sqft'] : 'N/A'; ?> sq ft</span>
                            <?php 
                                $features = getPropertyFeatures($pdo, $property['id']);
                                $qualityScore = calculateQualityScore($features, $property['property_type']);
                            ?>
                            <?php echo generateQualityScoreHTML($qualityScore, 'small'); ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="property-price"><?php echo formatPropertyPrice($property['price'], 'Rs. ', $property['property_type'], $property['listing_type']); ?></span>
                            <a href="property-details.php?id=<?php echo $property['id']; ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="properties.php" class="btn btn-primary btn-lg">View All Properties</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
// For index.php, we don't need Choices.js, so we set it to false
$includeChoicesJS = false;
require_once 'includes/shared-footer-scripts.php';
require_once 'includes/footer.php';
?>
