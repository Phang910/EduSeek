<?php
/**
 * School Details Page
 * Displays school information, reviews, photos, FAQs, and interest form
 */

$page_title = 'School Details';
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';
require_once 'includes/header.php';

// Get school ID
$school_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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

// Get reviews and ratings
$rating_data = getSchoolAverageRating($school_id);
$current_user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
$initial_limit = 10; // Show first 10 reviews
$reviews = getSchoolReviews($school_id, $initial_limit, 0, $current_user_id);
$total_reviews = getSchoolReviewCount($school_id, $current_user_id);
$has_more_reviews = ($total_reviews > $initial_limit);
$photos = getSchoolPhotos($school_id);
$faqs = getSchoolFAQs($school_id);
$external_reviews = getSchoolExternalReviews($school_id);
$highlights = getSchoolHighlights($school_id);
$facilities = getSchoolFacilities($school_id);
$similar_schools = getSimilarSchools($school_id, 6);

// Prepare custom information fields for display
$custom_field_definitions = [
    'classes' => ['label' => 'Classes'],
    'principal' => ['label' => 'Principal'],
    'founded' => [
        'label' => 'Founded',
        'formatter' => function ($value) {
            $value = trim((string) $value);
            if ($value === '') {
                return '';
            }
            $date = DateTime::createFromFormat('Y-m-d', $value);
            return $date ? $date->format('F j, Y') : $value;
        }
    ],
    'established_year' => ['label' => 'Established Year'],
    'chairman' => ['label' => 'Chairman'],
    'sister_schools' => [
        'label' => 'Sister Schools',
        'formatter' => function ($value) {
            $parts = array_filter(array_map('trim', preg_split('/[,\\n]+/', (string) $value)));
            return $parts ? implode(', ', $parts) : trim((string) $value);
        }
    ],
    'student_capacity' => [
        'label' => 'Student Capacity',
        'formatter' => function ($value) {
            if ($value === null || $value === '') {
                return '';
            }
            $numeric = is_numeric($value) ? (int) $value : $value;
            return is_int($numeric) ? number_format($numeric) . ' students' : trim((string) $value);
        }
    ],
    'accreditation' => ['label' => 'Accreditation', 'full_width' => true],
    'motto' => ['label' => 'Motto', 'full_width' => true],
    'vision' => ['label' => 'Vision', 'full_width' => true],
    'mission' => ['label' => 'Mission', 'full_width' => true],
];

$custom_fields = [];
foreach ($custom_field_definitions as $field_key => $meta) {
    $raw_value = $school[$field_key] ?? null;
    if ($raw_value === null) {
        continue;
    }
    if (is_string($raw_value)) {
        $raw_value = trim($raw_value);
    }
    if ($raw_value === '' || $raw_value === []) {
        continue;
    }

    $formatted_value = $raw_value;
    if (isset($meta['formatter']) && is_callable($meta['formatter'])) {
        $formatted_value = call_user_func($meta['formatter'], $raw_value);
    } elseif ($field_key === 'student_capacity' && is_numeric($raw_value)) {
        $formatted_value = number_format((int) $raw_value) . ' students';
    }

    if ($formatted_value === '' || $formatted_value === null) {
        continue;
    }

    $custom_fields[] = [
        'label' => $meta['label'],
        'value' => $formatted_value,
        'col_class' => !empty($meta['full_width']) ? 'col-12' : 'col-12 col-md-6'
    ];
}

// Get rating breakdown
$rating_breakdown = getSchoolRatingBreakdown($school_id);

// Rating categories metadata and top category calculation
$rating_categories_meta = [
    'location_rating' => ['label' => 'Location', 'icon' => 'fas fa-map-marker-alt'],
    'service_rating' => ['label' => 'Service', 'icon' => 'fas fa-concierge-bell'],
    'facilities_rating' => ['label' => 'Facilities', 'icon' => 'fas fa-building'],
    'cleanliness_rating' => ['label' => 'Cleanliness', 'icon' => 'fas fa-broom'],
    'value_rating' => ['label' => 'Value for Money', 'icon' => 'fas fa-dollar-sign'],
    'education_rating' => ['label' => 'Education Quality', 'icon' => 'fas fa-graduation-cap'],
];

$top_rating_categories = [];
foreach ($rating_categories_meta as $key => $meta) {
    $category_rating = floatval($rating_breakdown[$key]['rating'] ?? 0);
    $category_count = intval($rating_breakdown[$key]['count'] ?? 0);
    if ($category_count > 0) {
        $top_rating_categories[] = [
            'key' => $key,
            'label' => $meta['label'],
            'icon' => $meta['icon'],
            'rating' => $category_rating,
            'count' => $category_count,
        ];
    }
}

usort($top_rating_categories, function ($a, $b) {
    if ($b['rating'] === $a['rating']) {
        return $b['count'] <=> $a['count'];
    }
    return $b['rating'] <=> $a['rating'];
});
$top_rating_categories = array_slice($top_rating_categories, 0, 3);

// Prepare description bullet points
$description_points = [];
if (!empty($school['description'])) {
    $raw_points = preg_split('/\r\n|\n|\r|•/', $school['description']);
    if ($raw_points && is_array($raw_points)) {
        foreach ($raw_points as $point) {
            $point = trim($point, " \t\n\r\0\x0B-•");
            if ($point !== '') {
                $description_points[] = $point;
            }
        }
    }
    if (count($description_points) <= 1) {
        $sentence_points = preg_split('/\.\s+/', $school['description']);
        if ($sentence_points && is_array($sentence_points)) {
            $description_points = [];
            foreach ($sentence_points as $sentence) {
                $sentence = trim($sentence, " \t\n\r\0\x0B-•.");
                if ($sentence !== '') {
                    $description_points[] = $sentence;
                }
            }
        }
    }
}

// Google Maps URL for overview section
$maps_query = '';
if (!empty($school['latitude']) && !empty($school['longitude'])) {
    $maps_query = $school['latitude'] . ',' . $school['longitude'];
} elseif (!empty($school['address'])) {
    $maps_query = $school['address'];
}
$google_maps_url = $maps_query ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($maps_query) : '';

// Hero photo assets
$primary_photo_url = getSchoolImage($school_id, $school['image'] ?? null);
$primary_is_placeholder = strpos($primary_photo_url, 'school-default') !== false;

$gallery_photos = [];
if (!empty($photos) && is_array($photos)) {
    foreach ($photos as $index => $photo) {
        if (empty($photo['photo_path'])) {
            continue;
        }

        $photo_url = SITE_URL . 'uploads/schools/' . $photo['photo_path'];

        if ($primary_is_placeholder) {
            // Use the first available uploaded photo as the main photo when no custom image is set
            $primary_photo_url = $photo_url;
            $primary_is_placeholder = false;
            continue;
        }

        if ($photo_url === $primary_photo_url) {
            // Skip duplicates if the primary photo comes from the gallery uploads
            continue;
        }

        $gallery_photos[] = $photo;
    }
}

$modal_photos = [];
if (!empty($primary_photo_url)) {
    $modal_photos[] = $primary_photo_url;
}
if (!empty($gallery_photos)) {
    foreach ($gallery_photos as $gallery_photo) {
        $modal_photos[] = SITE_URL . 'uploads/schools/' . $gallery_photo['photo_path'];
    }
}

$modal_photos = array_values(array_unique(array_filter($modal_photos)));

$you_might_like = array_slice($similar_schools ?? [], 0, 3);

$comparison_school = $you_might_like[0] ?? null;
$comparison_school_rating = null;
$comparison_breakdown = [];
if ($comparison_school) {
    $comparison_school_rating = getSchoolAverageRating($comparison_school['id']);
    $comparison_breakdown = getSchoolRatingBreakdown($comparison_school['id']);
}

// Prepare operating hours summary strings
$operating_hours_display = [];
if (!empty($school['operating_hours'])) {
    $operating_hours_data = json_decode($school['operating_hours'], true);
    if ($operating_hours_data && is_array($operating_hours_data)) {
        $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        if (!empty($operating_hours_data['schedules']) && is_array($operating_hours_data['schedules'])) {
            foreach ($operating_hours_data['schedules'] as $schedule) {
                if (empty($schedule['days']) || !is_array($schedule['days'])) {
                    continue;
                }
                $sortedDays = [];
                foreach ($dayOrder as $orderedDay) {
                    if (in_array($orderedDay, $schedule['days'])) {
                        $sortedDays[] = $orderedDay;
                    }
                }
                $day_abbrevs = [];
                foreach ($sortedDays as $day) {
                    $index = array_search($day, $dayOrder);
                    $day_abbrevs[] = $dayLabels[$index];
                }
                $days_str = implode(', ', $day_abbrevs);
                if (!empty($schedule['is24Hours'])) {
                    $operating_hours_display[] = $days_str . ': Open 24 hours';
                } elseif (!empty($schedule['isClosed'])) {
                    $operating_hours_display[] = $days_str . ': Closed';
                } elseif (!empty($schedule['timeSlots']) && is_array($schedule['timeSlots'])) {
                    $time_parts = [];
                    foreach ($schedule['timeSlots'] as $slot) {
                        if (!empty($slot['open']) && !empty($slot['close'])) {
                            $time_parts[] = $slot['open'] . ' - ' . $slot['close'];
                        }
                    }
                    if (!empty($time_parts)) {
                        $operating_hours_display[] = $days_str . ': ' . implode(', ', $time_parts);
                    }
                }
            }
        } elseif (!empty($operating_hours_data['days'])) {
            $sortedDays = [];
            foreach ($dayOrder as $orderedDay) {
                if (in_array($orderedDay, $operating_hours_data['days'])) {
                    $sortedDays[] = $orderedDay;
                }
            }
            foreach ($operating_hours_data['days'] as $day) {
                if (!in_array($day, $sortedDays)) {
                    $sortedDays[] = $day;
                }
            }
            $day_abbrevs = [];
            foreach ($sortedDays as $day) {
                $index = array_search($day, $dayOrder);
                if ($index !== false) {
                    $day_abbrevs[] = $dayLabels[$index];
                }
            }
            $days_str = implode(', ', $day_abbrevs);
            if (!empty($operating_hours_data['is24Hours'])) {
                $operating_hours_display[] = $days_str . ': Open 24 hours';
            } elseif (!empty($operating_hours_data['isClosed'])) {
                $operating_hours_display[] = $days_str . ': Closed';
            } elseif (!empty($operating_hours_data['timeSlots']) && is_array($operating_hours_data['timeSlots'])) {
                $time_parts = [];
                foreach ($operating_hours_data['timeSlots'] as $slot) {
                    if (!empty($slot['open']) && !empty($slot['close'])) {
                        $time_parts[] = $slot['open'] . ' - ' . $slot['close'];
                    }
                }
                if (!empty($time_parts)) {
                    $operating_hours_display[] = $days_str . ': ' . implode(', ', $time_parts);
                }
            }
        }
    }
}

$back_params = [];
if (isset($_GET['sort_by']) && !empty($_GET['sort_by'])) {
    $back_params['sort_by'] = $_GET['sort_by'];
}
if (isset($_GET['sort_order']) && !empty($_GET['sort_order'])) {
    $back_params['sort_order'] = $_GET['sort_order'];
}
if (isset($_GET['level']) && !empty($_GET['level'])) {
    $back_params['level'] = $_GET['level'];
}
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $back_params['search'] = $_GET['search'];
}

// Get review keywords
$review_keywords = extractReviewKeywords($school_id);

// Handle review filtering and sorting
$selected_keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$review_sort = isset($_GET['review_sort']) ? $_GET['review_sort'] : 'most_recent';

// If filtering by keyword, fetch all reviews
if (!empty($selected_keyword)) {
    $reviews = getSchoolReviews($school_id, 1000, 0, $current_user_id); // Fetch large number for filtering
    $total_reviews = count($reviews);
}

