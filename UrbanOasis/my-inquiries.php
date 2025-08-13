<?php
session_start();
require_once 'config/database.php';
require_once 'config/property_utils.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $inquiry_id = intval($_POST['inquiry_id']);
    $new_status = $_POST['status'];
    
    // Verify this inquiry belongs to a property owned by the current user
    $stmt = $pdo->prepare("
        SELECT i.id 
        FROM property_inquiries i
        JOIN properties p ON i.property_id = p.id
        WHERE i.id = ? AND p.user_id = ?
    ");
    $stmt->execute([$inquiry_id, $user_id]);
    
    if ($stmt->fetch()) {
        $updateStmt = $pdo->prepare("UPDATE property_inquiries SET status = ? WHERE id = ?");
        $updateStmt->execute([$new_status, $inquiry_id]);
        $_SESSION['success_message'] = 'Inquiry status updated successfully!';
    }
    
    header('Location: my-inquiries.php');
    exit();
}

// Get all inquiries for properties owned by the logged-in user
$stmt = $pdo->prepare("
    SELECT 
        i.*, 
        p.title as property_title, 
        p.id as property_id,
        p.property_type,
        p.listing_type,
        p.price
    FROM property_inquiries i
    JOIN properties p ON i.property_id = p.id
    WHERE p.user_id = ?
    ORDER BY i.created_at DESC
");
$stmt->execute([$user_id]);
$inquiries = $stmt->fetchAll();

// Get inquiry counts by status
$stmt = $pdo->prepare("
    SELECT 
        i.status,
        COUNT(*) as count
    FROM property_inquiries i
    JOIN properties p ON i.property_id = p.id
    WHERE p.user_id = ?
    GROUP BY i.status
");
$stmt->execute([$user_id]);
$statusCounts = [];
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = $row['count'];
}

$pageTitle = 'My Inquiries';
require_once 'includes/header.php';
?>

<main class="container py-5">
    <h1 class="mb-4"><i class="fas fa-envelope-open-text me-2"></i>My Inquiries</h1>

    <!-- Status Summary -->
    <div class="row mb-4">
        <div class="col-md-3"><div class="card text-center"><div class="card-body"><h5 class="card-title">New</h5><p class="card-text display-4"><?php echo isset($statusCounts['new']) ? $statusCounts['new'] : 0; ?></p></div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body"><h5 class="card-title">Read</h5><p class="card-text display-4"><?php echo isset($statusCounts['read']) ? $statusCounts['read'] : 0; ?></p></div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body"><h5 class="card-title">Replied</h5><p class="card-text display-4"><?php echo isset($statusCounts['replied']) ? $statusCounts['replied'] : 0; ?></p></div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body"><h5 class="card-title">Closed</h5><p class="card-text display-4"><?php echo isset($statusCounts['closed']) ? $statusCounts['closed'] : 0; ?></p></div></div></div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (empty($inquiries)): ?>
        <div class="alert alert-info text-center">
            <i class="fas fa-info-circle fa-3x mb-3"></i>
            <h4>You have not received any inquiries yet.</h4>
            <p>When a user sends an inquiry about one of your properties, it will appear here.</p>
        </div>
    <?php else: ?>
        <div class="accordion" id="inquiriesAccordion">
            <?php foreach ($inquiries as $inquiry): ?>
                <div class="accordion-item mb-3 border-<?php echo $inquiry['status'] == 'new' ? 'primary' : ($inquiry['status'] == 'replied' ? 'success' : 'secondary'); ?>">
                    <h2 class="accordion-header" id="heading-<?php echo $inquiry['id']; ?>">
                        <button class="accordion-button collapsed d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $inquiry['id']; ?>">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($inquiry['property_title']); ?></strong>
                                        <br><small class="text-muted">from <?php echo htmlspecialchars($inquiry['sender_name']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php 
                                            switch($inquiry['status']) {
                                                case 'new': echo 'primary'; break;
                                                case 'read': echo 'warning'; break;
                                                case 'replied': echo 'success'; break;
                                                case 'closed': echo 'secondary'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>">
                                            <?php echo ucfirst($inquiry['status']); ?>
                                        </span>
                                        <br><small class="text-muted"><?php echo date('M d, Y', strtotime($inquiry['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="collapse-<?php echo $inquiry['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#inquiriesAccordion">
                        <div class="accordion-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6><i class="fas fa-user me-2"></i>Contact Information</h6>
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($inquiry['sender_name']); ?></p>
                                    <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($inquiry['sender_email']); ?>"><?php echo htmlspecialchars($inquiry['sender_email']); ?></a></p>
                                    <?php if (!empty($inquiry['sender_phone'])): ?>
                                    <p><strong>Phone:</strong> <a href="tel:<?php echo htmlspecialchars($inquiry['sender_phone']); ?>"><?php echo htmlspecialchars($inquiry['sender_phone']); ?></a></p>
                                    <?php endif; ?>
                                    
                                    <h6 class="mt-4"><i class="fas fa-comment-alt me-2"></i>Message</h6>
                                    <div class="bg-light p-3 rounded">
                                        <?php echo nl2br(htmlspecialchars($inquiry['message'])); ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <h6><i class="fas fa-home me-2"></i>Property Details</h6>
                                    <p><strong>Type:</strong> <?php echo ucfirst($inquiry['property_type']); ?></p>
                                    <p><strong>Listing:</strong> <?php echo ucfirst($inquiry['listing_type']); ?></p>
                                    <p><strong>Price:</strong> <?php echo formatPropertyPrice($inquiry['price'], 'Rs. ', $inquiry['property_type'], $inquiry['listing_type']); ?></p>
                                    <a href="property-details.php?id=<?php echo $inquiry['property_id']; ?>" class="btn btn-outline-primary btn-sm mb-3">
                                        <i class="fas fa-eye me-1"></i>View Property
                                    </a>
                                    
                                    <h6><i class="fas fa-clock me-2"></i>Inquiry Details</h6>
                                    <p><strong>Received:</strong> <?php echo date('M d, Y h:i A', strtotime($inquiry['created_at'])); ?></p>
                                    <p><strong>Type:</strong> <?php echo ucfirst($inquiry['inquiry_type']); ?></p>
                                </div>
                            </div>
                            
                            <hr>
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <form action="my-inquiries.php" method="POST" class="d-flex align-items-center">
                                        <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['id']; ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <label for="status-<?php echo $inquiry['id']; ?>" class="form-label me-2 mb-0">Status:</label>
                                        <select name="status" id="status-<?php echo $inquiry['id']; ?>" class="form-select me-2" style="width: auto;">
                                            <option value="new" <?php echo $inquiry['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                                            <option value="read" <?php echo $inquiry['status'] == 'read' ? 'selected' : ''; ?>>Read</option>
                                            <option value="replied" <?php echo $inquiry['status'] == 'replied' ? 'selected' : ''; ?>>Replied</option>
                                            <option value="closed" <?php echo $inquiry['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-save me-1"></i>Update
                                        </button>
                                    </form>
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="mailto:<?php echo htmlspecialchars($inquiry['sender_email']); ?>?subject=Re: <?php echo urlencode($inquiry['property_title']); ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-reply me-1"></i>Reply via Email
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>
