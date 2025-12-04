<?php
/**
 * Add Review for School
 * Allows logged-in users to submit reviews with ratings and photos
 */

$page_title = 'Add Review';
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';
requireLogin();

$school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
$message = '';
$message_type = '';

if (!$school_id) {
    header('Location: directory.php');
    exit;
}

// Get school details
$school = getSchoolById($school_id);
if (!$school) {
    header('Location: directory.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'db.php';
    
    $user_id = $_SESSION['user_id'];
    $location_rating = intval($_POST['location_rating'] ?? 0);
    $service_rating = intval($_POST['service_rating'] ?? 0);
    $facilities_rating = intval($_POST['facilities_rating'] ?? 0);
    $cleanliness_rating = intval($_POST['cleanliness_rating'] ?? 0);
    $value_rating = intval($_POST['value_rating'] ?? 0);
    $education_rating = intval($_POST['education_rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    
    // Validate ratings (1-5)
    $ratings = [
        'location' => $location_rating,
        'service' => $service_rating,
        'facilities' => $facilities_rating,
        'cleanliness' => $cleanliness_rating,
        'value' => $value_rating,
        'education' => $education_rating
    ];
    
    $valid = true;
    foreach ($ratings as $key => $rating) {
        if ($rating < 1 || $rating > 5) {
            $valid = false;
            break;
        }
    }
    
    if (!$valid) {
        $message = 'All ratings must be between 1 and 5 stars.';
        $message_type = 'danger';
    } else {
        // Calculate overall rating (average) - ensure it's a float
        $overall_rating = floatval(array_sum($ratings) / count($ratings));
        
        // Handle photo uploads (max 10)
        $photo_paths = [];
        $upload_dir = __DIR__ . '/uploads/reviews';
        
        if (!empty($_FILES['photos']['name'][0])) {
            $uploaded_count = 0;
            for ($i = 0; $i < min(10, count($_FILES['photos']['name'])); $i++) {
                if ($_FILES['photos']['error'][$i] == UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['photos']['name'][$i],
                        'type' => $_FILES['photos']['type'][$i],
                        'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                        'error' => $_FILES['photos']['error'][$i],
                        'size' => $_FILES['photos']['size'][$i]
                    ];
                    
                    $upload_result = uploadFile($file, $upload_dir, ['jpg', 'jpeg', 'png', 'gif', 'webp'], 5242880); // 5MB max
                    
                    if ($upload_result['success']) {
                        $photo_paths[] = $upload_result['filename'];
                        $uploaded_count++;
                    }
                }
            }
        }
        
        // Insert review - ensure photo_paths_json is a string or null
        $photo_paths_json = !empty($photo_paths) ? json_encode($photo_paths) : null;
        if ($photo_paths_json === null) {
            $photo_paths_json = '';
        }
        
        // Ensure comment is a string (not null)
        if ($comment === null) {
            $comment = '';
        }
        
        // Prepare statement: 11 placeholders for 11 parameters
        // SQL has 11 ? placeholders + 1 hardcoded 'Pending' for status
        $stmt = $conn->prepare("INSERT INTO school_reviews (school_id, user_id, location_rating, service_rating, facilities_rating, cleanliness_rating, value_rating, education_rating, overall_rating, comment, photo_paths, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        
        // Verify: 11 parameters need 11 type characters
        // Type string: "iiiiiiiidss" = 8 integers + 1 double + 2 strings
        $stmt->bind_param("iiiiiiiidss", 
            $school_id,              // 1: i (int)
            $user_id,                // 2: i (int)
            $location_rating,        // 3: i (int)
            $service_rating,         // 4: i (int)
            $facilities_rating,      // 5: i (int)
            $cleanliness_rating,     // 6: i (int)
            $value_rating,           // 7: i (int)
            $education_rating,       // 8: i (int)
            $overall_rating,         // 9: d (double/float)
            $comment,               // 10: s (string)
            $photo_paths_json       // 11: s (string)
        );
        
        if ($stmt->execute()) {
            $message = 'Review submitted successfully! It will be reviewed by admin before publishing.';
            $message_type = 'success';
            
            // Redirect after 2 seconds
            header("refresh:2;url=school-details.php?id=" . $school_id);
        } else {
            $message = 'Error submitting review. Please try again.';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

require_once 'includes/header.php';
?>

<div class="container my-5 page-content">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <a href="school-details.php?id=<?php echo $school_id; ?>" class="btn btn-outline-secondary btn-sm mb-3">
                <i class="fas fa-arrow-left me-2"></i><?php echo t('btn_back'); ?>
            </a>
            
            <h1 class="page-title mb-4"><?php echo t('review_write'); ?></h1>
            
            <div class="card shadow-soft">
                <div class="card-body p-4">
                    <h5 class="mb-3"><?php echo htmlspecialchars($school['name']); ?></h5>
                    
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Location Rating -->
                        <div class="mb-4">
                            <label class="form-label fw-bold"><?php echo t('review_location'); ?> *</label>
                            <div class="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="location_rating" value="<?php echo $i; ?>" id="location_<?php echo $i; ?>" required>
                                <label for="location_<?php echo $i; ?>" class="star-label"><i class="fas fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- Service Rating -->
                        <div class="mb-4">
                            <label class="form-label fw-bold"><?php echo t('review_service'); ?> *</label>
                            <div class="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="service_rating" value="<?php echo $i; ?>" id="service_<?php echo $i; ?>" required>
                                <label for="service_<?php echo $i; ?>" class="star-label"><i class="fas fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- Facilities Rating -->
                        <div class="mb-4">
                            <label class="form-label fw-bold"><?php echo t('review_facilities'); ?> *</label>
                            <div class="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="facilities_rating" value="<?php echo $i; ?>" id="facilities_<?php echo $i; ?>" required>
                                <label for="facilities_<?php echo $i; ?>" class="star-label"><i class="fas fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- Cleanliness Rating -->
                        <div class="mb-4">
                            <label class="form-label fw-bold"><?php echo t('review_cleanliness'); ?> *</label>
                            <div class="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="cleanliness_rating" value="<?php echo $i; ?>" id="cleanliness_<?php echo $i; ?>" required>
                                <label for="cleanliness_<?php echo $i; ?>" class="star-label"><i class="fas fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- Value Rating -->
                        <div class="mb-4">
                            <label class="form-label fw-bold"><?php echo t('review_value'); ?> *</label>
                            <div class="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="value_rating" value="<?php echo $i; ?>" id="value_<?php echo $i; ?>" required>
                                <label for="value_<?php echo $i; ?>" class="star-label"><i class="fas fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- Education Rating -->
                        <div class="mb-4">
                            <label class="form-label fw-bold"><?php echo t('review_education'); ?> *</label>
                            <div class="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="education_rating" value="<?php echo $i; ?>" id="education_<?php echo $i; ?>" required>
                                <label for="education_<?php echo $i; ?>" class="star-label"><i class="fas fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- Comment -->
                        <div class="mb-4">
                            <label for="comment" class="form-label fw-bold"><?php echo t('review_comment'); ?></label>
                            <textarea class="form-control" id="comment" name="comment" rows="5" placeholder="<?php echo t('review_comment'); ?>"></textarea>
                        </div>
                        
                        <!-- Photos -->
                        <div class="mb-4">
                            <label for="photos" class="form-label fw-bold"><?php echo t('review_photos'); ?></label>
                            <input type="file" class="form-control" id="photos" name="photos[]" multiple accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" max="10">
                            <small class="text-muted">Maximum 10 images, 5MB each</small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i><?php echo t('review_submit'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 5px;
}

.star-rating input[type="radio"] {
    display: none;
}

.star-rating label {
    font-size: 2rem;
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s;
}

.star-rating input[type="radio"]:checked ~ label,
.star-rating label:hover,
.star-rating label:hover ~ label {
    color: #FFD966;
}

.star-rating input[type="radio"]:checked ~ label {
    color: #FFD966;
}
</style>

<?php require_once 'includes/footer.php'; ?>

