<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/property_utils.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Read JSON or form-encoded
$input = [];
if (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if (empty($input)) {
    $input = $_POST;
}

// Basic sanitization and defaults
$propertyType = strtolower(trim($input['property_type'] ?? ''));
$listingType = strtolower(trim($input['listing_type'] ?? ''));
$city = trim($input['city'] ?? '');
$features = $input['features'] ?? [];
$areaSqft = isset($input['area_sqft']) && is_numeric($input['area_sqft']) ? (float)$input['area_sqft'] : null;
$qualityScore = isset($input['quality_score']) && is_numeric($input['quality_score']) ? (int)$input['quality_score'] : null;

$allowedPropertyTypes = ['house', 'apartment', 'room'];
$allowedListingTypes = ['rent', 'sale'];

if (!in_array($propertyType, $allowedPropertyTypes, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid property_type']);
    exit;
}
if (!in_array($listingType, $allowedListingTypes, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid listing_type']);
    exit;
}
if ($propertyType === 'room' && $listingType === 'sale') {
    echo json_encode(['success' => false, 'error' => 'Rooms are only supported for rent']);
    exit;
}

// Ensure features is array
if (!is_array($features)) {
    $features = [];
}

$formData = [
    'property_type' => $propertyType,
    'listing_type' => $listingType,
    'city' => $city,
    'features' => $features,
];
if ($qualityScore !== null) {
    $formData['quality_score'] = $qualityScore;
}
if ($areaSqft !== null) {
    $formData['area_sqft'] = $areaSqft;
}

try {
    $result = suggestPriceForForm($pdo, $formData);
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Prediction failed', 'details' => $e->getMessage()]);
}
?>


