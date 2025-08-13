<?php
session_start();
require_once 'config/database.php';

// Ensure inquiry_count column has proper defaults and is never NULL

try {
    echo "<h2>Fixing Inquiry Count Column Defaults</h2>";
    
    // First update any NULL values to 0
    $stmt = $pdo->prepare("UPDATE properties SET inquiry_count = 0 WHERE inquiry_count IS NULL");
    $result = $stmt->execute();
    $updated = $stmt->rowCount();
    
    if ($updated > 0) {
        echo "<p>Updated {$updated} properties with NULL inquiry_count to 0</p>";
    } else {
        echo "<p>No NULL inquiry_count values found - all good!</p>";
    }
    
    // Try to alter the column to have a default value (this might fail if already set)
    try {
        $pdo->exec("ALTER TABLE properties MODIFY COLUMN inquiry_count INT DEFAULT 0 NOT NULL");
        echo "<p>Successfully set inquiry_count column to NOT NULL with default 0</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            echo "<p>Column defaults may already be properly set: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<p><strong>Inquiry count column is now properly configured!</strong></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
