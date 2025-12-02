<?php
/**
 * Load More Reviews via AJAX
 * Returns reviews in JSON format for pagination
 */

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';

header('Content-Type: application/json');

// Handle GET request for rating breakdown
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_rating_breakdown') {
    $school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
    
    if (!$school_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid school ID']);
        exit;
    }
    
    require_once 'db.php';
    $breakdown_data = getSchoolRatingBreakdown($school_id);
    
    // Format breakdown for tooltip
    $breakdown = [];
    foreach ($breakdown_data as $category => $data) {
        if ($category !== 'overall_rating') {
            $breakdown[$category] = $data['rating'];
        }
    }
    
    $overall_rating = $breakdown_data['overall_rating']['rating'] ?? 0;
    $review_count = $breakdown_data['overall_rating']['count'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'breakdown' => $breakdown,
        'overall_rating' => $overall_rating,
        'review_count' => $review_count
    ]);
    exit;
}

// Check if request is POST for loading reviews
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get parameters
$school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : 0;
$offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
$user_id = null;

// Get user ID if logged in
if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
} elseif (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
}

// Validate school ID
if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid school ID']);
    exit;
}

// Get reviews
require_once 'db.php';
$reviews = getSchoolReviews($school_id, $limit, $offset, $user_id);
$total_reviews = getSchoolReviewCount($school_id, $user_id);
$has_more = ($total_reviews > ($offset + count($reviews)));

// Prepare response
$response = [
    'success' => true,
    'reviews' => $reviews,
    'has_more' => $has_more,
    'total' => $total_reviews,
    'loaded' => $offset + count($reviews)
];

echo json_encode($response);
exit;
?>
