<?php
/*
 * Shared Footer Scripts Snippet
 * 
 * This file consolidates commonly used JavaScript and CSS libraries across pages
 * to prevent code duplication and ensure consistency.
 * 
 * Usage:
 * 1. Include this file BEFORE the main footer.php in any page that needs shared scripts
 * 2. Set the $includeChoicesJS variable to true if the page needs Choices.js library
 * 3. Use $additionalJS variable for any page-specific scripts that should run after shared scripts
 * 
 * Example usage in a page:
 * <?php
 * $includeChoicesJS = true; // Set to true if page uses enhanced select dropdowns
 * $additionalJS = '
 *     <script>
 *         // Page-specific JavaScript here
 *     </script>
 * ';
 * require_once 'includes/shared-footer-scripts.php';
 * require_once 'includes/footer.php';
 * ?>
 */

// Initialize variables if not set
$includeChoicesJS = $includeChoicesJS ?? false;
$additionalJS = $additionalJS ?? '';

// Shared CSS libraries (loaded in head via header, but documented here for reference)
/*
 * Shared CSS Dependencies:
 * - Bootstrap 5.3.0 CSS (loaded in header.php)
 * - FontAwesome icons (loaded in header.php)
 * - Choices.js CSS (conditionally loaded below)
 */

// Shared JavaScript libraries that are commonly used across multiple pages
$sharedScripts = '
    <!-- Feedback System JavaScript (temporarily disabled) -->
    <!-- <script src="js/feedback.js"></script> -->
';

// Choices.js - Enhanced select dropdowns library
// Used by: properties.php, add_property.php, edit-property.php
if ($includeChoicesJS) {
    $sharedScripts .= '
    <!-- Choices.js - Enhanced Select Dropdowns -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    ';
}

// Output shared scripts
echo $sharedScripts;

// Output any additional page-specific scripts
if (!empty($additionalJS)) {
    echo $additionalJS;
}

/*
 * Common JavaScript patterns used across pages:
 * 
 * 1. Choices.js initialization:
 *    const choices = new Choices('#select-element', {
 *        searchEnabled: false,
 *        shouldSort: false,
 *        position: 'bottom'
 *    });
 * 
 * 2. Image lazy loading and error handling (already handled in property listings)
 * 
 * 3. Form validation patterns (can be standardized here in future)
 * 
 * Asset Inventory:
 * ===============
 * 
 * Core dependencies (always loaded):
 * - Bootstrap 5.3.0 JS Bundle (loaded in footer.php)
 * - Custom js/script.js (loaded in footer.php)
 * 
 * Conditional dependencies:
 * - Choices.js CSS + JS (for enhanced form selects)
 *   Used by: properties.php, add_property.php
 *   Not used by: index.php, contact.php, login.php, register.php
 * 
 * Future shared assets to consider:
 * - Image upload/preview libraries
 * - Date picker libraries
 * - Map integration scripts
 * - Analytics tracking scripts
 * - Notification/toast libraries
 */
?>
