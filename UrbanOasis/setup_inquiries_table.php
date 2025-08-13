<?php
require_once 'config/database.php';

try {
    // Check if property_inquiries table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'property_inquiries'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "Creating property_inquiries table...\n";
        
        // Create the table
        $sql = "CREATE TABLE `property_inquiries` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `property_id` int(11) NOT NULL,
          `sender_id` int(11) DEFAULT NULL,
          `sender_name` varchar(100) NOT NULL,
          `sender_email` varchar(100) NOT NULL,
          `sender_phone` varchar(20) DEFAULT NULL,
          `subject` varchar(200) NOT NULL DEFAULT 'Property Inquiry',
          `message` text NOT NULL,
          `inquiry_type` enum('general','viewing','price','availability','other') DEFAULT 'general',
          `status` enum('new','read','replied','closed') DEFAULT 'new',
          `owner_replied` tinyint(1) DEFAULT 0,
          `owner_reply` text DEFAULT NULL,
          `owner_reply_date` timestamp NULL DEFAULT NULL,
          `is_urgent` tinyint(1) DEFAULT 0,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_property_inquiries_property_id` (`property_id`),
          KEY `idx_property_inquiries_sender_id` (`sender_id`),
          KEY `idx_property_inquiries_status` (`status`),
          KEY `idx_property_inquiries_created_at` (`created_at`),
          CONSTRAINT `property_inquiries_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
          CONSTRAINT `property_inquiries_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $pdo->exec($sql);
        
        // Add additional indexes
        $pdo->exec("CREATE INDEX `idx_property_inquiries_owner_lookup` ON `property_inquiries` (`property_id`, `status`, `created_at`)");
        $pdo->exec("CREATE INDEX `idx_property_inquiries_sender_lookup` ON `property_inquiries` (`sender_id`, `created_at`)");
        
        echo "Table created successfully!\n";
    } else {
        echo "Table already exists.\n";
    }
    
    // Check if inquiry_count column exists in properties table
    $stmt = $pdo->query("SHOW COLUMNS FROM properties LIKE 'inquiry_count'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        echo "Adding inquiry_count column to properties table...\n";
        $pdo->exec("ALTER TABLE properties ADD COLUMN inquiry_count INT DEFAULT 0");
        echo "Column added successfully!\n";
    } else {
        echo "inquiry_count column already exists.\n";
    }
    
    echo "Setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
