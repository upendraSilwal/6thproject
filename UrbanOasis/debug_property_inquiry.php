<?php
session_start();
require_once 'config/database.php';

// Test property inquiry count visibility
$propertyId = 27; // This has 1 inquiry according to our script

echo "<h2>Debug Property Inquiry Count Visibility</h2>";

echo "<h3>Session Info:</h3>";
echo "<p>Logged in: " . (isset($_SESSION['user_id']) ? "Yes (User ID: {$_SESSION['user_id']})" : "No (Guest)") . "</p>";

try {
    // Get property details - same query as property-details.php
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ? AND approval_status = 'approved' AND is_active = 1 AND expires_at > NOW()");
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch();
    
    if ($property) {
        echo "<h3>Property Data Retrieved:</h3>";
        echo "<p><strong>Title:</strong> " . htmlspecialchars($property['title']) . "</p>";
        echo "<p><strong>Inquiry Count:</strong> " . $property['inquiry_count'] . "</p>";
        echo "<p><strong>User ID (Owner):</strong> " . $property['user_id'] . "</p>";
        echo "<p><strong>Active:</strong> " . ($property['is_active'] ? 'Yes' : 'No') . "</p>";
        echo "<p><strong>Approval Status:</strong> " . $property['approval_status'] . "</p>";
        
        // Show full property array for debugging
        echo "<h3>Full Property Array:</h3>";
        echo "<pre>";
        print_r($property);
        echo "</pre>";
        
        // Check actual inquiry count from database
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM property_inquiries WHERE property_id = ?");
        $stmt->execute([$propertyId]);
        $actual_count = $stmt->fetchColumn();
        
        echo "<h3>Database Cross-Check:</h3>";
        echo "<p><strong>Actual inquiries in database:</strong> {$actual_count}</p>";
        echo "<p><strong>Property inquiry_count field:</strong> {$property['inquiry_count']}</p>";
        
        if ($actual_count != $property['inquiry_count']) {
            echo "<p style='color: red;'><strong>MISMATCH!</strong> The counts don't match.</p>";
        } else {
            echo "<p style='color: green;'><strong>MATCH!</strong> The counts are correct.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>Property not found or not active!</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
