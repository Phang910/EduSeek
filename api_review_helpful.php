<?php
/**
 * Review Helpful API
 * Handles marking reviews as helpful/unhelpful
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'db.php';

ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to mark reviews as helpful']);
    exit;
}

$review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if (!$review_id || !in_array($action, ['helpful', 'unhelpful'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if review_helpful_votes table exists
$check_table = $conn->query("SHOW TABLES LIKE 'review_helpful_votes'");
$has_table = ($check_table->num_rows > 0);
$check_table->close();

if (!$has_table) {
    echo json_encode(['success' => false, 'message' => 'Helpful votes table does not exist']);
    exit;
}

// Check if user already voted
$stmt = $conn->prepare("SELECT id, is_helpful FROM review_helpful_votes WHERE review_id = ? AND user_id = ?");
$stmt->bind_param("ii", $review_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$existing_vote = $result->fetch_assoc();
$stmt->close();

if ($action === 'helpful') {
    if ($existing_vote) {
        // Update existing vote
        if ($existing_vote['is_helpful'] == 0) {
            // Change from not helpful to helpful
            $update_stmt = $conn->prepare("UPDATE review_helpful_votes SET is_helpful = 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update_stmt->bind_param("i", $existing_vote['id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
        // If already helpful, do nothing
    } else {
        // Insert new vote
        $insert_stmt = $conn->prepare("INSERT INTO review_helpful_votes (review_id, user_id, is_helpful) VALUES (?, ?, 1)");
        $insert_stmt->bind_param("ii", $review_id, $user_id);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
} else {
    // unhelpful - remove vote
    if ($existing_vote && $existing_vote['is_helpful'] == 1) {
        $delete_stmt = $conn->prepare("DELETE FROM review_helpful_votes WHERE id = ?");
        $delete_stmt->bind_param("i", $existing_vote['id']);
        $delete_stmt->execute();
        $delete_stmt->close();
    }
}

// Get updated helpful count
$count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM review_helpful_votes WHERE review_id = ? AND is_helpful = 1");
$count_stmt->bind_param("i", $review_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_data = $count_result->fetch_assoc();
$helpful_count = intval($count_data['count'] ?? 0);
$count_stmt->close();

ob_clean();
echo json_encode([
    'success' => true,
    'helpful_count' => $helpful_count,
    'is_helpful' => ($action === 'helpful')
]);
exit;
?>


