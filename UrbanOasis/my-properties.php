<?php
session_start();
require_once 'config/database.php';
require_once 'config/images.php';
require_once 'config/property_utils.php';
require_once 'config/user_utils.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's subscription information
// Get user's credit and property info using centralized function
$user = getUserById($pdo, $user_id);

// Get user's properties with expiry information
$stmt = $pdo->prepare("SELECT p.*, 
(SELECT COUNT(*) FROM property_inquiries WHERE property_id = p.id) as inquiry_count,
CASE 
    WHEN p.expires_at < NOW() THEN 'expired'
    WHEN p.expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 'expiring_soon'
    ELSE 'active'
END as expiry_status,
DATEDIFF(p.expires_at, NOW()) as days_until_expiry
FROM properties p 
WHERE p.user_id = ? 
ORDER BY p.created_at DESC");
$stmt->execute([$user_id]);
$properties = $stmt->fetchAll();

$total_properties = $user['total_listings_created'];
$free_listings_remaining = max(0, 1 - $user['free_listings_used']);


$pageTitle = "My Properties";
require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-home me-2"></i>My Properties</h1>
                <a href="add_property.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Property
                </a>
            </div>
            
            <!-- Credits & Listings Overview -->
            <div class="row text-center mb-4">
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Available Credits</h5>
                            <h2 class="text-primary"><?php echo $user['listing_credits']; ?></h2>
                            <a href="pricing.php" class="btn btn-primary btn-sm mt-2">Buy Credits</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Free Listings Remaining</h5>
                            <h2 class="text-success"><?php echo $free_listings_remaining; ?>/1</h2>
                            <small class="text-muted">Your first listing is free!</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Total Properties Listed</h5>
                            <h2 class="text-info"><?php echo $total_properties; ?></h2>
                             <a href="subscription.php" class="btn btn-info btn-sm mt-2">View History</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($user['listing_credits'] == 0 && $free_listings_remaining == 0): ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    You have no listing credits and have used up your free listings. 
                    <a href="pricing.php" class="alert-link">Purchase credits</a> to list more properties.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php 
                    switch($_GET['success']) {
                        case '1':
                            echo '<strong>Property added successfully!</strong> Your property is now pending admin approval. You will be notified once it\'s reviewed.';
                            break;
                        case 'deleted':
                            echo '<strong>Property deleted successfully!</strong> Your property has been removed from the system.';
                            break;
                        default:
                            echo '<strong>Operation completed successfully!</strong>';
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php 
                    switch($_GET['error']) {
                        case 'invalid':
                            echo '<strong>Invalid property ID!</strong> The property you\'re trying to access doesn\'t exist.';
                            break;
                        case 'notfound':
                            echo '<strong>Property not found!</strong> You can only manage your own properties.';
                            break;
                        case 'deletefail':
                            echo '<strong>Delete failed!</strong> There was an error deleting your property. Please try again.';
                            break;
                        default:
                            echo '<strong>An error occurred!</strong> Please try again.';
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Your Property Listings</h3>
            </div>
        </div>
    </div>
    <?php if (empty($properties)): ?>
        <div class="alert alert-info text-center">You have not listed any properties yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white">
                <thead class="table-light">
                    <tr>
                        <th>Property</th>
                        <th>Status</th>
                        <th>Expiry</th>
                        <th>Inquiries</th>
                        <th>Listed On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($properties as $property): 
                        $image_url = getPropertyImageUrl($property);
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($property['title']); ?>" class="img-thumbnail me-3" style="width: 100px; height: 70px; object-fit: cover;">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($property['title']); ?></h6>
                                        <small class="text-muted"><?php echo getPropertyTypeDisplay($property['property_type']); ?> for <?php echo getListingTypeDisplay($property['listing_type']); ?></small>
                                    </div>
                                </div>
                            </td>
                           
                            <td>
                                <?php if ($property['approval_status'] === 'pending'): ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php elseif ($property['approval_status'] === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($property['expiry_status'] === 'expired'): ?>
                                    <span class="badge bg-danger">Expired</span>
                                    <small class="d-block text-muted"><?php echo date('M d, Y', strtotime($property['expires_at'])); ?></small>
                                <?php elseif ($property['expiry_status'] === 'expiring_soon'): ?>
                                    <span class="badge bg-warning"><?php echo $property['days_until_expiry']; ?> days left</span>
                                    <small class="d-block text-muted">Expires: <?php echo date('M d, Y', strtotime($property['expires_at'])); ?></small>
                                <?php else: ?>
                                    <span class="badge bg-success"><?php echo $property['days_until_expiry']; ?> days left</span>
                                    <small class="d-block text-muted">Expires: <?php echo date('M d, Y', strtotime($property['expires_at'])); ?></small>
                                <?php endif; ?>
                            </td>
                             <td>
                                <span class="badge bg-info"><?php echo $property['inquiry_count']; ?></span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($property['created_at'])); ?></td>
                            <td>
                                <a href="property-details.php?id=<?php echo $property['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="View"><i class="fas fa-eye"></i></a>
                                <?php if ($property['expiry_status'] !== 'expired'): ?>
                                    <a href="edit-property.php?id=<?php echo $property['id']; ?>" class="btn btn-sm btn-outline-secondary me-1" title="Edit"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                                <?php if ($property['expiry_status'] === 'expired' || $property['expiry_status'] === 'expiring_soon'): ?>
                                    <button class="btn btn-sm btn-outline-success me-1" onclick="renewProperty(<?php echo $property['id']; ?>)" title="Renew for 30 days (5 credits)"><i class="fas fa-refresh"></i></button>
                                <?php endif; ?>
                                <a href="delete-property.php?id=<?php echo $property['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this property?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function renewProperty(propertyId) {
    if (!confirm('Are you sure you want to renew this property for 30 days? This will cost 5 credits.')) {
        return;
    }
    
    // Show loading state
    const button = event.target.closest('button');
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    // Send renewal request
    fetch('renew-property.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'property_id=' + propertyId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container').insertBefore(alert, document.querySelector('.table-responsive'));
            
            // Reload page after 2 seconds to show updated expiry dates
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            // Show error message
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container').insertBefore(alert, document.querySelector('.table-responsive'));
            
            // Restore button
            button.innerHTML = originalHTML;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while renewing the property. Please try again.');
        
        // Restore button
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
