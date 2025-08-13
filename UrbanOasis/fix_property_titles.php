<?php
/**
 * Fix Property Titles Script
 * This script finds and fixes properties with garbage titles like "sdfsdf"
 */

session_start();
require_once 'config/database.php';

// Check if user is admin or if running from command line
if (!isset($_SESSION['admin_id']) && php_sapi_name() !== 'cli') {
    die('Access denied. Admin login required.');
}

echo "<h2>Urban Oasis - Property Title Fix</h2>\n";
echo "<p>Checking for properties with garbage titles...</p>\n";

try {
    // Find properties with garbage or test titles
    $stmt = $pdo->query("
        SELECT id, title, property_type, city, location, created_at 
        FROM properties 
        WHERE title LIKE '%sdf%' 
           OR title LIKE '%test%' 
           OR title LIKE '%asdf%'
           OR title LIKE '%qwer%'
           OR title LIKE '%123%'
           OR LENGTH(title) < 10
        ORDER BY created_at DESC
    ");
    
    $problematicProperties = $stmt->fetchAll();
    
    if (empty($problematicProperties)) {
        echo "<p style='color: green;'>✓ No properties found with garbage titles!</p>\n";
    } else {
        echo "<h3>Found " . count($problematicProperties) . " properties with potential garbage titles:</h3>\n";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background: #f8f9fa;'>\n";
        echo "<th>ID</th><th>Current Title</th><th>Type</th><th>Location</th><th>Action</th>\n";
        echo "</tr>\n";
        
        foreach ($problematicProperties as $property) {
            $newTitle = generateProperTitle($property);
            echo "<tr>\n";
            echo "<td>{$property['id']}</td>\n";
            echo "<td style='color: red;'>" . htmlspecialchars($property['title']) . "</td>\n";
            echo "<td>" . htmlspecialchars($property['property_type']) . "</td>\n";
            echo "<td>" . htmlspecialchars($property['city'] . ', ' . $property['location']) . "</td>\n";
            echo "<td>\n";
            echo "<form method='POST' style='display: inline;'>\n";
            echo "<input type='hidden' name='property_id' value='{$property['id']}'>\n";
            echo "<input type='hidden' name='new_title' value='" . htmlspecialchars($newTitle) . "'>\n";
            echo "<input type='text' name='custom_title' value='" . htmlspecialchars($newTitle) . "' style='width: 300px;'>\n";
            echo "<button type='submit' name='fix_title' style='background: #28a745; color: white; border: none; padding: 5px 10px; margin-left: 5px;'>Fix Title</button>\n";
            echo "</form>\n";
            echo "</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        echo "<br><form method='POST'>\n";
        echo "<button type='submit' name='fix_all' style='background: #007bff; color: white; border: none; padding: 10px 20px; font-size: 16px;'>Fix All Titles Automatically</button>\n";
        echo "</form>\n";
    }
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['fix_title'])) {
            $propertyId = intval($_POST['property_id']);
            $newTitle = trim($_POST['custom_title']);
            
            if (!empty($newTitle) && $propertyId > 0) {
                $updateStmt = $pdo->prepare("UPDATE properties SET title = ? WHERE id = ?");
                $updateStmt->execute([$newTitle, $propertyId]);
                
                echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 5px;'>\n";
                echo "<p style='color: #155724; margin: 0;'>✓ Updated property ID {$propertyId} title to: \"{$newTitle}\"</p>\n";
                echo "</div>\n";
                
                // Refresh page after 2 seconds
                echo "<script>setTimeout(() => window.location.reload(), 2000);</script>\n";
            }
        }
        
        if (isset($_POST['fix_all'])) {
            $fixed = 0;
            foreach ($problematicProperties as $property) {
                $newTitle = generateProperTitle($property);
                $updateStmt = $pdo->prepare("UPDATE properties SET title = ? WHERE id = ?");
                $updateStmt->execute([$newTitle, $property['id']]);
                $fixed++;
            }
            
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 10px 0; border-radius: 5px;'>\n";
            echo "<h4 style='color: #155724; margin: 0 0 10px 0;'>✅ Fixed {$fixed} property titles!</h4>\n";
            echo "<p style='color: #155724; margin: 0;'>All garbage titles have been replaced with proper descriptive titles.</p>\n";
            echo "</div>\n";
            
            // Refresh page after 3 seconds
            echo "<script>setTimeout(() => window.location.reload(), 3000);</script>\n";
        }
    }
    
    // Show some statistics
    echo "<h3>Property Statistics:</h3>\n";
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM properties");
    $totalProperties = $totalStmt->fetchColumn();
    
    $approvedStmt = $pdo->query("SELECT COUNT(*) FROM properties WHERE approval_status = 'approved'");
    $approvedProperties = $approvedStmt->fetchColumn();
    
    echo "<ul>\n";
    echo "<li>Total properties: {$totalProperties}</li>\n";
    echo "<li>Approved properties: {$approvedProperties}</li>\n";
    echo "<li>Properties with potential issues: " . count($problematicProperties) . "</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 10px 0; border-radius: 5px;'>\n";
    echo "<h4 style='color: #721c24; margin: 0 0 10px 0;'>❌ Error</h4>\n";
    echo "<p style='color: #721c24; margin: 0;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

function generateProperTitle($property) {
    $type = ucfirst($property['property_type']);
    $location = $property['city'];
    $area = !empty($property['location']) ? $property['location'] : 'Central Area';
    
    // Generate a proper title based on property type and location
    $titles = [
        "Beautiful {$type} in {$location}",
        "Modern {$type} for Sale/Rent in {$area}",
        "Spacious {$type} in {$location}",
        "Well-maintained {$type} in {$area}",
        "Comfortable {$type} in {$location}",
        "Premium {$type} in {$area}",
        "Affordable {$type} in {$location}"
    ];
    
    // Pick a title based on property ID to ensure some variety
    $index = $property['id'] % count($titles);
    return $titles[$index];
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background: #f8f9fa;
}
h2, h3 {
    color: #333;
}
table {
    background: white;
    margin: 20px 0;
}
th {
    background: #007bff !important;
    color: white !important;
}
</style>

<p><a href="properties.php" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Back to Properties</a></p>