// Filter reviews by keyword if selected
if (!empty($selected_keyword) && !empty($reviews)) {
    $filtered_reviews = [];
    foreach ($reviews as $review) {
        $text = strtolower($review['comment'] ?? '');
        if (stripos($text, strtolower($selected_keyword)) !== false) {
            $filtered_reviews[] = $review;
        }
    }
    $reviews = $filtered_reviews;
    $total_reviews = count($filtered_reviews);
    $has_more_reviews = false; // Disable pagination when filtering
}

// Sort reviews while keeping current user's reviews first
if (!empty($reviews)) {
    $own_reviews = [];
    $other_reviews = [];

    if ($current_user_id) {
        foreach ($reviews as $review_item) {
            if (intval($review_item['user_id'] ?? 0) === intval($current_user_id)) {
                $own_reviews[] = $review_item;
            } else {
                $other_reviews[] = $review_item;
            }
        }
    } else {
        $other_reviews = $reviews;
    }

    $sortByDateDesc = function($a, $b) {
        $date_a = strtotime($a['created_at'] ?? '0');
        $date_b = strtotime($b['created_at'] ?? '0');
        return $date_b <=> $date_a;
    };

    $comparator = null;
    switch ($review_sort) {
        case 'rating_high':
            $comparator = function($a, $b) use ($sortByDateDesc) {
                $rating_a = floatval($a['overall_rating'] ?? 0);
                $rating_b = floatval($b['overall_rating'] ?? 0);
                if ($rating_a == $rating_b) {
                    return $sortByDateDesc($a, $b);
                }
                return $rating_b <=> $rating_a;
            };
            break;
        case 'rating_low':
            $comparator = function($a, $b) use ($sortByDateDesc) {
                $rating_a = floatval($a['overall_rating'] ?? 0);
                $rating_b = floatval($b['overall_rating'] ?? 0);
                if ($rating_a == $rating_b) {
                    return $sortByDateDesc($a, $b);
                }
                return $rating_a <=> $rating_b;
            };
            break;
        case 'most_helpful':
            $comparator = function($a, $b) use ($sortByDateDesc) {
                $helpful_a = intval($a['helpful_count'] ?? 0);
                $helpful_b = intval($b['helpful_count'] ?? 0);
                if ($helpful_a !== $helpful_b) {
                    return $helpful_b <=> $helpful_a;
                }
                $rating_a = floatval($a['overall_rating'] ?? 0);
                $rating_b = floatval($b['overall_rating'] ?? 0);
                if ($rating_a !== $rating_b) {
                    return $rating_b <=> $rating_a;
                }
                return $sortByDateDesc($a, $b);
            };
            break;
        case 'most_recent':
        default:
            $comparator = $sortByDateDesc;
            break;
    }

    if ($comparator) {
        if (!empty($own_reviews)) {
            usort($own_reviews, $comparator);
        }
        if (!empty($other_reviews)) {
            usort($other_reviews, $comparator);
        }
    }

    $reviews = array_merge($own_reviews, $other_reviews);
}

// Get user info if logged in (for interest form)
$is_logged_in = isLoggedIn();
$user_info = null;
if ($is_logged_in) {
    $user_info = getUserInfo($_SESSION['user_id']);
}
$is_vendor_user = $is_logged_in && (($user_info['role'] ?? '') === 'vendor');
$is_vendor_approved = $is_vendor_user && (($user_info['vendor_status'] ?? '') === 'approved');

// Check if school has a vendor owner (by account number)
$has_vendor_owner = false;
$vendor_owner_account_number = null;
$is_current_user_owner = false;
$vendor_owner_info = null;
$same_owner_schools = [];

if (!empty($school['vendor_owner_account_number'])) {
    global $conn;
    $vendor_owner_account_number = intval($school['vendor_owner_account_number']);
    // Check if this account number belongs to an approved vendor
    $owner_check = $conn->prepare("SELECT id, name, email, phone_number, contact, unique_number, role, vendor_status FROM users WHERE unique_number = ? AND role = 'vendor' AND vendor_status = 'approved'");
    $owner_check->bind_param("i", $vendor_owner_account_number);
    $owner_check->execute();
    $owner_result = $owner_check->get_result();
    if ($owner_result->num_rows > 0) {
        $has_vendor_owner = true;
        $owner_data = $owner_result->fetch_assoc();
        $vendor_owner_info = getUserInfo($owner_data['id']);
        
        // Fetch other schools owned by the same vendor (limit 3 for display)
        $owner_schools_stmt = $conn->prepare("SELECT id, name, address, level, image, special_label, fee_type, fee_remarks FROM schools WHERE vendor_owner_account_number = ? AND id != ? ORDER BY created_at DESC LIMIT 3");
        $owner_schools_stmt->bind_param("ii", $vendor_owner_account_number, $school_id);
        $owner_schools_stmt->execute();
        $owner_schools_result = $owner_schools_stmt->get_result();
        $same_owner_schools = $owner_schools_result->fetch_all(MYSQLI_ASSOC);
        $owner_schools_stmt->close();
        
        // Check if current user is the owner
        if (isLoggedIn() && $user_info) {
            $current_user_account_number = $user_info['unique_number'] ?? null;
            if ($current_user_account_number && intval($current_user_account_number) === $vendor_owner_account_number) {
                $is_current_user_owner = true;
            }
        }
    }
    $owner_check->close();
}
?>

<style>
    .page-content {
        padding-top: 120px !important;
    }
    .back-link {
        font-weight: 600;
        color: #0d6efd;
        text-decoration: none;
    }
    .back-link:hover {
        text-decoration: underline;
    }
    .main-photo-wrapper {
        border-radius: 1rem;
        overflow: hidden;
        background: #f8f9fb;
    }
    .main-photo-btn {
        display: block;
        padding: 0;
        border: none;
        background: transparent;
        width: 100%;
        cursor: pointer;
    }
    .main-photo-btn:focus {
        outline: none;
        box-shadow: none;
    }
    .main-photo {
        width: 100%;
        aspect-ratio: 16/9;
        object-fit: cover;
        display: block;
    }
    .photo-gallery-header {
        border-bottom: none;
        background: linear-gradient(135deg, var(--primary-blue), #1e63ff);
        color: #fff;
        border-radius: 1.25rem 1.25rem 0 0;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .photo-gallery-header i {
        color: #fff;
        font-size: 1.25rem;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 999px;
        padding: 0.5rem;
    }
    .photo-gallery-header h5 {
        color: #fff;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0;
    }
    .photo-carousel .carousel-item {
        border-radius: 1rem;
        overflow: hidden;
    }
    .photo-carousel-btn {
        display: block;
        border: none;
        background: transparent;
        padding: 0;
        width: 100%;
        cursor: pointer;
    }
    .photo-carousel-btn:focus {
        outline: none;
        box-shadow: none;
    }
    .photo-carousel-image {
        width: 100%;
        aspect-ratio: 4/3;
        object-fit: cover;
        display: block;
    }
    .photo-carousel .carousel-control-prev,
    .photo-carousel .carousel-control-next {
        top: 50%;
        transform: translateY(-50%);
        width: 3rem;
        height: 3rem;
        background: transparent;
        border: none;
        opacity: 1;
        transition: transform 0.2s ease;
        padding: 0;
    }
    .photo-carousel .carousel-control-prev:hover,
    .photo-carousel .carousel-control-next:hover {
        transform: translateY(-50%) scale(1.05);
    }
    .photo-carousel .carousel-control-prev:focus,
    .photo-carousel .carousel-control-next:focus {
        box-shadow: none;
    }
    .photo-carousel .carousel-control-prev-icon,
    .photo-carousel .carousel-control-next-icon {
        width: 100%;
        height: 100%;
        background-size: 70% 70%;
        background-position: center;
        background-repeat: no-repeat;
        filter: none;
    }
    .photo-carousel .carousel-control-prev-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='rgba(0,0,0,0.85)' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M10.5 3.5 6.5 8l4 4.5'/%3e%3c/svg%3e");
    }
    .photo-carousel .carousel-control-next-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='rgba(0,0,0,0.85)' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M5.5 3.5 9.5 8l-4 4.5'/%3e%3c/svg%3e");
    }
    .photo-carousel .carousel-indicators {
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }
    .photo-carousel .carousel-indicators [data-bs-target] {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: rgba(13, 110, 253, 0.25);
    }
    .photo-carousel .carousel-indicators .active {
        width: 26px;
        border-radius: 999px;
        background-color: #0d6efd;
    }
    #photoModal .modal-body {
        position: relative;
        background: #000;
    }
    #modalPhoto {
        width: 100%;
        max-height: 80vh;
        object-fit: contain;
        display: block;
        margin: 0 auto;
        background: #000;
    }
    .modal-photo-nav-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 50%;
        border: none;
        background: rgba(13, 110, 253, 0.9);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        z-index: 20;
        cursor: pointer;
        transition: all 0.25s ease;
        box-shadow: 0 6px 16px rgba(13, 110, 253, 0.35);
    }
    .modal-photo-nav-btn:hover:not(:disabled) {
        background: rgba(79, 163, 247, 1);
        transform: translateY(-50%) scale(1.05);
    }
    .modal-photo-nav-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        box-shadow: none;
    }
    #modalPhotoPrev {
        left: 1.5rem;
    }
    #modalPhotoNext {
        right: 1.5rem;
    }
    .modal-photo-counter {
        position: absolute;
        bottom: 1rem;
        right: 1rem;
        background: rgba(0, 0, 0, 0.6);
        color: #fff;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        font-size: 0.85rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        z-index: 20;
    }
    .sticky-school-card {
        position: sticky;
        top: 80px;
        z-index: 5;
        width: 100%;
        max-width: 380px;
        max-height: 75vh;
        margin-left: auto;
        align-self: flex-start;
    }
    .sticky-school-card .card {
        border-radius: 1.25rem;
        height: 100%;
        max-height: 100%;
        overflow-y: auto;
        scrollbar-gutter: stable;
    }
    .sticky-card-photo {
        width: 100%;
        height: 220px;
        object-fit: cover;
    }
    @media (max-width: 576px) {
        .sticky-card-photo {
            height: 200px;
        }
    }
    .sticky-school-card h4 {
        font-size: 0.98rem;
        font-weight: 700;
    }
    .collection-grid {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.55rem;
        margin-bottom: 0.65rem;
        flex-wrap: nowrap;
        font-size: 0.9rem;
    }
    .collection-grid-single {
        justify-content: flex-start;
    }
    .collection-grid-right {
        justify-content: center;
    }
    .collection-grid:last-child {
        margin-bottom: 0;
    }
    .collection-action {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        flex: 1 1 auto;
    }
    .collection-action .collection-label {
        margin: 0;
        font-weight: 600;
        color: #0d6efd;
        white-space: nowrap;
        font-size: 0.9rem;
    }
    .sticky-school-card .school-card-heart-btn {
        position: static !important;
        top: auto !important;
        right: auto !important;
        margin: 0 !important;
        width: 34px !important;
        height: 34px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: linear-gradient(135deg, #ffd54f, #ff9800) !important;
        box-shadow: 0 3px 10px rgba(255, 152, 0, 0.35) !important;
        border-radius: 999px !important;
        padding: 0 !important;
    }
    .sticky-school-card .school-card-heart-btn i {
        color: #2c2c2c !important;
        font-size: 0.9rem !important;
    }
    .collection-link {
        color: #0d6efd;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        font-size: 0.9rem;
    }
    .collection-link:hover {
        text-decoration: underline;
    }
    .collection-link i {
        margin-right: 0.35rem;
        font-size: 0.85rem;
    }
    .rating-stars {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .rating-stars i {
        color: #ffc107;
        font-size: 0.9rem;
    }
    .review-metric {
        background: #f8f9fb;
        border-radius: 12px;
        padding: 12px;
        height: 100%;
    }
    .review-metric .label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #6c757d;
        display: block;
        margin-bottom: 4px;
    }
    .review-metric .value {
        font-weight: 600;
    }
    .vendor-school-card {
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }
    .vendor-school-image {
        height: 160px;
        object-fit: cover;
        border-radius: 12px;
        width: 100%;
    }
    .info-pill {
        background: #f8f9fb;
        border-radius: 12px;
        padding: 16px;
        height: 100%;
    }
    .comparison-thumb {
        width: 72px;
        height: 72px;
        object-fit: cover;
        border-radius: 12px;
        background: #f0f2f5;
    }
    .review-tab-nav {
        margin: 0;
        padding: 0 1rem;
    }
    .review-tab-nav .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        padding: 0.75rem 1rem;
        background: #fff;
        color: #6c757d;
        border-radius: 0;
        transition: all 0.3s ease;
    }
    .review-tab-nav .nav-link.active {
        color: #212529;
        border-bottom-color: #0d6efd;
        background: #fff;
    }
    .review-tab-nav .nav-link:hover:not(.active) {
        color: #212529;
        border-bottom-color: #0d6efd;
    }
    .review-card .review-avatar {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        object-fit: cover;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
    }
    .review-status-badge {
        font-size: 0.7rem;
        letter-spacing: 0.02em;
    }
    .review-overall-badge {
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-weight: 600;
        font-size: 0.9rem;
        border-radius: 999px;
        padding: 0.4rem 0.8rem;
        box-shadow: 0 4px 14px rgba(255, 193, 7, 0.28);
    }
    .review-overall-badge .fa-star {
        color: #f4b400;
    }
    .review-helpful-btn {
        transition: all 0.2s ease;
    }
    .review-helpful-btn:hover {
        transform: translateY(-1px);
    }
    .review-helpful-btn.active {
        background-color: var(--bs-success);
        border-color: var(--bs-success);
        color: #fff;
    }
    .tooltip.review-rating-tooltip .tooltip-inner {
        max-width: 320px;
        padding: 0;
        border-radius: 12px;
        text-align: left;
        background: #fff;
        color: #212529;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.18);
    }
    .review-rating-tooltip-container {
        padding: 1rem 1.05rem;
    }
    .review-rating-tooltip-header {
        font-weight: 700;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        padding-bottom: 0.5rem;
    }
    .review-rating-tooltip-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.65rem 1.1rem;
    }
    .review-rating-tooltip-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.85rem;
    }
    .review-rating-tooltip-item .label {
        color: #6c757d;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-weight: 500;
    }
    .review-rating-tooltip-item .value {
        font-weight: 600;
        color: #0d6efd;
    }
    .review-rating-tooltip-footer {
        margin-top: 0.9rem;
        padding-top: 0.65rem;
        border-top: 1px solid rgba(15, 23, 42, 0.08);
        font-size: 0.8rem;
        color: #6c757d;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    @media (max-width: 991.98px) {
        .sticky-school-card {
            position: static;
            top: auto;
            max-width: none;
            max-height: none;
            margin-left: 0;
            margin-bottom: 1.5rem;
        }
        .sticky-school-card .card {
            overflow: visible;
        }
        .photo-carousel-image {
            aspect-ratio: 3/2;
        }
        .photo-carousel .carousel-control-prev,
        .photo-carousel .carousel-control-next {
            width: 2.75rem;
            height: 2.75rem;
        }
    }
    @media (max-width: 768px) {
        .photo-carousel-image {
            aspect-ratio: 16/9;
        }
        #modalPhotoPrev,
        #modalPhotoNext {
            width: 3rem;
            height: 3rem;
            font-size: 1.2rem;
        }
    }
    @media (max-width: 576px) {
        .photo-carousel-image {
            aspect-ratio: 4/3;
        }
        .modal-photo-counter {
            right: 0.75rem;
            bottom: 0.75rem;
        }
    }
