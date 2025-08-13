<?php
session_start();
require_once 'config/database.php';

// This script updates the inquiry_count for all properties based on actual inquiries in the database

try {
    echo "<h2>Updating Property Inquiry Counts</h2>";
    
    // Get all properties
    $stmt = $pdo->prepare("SELECT id, title, inquiry_count FROM properties ORDER BY id");
    $stmt->execute();
    $properties = $stmt->fetchAll();
    
    echo "<p>Found " . count($properties) . " properties to check.</p>";
    
    $updated = 0;
    
    foreach ($properties as $property) {
        // Count actual inquiries for this property
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM property_inquiries WHERE property_id = ?");
        $stmt->execute([$property['id']]);
        $actual_count = $stmt->fetchColumn();
        
        // Update if the counts don't match
        if ($actual_count != $property['inquiry_count']) {
            $stmt = $pdo->prepare("UPDATE properties SET inquiry_count = ? WHERE id = ?");
            $stmt->execute([$actual_count, $property['id']]);
            
            echo "<p>Updated property '{$property['title']}' (ID: {$property['id']}): {$property['inquiry_count']} → {$actual_count}</p>";
            $updated++;
        } else {
            echo "<p>Property '{$property['title']}' (ID: {$property['id']}): {$actual_count} inquiries (already correct)</p>";
        }
    }
    
    echo "<h3>Summary</h3>";
    echo "<p>Updated {$updated} properties with correct inquiry counts.</p>";
    echo "<p><strong>All properties now have accurate inquiry counts visible to all users!</strong></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