</style>

<div class="container page-content py-4">
    <div class="row align-items-center mb-3">
        <div class="col-auto">
            <a href="directory.php<?php echo !empty($back_params) ? '?' . http_build_query($back_params) : ''; ?>" class="back-link d-inline-flex align-items-center text-primary fw-semibold text-decoration-none">
                <i class="fas fa-arrow-left me-2"></i><?php echo t('btn_back'); ?>
            </a>
        </div>
    </div>
    <div class="d-flex align-items-start flex-wrap gap-3 mb-4">
        <h1 class="page-title mb-0"><?php echo htmlspecialchars($school['name']); ?></h1>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if (!empty($school['special_label'])): ?>
            <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($school['special_label']); ?></span>
            <?php endif; ?>
            <?php if (!empty($school['level'])): ?>
            <span class="badge bg-primary"><?php echo htmlspecialchars($school['level']); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="row g-4 align-items-start">
        <div class="col-xl-8 col-lg-7">
            <section id="mainPhotoSection" class="card shadow-soft mb-4">
                <div class="card-body">
                    <div class="main-photo-wrapper">
                        <button type="button" class="main-photo-btn" data-bs-toggle="modal" data-bs-target="#photoModal" data-photo="<?php echo htmlspecialchars($primary_photo_url); ?>" data-photo-index="0" aria-label="View full photo">
                            <img src="<?php echo htmlspecialchars($primary_photo_url); ?>" alt="<?php echo htmlspecialchars($school['name']); ?> photo" class="main-photo">
                        </button>
                    </div>
                </div>
            </section>

            <section id="schoolInfoSection" class="card shadow-soft mb-4">
                <div class="card-header border-0 bg-white">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-info-circle text-primary"></i>School information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <?php if (!empty($school['level'])): ?>
                        <div class="col-sm-6">
                            <span class="text-muted small text-uppercase d-block">Level</span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($school['level']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($school['school_type'])): ?>
                        <div class="col-sm-6">
                            <span class="text-muted small text-uppercase d-block">School type</span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($school['school_type']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($school['languages_used'])): 
                            $languages = json_decode($school['languages_used'], true);
                            if (is_array($languages) && !empty($languages)):
                        ?>
                        <div class="col-sm-6">
                            <span class="text-muted small text-uppercase d-block">Languages</span>
                            <span class="fw-semibold"><?php echo implode(', ', array_map('htmlspecialchars', $languages)); ?></span>
                        </div>
                        <?php endif; endif; ?>
                        <?php if (!empty($school['fee_type'])): ?>
                        <div class="col-sm-6">
                            <span class="text-muted small text-uppercase d-block">Budget</span>
                            <span class="fw-semibold text-primary"><?php echo formatFeeStructure($school); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($school['available_seats'])): ?>
                        <div class="col-sm-6">
                            <span class="text-muted small text-uppercase d-block">Available seats</span>
                            <span class="fw-semibold"><?php echo intval($school['available_seats']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($operating_hours_display)): ?>
                    <div class="mb-3">
                        <span class="text-muted small text-uppercase d-block mb-2">Operating hours</span>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($operating_hours_display as $display): ?>
                            <li class="text-muted"><?php echo htmlspecialchars($display); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($custom_fields)): ?>
                    <div class="mb-3">
                        <span class="text-muted small text-uppercase d-block mb-2">Key facts</span>
                        <div class="row g-3">
                            <?php foreach ($custom_fields as $field): ?>
                            <div class="<?php echo htmlspecialchars($field['col_class']); ?>">
                                <div class="p-3 bg-light rounded-3 h-100">
                                    <span class="text-muted small d-block text-uppercase"><?php echo htmlspecialchars($field['label']); ?></span>
                                    <div class="fw-semibold text-dark"><?php echo nl2br(htmlspecialchars($field['value'])); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($description_points)): ?>
                    <div>
                        <span class="text-muted small text-uppercase d-block mb-2">About this school</span>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($description_points as $point): ?>
                            <li><?php echo htmlspecialchars($point); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php elseif (!empty($school['description'])): ?>
                    <div>
                        <span class="text-muted small text-uppercase d-block mb-2">About this school</span>
                        <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($school['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (!empty($highlights)): ?>
            <section id="highlightsSection" class="card shadow-soft mb-4">
                <div class="card-header border-0 bg-white">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-tag text-primary"></i>Highlights
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <?php foreach ($highlights as $highlight): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="p-3 bg-light rounded-3 h-100 d-flex align-items-center gap-3">
                                <i class="<?php echo htmlspecialchars($highlight['icon']); ?> text-primary fa-lg"></i>
                                <span class="fw-semibold"><?php echo htmlspecialchars($highlight['name']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($facilities)): ?>
            <section id="facilitiesSection" class="card shadow-soft mb-4">
                <div class="card-header border-0 bg-white">
                    <div>
                        <h5 class="mb-0 d-flex align-items-center gap-2">
                            <i class="fas fa-school text-primary"></i>Facilities
                        </h5>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <?php foreach ($facilities as $facility): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="p-3 bg-light rounded-3 h-100 d-flex align-items-center gap-3">
                                <i class="<?php echo htmlspecialchars($facility['icon']); ?> text-primary fa-lg"></i>
                                <span class="fw-semibold"><?php echo htmlspecialchars($facility['name']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($has_vendor_owner && !empty($same_owner_schools)): ?>
            <section id="ownerSchoolsSection" class="card shadow-soft mb-4">
                <div class="card-header border-0 bg-white">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-briefcase text-primary"></i>From the same vendor
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <?php foreach ($same_owner_schools as $owner_school): 
                            $owner_school_rating = getSchoolAverageRating($owner_school['id']);
                        ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="vendor-school-card border rounded-3 h-100 p-3">
                                <a href="school-details.php?id=<?php echo $owner_school['id']; ?>" class="d-block mb-3">
                                    <img src="<?php echo getSchoolImage($owner_school['id'], $owner_school['image'] ?? null); ?>" alt="<?php echo htmlspecialchars($owner_school['name']); ?>" class="vendor-school-image w-100 rounded">
                                </a>
                                <h6 class="mb-1">
                                    <a href="school-details.php?id=<?php echo $owner_school['id']; ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($owner_school['name']); ?></a>
                                </h6>
                                <?php if ($owner_school_rating['count'] > 0): ?>
                                <div class="d-flex align-items-center gap-2 text-warning small">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="<?php echo ($owner_school_rating['rating'] >= $i ? 'fas' : 'far'); ?> fa-star"></i>
                                    <?php endfor; ?>
                                    <span class="text-muted ms-1"><?php echo number_format($owner_school_rating['rating'], 1); ?>/5</span>
                                </div>
                                <small class="text-muted d-block mb-2"><?php echo $owner_school_rating['count']; ?> review<?php echo $owner_school_rating['count'] == 1 ? '' : 's'; ?></small>
                                <?php else: ?>
                                <small class="text-muted d-block mb-2">No reviews yet</small>
                                <?php endif; ?>
                                <?php if (!empty($owner_school['fee_type'])): ?>
                                <div class="small text-muted">Budget: <?php echo formatFeeStructure($owner_school); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <section id="vendorInfoSection" class="card shadow-soft mb-4">
                <div class="card-header border-0 bg-white">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-id-badge text-primary"></i>Vendor information
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <?php if ($vendor_owner_info): ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-pill">
                                <span class="text-muted small d-block text-uppercase">Owner</span>
                                <span class="fw-semibold"><?php echo htmlspecialchars($vendor_owner_info['name'] ?? ''); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-pill">
                                <span class="text-muted small d-block text-uppercase">Account number</span>
                                <span class="fw-semibold"><?php echo htmlspecialchars($vendor_owner_account_number); ?></span>
                            </div>
                        </div>
                        <?php if (!empty($vendor_owner_info['phone_number']) || !empty($vendor_owner_info['contact'])): ?>
                        <div class="col-md-6">
                            <div class="info-pill">
                                <span class="text-muted small d-block text-uppercase">Phone</span>
                                <span><?php echo htmlspecialchars($vendor_owner_info['phone_number'] ?? $vendor_owner_info['contact'] ?? '-'); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($vendor_owner_info['email'])): ?>
                        <div class="col-md-6">
                            <div class="info-pill">
                                <span class="text-muted small d-block text-uppercase">Email</span>
                                <a href="mailto:<?php echo htmlspecialchars($vendor_owner_info['email']); ?>" class="text-decoration-none"><?php echo htmlspecialchars($vendor_owner_info['email']); ?></a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-light border mb-0">
                        This school has not been claimed yet.
                        <a href="#" class="text-primary fw-semibold ms-1" data-bs-toggle="modal" data-bs-target="#ownBusinessModal">Own this business?</a>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (!empty($you_might_like)): ?>
            <section id="recommendationsSection" class="card shadow-soft mb-4">
                <div class="card-header border-0 bg-white">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-thumbs-up text-primary"></i>You might like
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <?php foreach ($you_might_like as $similar_school): 
                            $similar_rating = getSchoolAverageRating($similar_school['id']);
                        ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="border rounded-3 h-100 overflow-hidden">
                                <a href="school-details.php?id=<?php echo $similar_school['id']; ?>" class="d-block">
                                    <img src="<?php echo getSchoolImage($similar_school['id'], $similar_school['image'] ?? null); ?>" alt="<?php echo htmlspecialchars($similar_school['name']); ?>" class="vendor-school-image w-100">
                                </a>
                                <div class="p-3">
                                    <h6 class="mb-1"><a href="school-details.php?id=<?php echo $similar_school['id']; ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($similar_school['name']); ?></a></h6>
                                    <?php if ($similar_rating['count'] > 0): ?>
                                    <div class="d-flex align-items-center gap-2 text-warning small">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="<?php echo ($similar_rating['rating'] >= $i ? 'fas' : 'far'); ?> fa-star"></i>
                                        <?php endfor; ?>
                                        <span class="text-muted ms-1"><?php echo number_format($similar_rating['rating'], 1); ?>/5</span>
                                    </div>
                                    <small class="text-muted d-block mb-2"><?php echo $similar_rating['count']; ?> review<?php echo $similar_rating['count'] == 1 ? '' : 's'; ?></small>
                                    <?php else: ?>
                                    <small class="text-muted d-block mb-2">No reviews yet</small>
                                    <?php endif; ?>
                                    <?php if (!empty($similar_school['fee_type'])): ?>
                                    <div class="small text-muted">Budget: <?php echo formatFeeStructure($similar_school); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($faqs)): ?>
            <section id="faqSection" class="card shadow-soft mb-4">
                <div class="card-header border-0 bg-white">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-question-circle text-primary"></i>Frequently asked questions
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="accordion" id="faqAccordion">
                        <?php foreach (array_slice($faqs, 0, 3) as $index => $faq): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faqHeading<?php echo $faq['id']; ?>">
                                <button class="accordion-button <?php echo $index == 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse<?php echo $faq['id']; ?>" aria-expanded="<?php echo $index == 0 ? 'true' : 'false'; ?>">
                                    <?php echo htmlspecialchars($faq['question']); ?>
                                </button>
                            </h2>
                            <div id="faqCollapse<?php echo $faq['id']; ?>" class="accordion-collapse collapse <?php echo $index == 0 ? 'show' : ''; ?>" aria-labelledby="faqHeading<?php echo $faq['id']; ?>">
                                <div class="accordion-body">
                                    <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <section id="comparisonSection" class="card shadow-soft mb-4">
                <div class="card-header border-0 bg-white">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-balance-scale text-primary"></i>Compare schools
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="comparison-card h-100 border rounded-3 p-3">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <img src="<?php echo htmlspecialchars($primary_photo_url); ?>" alt="<?php echo htmlspecialchars($school['name']); ?>" class="comparison-thumb">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($school['name']); ?></h6>
                                        <?php if ($rating_data['count'] > 0): ?>
                                        <div class="d-flex align-items-center gap-2 text-warning small">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo ($rating_data['rating'] >= $i ? 'fas' : 'far'); ?> fa-star"></i>
                                            <?php endfor; ?>
                                            <span class="text-muted ms-1"><?php echo number_format($rating_data['rating'], 1); ?>/5</span>
                                        </div>
                                        <small class="text-muted"><?php echo $rating_data['count']; ?> review<?php echo $rating_data['count'] == 1 ? '' : 's'; ?></small>
                                        <?php else: ?>
                                        <small class="text-muted">No reviews yet</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row g-2">
                                    <?php foreach ($rating_categories_meta as $key => $meta): 
                                        $metric_rating = floatval($rating_breakdown[$key]['rating'] ?? 0);
                                        $metric_count = intval($rating_breakdown[$key]['count'] ?? 0);
                                    ?>
                                    <div class="col-6">
                                        <div class="review-metric h-100">
                                            <span class="label"><?php echo $meta['label']; ?></span>
                                            <span class="value"><?php echo $metric_count ? number_format($metric_rating, 1) . '/5' : '--'; ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <?php if ($comparison_school): ?>
                            <div class="comparison-card h-100 border rounded-3 p-3">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <img src="<?php echo getSchoolImage($comparison_school['id'], $comparison_school['image'] ?? null); ?>" alt="<?php echo htmlspecialchars($comparison_school['name']); ?>" class="comparison-thumb">
                                    <div>
                                        <h6 class="mb-1"><a href="school-details.php?id=<?php echo $comparison_school['id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($comparison_school['name']); ?></a></h6>
                                        <?php if (!empty($comparison_school_rating['count'])): ?>
                                        <div class="d-flex align-items-center gap-2 text-warning small">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo ($comparison_school_rating['rating'] >= $i ? 'fas' : 'far'); ?> fa-star"></i>
                                            <?php endfor; ?>
                                            <span class="text-muted ms-1"><?php echo number_format($comparison_school_rating['rating'], 1); ?>/5</span>
                                        </div>
                                        <small class="text-muted"><?php echo $comparison_school_rating['count']; ?> review<?php echo $comparison_school_rating['count'] == 1 ? '' : 's'; ?></small>
                                        <?php else: ?>
                                        <small class="text-muted">No reviews yet</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row g-2">
                                    <?php foreach ($rating_categories_meta as $key => $meta): 
                                        $comparison_metric_rating = floatval($comparison_breakdown[$key]['rating'] ?? 0);
                                        $comparison_metric_count = intval($comparison_breakdown[$key]['count'] ?? 0);
                                    ?>
                                    <div class="col-6">
                                        <div class="review-metric h-100">
                                            <span class="label"><?php echo $meta['label']; ?></span>
                                            <span class="value"><?php echo $comparison_metric_count ? number_format($comparison_metric_rating, 1) . '/5' : '--'; ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="comparison-card h-100 border rounded-3 p-3 d-flex align-items-center justify-content-center text-center text-muted">
                                <div>
                                    <i class="fas fa-search fa-2x mb-3"></i>
                                    <p class="mb-0">More schools coming soon for comparison.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section id="fullReviewSection" class="card shadow-soft mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0"><i class="fas fa-star me-2"></i><?php echo t('school_reviews'); ?></h5>
                    <?php if (isLoggedIn()): ?>
                    <a href="add_review.php?school_id=<?php echo $school_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i><?php echo t('review_write'); ?>
                    </a>
                    <?php endif; ?>
                </div>

                <ul class="nav nav-tabs border-bottom review-tab-nav" id="reviewTypeTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo empty($_GET['review_type']) || $_GET['review_type'] == 'platform' ? 'active' : ''; ?>" id="platform-tab" data-bs-toggle="tab" data-bs-target="#platform-reviews" type="button" role="tab">
                            <i class="fas fa-star me-2"></i>Platform Reviews
                        </button>
                    </li>
                    <?php if (!empty($external_reviews)): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo isset($_GET['review_type']) && $_GET['review_type'] == 'external' ? 'active' : ''; ?>" id="external-tab" data-bs-toggle="tab" data-bs-target="#external-reviews" type="button" role="tab">
                            <i class="fas fa-external-link-alt me-2"></i>External Reviews
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>

                <div class="tab-content" id="reviewTypeTabContent">
                    <div class="tab-pane fade <?php echo empty($_GET['review_type']) || $_GET['review_type'] == 'platform' ? 'show active' : ''; ?>" id="platform-reviews" role="tabpanel">
                        <?php if ($rating_data['count'] > 0): ?>
                        <div class="card-body border-bottom p-4 bg-light">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <div class="text-center p-3 bg-white rounded shadow-sm">
                                        <div class="display-3 fw-bold text-primary mb-2"><?php echo number_format($rating_data['rating'], 1); ?><span class="fs-4 text-muted">/5</span></div>
                                        <div class="mb-2">
                                            <?php $stars = round($rating_data['rating']); for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo $i <= $stars ? 'fas' : 'far'; ?> fa-star <?php echo $i <= $stars ? 'text-warning' : 'text-muted'; ?> fs-5"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fas fa-check-circle me-1 text-success"></i>
                                            From <?php echo $rating_data['count']; ?> verified review<?php echo $rating_data['count'] != 1 ? 's' : ''; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="row g-3">
                                        <?php foreach ($rating_categories_meta as $category => $meta):
                                            $cat_rating = $rating_breakdown[$category]['rating'] ?? 0;
                                            $cat_count = $rating_breakdown[$category]['count'] ?? 0;
                                            if ($cat_count > 0): ?>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between align-items-center p-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="<?php echo $meta['icon']; ?> text-primary"></i>
                                                    <span class="small"><?php echo $meta['label']; ?></span>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-bold"><?php echo number_format($cat_rating, 1); ?></span>
                                                    <div class="progress" style="width: 60px; height: 6px;">
                                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo ($cat_rating / 5) * 100; ?>%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($review_keywords)): ?>
                        <div class="card-body border-bottom p-4">
                            <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Show reviews that mention:</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="?id=<?php echo $school_id; ?>&review_sort=<?php echo htmlspecialchars($review_sort); ?>" class="badge <?php echo empty($selected_keyword) ? 'bg-primary' : 'bg-secondary'; ?> px-3 py-2 text-decoration-none">
                                    All Reviews (<?php echo $rating_data['count']; ?>)
                                </a>
                                <?php $display_keywords = array_slice($review_keywords, 0, 20, true);
                                foreach ($display_keywords as $keyword => $count): ?>
                                <a href="?id=<?php echo $school_id; ?>&keyword=<?php echo urlencode($keyword); ?>&review_sort=<?php echo htmlspecialchars($review_sort); ?>" class="badge <?php echo $selected_keyword == $keyword ? 'bg-primary' : 'bg-light text-dark'; ?> px-3 py-2 text-decoration-none">
                                    <?php echo ucfirst($keyword); ?> (<?php echo $count; ?>)
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="card-body border-bottom p-4">
                            <form method="GET" action="" class="row g-3 align-items-center">
                                <input type="hidden" name="id" value="<?php echo $school_id; ?>">
                                <?php if (!empty($selected_keyword)): ?>
                                <input type="hidden" name="keyword" value="<?php echo htmlspecialchars($selected_keyword); ?>">
                                <?php endif; ?>
                                <div class="col-md-6">
                                    <label for="review_sort" class="form-label mb-1">Sort reviews by</label>
                                    <select class="form-select" id="review_sort" name="review_sort" onchange="this.form.submit()">
                                        <option value="most_recent" <?php echo $review_sort === 'most_recent' ? 'selected' : ''; ?>>Most Recent</option>
                                        <option value="rating_high" <?php echo $review_sort === 'rating_high' ? 'selected' : ''; ?>>Highest Rated</option>
                                        <option value="rating_low" <?php echo $review_sort === 'rating_low' ? 'selected' : ''; ?>>Lowest Rated</option>
                                        <option value="most_helpful" <?php echo $review_sort === 'most_helpful' ? 'selected' : ''; ?>>Most Helpful</option>
                                    </select>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <small class="text-muted d-block">Showing <?php echo count($reviews); ?> of <?php echo $total_reviews; ?> reviews</small>
                                    <?php if (!empty($selected_keyword)): ?>
                                    <a href="?id=<?php echo $school_id; ?>" class="btn btn-link btn-sm p-0">Clear filters</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                        <div class="card-body p-4">
                            <?php if (!empty($reviews)): ?>
                            <div class="review-list">
                                <?php foreach ($reviews as $review): 
                                    $photo_paths = !empty($review['photo_paths']) ? json_decode($review['photo_paths'], true) : [];
                                    $has_photos = !empty($photo_paths) && is_array($photo_paths);
                                    $user_name = !empty($review['user_name']) ? $review['user_name'] : ($review['user_account_number'] ? 'User #' . str_pad($review['user_account_number'], 6, '0', STR_PAD_LEFT) : 'Anonymous');
                                    $profile_photo_url = $review['profile_photo_url'] ?? getUserProfilePhoto($review['user_id'] ?? 0, $review['profile_photo'] ?? null);
                                    $is_own_review = $current_user_id && intval($review['user_id']) === intval($current_user_id);
                                    $status_badge = '';
                                    if ($is_own_review) {
                                        $status = $review['status'] ?? 'Pending';
                                        $status_class = 'bg-warning text-dark';
                                        if ($status === 'Approved') {
                                            $status_class = 'bg-success';
                                        } elseif ($status === 'Rejected') {
                                            $status_class = 'bg-danger';
                                        }
                                        $status_badge = '<span class="badge ' . $status_class . ' review-status-badge">' . htmlspecialchars($status) . '</span>';
                                    }

                                    $tooltipRowsHtml = '';
                                    foreach ($rating_categories_meta as $key => $meta) {
                                        $tooltipRowsHtml .= '<div class="review-rating-tooltip-item"><span class="label"><i class="' . $meta['icon'] . '"></i>' . htmlspecialchars($meta['label']) . '</span><span class="value">' . number_format($review[$key] ?? 0, 1) . '</span></div>';
                                    }
                                    $tooltipContent = '<div class="review-rating-tooltip-container">'
                                        . '<div class="review-rating-tooltip-header">Rating Breakdown<span class="text-muted small">' . number_format($review['overall_rating'] ?? 0, 1) . '/5</span></div>'
                                        . '<div class="review-rating-tooltip-grid">' . $tooltipRowsHtml . '</div>'
                                        . '</div>';
                                    $tooltip_attr = htmlspecialchars($tooltipContent, ENT_QUOTES, 'UTF-8');

                                    $helpful_count = intval($review['helpful_count'] ?? 0);
                                    $user_marked_helpful = !empty($review['user_vote']) && intval($review['user_vote']) === 1;
                                    $can_mark_helpful = isLoggedIn() && !$is_own_review;
                                ?>
                                <div class="review-card border-bottom pb-4 mb-4" data-review-id="<?php echo $review['id']; ?>">
                                    <div class="d-flex align-items-start gap-3">
                                        <img src="<?php echo htmlspecialchars($profile_photo_url); ?>" alt="<?php echo htmlspecialchars($user_name); ?> avatar" class="review-avatar" loading="lazy">
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                                <div>
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <h6 class="mb-0 fw-semibold"><?php echo htmlspecialchars($user_name); ?></h6>
                                                        <?php if ($is_own_review): echo $status_badge; endif; ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo date('F j, Y', strtotime($review['created_at'])); ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-warning text-dark review-overall-badge" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true" data-bs-custom-class="review-rating-tooltip" title="<?php echo $tooltip_attr; ?>">
                                                        <i class="fas fa-star me-1"></i><?php echo number_format($review['overall_rating'], 1); ?> / 5
                                                    </span>
                                                    <div class="small text-muted mt-1 review-helpful-count<?php echo $helpful_count === 0 ? ' d-none' : ''; ?>">
                                                        <i class="fas fa-thumbs-up text-success me-1"></i><span class="count-value"><?php echo $helpful_count; ?></span> found helpful
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if (!empty($review['comment'])): ?>
                                            <p class="mt-3 mb-3"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                            <?php endif; ?>

                                            <?php if ($has_photos): ?>
                                            <div class="review-photos mb-3">
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php foreach ($photo_paths as $photo): ?>
                                                    <a href="<?php echo SITE_URL . 'uploads/reviews/' . $photo; ?>" target="_blank" rel="noopener noreferrer">
                                                        <img src="<?php echo SITE_URL . 'uploads/reviews/' . $photo; ?>" alt="Review photo" class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
                                                    </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <div class="d-flex align-items-center gap-3 mt-3">
                                                <?php if ($can_mark_helpful): ?>
                                                <button type="button" class="btn btn-outline-success btn-sm review-helpful-btn<?php echo $user_marked_helpful ? ' active' : ''; ?>" data-review-id="<?php echo $review['id']; ?>">
                                                    <i class="fas fa-thumbs-up me-1"></i>Helpful
                                                </button>
                                                <?php elseif (!isLoggedIn()): ?>
                                                <a href="login.php" class="btn btn-outline-success btn-sm">
                                                    <i class="fas fa-thumbs-up me-1"></i>Helpful
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="reviewLoading" class="text-center my-3" style="display:none;">
                                <span class="spinner-border text-primary" role="status" aria-hidden="true"></span>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                                <h5>No reviews yet</h5>
                                <p class="text-muted">Be the first to share your experience with this school.</p>
                                <?php if (isLoggedIn()): ?>
                                <a href="add_review.php?school_id=<?php echo $school_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-pencil-alt me-2"></i>Write a review
                                </a>
                                <?php else: ?>
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login to write a review
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($has_more_reviews): ?>
                        <div class="card-footer bg-white text-center mt-4">
                            <button class="btn btn-outline-primary" id="loadMoreReviews" data-school-id="<?php echo $school_id; ?>" data-offset="<?php echo $initial_limit; ?>" data-user-id="<?php echo $current_user_id ?? ''; ?>">
                                <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                Load more reviews
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($external_reviews)): ?>
                    <div class="tab-pane fade <?php echo isset($_GET['review_type']) && $_GET['review_type'] == 'external' ? 'show active' : ''; ?>" id="external-reviews" role="tabpanel">
                        <div class="card-body p-4">
                            <div class="row g-4">
                                <?php foreach ($external_reviews as $review): ?>
                                <div class="col-md-6">
                                    <div class="border rounded p-4 h-100">
                                        <div class="d-flex justify-content-between mb-2">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($review['source']); ?></h6>
                                            <?php if ($review['rating']): ?>
                                            <div class="badge bg-warning text-dark">
                                                <i class="fas fa-star me-1"></i><?php echo number_format($review['rating'], 1); ?> / 5
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small mb-3"><?php echo date('F j, Y', strtotime($review['review_date'])); ?></div>
                                        <?php if ($review['review_text']): ?>
                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                        <?php endif; ?>
                                        <?php if ($review['review_url']): ?>
                                        <a href="<?php echo htmlspecialchars($review['review_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                            <i class="fas fa-external-link-alt me-1"></i>View Original Review
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>


            <?php // Reviews section remains as currently implemented 
?>
            <?php // Existing full review section markup will follow in remaining text 
?>
        </div>
        <div class="col-xl-4 col-lg-5">
            <?php if (!empty($gallery_photos)): ?>
            <div class="card shadow-soft border-0 mb-4 photo-carousel-card">
                <div class="card-header border-0 photo-gallery-header">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-images"></i>Photo Gallery
                    </h5>
                </div>
                <div class="card-body">
                    <div id="schoolPhotoCarousel<?php echo $school_id; ?>" class="carousel slide photo-carousel" data-bs-ride="false">
                        <?php if (count($gallery_photos) > 1): ?>
                        <div class="carousel-indicators">
                            <?php foreach ($gallery_photos as $index => $gallery_photo): ?>
                            <button type="button" data-bs-target="#schoolPhotoCarousel<?php echo $school_id; ?>" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>" aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-label="Slide <?php echo $index + 1; ?>"></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="carousel-inner">
                            <?php foreach ($gallery_photos as $index => $gallery_photo): 
                                $gallery_src = SITE_URL . 'uploads/schools/' . $gallery_photo['photo_path'];
                            ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <button type="button" class="photo-carousel-btn" data-bs-toggle="modal" data-bs-target="#photoModal" data-photo="<?php echo $gallery_src; ?>" data-photo-index="<?php echo $index + 1; ?>" aria-label="View photo <?php echo $index + 2; ?>">
                                    <img src="<?php echo $gallery_src; ?>" class="photo-carousel-image" alt="<?php echo htmlspecialchars($school['name']); ?> gallery photo <?php echo $index + 1; ?>">
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($gallery_photos) > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#schoolPhotoCarousel<?php echo $school_id; ?>" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#schoolPhotoCarousel<?php echo $school_id; ?>" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <aside class="sticky-school-card">
                <div class="card shadow-soft border-0">
                    <div class="card-body">
                        <div class="rounded-3 overflow-hidden mb-3">
                            <img src="<?php echo htmlspecialchars($primary_photo_url); ?>" alt="<?php echo htmlspecialchars($school['name']); ?>" class="img-fluid sticky-card-photo">
                        </div>
                        <h4 class="mb-1"><?php echo htmlspecialchars($school['name']); ?></h4>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <?php if ($rating_data['count'] > 0): ?>
                            <div class="text-warning d-flex align-items-center gap-1">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="<?php echo ($rating_data['rating'] >= $i ? 'fas' : 'far'); ?> fa-star"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="fw-semibold"><?php echo number_format($rating_data['rating'], 1); ?></span>
                            <small class="text-muted"><?php echo $rating_data['count']; ?> review<?php echo $rating_data['count'] == 1 ? '' : 's'; ?></small>
                            <?php else: ?>
                            <span class="text-muted">No reviews yet</span>
                            <?php endif; ?>
                        </div>
                        <div class="collection-grid">
                            <div class="collection-action">
                                <button type="button" class="school-card-heart-btn school-collection-btn" data-school-id="<?php echo $school_id; ?>" aria-label="Save to collection">
                                    <i class="far fa-heart"></i>
                                </button>
                                <p class="collection-label mb-0">Save to collection</p>
                            </div>
                            <a href="profile.php#manage-collection" class="collection-link">View collections</a>
                        </div>
                        <?php if (!$is_current_user_owner): ?>
                            <?php if ($has_vendor_owner): ?>
                            <div class="collection-grid collection-grid-right mt-2">
                                <span></span>
                                <a href="#" class="collection-link" data-bs-toggle="modal" data-bs-target="#interestModal">
                                    <i class="fas fa-handshake"></i>I'm interested
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="collection-grid">
                                <a href="#" class="collection-link" data-bs-toggle="modal" data-bs-target="#suggestEditModal">Suggest an edit</a>
                                <?php if ($is_vendor_approved): ?>
                                <a href="#" class="collection-link" data-bs-toggle="modal" data-bs-target="#ownBusinessModal">Own this business?</a>
                                <?php else: ?>
                                <a href="#" class="collection-link" data-bs-toggle="modal" data-bs-target="#vendorAccountRequiredModal">Own this business?</a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<!-- Interest Modal -->
<div class="modal fade" id="interestModal" tabindex="-1" aria-labelledby="interestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="interestModalLabel"><?php echo t('interest_title'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="interestForm" class="interest-form" data-school-id="<?php echo $school_id; ?>">
                    <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label"><?php echo t('interest_name'); ?> *</label>
                        <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($user_info['name'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="contact_number" class="form-label"><?php echo t('interest_contact'); ?> *</label>
                        <input type="tel" class="form-control" id="contact_number" name="contact_number" required value="<?php echo htmlspecialchars($user_info['contact'] ?? $user_info['phone_number'] ?? ''); ?>"<?php echo isLoggedIn() && $user_info ? ' readonly' : ''; ?>>
                        <?php if (isLoggedIn() && $user_info): ?>
                            <small class="text-muted">This field is automatically filled from your account.</small>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label"><?php echo t('interest_email'); ?> *</label>
                        <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="child_year_of_birth" class="form-label">Child's Year of Birth *</label>
                        <input type="number" class="form-control" id="child_year_of_birth" name="child_year_of_birth" min="1990" max="<?php echo date('Y'); ?>" required placeholder="e.g., 2020">
                        <small class="text-muted">Enter the year your child was born</small>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label"><?php echo t('interest_message'); ?></label>
                        <textarea class="form-control" id="message" name="message" rows="3" placeholder="Any additional information or questions..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer p-0 pt-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('btn_close'); ?></button>
                <button type="submit" form="interestForm" class="btn btn-primary"><?php echo t('interest_submit'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Suggest Edit Modal -->
<div class="modal fade" id="suggestEditModal" tabindex="-1" aria-labelledby="suggestEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="suggestEditModalLabel">Suggest an edit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush">
                    <!-- School Name -->
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between suggest-edit-item" data-edit-type="name">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-store text-primary me-3" style="width: 24px;"></i>
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($school['name']); ?></div>
                                <small class="text-muted">School name</small>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </button>
                    
                    <!-- Address -->
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between suggest-edit-item" data-edit-type="address">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-map-marker-alt text-primary me-3" style="width: 24px;"></i>
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($school['address']); ?></div>
                                <small class="text-muted">Address</small>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </button>
                    
                    <!-- Hours -->
                    <?php
                    // Format existing operating hours for display
                    $hours_display_text = 'Add hours';
                    if (!empty($school['operating_hours'])) {
                        $operating_hours = json_decode($school['operating_hours'], true);
                        if ($operating_hours && is_array($operating_hours) && !empty($operating_hours['days'])) {
                            // Sort days starting from Monday
                            $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            $sortedDays = [];
                            foreach ($dayOrder as $orderedDay) {
                                if (in_array($orderedDay, $operating_hours['days'])) {
                                    $sortedDays[] = $orderedDay;
                                }
                            }
                            foreach ($operating_hours['days'] as $day) {
                                if (!in_array($day, $sortedDays)) {
                                    $sortedDays[] = $day;
                                }
                            }
                            
                            $day_abbrevs = [];
                            foreach ($sortedDays as $day) {
                                $day_abbrevs[] = substr($day, 0, 3);
                            }
                            $days_str = implode(', ', $day_abbrevs);
                            
                            if (!empty($operating_hours['is24Hours'])) {
                                $hours_display_text = $days_str . ': Open 24 hours';
                            } elseif (!empty($operating_hours['isClosed'])) {
                                $hours_display_text = $days_str . ': Closed';
                            } elseif (!empty($operating_hours['timeSlots']) && is_array($operating_hours['timeSlots'])) {
                                $time_parts = [];
                                foreach ($operating_hours['timeSlots'] as $slot) {
                                    if (!empty($slot['open']) && !empty($slot['close'])) {
                                        $time_parts[] = $slot['open'] . ' - ' . $slot['close'];
                                    }
                                }
                                if (!empty($time_parts)) {
                                    $hours_display_text = $days_str . ': ' . implode(', ', $time_parts);
                                }
                            }
                        }
                    }
                    ?>
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between suggest-edit-item" data-edit-type="hours">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-clock text-primary me-3" style="width: 24px;"></i>
                            <div>
                                <div class="fw-bold"><?php echo htmlspecialchars($hours_display_text); ?></div>
                                <small class="text-muted">Operating hours</small>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </button>
                    
                    <!-- Phone Number -->
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between suggest-edit-item" data-edit-type="phone">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-phone text-primary me-3" style="width: 24px;"></i>
                            <div>
                                <div class="fw-bold"><?php echo !empty($school['contact']) ? htmlspecialchars($school['contact']) : 'Add phone number'; ?></div>
                                <small class="text-muted">Contact number</small>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </button>
                    
                    <!-- Place is closed or not here -->
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between suggest-edit-item" data-edit-type="closed">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-times-circle text-primary me-3" style="width: 24px;"></i>
                            <div>
                                <div class="fw-bold">Place is closed or not here</div>
                                <small class="text-muted">Report if this location is incorrect</small>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-muted"></i>
                    </button>
                </div>
                
                <!-- Edit Form (Hidden by default, shown when item is clicked) -->
                <div id="suggestEditFormContainer" class="p-4" style="display: none;">
                    <form id="suggestEditForm" data-school-id="<?php echo $school_id; ?>">
                        <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                        <input type="hidden" name="edit_type" id="editType" value="">
                        
                        <div id="editFormContent">
                            <!-- Form content will be dynamically loaded based on edit_type -->
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-secondary" id="cancelEditBtn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Send</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Own Business Modal -->
<div class="modal fade" id="ownBusinessModal" tabindex="-1" aria-labelledby="ownBusinessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ownBusinessModalLabel">Own this business?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (!isLoggedIn()): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Please <a href="login.php">login</a> or <a href="register.php">register</a> to claim this business.
                    </div>
                <?php else: ?>
                    <?php 
                    $current_user_role = $user_info['role'] ?? 'user';
                    $current_vendor_status = $user_info['vendor_status'] ?? '';
                    ?>
                    
                    <?php if ($current_user_role !== 'vendor' || $current_vendor_status !== 'approved'): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>You need to have a <strong>vendor account</strong> to claim this business. 
                            <?php if ($current_user_role !== 'vendor'): ?>
                                <a href="register.php?upgrade=vendor" class="alert-link">Register as vendor account</a> first.
                            <?php elseif ($current_vendor_status !== 'approved'): ?>
                                Your vendor account is pending approval. Please wait for admin approval.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form id="ownBusinessForm" data-school-id="<?php echo $school_id; ?>" enctype="multipart/form-data">
                            <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
                            
                            <p class="mb-4">To claim this business, please provide verification documents. You need to upload <strong>at least 2 of the following 3 documents</strong>:</p>
                            
                            <!-- Verification Photos -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Verification Documents <span class="text-danger">*</span></label>
                                <small class="text-muted d-block mb-3">Upload at least 2 of the following documents:</small>
                                
                                <!-- Utility Bill (水电账单) -->
                                <div class="mb-3">
                                    <label for="verification_utility" class="form-label">
                                        <i class="fas fa-file-invoice me-2"></i>Utility Bill (水电账单)
                                    </label>
                                    <input type="file" class="form-control" id="verification_utility" name="verification_utility" accept="image/*,.pdf">
                                    <small class="text-muted">Upload utility bill (water/electricity)</small>
                                </div>
                                
                                <!-- Rental Contract (店铺租赁合同) -->
                                <div class="mb-3">
                                    <label for="verification_rental" class="form-label">
                                        <i class="fas fa-file-contract me-2"></i>Shop Rental Contract (店铺租赁合同)
                                    </label>
                                    <input type="file" class="form-control" id="verification_rental" name="verification_rental" accept="image/*,.pdf">
                                    <small class="text-muted">Upload shop rental contract</small>
                                </div>
                                
                                <!-- SSM / Business License (SSM / 营业执照) -->
                                <div class="mb-3">
                                    <label for="verification_ssm" class="form-label">
                                        <i class="fas fa-certificate me-2"></i>SSM / Business License (SSM / 营业执照)
                                    </label>
                                    <input type="file" class="form-control" id="verification_ssm" name="verification_ssm" accept="image/*,.pdf">
                                    <small class="text-muted">Upload SSM registration or business license</small>
                                </div>
                            </div>
                            
                            <!-- School Information Form (Same as manage-school form) -->
                            <hr class="my-4">
                            <h6 class="mb-3">Business Information</h6>
                            
                            <!-- Include the same form fields as manage-school.php -->
                            <div class="mb-3">
                                <label for="own_name" class="form-label">School Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="own_name" name="name" required value="<?php echo htmlspecialchars($school['name']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="own_address" class="form-label">Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="own_address" name="address" rows="2" required><?php echo htmlspecialchars($school['address']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="own_state" class="form-label">State <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="own_state" name="state" required value="<?php echo htmlspecialchars($school['state'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="own_city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="own_city" name="city" value="<?php echo htmlspecialchars($school['city'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="own_contact" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="own_contact" name="contact" required value="<?php echo htmlspecialchars($school['contact'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="own_email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="own_email" name="email" required value="<?php echo htmlspecialchars($school['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="own_description" class="form-label">Description</label>
                                <textarea class="form-control" id="own_description" name="description" rows="3"><?php echo htmlspecialchars($school['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>After submission, our admin will review your verification documents and business information. You will be notified once approved.
                            </div>
                            
                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Submit Claim</button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Vendor Account Required Modal -->
<div class="modal fade" id="vendorAccountRequiredModal" tabindex="-1" aria-labelledby="vendorAccountRequiredModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="vendorAccountRequiredModalLabel"><i class="fas fa-user-tie me-2 text-primary"></i>Vendor account required</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <?php if ($is_logged_in): ?>
                    <p class="text-muted">You are currently signed in as a regular user. To claim this business, please register as a vendor account.</p>
                    <a href="register.php?upgrade=vendor" class="btn btn-primary w-100 mb-2">Register as vendor account</a>
                    <p class="small text-muted mb-0">Need help? <a href="contact.php">Contact our support team</a>.</p>
                <?php else: ?>
                    <p class="text-muted">You need a vendor account to claim this business. Log in with your vendor account or register as vendor account now.</p>
                    <div class="d-grid gap-2">
                        <a href="login.php" class="btn btn-outline-primary">Log in</a>
                        <a href="register.php?upgrade=vendor" class="btn btn-primary">Register as vendor account</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Photo Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-0">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close" style="z-index: 30;"></button>
                <button type="button" class="modal-photo-nav-btn" id="modalPhotoPrev" aria-label="Previous photo">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <img id="modalPhoto" src="" alt="Photo" class="img-fluid">
                <button type="button" class="modal-photo-nav-btn" id="modalPhotoNext" aria-label="Next photo">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="modal-photo-counter" id="modalPhotoCounter"></div>
            </div>
        </div>
    </div>
</div>

<script>
// Handle Suggest Edit Modal
document.addEventListener('DOMContentLoaded', function() {
    // Suggest Edit Item Click Handler
    const suggestEditItems = document.querySelectorAll('.suggest-edit-item');
    const suggestEditFormContainer = document.getElementById('suggestEditFormContainer');
    const editFormContent = document.getElementById('editFormContent');
    const editTypeInput = document.getElementById('editType');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const suggestEditForm = document.getElementById('suggestEditForm');
    
    // Form templates for each edit type
    const formTemplates = {
        name: `
            <div class="mb-3">
                <label for="edit_name" class="form-label">School Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="edit_name" name="name" required value="<?php echo htmlspecialchars($school['name']); ?>">
            </div>
        `,
        category: `
            <div class="mb-3">
                <label for="edit_category" class="form-label">Category</label>
                <input type="text" class="form-control" id="edit_category" name="category" placeholder="e.g., Private School, International School">
                <small class="text-muted">Suggest a category for this school</small>
            </div>
        `,
        address: `
            <div class="mb-3">
                <label for="edit_address" class="form-label">Address <span class="text-danger">*</span></label>
                <textarea class="form-control" id="edit_address" name="address" rows="3" required><?php echo htmlspecialchars($school['address']); ?></textarea>
            </div>
        `,
        hours: `
            <div class="mb-3">
                <div id="suggestEditOperatingHours"></div>
                <input type="hidden" name="operating_hours[days][]" id="operating_hours_days" value="">
                <input type="hidden" name="operating_hours[is24Hours]" id="operating_hours_24h" value="0">
                <input type="hidden" name="operating_hours[isClosed]" id="operating_hours_closed" value="0">
            </div>
        `,
        phone: `
            <div class="mb-3">
                <label for="edit_phone" class="form-label">Contact Number</label>
                <input type="tel" class="form-control" id="edit_phone" name="phone" value="<?php echo htmlspecialchars($school['contact'] ?? ''); ?>">
            </div>
        `,
        closed: `
            <div class="mb-3">
                <label class="form-label">Report Issue</label>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="closed_reason" id="closed_yes" value="closed" required>
                    <label class="form-check-label" for="closed_yes">This place is permanently closed</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="closed_reason" id="closed_wrong" value="wrong_location" required>
                    <label class="form-check-label" for="closed_wrong">This place doesn't exist here</label>
                </div>
                <div class="mb-3 mt-3">
                    <label for="closed_details" class="form-label">Additional Details</label>
                    <textarea class="form-control" id="closed_details" name="closed_details" rows="3" placeholder="Please provide more information..."></textarea>
                </div>
            </div>
        `
    };
    
    // Operating hours picker instance
    let operatingHoursPicker = null;
    
    // Handle item click
    suggestEditItems.forEach(item => {
        item.addEventListener('click', function() {
            const editType = this.dataset.editType;
            editTypeInput.value = editType;
            editFormContent.innerHTML = formTemplates[editType] || '';
            suggestEditFormContainer.style.display = 'block';
            
            // Initialize operating hours picker if hours type
            if (editType === 'hours') {
                setTimeout(() => {
                    if (document.getElementById('suggestEditOperatingHours')) {
                        operatingHoursPicker = new OperatingHoursPicker('suggestEditOperatingHours', {
                            namePrefix: 'operating_hours'
                        });
                        <?php if (!empty($school['operating_hours'])): 
                            $existing_hours = json_decode($school['operating_hours'], true);
                            if ($existing_hours && is_array($existing_hours)): ?>
                        // Load existing operating hours
                        operatingHoursPicker.setValue(<?php echo json_encode($existing_hours); ?>);
                        <?php endif; endif; ?>
                    }
                }, 100);
            }
            
            // Scroll to form
            suggestEditFormContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });
    
    // Cancel button handler
    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', function() {
            suggestEditFormContainer.style.display = 'none';
            editFormContent.innerHTML = '';
            editTypeInput.value = '';
        });
    }
    
    // Handle form submission
    if (suggestEditForm) {
        suggestEditForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Collect operating hours data if hours type
            if (editTypeInput.value === 'hours' && operatingHoursPicker) {
                const hoursValue = operatingHoursPicker.getValue();
                // Clear existing hidden inputs
                const existingInputs = this.querySelectorAll('input[name^="operating_hours"]');
                existingInputs.forEach(input => input.remove());
                
                // Check if new format with schedules
                if (hoursValue.schedules && Array.isArray(hoursValue.schedules)) {
                    // New format: multiple schedules
                    hoursValue.schedules.forEach((schedule, scheduleIndex) => {
                        // Add days for this schedule
                        schedule.days.forEach(day => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = `operating_hours[schedules][${scheduleIndex}][days][]`;
                            input.value = day;
                            this.appendChild(input);
                        });
                        
                        // Add 24h flag
                        const input24h = document.createElement('input');
                        input24h.type = 'hidden';
                        input24h.name = `operating_hours[schedules][${scheduleIndex}][is24Hours]`;
                        input24h.value = schedule.is24Hours ? '1' : '0';
                        this.appendChild(input24h);
                        
                        // Add closed flag
                        const inputClosed = document.createElement('input');
                        inputClosed.type = 'hidden';
                        inputClosed.name = `operating_hours[schedules][${scheduleIndex}][isClosed]`;
                        inputClosed.value = schedule.isClosed ? '1' : '0';
                        this.appendChild(inputClosed);
                        
                        // Add time slots
                        if (schedule.timeSlots && Array.isArray(schedule.timeSlots)) {
                            schedule.timeSlots.forEach((slot, slotIndex) => {
                                const inputOpen = document.createElement('input');
                                inputOpen.type = 'hidden';
                                inputOpen.name = `operating_hours[schedules][${scheduleIndex}][timeSlots][${slotIndex}][open]`;
                                inputOpen.value = slot.open;
                                this.appendChild(inputOpen);
                                
                                const inputClose = document.createElement('input');
                                inputClose.type = 'hidden';
                                inputClose.name = `operating_hours[schedules][${scheduleIndex}][timeSlots][${slotIndex}][close]`;
                                inputClose.value = slot.close;
                                this.appendChild(inputClose);
                            });
                        }
                    });
                } else {
                    // Old format: single schedule
                    // Add days
                    hoursValue.days.forEach(day => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'operating_hours[days][]';
                        input.value = day;
                        this.appendChild(input);
                    });
                    
                    // Add 24h flag
                    const input24h = document.createElement('input');
                    input24h.type = 'hidden';
                    input24h.name = 'operating_hours[is24Hours]';
                    input24h.value = hoursValue.is24Hours ? '1' : '0';
                    this.appendChild(input24h);
                    
                    // Add closed flag
                    const inputClosed = document.createElement('input');
                    inputClosed.type = 'hidden';
                    inputClosed.name = 'operating_hours[isClosed]';
                    inputClosed.value = hoursValue.isClosed ? '1' : '0';
                    this.appendChild(inputClosed);
                    
                    // Add time slots
                    if (hoursValue.timeSlots && Array.isArray(hoursValue.timeSlots)) {
                        hoursValue.timeSlots.forEach((slot, index) => {
                            const inputOpen = document.createElement('input');
                            inputOpen.type = 'hidden';
                            inputOpen.name = `operating_hours[${index}][open]`;
                            inputOpen.value = slot.open;
                            this.appendChild(inputOpen);
                            
                            const inputClose = document.createElement('input');
                            inputClose.type = 'hidden';
                            inputClose.name = `operating_hours[${index}][close]`;
                            inputClose.value = slot.close;
                            this.appendChild(inputClose);
                        });
                    }
                }
            }
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
            
            fetch('suggest_edit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Your edit suggestion has been submitted successfully. Thank you!');
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('suggestEditModal'));
                    if (modal) modal.hide();
                    // Reset form
                    suggestEditFormContainer.style.display = 'none';
                    editFormContent.innerHTML = '';
                    editTypeInput.value = '';
                    operatingHoursPicker = null;
                } else {
                    alert(data.message || 'Failed to submit edit suggestion. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    // Handle Own Business Form
    const ownBusinessForm = document.getElementById('ownBusinessForm');
    if (ownBusinessForm) {
        ownBusinessForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Validate at least 2 verification documents
            const utilityInput = this.querySelector('#verification_utility');
            const rentalInput = this.querySelector('#verification_rental');
            const ssmInput = this.querySelector('#verification_ssm');
            
            let docCount = 0;
            if (utilityInput && utilityInput.files && utilityInput.files.length > 0) docCount++;
            if (rentalInput && rentalInput.files && rentalInput.files.length > 0) docCount++;
            if (ssmInput && ssmInput.files && ssmInput.files.length > 0) docCount++;
            
            if (docCount < 2) {
                alert('Please upload at least 2 verification documents (Utility Bill, Rental Contract, or SSM/Business License).');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            
            fetch('own_business.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Your business claim has been submitted successfully. Our admin will review it soon.');
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('ownBusinessModal'));
                    if (modal) modal.hide();
                    // Reload page
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to submit business claim. Please try again.'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again. Error: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
});

// Submit interest form (same as directory.php)
document.addEventListener('DOMContentLoaded', function() {
    // Handle interest form submissions
    const interestForms = document.querySelectorAll('.interest-form');
    
    interestForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            }
            
            fetch('add_interest.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    const modalId = form.closest('.modal').id;
                    const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
                    if (modal) {
                        modal.hide();
                    }
                    form.reset();
                } else {
                    alert(data.message);
                }
                
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        });
    });
});

// Save to collection button
document.addEventListener('DOMContentLoaded', function() {
    const collectionBtn = document.querySelector('.school-collection-btn');
    if (!collectionBtn) return;

    const isLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
    const schoolId = collectionBtn.dataset.schoolId;

    function setActiveState(active) {
        collectionBtn.classList.toggle('active', active);
        const icon = collectionBtn.querySelector('i');
        if (icon) {
            icon.classList.toggle('fas', active);
            icon.classList.toggle('far', !active);
        }
    }

    if (!isLoggedIn) {
        collectionBtn.addEventListener('click', function(event) {
            event.preventDefault();
            if (confirm('Please login to save schools to your collection. Would you like to login now?')) {
                window.location.href = 'login.php';
            }
        });
        return;
    }

    // Initial state check
    fetch(`api_collection.php?action=check&school_id=${schoolId}`)
        .then(response => response.json())
        .then(data => {
            if (data && data.success) {
                setActiveState(!!data.in_collection);
            }
        })
        .catch(error => console.error('Error checking collection status:', error));

    collectionBtn.addEventListener('click', function(event) {
        event.preventDefault();
        const isActive = collectionBtn.classList.contains('active');
        const action = isActive ? 'remove' : 'add';

        fetch('api_collection.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=${action}&school_id=${schoolId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.success) {
                setActiveState(!isActive);
                alert(data.message || (action === 'add' ? 'School added to collection.' : 'School removed from collection.'));
            } else if (data && data.message) {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error updating collection:', error);
            alert('An error occurred. Please try again.');
        });
    });
});

// Sticky sidebar sizing helper
document.addEventListener('DOMContentLoaded', function() {
    const stickyCard = document.querySelector('.sticky-school-card');
    if (!stickyCard) return;
    const stickyParent = stickyCard.parentElement;
    const mainColumn = document.querySelector('.col-xl-8.col-lg-7');
    const footer = document.querySelector('footer');
    const TOP_OFFSET = 80;

    function resetStyles() {
        stickyCard.style.width = '';
        stickyCard.style.transform = '';
        stickyParent.style.minHeight = '';
    }

    function updateStickySidebar() {
        if (window.innerWidth < 992) {
            resetStyles();
            return;
        }

        if (mainColumn) {
            stickyParent.style.minHeight = mainColumn.offsetHeight + 'px';
        }

        const parentRect = stickyParent.getBoundingClientRect();
        stickyCard.style.width = parentRect.width + 'px';
        stickyCard.style.top = TOP_OFFSET + 'px';

        if (footer) {
            const footerRect = footer.getBoundingClientRect();
            if (footerRect.top < window.innerHeight) {
                const overlap = window.innerHeight - footerRect.top + 24;
                stickyCard.style.transform = `translateY(-${overlap}px)`;
            } else {
                stickyCard.style.transform = '';
            }
        }
    }

    const handleScroll = () => window.requestAnimationFrame(updateStickySidebar);
    const handleResize = () => window.requestAnimationFrame(updateStickySidebar);

    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', handleResize);
    updateStickySidebar();
});

// Photo modal with navigation
document.addEventListener('DOMContentLoaded', function() {
    const modalPhotos = <?php echo json_encode($modal_photos); ?>;
    const photoModal = document.getElementById('photoModal');
    const modalImage = document.getElementById('modalPhoto');
    const modalPrev = document.getElementById('modalPhotoPrev');
    const modalNext = document.getElementById('modalPhotoNext');
    const modalCounter = document.getElementById('modalPhotoCounter');

    if (!photoModal || !modalImage || !Array.isArray(modalPhotos) || modalPhotos.length === 0) {
        return;
    }

    let currentModalIndex = 0;
    let modalKeyHandler = null;

    function updateModalPhoto() {
        if (!modalPhotos[currentModalIndex]) {
            modalImage.src = '';
            return;
        }

        modalImage.src = modalPhotos[currentModalIndex];

        if (modalCounter) {
            modalCounter.textContent = `${currentModalIndex + 1} / ${modalPhotos.length}`;
            modalCounter.style.display = modalPhotos.length > 1 ? 'block' : 'none';
        }

        if (modalPrev) {
            modalPrev.style.display = modalPhotos.length > 1 ? 'flex' : 'none';
            modalPrev.disabled = currentModalIndex === 0;
        }

        if (modalNext) {
            modalNext.style.display = modalPhotos.length > 1 ? 'flex' : 'none';
            modalNext.disabled = currentModalIndex === modalPhotos.length - 1;
        }
    }

    photoModal.addEventListener('show.bs.modal', function(event) {
        const trigger = event.relatedTarget;
        const indexAttr = trigger ? parseInt(trigger.getAttribute('data-photo-index'), 10) : 0;
        currentModalIndex = Number.isInteger(indexAttr) && indexAttr >= 0 && indexAttr < modalPhotos.length ? indexAttr : 0;
        updateModalPhoto();
    });

    if (modalPrev) {
        modalPrev.addEventListener('click', function() {
            if (currentModalIndex > 0) {
                currentModalIndex--;
                updateModalPhoto();
            }
        });
    }

    if (modalNext) {
        modalNext.addEventListener('click', function() {
            if (currentModalIndex < modalPhotos.length - 1) {
                currentModalIndex++;
                updateModalPhoto();
            }
        });
    }

    photoModal.addEventListener('shown.bs.modal', function() {
        modalKeyHandler = function(e) {
            if (modalPhotos.length <= 1) return;
            if (e.key === 'ArrowLeft' && currentModalIndex > 0) {
                currentModalIndex--;
                updateModalPhoto();
            } else if (e.key === 'ArrowRight' && currentModalIndex < modalPhotos.length - 1) {
                currentModalIndex++;
                updateModalPhoto();
            }
        };
        document.addEventListener('keydown', modalKeyHandler);
    });

    photoModal.addEventListener('hidden.bs.modal', function() {
        if (modalKeyHandler) {
            document.removeEventListener('keydown', modalKeyHandler);
            modalKeyHandler = null;
        }
    });
});

// Auto-open modal based on query parameter (used when navigating from directory cards)
document.addEventListener('DOMContentLoaded', function() {
    const modalKey = '<?php echo isset($_GET['open_modal']) ? trim($_GET['open_modal']) : ''; ?>';
    if (!modalKey) return;

    const modalMap = {
        'suggest_edit': 'suggestEditModal',
        'own_business': 'ownBusinessModal',
        'interest': 'interestModal'
    };

    const modalId = modalMap[modalKey];
    if (!modalId) return;

    const target = document.getElementById(modalId);
    if (!target) return;

    const modalInstance = new bootstrap.Modal(target);
    modalInstance.show();
});

const defaultAvatarUrl = '<?php echo getUserProfilePhoto(0); ?>';
const siteUrl = '<?php echo SITE_URL; ?>';
const isUserLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;

// Load More Reviews functionality
document.addEventListener('DOMContentLoaded', function() {
    const loadMoreBtn = document.getElementById('loadMoreReviews');
    const reviewLoading = document.getElementById('reviewLoading');
    let currentOffset = parseInt(loadMoreBtn?.getAttribute('data-offset') || 10, 10);
    const schoolId = loadMoreBtn?.getAttribute('data-school-id');
    const currentUserId = loadMoreBtn?.getAttribute('data-user-id') || '';
    const reviewsPerPage = 10;

    const ratingCategories = [
        { key: 'cleanliness_rating', label: 'Cleanliness' },
        { key: 'facilities_rating', label: 'Facilities' },
        { key: 'location_rating', label: 'Location' },
        { key: 'service_rating', label: 'Service' },
        { key: 'value_rating', label: 'Value for Money' },
        { key: 'education_rating', label: 'Education Quality' }
    ];

    if (loadMoreBtn && schoolId) {
        loadMoreBtn.addEventListener('click', function() {
            loadMoreBtn.style.display = 'none';
            if (reviewLoading) {
                reviewLoading.style.display = 'block';
            }

            const formData = new FormData();
            formData.append('school_id', schoolId);
            formData.append('offset', currentOffset);
            formData.append('limit', reviewsPerPage);
            if (currentUserId) {
                formData.append('user_id', currentUserId);
            }

            fetch('load_reviews.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const reviewList = document.querySelector('#fullReviewSection .review-list');
                const loadMoreWrapper = loadMoreBtn.closest('.card-footer');

                if (data.success && data.reviews && data.reviews.length > 0 && reviewList) {
                    data.reviews.forEach(review => {
                        const reviewHtml = createReviewHTML(review, currentUserId, ratingCategories);
                        const reviewDiv = document.createElement('div');
                        reviewDiv.className = 'review-card border-bottom pb-4 mb-4';
                        reviewDiv.dataset.reviewId = review.id;
                        reviewDiv.innerHTML = reviewHtml;
                        reviewList.appendChild(reviewDiv);
                        initReviewTooltips(reviewDiv);
                        attachReviewHelpfulHandlers(reviewDiv);
                    });

                    currentOffset += data.reviews.length;
                    loadMoreBtn.setAttribute('data-offset', currentOffset);

                    if (data.has_more) {
                        loadMoreBtn.style.display = 'inline-block';
                    } else if (loadMoreWrapper) {
                        loadMoreWrapper.remove();
                    }
                } else if (loadMoreWrapper) {
                    loadMoreWrapper.remove();
                }

                if (reviewLoading) {
                    reviewLoading.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error loading reviews:', error);
                if (reviewLoading) {
                    reviewLoading.style.display = 'none';
                }
                loadMoreBtn.style.display = 'inline-block';
                alert('Error loading reviews. Please try again.');
            });
        });
    }

    initReviewTooltips();
    attachReviewHelpfulHandlers();
});

function createReviewHTML(review, currentUserId, ratingCategories) {
    const numericCurrentUserId = currentUserId ? parseInt(currentUserId, 10) : null;
    const reviewUserId = parseInt(review.user_id || 0, 10);
    const isOwnReview = numericCurrentUserId && reviewUserId === numericCurrentUserId;
    const profilePhotoUrl = review.profile_photo_url || defaultAvatarUrl;
    const helpfulCount = parseInt(review.helpful_count || 0, 10);
    const helpfulCountClass = helpfulCount === 0 ? ' d-none' : '';
    const canMarkHelpful = Boolean(numericCurrentUserId) && !isOwnReview;
    const userMarkedHelpful = parseInt(review.user_vote || 0, 10) === 1;
    const userName = review.user_name || (review.user_account_number ? `User #${String(review.user_account_number).padStart(6, '0')}` : 'Anonymous');
    const statusBadge = isOwnReview ? getStatusBadge(review.status) : '';
    const tooltipHtml = buildTooltipHtml(review, ratingCategories);
    const overallRating = parseFloat(review.overall_rating || 0).toFixed(1);
    const reviewDate = formatReviewDate(review.created_at);

    const commentHtml = review.comment ? `<p class="mt-3 mb-3">${escapeHtml(review.comment).replace(/\n/g, '<br>')}</p>` : '';
    const photosHtml = buildPhotosHtml(review.photo_paths);
    let helpfulControl = '';

    if (canMarkHelpful) {
        helpfulControl = `<button type="button" class="btn btn-outline-success btn-sm review-helpful-btn${userMarkedHelpful ? ' active' : ''}" data-review-id="${review.id}">
                <i class="fas fa-thumbs-up me-1"></i>Helpful
            </button>`;
    } else if (!numericCurrentUserId) {
        helpfulControl = `<a href="login.php" class="btn btn-outline-success btn-sm">
                <i class="fas fa-thumbs-up me-1"></i>Helpful
            </a>`;
    }

    const helpfulCountHtml = `<div class="small text-muted mt-1 review-helpful-count${helpfulCountClass}">
            <i class="fas fa-thumbs-up text-success me-1"></i><span class="count-value">${helpfulCount}</span> found helpful
        </div>`;

    const helpfulSection = helpfulControl ? `<div class="d-flex align-items-center gap-3 mt-3">${helpfulControl}</div>` : '';

    return `
        <div class="d-flex align-items-start gap-3">
            <img src="${escapeHtmlAttr(profilePhotoUrl)}" alt="${escapeHtml(userName)} avatar" class="review-avatar" loading="lazy">
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h6 class="mb-0 fw-semibold">${escapeHtml(userName)}</h6>
                            ${statusBadge}
                        </div>
                        <small class="text-muted">${reviewDate}</small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-warning text-dark review-overall-badge" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true" data-bs-custom-class="review-rating-tooltip" title="${escapeHtml(tooltipHtml)}">
                            <i class="fas fa-star me-1"></i>${overallRating} / 5
                        </span>
                        ${helpfulCountHtml}
                    </div>
                </div>
                ${commentHtml}
                ${photosHtml}
                ${helpfulSection}
            </div>
        </div>
    `;
}

function buildTooltipHtml(review, ratingCategories) {
    const rows = ratingCategories.map(cat => {
        const score = parseFloat(review[cat.key] || 0).toFixed(1);
        return `<div class="review-rating-tooltip-row"><span>${escapeHtml(cat.label)}</span><span class="fw-semibold">${score}/5</span></div>`;
    }).join('');
    return `<div class="review-rating-tooltip-wrapper">${rows}</div>`;
}

function buildPhotosHtml(photoPaths) {
    if (!photoPaths) return '';

    let photos = [];
    if (Array.isArray(photoPaths)) {
        photos = photoPaths;
    } else {
        try {
            photos = JSON.parse(photoPaths) || [];
        } catch (e) {
            photos = [];
        }
    }

    if (!Array.isArray(photos) || photos.length === 0) {
        return '';
    }

    let html = '<div class="review-photos mb-3"><div class="d-flex flex-wrap gap-2">';
    photos.forEach(photo => {
        const path = typeof photo === 'string' ? photo : (photo.path || photo.url || photo.filename || '');
        if (path) {
            const fullPath = siteUrl + 'uploads/reviews/' + path;
            html += `<a href="${fullPath}" target="_blank" rel="noopener noreferrer">
                    <img src="${fullPath}" alt="Review photo" class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
                </a>`;
        }
    });
    html += '</div></div>';
    return html;
}

function getStatusBadge(status) {
    const normalized = status || 'Pending';
    let badgeClass = 'bg-warning text-dark';
    if (normalized === 'Approved') {
        badgeClass = 'bg-success';
    } else if (normalized === 'Rejected') {
        badgeClass = 'bg-danger';
    }
    return `<span class="badge ${badgeClass} review-status-badge">${escapeHtml(normalized)}</span>`;
}

function formatReviewDate(dateString) {
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) {
        return escapeHtml(dateString || '');
    }
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function initReviewTooltips(context = document) {
    const tooltipElements = context.querySelectorAll('.review-overall-badge[data-bs-toggle="tooltip"]');
    tooltipElements.forEach(el => {
        const existing = bootstrap.Tooltip.getInstance(el);
        if (existing) {
            existing.dispose();
        }
        new bootstrap.Tooltip(el, {
            customClass: 'review-rating-tooltip'
        });
    });
}

function attachReviewHelpfulHandlers(context = document) {
    const buttons = context.querySelectorAll('.review-helpful-btn');
    buttons.forEach(button => {
        if (button.dataset.bound === '1') {
            return;
        }
        button.dataset.bound = '1';
        button.addEventListener('click', function() {
            if (!isUserLoggedIn) {
                if (confirm('Please login to mark reviews as helpful. Would you like to login now?')) {
                    window.location.href = 'login.php';
                }
                return;
            }

            const reviewId = this.getAttribute('data-review-id');
            if (!reviewId) return;

            const isActive = this.classList.contains('active');
            const action = isActive ? 'unhelpful' : 'helpful';
            const buttonEl = this;
            buttonEl.disabled = true;

            fetch('api_review_helpful.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `review_id=${encodeURIComponent(reviewId)}&action=${action}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    buttonEl.classList.toggle('active', Boolean(data.is_helpful));
                    const card = buttonEl.closest('.review-card');
                    if (card) {
                        const countContainer = card.querySelector('.review-helpful-count');
                        if (countContainer) {
                            const countValue = countContainer.querySelector('.count-value');
                            if (countValue) {
                                countValue.textContent = data.helpful_count || 0;
                            }
                            if ((data.helpful_count || 0) > 0) {
                                countContainer.classList.remove('d-none');
                            } else {
                                countContainer.classList.add('d-none');
                            }
                        }
                    }
                } else {
                    alert(data.message || 'Unable to update helpful vote. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error updating helpful status:', error);
                alert('An error occurred. Please try again.');
            })
            .finally(() => {
                buttonEl.disabled = false;
            });
        });
    });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? String(text).replace(/[&<>"']/g, m => map[m]) : '';
}

function escapeHtmlAttr(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}
</script>

<?php require_once 'includes/footer.php'; ?>

