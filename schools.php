<?php
$page_title = 'Manage Schools';
require_once '../config.php';
require_once '../db.php';
require_once '../includes/functions.php';

$message = '';
$message_type = '';

// Handle delete school
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM schools WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $message = 'School deleted successfully.';
        $message_type = 'success';
    } else {
        $message = 'Error deleting school.';
        $message_type = 'danger';
    }
    $stmt->close();
}

// Handle delete photo
if (isset($_GET['delete_photo']) && is_numeric($_GET['delete_photo'])) {
    $photo_id = intval($_GET['delete_photo']);
    $stmt = $conn->prepare("SELECT photo_path, school_id FROM school_photos WHERE id = ?");
    $stmt->bind_param("i", $photo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $photo = $result->fetch_assoc();
    $stmt->close();
    
    if ($photo) {
        // Delete file
        $photo_file = __DIR__ . '/../uploads/schools/' . $photo['photo_path'];
        if (file_exists($photo_file)) {
            unlink($photo_file);
        }
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM school_photos WHERE id = ?");
        $stmt->bind_param("i", $photo_id);
        if ($stmt->execute()) {
            $message = 'Photo deleted successfully.';
            $message_type = 'success';
        }
        $stmt->close();
        
        // Redirect back to edit page
        header('Location: schools.php?edit=' . $photo['school_id']);
        exit;
    }
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $level = $_POST['level'] ?? '';
    $special_label = trim($_POST['special_label'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $google_maps_iframe_src = trim($_POST['google_maps_iframe_src'] ?? '');
    
    // Custom fields
    $classes = trim($_POST['classes'] ?? '');
    $principal = trim($_POST['principal'] ?? '');
    $founded = !empty($_POST['founded']) ? $_POST['founded'] : NULL;
    $established_year = !empty($_POST['established_year']) ? intval($_POST['established_year']) : NULL;
    $chairman = trim($_POST['chairman'] ?? '');
    $sister_schools = trim($_POST['sister_schools'] ?? '');
    $student_capacity = !empty($_POST['student_capacity']) ? intval($_POST['student_capacity']) : NULL;
    $accreditation = trim($_POST['accreditation'] ?? '');
    $motto = trim($_POST['motto'] ?? '');
    $vision = trim($_POST['vision'] ?? '');
    $mission = trim($_POST['mission'] ?? '');
    
    // Operating hours
    $operating_hours_json = null;
    if (isset($_POST['operating_hours']) && is_array($_POST['operating_hours'])) {
        $days = $_POST['operating_hours']['days'] ?? [];
        $is24Hours = isset($_POST['operating_hours']['is24Hours']) && ($_POST['operating_hours']['is24Hours'] == '1' || $_POST['operating_hours']['is24Hours'] === true);
        $isClosed = isset($_POST['operating_hours']['isClosed']) && ($_POST['operating_hours']['isClosed'] == '1' || $_POST['operating_hours']['isClosed'] === true);
        $timeSlots = [];
        
        // Process time slots
        foreach ($_POST['operating_hours'] as $key => $value) {
            if (is_numeric($key) && is_array($value)) {
                if (!empty($value['open']) && !empty($value['close'])) {
                    $timeSlots[] = [
                        'open' => trim($value['open']),
                        'close' => trim($value['close'])
                    ];
                }
            }
        }
        
        if (!empty($days)) {
            $operating_hours_json = json_encode([
                'days' => $days,
                'is24Hours' => $is24Hours,
                'isClosed' => $isClosed,
                'timeSlots' => $timeSlots
            ]);
        }
    }
    
    // New fields: Extract state and city from address, or use manual input
    $state = trim($_POST['state'] ?? '');
    $city = trim($_POST['city'] ?? '');
    if (empty($state) || empty($city)) {
        $location_data = extractStateCityFromAddress($address);
        if (empty($state)) $state = $location_data['state'];
        if (empty($city)) $city = $location_data['city'];
    }
    
    // Vendor owner: Get vendor owner account number
    $vendor_owner_account_number = null;
    $vendor_owner_type = trim($_POST['vendor_owner_type'] ?? 'free');
    if ($vendor_owner_type === 'vendor') {
        $vendor_account_number = trim($_POST['vendor_owner_account_number'] ?? '');
        if (!empty($vendor_account_number)) {
            // Validate that the account number exists and belongs to an approved vendor
            $vendor_check = $conn->prepare("SELECT id FROM users WHERE unique_number = ? AND role = 'vendor' AND vendor_status = 'approved'");
            $vendor_check->bind_param("i", $vendor_account_number);
            $vendor_check->execute();
            $vendor_result = $vendor_check->get_result();
            if ($vendor_result->num_rows > 0) {
                $vendor_owner_account_number = intval($vendor_account_number);
            }
            $vendor_check->close();
        }
    }
    // If vendor_owner_type is 'free', vendor_owner_account_number remains NULL
    
    // Budget fields
    $fee_type = trim($_POST['fee_type'] ?? '');
    $fee_remarks = trim($_POST['fee_remarks'] ?? '');
    
    // Languages used (multi-select checkbox)
    $languages_used = [];
    $language_options = ['English', 'Malay', 'Chinese'];
    foreach ($language_options as $lang) {
        if (isset($_POST['languages_' . strtolower($lang)])) {
            $languages_used[] = $lang;
        }
    }
    
    // School type
    $school_type = trim($_POST['school_type'] ?? '');
    
    // Available seats
    $available_seats = !empty($_POST['available_seats']) ? intval($_POST['available_seats']) : NULL;
    
    // Highlights and Facilities (will be processed after school is saved)
    $highlight_ids = isset($_POST['highlights']) && is_array($_POST['highlights']) ? array_map('intval', $_POST['highlights']) : [];
    $facility_ids = isset($_POST['facilities']) && is_array($_POST['facilities']) ? array_map('intval', $_POST['facilities']) : [];
    
    if (empty($name) || empty($address) || empty($level) || empty($email)) {
        $message = 'Please fill in required fields.';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'danger';
    } else {
        $image = trim($_POST['image'] ?? '');
        $uploaded_file_ext = null;
        $upload_dir = __DIR__ . '/../assets/images/schools/';
        
        // Handle main image upload (for schools.image field)
        if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] == UPLOAD_ERR_OK) {
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Validate file
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $file_ext = strtolower(pathinfo($_FILES['image_upload']['name'], PATHINFO_EXTENSION));
            $uploaded_file_ext = $file_ext;
            $max_size = 5242880; // 5MB
            
            if (!in_array($file_ext, $allowed_types)) {
                $message = 'Invalid file type. Allowed: JPG, JPEG, PNG, GIF, WEBP';
                $message_type = 'danger';
            } elseif ($_FILES['image_upload']['size'] > $max_size) {
                $message = 'File size exceeds 5MB limit.';
                $message_type = 'danger';
            } else {
                // Generate unique filename (not based on ID to avoid gaps when IDs are skipped)
                $new_filename = 'school_' . uniqid() . '_' . time() . '.' . $file_ext;
                
                $filepath = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['image_upload']['tmp_name'], $filepath)) {
                    // If editing existing school and has old image, delete it (if using old ID-based naming)
                    if ($id > 0 && !empty($edit_school['image'])) {
                        $old_image_path = $upload_dir . $edit_school['image'];
                        if (file_exists($old_image_path) && $old_image_path != $filepath && preg_match('/^school-\d+\./', $edit_school['image'])) {
                            @unlink($old_image_path); // Only delete if using old ID-based naming
                        }
                    }
                    $image = $new_filename;
                } else {
                    $message = 'Failed to upload image.';
                    $message_type = 'danger';
                }
            }
        }
        
        // Handle multiple photo uploads (for school_photos table) - up to 10 photos
        $photos_uploaded = 0;
        if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
            // For new schools, we'll process photos after insert, so we need the school_id
            // For existing schools, use the current id
            $target_school_id = ($id > 0) ? $id : null; // Will be set after insert if new school
            $photos_upload_dir = __DIR__ . '/../uploads/schools';
            
            // Create directory if it doesn't exist
            if (!file_exists($photos_upload_dir)) {
                mkdir($photos_upload_dir, 0755, true);
            }
            
            // Get current photo count (only for existing schools)
            $current_count = 0;
            if ($target_school_id && $target_school_id > 0) {
                $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM school_photos WHERE school_id = ?");
                $count_stmt->bind_param("i", $target_school_id);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                $current_count = $count_result->fetch_assoc()['count'];
                $count_stmt->close();
            }
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_size = 5242880; // 5MB
            $max_photos = 10;
            
            for ($i = 0; $i < count($_FILES['photos']['name']) && ($current_count + $photos_uploaded) < $max_photos; $i++) {
                if ($_FILES['photos']['error'][$i] == UPLOAD_ERR_OK) {
                    $file_ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
                    
                    if (in_array($file_ext, $allowed_types) && $_FILES['photos']['size'][$i] <= $max_size) {
                        // Generate unique filename
                        $filename = uniqid() . '_' . time() . '_' . $i . '.' . $file_ext;
                        $filepath = $photos_upload_dir . '/' . $filename;
                        
                        if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $filepath)) {
                            // Store filename for later processing (will save to DB after school is created/updated)
                            $photos_to_save[] = $filename;
                            $photos_uploaded++;
                        }
                    }
                }
            }
            
            if ($photos_uploaded > 0) {
                $message = ($message ? $message . ' ' : '') . "{$photos_uploaded} photo(s) uploaded successfully.";
                $message_type = 'success';
            }
        }
        
        // Prepare FAQs data (will process after school is created/updated)
        $faqs_to_save = [];
        for ($i = 1; $i <= 3; $i++) {
            $faq_question = trim($_POST['faq_question_' . $i] ?? '');
            $faq_answer = trim($_POST['faq_answer_' . $i] ?? '');
            
            if (!empty($faq_question) && !empty($faq_answer)) {
                $faqs_to_save[] = [
                    'question' => $faq_question,
                    'answer' => $faq_answer
                ];
            }
        }
        
        // If we have errors from file upload, don't proceed with database operation
        if ($message_type == 'danger' && !empty($message)) {
            // Error already set, skip database operation
        } else {
            // Check if google_maps_iframe_src column exists, if not, add it
            $check_col = $conn->query("SHOW COLUMNS FROM schools LIKE 'google_maps_iframe_src'");
            if ($check_col->num_rows == 0) {
                $conn->query("ALTER TABLE schools ADD COLUMN google_maps_iframe_src TEXT NULL AFTER longitude");
            }
            
            // Check if email column exists, if not, add it
            $check_email_col = $conn->query("SHOW COLUMNS FROM schools LIKE 'email'");
            if ($check_email_col->num_rows == 0) {
                $conn->query("ALTER TABLE schools ADD COLUMN email VARCHAR(255) NULL AFTER contact");
            }
            
            // Ensure new columns exist
            $new_columns = [
                'special_label' => "ALTER TABLE schools ADD COLUMN special_label VARCHAR(255) NULL AFTER level",
                'operating_hours' => "ALTER TABLE schools ADD COLUMN operating_hours JSON NULL AFTER description",
                'fee_type' => "ALTER TABLE schools ADD COLUMN fee_type VARCHAR(10) NULL AFTER operating_hours",
                'fee_remarks' => "ALTER TABLE schools ADD COLUMN fee_remarks TEXT NULL AFTER fee_type",
                'languages_used' => "ALTER TABLE schools ADD COLUMN languages_used JSON NULL AFTER fee_remarks",
                'school_type' => "ALTER TABLE schools ADD COLUMN school_type ENUM('Private', 'Public', 'International') NULL AFTER languages_used",
                'available_seats' => "ALTER TABLE schools ADD COLUMN available_seats INT NULL AFTER school_type",
                'state' => "ALTER TABLE schools ADD COLUMN state VARCHAR(100) NULL AFTER address",
                'city' => "ALTER TABLE schools ADD COLUMN city VARCHAR(100) NULL AFTER state",
                'vendor_owner_account_number' => "ALTER TABLE schools ADD COLUMN vendor_owner_account_number INT NULL AFTER city COMMENT 'Account number of vendor who owns this school. NULL means free to own by anyone.'"
            ];
            
            foreach ($new_columns as $col_name => $sql) {
                $check_col = $conn->query("SHOW COLUMNS FROM schools LIKE '$col_name'");
                if ($check_col->num_rows == 0) {
                    @$conn->query($sql);
                }
            }

            // Ensure fee_type column can store numeric budget levels
            $fee_type_col = $conn->query("SHOW COLUMNS FROM schools LIKE 'fee_type'");
            if ($fee_type_col && $fee_type_col->num_rows > 0) {
                $fee_type_info = $fee_type_col->fetch_assoc();
                if (stripos($fee_type_info['Type'], 'varchar') === false || stripos($fee_type_info['Type'], 'varchar(10)') === false) {
                    $conn->query("ALTER TABLE schools MODIFY COLUMN fee_type VARCHAR(10) NULL");
                }
            }
            
            // Prepare languages_used as JSON (convert to NULL if empty for proper binding)
            $languages_used_json = !empty($languages_used) ? json_encode($languages_used) : NULL;
            
            // Convert empty strings to NULL for optional fields to avoid type issues
            if ($school_type === '') {
                $school_type = NULL;
            }
            if ($special_label === '') {
                $special_label = NULL;
            }
            
            if ($id > 0) {
                // Update - 20 SET placeholders + 1 WHERE = 21 total
                // Type string: name(s), address(s), state(s), city(s), vendor_owner_account_number(i), level(s), special_label(s), contact(s), email(s), latitude(d), longitude(d), description(s), operating_hours(s), image(s), google_maps_iframe_src(s), fee_type(s), fee_remarks(s), languages_used(s), school_type(s), available_seats(i), id(i)
                // Count: 8s + 1i + 2d + 9s + 2i = 22 characters
                $stmt = $conn->prepare("UPDATE schools SET name = ?, address = ?, state = ?, city = ?, vendor_owner_account_number = ?, level = ?, special_label = ?, contact = ?, email = ?, description = ?, operating_hours = ?, image = ?, google_maps_iframe_src = ?, fee_type = ?, fee_remarks = ?, languages_used = ?, school_type = ?, available_seats = ?, classes = ?, principal = ?, founded = ?, established_year = ?, chairman = ?, sister_schools = ?, website = ?, student_capacity = ?, accreditation = ?, motto = ?, vision = ?, mission = ? WHERE id = ?");
                // Type string: 4s (name,address,state,city) + 1i (vendor_owner_account_number) + 5s (level,special_label,contact,email) + 7s (description,operating_hours,image,google_maps_iframe_src,fee_type,fee_remarks,languages_used) + 1s (school_type) + 1i (available_seats) + 12s (classes,principal,founded,chairman,sister_schools,website,accreditation,motto,vision,mission) + 2i (established_year,student_capacity) + 1i (id) = 30 total
                // Parameters: name(s), address(s), state(s), city(s), vendor_owner_account_number(i), level(s), special_label(s), contact(s), email(s), description(s), operating_hours(s), image(s), google_maps_iframe_src(s), fee_type(s), fee_remarks(s), languages_used(s), school_type(s), available_seats(i), classes(s), principal(s), founded(s), established_year(i), chairman(s), sister_schools(s), website(s), student_capacity(i), accreditation(s), motto(s), vision(s), mission(s), id(i)
                $stmt->bind_param("ssssisssssssssssssssssssssssssi", $name, $address, $state, $city, $vendor_owner_account_number, $level, $special_label, $contact, $email, $description, $operating_hours_json, $image, $google_maps_iframe_src, $fee_type, $fee_remarks, $languages_used_json, $school_type, $available_seats, $classes, $principal, $founded, $established_year, $chairman, $sister_schools, $website, $student_capacity, $accreditation, $motto, $vision, $mission, $id);
            } else {
                // Insert - 29 placeholders
                // Columns: name, address, state, city, vendor_owner_account_number, level, special_label, contact, email, description, operating_hours, image, google_maps_iframe_src, fee_type, fee_remarks, languages_used, school_type, available_seats, classes, principal, founded, established_year, chairman, sister_schools, website, student_capacity, accreditation, motto, vision, mission
                // Type string: 4s (name,address,state,city) + 1i (vendor_owner_account_number) + 5s (level,special_label,contact,email) + 7s (description,operating_hours,image,google_maps_iframe_src,fee_type,fee_remarks,languages_used) + 1s (school_type) + 1i (available_seats) + 12s (classes,principal,founded,chairman,sister_schools,website,accreditation,motto,vision,mission) + 2i (established_year,student_capacity) = 29 total
                $stmt = $conn->prepare("INSERT INTO schools (name, address, state, city, vendor_owner_account_number, level, special_label, contact, email, description, operating_hours, image, google_maps_iframe_src, fee_type, fee_remarks, languages_used, school_type, available_seats, classes, principal, founded, established_year, chairman, sister_schools, website, student_capacity, accreditation, motto, vision, mission) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                // Count type string: s(4) + i(1) + s(5) + s(7) + s(1) + i(1) + s(12) + i(2) = 29 characters
                $stmt->bind_param("ssssisssssssssssssssssssssssss", $name, $address, $state, $city, $vendor_owner_account_number, $level, $special_label, $contact, $email, $description, $operating_hours_json, $image, $google_maps_iframe_src, $fee_type, $fee_remarks, $languages_used_json, $school_type, $available_seats, $classes, $principal, $founded, $established_year, $chairman, $sister_schools, $website, $student_capacity, $accreditation, $motto, $vision, $mission);
            }
            
            if ($stmt->execute()) {
                // Get the school ID (for new schools, use insert_id; for existing, use the id)
                $school_id = ($id > 0) ? $id : $stmt->insert_id;
                
                // Extract coordinates from iframe if available
                $latitude = null;
                $longitude = null;
                
                if (!empty($google_maps_iframe_src)) {
                    // Try to extract coordinates from iframe src
                    // Method 1: Extract from pb parameter (common Google Maps embed format)
                    // Format: https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d...!2dLONGITUDE!3dLATITUDE
                    if (preg_match('/3d(-?\d+\.?\d*)/', $google_maps_iframe_src, $lat_match)) {
                        $latitude = floatval($lat_match[1]);
                        if (preg_match('/2d(-?\d+\.?\d*)/', $google_maps_iframe_src, $lng_match)) {
                            $longitude = floatval($lng_match[1]);
                        }
                    }
                    
                    // Method 2: Try ll parameter
                    if (is_null($latitude) && preg_match('/ll=(-?\d+\.?\d*),(-?\d+\.?\d*)/', $google_maps_iframe_src, $ll_match)) {
                        $latitude = floatval($ll_match[1]);
                        $longitude = floatval($ll_match[2]);
                    }
                    
                    // Method 3: Try q parameter with coordinates
                    if (is_null($latitude) && preg_match('/q=(-?\d+\.?\d*),(-?\d+\.?\d*)/', $google_maps_iframe_src, $q_match)) {
                        $latitude = floatval($q_match[1]);
                        $longitude = floatval($q_match[2]);
                    }
                    
                    // If coordinates were extracted, update the database
                    if (!is_null($latitude) && !is_null($longitude)) {
                        $update_coords_stmt = $conn->prepare("UPDATE schools SET latitude = ?, longitude = ? WHERE id = ?");
                        $update_coords_stmt->bind_param("ddi", $latitude, $longitude, $school_id);
                        $update_coords_stmt->execute();
                        $update_coords_stmt->close();
                    }
                }
                
                // If still no coordinates and editing existing school, get from database
                if (is_null($latitude) && $id > 0) {
                    $coords_stmt = $conn->prepare("SELECT latitude, longitude FROM schools WHERE id = ?");
                    $coords_stmt->bind_param("i", $id);
                    $coords_stmt->execute();
                    $coords_result = $coords_stmt->get_result();
                    if ($coords_row = $coords_result->fetch_assoc()) {
                        $latitude = $coords_row['latitude'];
                        $longitude = $coords_row['longitude'];
                    }
                    $coords_stmt->close();
                }
                
                // Auto-fetch external reviews from other platforms
                if (defined('AUTO_FETCH_EXTERNAL_REVIEWS') && AUTO_FETCH_EXTERNAL_REVIEWS) {
                    require_once __DIR__ . '/../includes/external_reviews_service.php';
                    $review_service = new ExternalReviewsService();
                    $school_data = [
                        'name' => $name,
                        'address' => $address,
                        'latitude' => $latitude,
                        'longitude' => $longitude
                    ];
                    $review_results = $review_service->fetchReviewsForSchool($school_id, $school_data);
                    
                    // Optionally log or display results
                    if ($review_results['success'] && $review_results['total_fetched'] > 0) {
                        $message = ($message ? $message . ' ' : '') . "Automatically fetched {$review_results['total_fetched']} external review(s).";
                    }
                }
                
                // Handle photo uploads - save to database now that we have the school_id
                if (!empty($photos_to_save) && $school_id > 0) {
                    foreach ($photos_to_save as $filename) {
                        $photo_stmt = $conn->prepare("INSERT INTO school_photos (school_id, photo_path, uploaded_by) VALUES (?, ?, NULL)");
                        $photo_stmt->bind_param("is", $school_id, $filename);
                        $photo_stmt->execute();
                        $photo_stmt->close();
                    }
                    if ($photos_uploaded > 0) {
                        $message = ($message ? $message . ' ' : '') . "{$photos_uploaded} photo(s) uploaded successfully.";
                        $message_type = 'success';
                    }
                }
                
                // Handle FAQs - save to database
                if ($school_id > 0) {
                    // For existing schools, delete old admin FAQs first
                    if ($id > 0) {
                        $delete_faq_stmt = $conn->prepare("DELETE FROM school_faqs WHERE school_id = ? AND created_by_user_id IS NULL");
                        $delete_faq_stmt->bind_param("i", $school_id);
                        $delete_faq_stmt->execute();
                        $delete_faq_stmt->close();
                    }
                    
                    // Insert new FAQs
                    foreach ($faqs_to_save as $faq) {
                        $faq_stmt = $conn->prepare("INSERT INTO school_faqs (school_id, question, answer, created_by_user_id, status) VALUES (?, ?, ?, NULL, 'Approved')");
                        $faq_stmt->bind_param("iss", $school_id, $faq['question'], $faq['answer']);
                        $faq_stmt->execute();
                        $faq_stmt->close();
                    }
                }
                
                // Handle highlights and facilities
                if ($school_id > 0) {
                    setSchoolHighlights($school_id, $highlight_ids);
                    setSchoolFacilities($school_id, $facility_ids);
                }
                
                // Image filename is already set with unique name, no need to rename
                // The image field is stored directly in the database with the unique filename
                
                $message = $id > 0 ? 'School updated successfully.' : 'School added successfully.';
                $message_type = 'success';
                
                // Refresh page to show updated school
                if ($id == 0) {
                    // After adding, redirect to remove edit parameter and close modal, show success message
                    header('Location: schools.php?added=' . $school_id);
                    exit;
                } else {
                    // After updating, redirect to remove edit parameter and close modal
                    header('Location: schools.php?updated=' . $school_id);
                    exit;
                }
            } else {
                $message = 'Error saving school.';
                $message_type = 'danger';
            }
            $stmt->close();
        }
    }
}

// Now include header after all POST processing is done
require_once 'includes/header.php';

// Get school for editing (if edit parameter is set, show modal with data)
$edit_school = null;
$school_photos = [];
$school_faqs = [];
$school_highlights = [];
$school_facilities = [];
$show_edit_modal = false;

// Handle updated parameter (after successful update, show success message)
if (isset($_GET['updated']) && is_numeric($_GET['updated'])) {
    $message = 'School updated successfully.';
    $message_type = 'success';
}

// Handle added parameter (after successful add, show success message)
if (isset($_GET['added']) && is_numeric($_GET['added'])) {
    $message = 'School added successfully.';
    $message_type = 'success';
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM schools WHERE id = $edit_id");
    if ($result && $result->num_rows > 0) {
        $edit_school = $result->fetch_assoc();
        $show_edit_modal = true;
        
        // Get school photos and FAQs
        if ($edit_school) {
        $photo_stmt = $conn->prepare("SELECT * FROM school_photos WHERE school_id = ? ORDER BY uploaded_at DESC");
        $photo_stmt->bind_param("i", $edit_id);
        $photo_stmt->execute();
        $photo_result = $photo_stmt->get_result();
        $school_photos = $photo_result->fetch_all(MYSQLI_ASSOC);
        $photo_stmt->close();
        
        // Get school FAQs (all FAQs for management)
        $faq_stmt = $conn->prepare("SELECT * FROM school_faqs WHERE school_id = ? ORDER BY created_at DESC");
        $faq_stmt->bind_param("i", $edit_id);
        $faq_stmt->execute();
        $faq_result = $faq_stmt->get_result();
        $school_faqs = $faq_result->fetch_all(MYSQLI_ASSOC);
        $faq_stmt->close();
        
        // Get school highlights and facilities
        $school_highlights = getSchoolHighlights($edit_id);
        $school_facilities = getSchoolFacilities($edit_id);
        }
    }
}

// Get all highlights and facilities for form checkboxes
$all_highlights = getAllHighlights();
$all_facilities = getAllFacilities();

// Get search parameter
$search_school = trim($_GET['search_school'] ?? '');

// Get all schools (ordered by ID descending - highest ID first)
$schools_query = "SELECT * FROM schools";
if (!empty($search_school)) {
    $schools_query .= " WHERE name LIKE ?";
    $search_param = '%' . $search_school . '%';
    $stmt = $conn->prepare($schools_query . " ORDER BY id DESC");
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    $schools = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $schools = $conn->query($schools_query . " ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
}
?>

<h1 class="mb-4">Manage Schools</h1>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Add School Button -->
<div class="mb-3">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#schoolModal" onclick="openSchoolModal(null)">
        <i class="fas fa-plus me-2"></i>Add New School
    </button>
</div>

<!-- Search Section -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="">
            <div class="row g-2">
                <div class="col-md-10">
                    <label class="form-label">Search by School Name</label>
                    <input type="text" class="form-control" name="search_school" placeholder="Search by school name..." value="<?php echo htmlspecialchars($search_school); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary me-2" type="submit">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <?php if (!empty($search_school)): ?>
                        <a href="schools.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Schools Table -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">All Schools (<?php echo count($schools); ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($schools)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Level</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schools as $school): ?>
                        <tr>
                            <td><?php echo $school['id']; ?></td>
                            <td>
                                <a href="../school-details.php?id=<?php echo $school['id']; ?>" target="_blank">
                                    <?php echo htmlspecialchars($school['name']); ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($school['level']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($school['contact'] ?? '-'); ?></td>
                            <td>
                                <div class="action-stack">
                                    <button type="button"
                                            class="btn btn-action btn-edit"
                                            onclick="openSchoolModal(<?php echo $school['id']; ?>)"
                                            title="Edit school"
                                            aria-label="Edit school">
                                        <i class="fas fa-pen-to-square"></i>
                                    </button>
                                    <a href="schools.php?delete=<?php echo $school['id']; ?>"
                                       class="btn btn-action btn-delete"
                                       title="Delete school"
                                       aria-label="Delete school"
                                       onclick="return confirm('Are you sure you want to delete this school?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-school fa-3x text-muted mb-3"></i>
                <p class="text-muted">No schools found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- School Modal -->
<div class="modal fade" id="schoolModal" tabindex="-1" aria-labelledby="schoolModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="schoolModalLabel"><?php echo $edit_school ? 'Edit School' : 'Add New School'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" enctype="multipart/form-data" id="schoolForm">
                    <input type="hidden" name="id" id="school_id" value="<?php echo $edit_school['id'] ?? ''; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">School Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($edit_school['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($edit_school['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">State <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="state" id="state_input" value="<?php echo htmlspecialchars($edit_school['state'] ?? ''); ?>" required autocomplete="off" list="state_list">
                            <datalist id="state_list"></datalist>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" id="city_input" value="<?php echo htmlspecialchars($edit_school['city'] ?? ''); ?>" autocomplete="off" list="city_list">
                            <datalist id="city_list"></datalist>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Level <span class="text-danger">*</span></label>
                        <select class="form-select" name="level" required>
                            <option value="">Select Level</option>
                            <option value="Kindergarten" <?php echo (isset($edit_school['level']) && $edit_school['level'] == 'Kindergarten') ? 'selected' : ''; ?>>Kindergarten</option>
                            <option value="Primary" <?php echo (isset($edit_school['level']) && $edit_school['level'] == 'Primary') ? 'selected' : ''; ?>>Primary</option>
                            <option value="Secondary" <?php echo (isset($edit_school['level']) && $edit_school['level'] == 'Secondary') ? 'selected' : ''; ?>>Secondary</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Special Label</label>
                        <input type="text" class="form-control" name="special_label" value="<?php echo htmlspecialchars($edit_school['special_label'] ?? ''); ?>" placeholder="e.g., Top School in Malaysia, Best Kindergarten, etc.">
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="fas fa-store me-2 text-primary"></i>Vendor Owner</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Owner Status <span class="text-danger">*</span></label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="vendor_owner_type" id="vendor_owner_free" value="free" <?php echo (empty($edit_school['vendor_owner_account_number'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="vendor_owner_free">
                                <i class="fas fa-unlock me-1 text-success"></i>Free to own by anyone
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="vendor_owner_type" id="vendor_owner_vendor" value="vendor" <?php echo (!empty($edit_school['vendor_owner_account_number'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="vendor_owner_vendor">
                                <i class="fas fa-store me-1 text-primary"></i>Owned by vendor (enter account number below)
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="vendorAccountNumberGroup" style="display: <?php echo (!empty($edit_school['vendor_owner_account_number'])) ? 'block' : 'none'; ?>;">
                        <label class="form-label">Vendor Account Number</label>
                        <input type="number" class="form-control" name="vendor_owner_account_number" id="vendor_owner_account_number" value="<?php echo htmlspecialchars($edit_school['vendor_owner_account_number'] ?? ''); ?>" placeholder="Enter vendor account number (e.g., 100000)">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact</label>
                        <input type="text" class="form-control" name="contact" value="<?php echo htmlspecialchars($edit_school['contact'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($edit_school['email'] ?? ''); ?>" placeholder="school@example.com" required>
                    </div>
                    
                    <!-- Custom Information Fields -->
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Custom Information Fields</h6>
                    
                    <div class="row g-3 mb-4">
                        <!-- Classes -->
                        <div class="col-md-6">
                            <div class="form-check mb-2">
                                <input class="form-check-input custom-field-checkbox" type="checkbox" name="custom_fields[]" value="classes" id="custom_classes" onchange="toggleCustomField('classes', this.checked);">
                                <label class="form-check-label fw-semibold" for="custom_classes">
                                    <i class="fas fa-users me-1 text-primary"></i>Classes
                                </label>
                            </div>
                            <input type="text" class="form-control custom-field-input" name="classes" id="field_classes" value="<?php echo htmlspecialchars($edit_school['classes'] ?? ''); ?>" placeholder="e.g., 101 (as of January 2025)" disabled>
                        </div>
                        
                        <!-- Principal -->
                        <div class="col-md-6">
                            <div class="form-check mb-2">
                                <input class="form-check-input custom-field-checkbox" type="checkbox" name="custom_fields[]" value="principal" id="custom_principal" onchange="toggleCustomField('principal', this.checked);">
                                <label class="form-check-label fw-semibold" for="custom_principal">
                                    <i class="fas fa-user-tie me-1 text-primary"></i>Principal
                                </label>
                            </div>
                            <input type="text" class="form-control custom-field-input" name="principal" id="field_principal" value="<?php echo htmlspecialchars($edit_school['principal'] ?? ''); ?>" placeholder="Principal name" disabled>
                        </div>
                        
                        <!-- Founded -->
                        <div class="col-md-6">
                            <div class="form-check mb-2">
                                <input class="form-check-input custom-field-checkbox" type="checkbox" name="custom_fields[]" value="founded" id="custom_founded" onchange="toggleCustomField('founded', this.checked);">
                                <label class="form-check-label fw-semibold" for="custom_founded">
                                    <i class="fas fa-calendar-alt me-1 text-primary"></i>Founded
                                </label>
                            </div>
                            <input type="date" class="form-control custom-field-input" name="founded" id="field_founded" value="<?php echo $edit_school['founded'] ?? ''; ?>" disabled>
                        </div>
                        
                        <!-- Established Year -->
                        <div class="col-md-6">
                            <div class="form-check mb-2">
                                <input class="form-check-input custom-field-checkbox" type="checkbox" name="custom_fields[]" value="established_year" id="custom_established_year" onchange="toggleCustomField('established_year', this.checked);">
                                <label class="form-check-label fw-semibold" for="custom_established_year">
                                    <i class="fas fa-calendar me-1 text-primary"></i>Established Year
                                </label>
                            </div>
                            <input type="number" class="form-control custom-field-input" name="established_year" id="field_established_year" value="<?php echo $edit_school['established_year'] ?? ''; ?>" placeholder="e.g., 1919" min="1800" max="<?php echo date('Y'); ?>" disabled>
                        </div>
                        
                        <!-- Chairman -->
                        <div class="col-md-6">
                            <div class="form-check mb-2">
                                <input class="form-check-input custom-field-checkbox" type="checkbox" name="custom_fields[]" value="chairman" id="custom_chairman" onchange="toggleCustomField('chairman', this.checked);">
                                <label class="form-check-label fw-semibold" for="custom_chairman">
                                    <i class="fas fa-user-graduate me-1 text-primary"></i>Chairman
                                </label>
                            </div>
                            <input type="text" class="form-control custom-field-input" name="chairman" id="field_chairman" value="<?php echo htmlspecialchars($edit_school['chairman'] ?? ''); ?>" placeholder="Chairman name" disabled>
                        </div>
                        
                        <!-- Sister Schools -->
                        <div class="col-md-6">
                            <div class="form-check mb-2">
                                <input class="form-check-input custom-field-checkbox" type="checkbox" name="custom_fields[]" value="sister_schools" id="custom_sister_schools" onchange="toggleCustomField('sister_schools', this.checked);">
                                <label class="form-check-label fw-semibold" for="custom_sister_schools">
                                    <i class="fas fa-school me-1 text-primary"></i>Sister Schools
                                </label>
                            </div>
                            <input type="text" class="form-control custom-field-input" name="sister_schools" id="field_sister_schools" value="<?php echo htmlspecialchars($edit_school['sister_schools'] ?? ''); ?>" placeholder="e.g., SMJK Chong Hwa, SJK(C) Chong Hwa" disabled>
                        </div>
                        
                        <!-- Student Capacity -->
                        <div class="col-md-6">
                            <div class="form-check mb-2">
                                <input class="form-check-input custom-field-checkbox" type="checkbox" name="custom_fields[]" value="student_capacity" id="custom_student_capacity" onchange="toggleCustomField('student_capacity', this.checked);">
                                <label class="form-check-label fw-semibold" for="custom_student_capacity">
                                    <i class="fas fa-user-friends me-1 text-primary"></i>Student Capacity
                                </label>
                            </div>
                            <input type="number" class="form-control custom-field-input" name="student_capacity" id="field_student_capacity" value="<?php echo $edit_school['student_capacity'] ?? ''; ?>" placeholder="Maximum number of students" min="0" disabled>
                        </div>
                        
                        <!-- Accreditation -->
                        <div class="col-md-12">
                            <div class="form-check mb-2">
                                <input class="form-check-input custom-field-checkbox" type="checkbox" name="custom_fields[]" value="accreditation" id="custom_accreditation" onchange="toggleCustomField('accreditation', this.checked);">
                                <label class="form-check-label fw-semibold" for="custom_accreditation">
                                    <i class="fas fa-certificate me-1 text-primary"></i>Accreditation
                                </label>
                            </div>
                            <textarea class="form-control custom-field-input" name="accreditation" id="field_accreditation" rows="2" placeholder="Accreditation information" disabled><?php echo htmlspecialchars($edit_school['accreditation'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Motto -->
                        <div class="col-md-12">
                            <div class="form-check mb-2">
                                <input class="form-check-input custom-field-checkbox" type="checkbox" name="custom_fields[]" value="motto" id="custom_motto" onchange="toggleCustomField('motto', this.checked);">
                                <label class="form-check-label fw-semibold" for="custom_motto">
                                    <i class="fas fa-quote-left me-1 text-primary"></i>Motto
                                </label>
                            </div>
                            <textarea class="form-control custom-field-input" name="motto" id="field_motto" rows="2" placeholder="School motto" disabled><?php echo htmlspecialchars($edit_school['motto'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Vision -->
                        <div class="col-md-12">
                            <div class="form-check mb-2">
                                <input class="form-check-input custom-field-checkbox" type="checkbox" name="custom_fields[]" value="vision" id="custom_vision" onchange="toggleCustomField('vision', this.checked);">
                                <label class="form-check-label fw-semibold" for="custom_vision">
                                    <i class="fas fa-eye me-1 text-primary"></i>Vision
                                </label>
                            </div>
                            <textarea class="form-control custom-field-input" name="vision" id="field_vision" rows="3" placeholder="School vision" disabled><?php echo htmlspecialchars($edit_school['vision'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Mission -->
                        <div class="col-md-12">
                            <div class="form-check mb-2">
                                <input class="form-check-input custom-field-checkbox" type="checkbox" name="custom_fields[]" value="mission" id="custom_mission" onchange="toggleCustomField('mission', this.checked);">
                                <label class="form-check-label fw-semibold" for="custom_mission">
                                    <i class="fas fa-bullseye me-1 text-primary"></i>Mission
                                </label>
                            </div>
                            <textarea class="form-control custom-field-input" name="mission" id="field_mission" rows="3" placeholder="School mission" disabled><?php echo htmlspecialchars($edit_school['mission'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <script>
                    // Enable/disable custom field inputs based on checkbox
                    function toggleCustomField(fieldName, enabled) {
                        const input = document.getElementById('field_' + fieldName);
                        if (input) {
                            input.disabled = !enabled;
                            if (!enabled) {
                                input.value = '';
                            }
                        }
                    }
                    
                    // Initialize: Check existing values and enable checkboxes
                    document.addEventListener('DOMContentLoaded', function() {
                        const customFields = ['classes', 'principal', 'founded', 'established_year', 'chairman', 'sister_schools', 'website', 'student_capacity', 'accreditation', 'motto', 'vision', 'mission'];
                        customFields.forEach(field => {
                            const input = document.getElementById('field_' + field);
                            const checkbox = document.getElementById('custom_' + field);
                            if (input && checkbox && input.value) {
                                checkbox.checked = true;
                                input.disabled = false;
                            }
                        });
                    });
                    </script>
                    
                    <!-- Operating Hours Section -->
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="fas fa-clock me-2 text-primary"></i>Operating Hours</h6>
                    <div class="mb-3">
                        <div id="adminOperatingHours"></div>
                    </div>
                    
                    <!-- Description Section -->
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="fas fa-align-left me-2 text-primary"></i>Description</h6>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="5" placeholder="Enter school description..."><?php echo htmlspecialchars($edit_school['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Google Maps Section -->
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="fas fa-map me-2 text-primary"></i>Google Maps</h6>
                    
                    <!-- Map Preview -->
                    <div class="mb-3" id="initialMapPreview">
                        <?php
                        $map_iframe_src = '';
                        if ($edit_school && !empty($edit_school['google_maps_iframe_src'])) {
                            $map_iframe_src = $edit_school['google_maps_iframe_src'];
                        } elseif ($edit_school && !empty($edit_school['address'])) {
                            // Fallback to geocoded address
                            $map_iframe_src = 'https://www.google.com/maps?q=' . urlencode($edit_school['address'] . ', Malaysia');
                        } else {
                            $map_iframe_src = 'https://www.google.com/maps?q=Malaysia';
                        }
                        ?>
                        <div class="border rounded p-2 bg-light text-center" style="min-height: 300px; display: flex; align-items: center; justify-content: center;">
                            <?php if (!empty($edit_school['google_maps_iframe_src'])): ?>
                                <iframe id="initialMapFrame" 
                                        src="<?php echo htmlspecialchars($edit_school['google_maps_iframe_src']); ?>" 
                                        width="100%" 
                                        height="300" 
                                        style="border:0; max-width: 100%;" 
                                        allowfullscreen="" 
                                        loading="lazy" 
                                        referrerpolicy="no-referrer-when-downgrade">
                                </iframe>
                            <?php else: ?>
                                <div class="text-muted">
                                    <i class="fas fa-map fa-3x mb-3"></i>
                                    <p>Map preview will appear here after pasting iframe code</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2 text-center">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="viewMapBtn" onclick="viewMapOnGoogleMaps()">
                                <i class="fas fa-external-link-alt me-1"></i>View Map on Google Maps
                            </button>
                        </div>
                    </div>
                    
                    <!-- Google Maps Iframe Paste Box -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-code me-2 text-primary"></i>
                            <strong>Paste Google Maps Embed Code Here</strong>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="googleMapsIframe" 
                               placeholder="Paste Google Maps iframe code here"
                               value="<?php echo htmlspecialchars($edit_school['google_maps_iframe'] ?? ''); ?>">
                        <input type="hidden" name="google_maps_iframe_src" id="googleMapsIframeSrc" value="<?php echo htmlspecialchars($edit_school['google_maps_iframe_src'] ?? ''); ?>">
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Click "View Map on Google Maps" above to open Google Maps. Locate the precise place, click "Share", select "Embed a map", copy the iframe code, and paste it here.
                        </small>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="fas fa-tag me-2 text-primary"></i>School Highlights</h6>
                    <div class="row g-2 mb-3">
                        <?php 
                        $current_highlight_ids = array_column($school_highlights, 'id');
                        foreach ($all_highlights as $highlight): 
                            $checked = in_array($highlight['id'], $current_highlight_ids) ? 'checked' : '';
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="highlights[]" value="<?php echo $highlight['id']; ?>" id="highlight_<?php echo $highlight['id']; ?>" <?php echo $checked; ?>>
                                <label class="form-check-label" for="highlight_<?php echo $highlight['id']; ?>">
                                    <i class="<?php echo htmlspecialchars($highlight['icon']); ?> me-1 text-primary"></i>
                                    <?php echo htmlspecialchars($highlight['name']); ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="fas fa-building me-2 text-primary"></i>Facilities</h6>
                    <div class="row g-2 mb-3">
                        <?php 
                        $current_facility_ids = array_column($school_facilities, 'id');
                        foreach ($all_facilities as $facility): 
                            $checked = in_array($facility['id'], $current_facility_ids) ? 'checked' : '';
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="facilities[]" value="<?php echo $facility['id']; ?>" id="facility_<?php echo $facility['id']; ?>" <?php echo $checked; ?>>
                                <label class="form-check-label" for="facility_<?php echo $facility['id']; ?>">
                                    <i class="<?php echo htmlspecialchars($facility['icon']); ?> me-1 text-primary"></i>
                                    <?php echo htmlspecialchars($facility['name']); ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="fas fa-dollar-sign me-2 text-primary"></i>Budget</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Budget <span class="text-danger">*</span></label>
                        <select class="form-select" name="fee_type" id="fee_type" required>
                            <option value="">Select Budget</option>
                            <option value="free" <?php echo (isset($edit_school['fee_type']) && $edit_school['fee_type'] == 'free') ? 'selected' : ''; ?>>Free / Sponsored</option>
                            <option value="1" <?php echo (isset($edit_school['fee_type']) && $edit_school['fee_type'] == '1') ? 'selected' : ''; ?>>$ (Budget Estimate)</option>
                            <option value="2" <?php echo (isset($edit_school['fee_type']) && $edit_school['fee_type'] == '2') ? 'selected' : ''; ?>>$$ (Budget Estimate)</option>
                            <option value="3" <?php echo (isset($edit_school['fee_type']) && $edit_school['fee_type'] == '3') ? 'selected' : ''; ?>>$$$ (Budget Estimate)</option>
                            <option value="4" <?php echo (isset($edit_school['fee_type']) && $edit_school['fee_type'] == '4') ? 'selected' : ''; ?>>$$$$ (Budget Estimate)</option>
                            <option value="5" <?php echo (isset($edit_school['fee_type']) && $edit_school['fee_type'] == '5') ? 'selected' : ''; ?>>$$$$$ (Budget Estimate)</option>
                        </select>
                    </div>
                    
                    <!-- Budget Remarks (Optional) -->
                    <div class="mb-3">
                        <label class="form-label">Budget Remarks (Optional)</label>
                        <textarea class="form-control" name="fee_remarks" rows="2" placeholder="Example: Fully funded by the Ministry of Education, except RM50 registration fee."><?php echo htmlspecialchars($edit_school['fee_remarks'] ?? ''); ?></textarea>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="fas fa-language me-2 text-primary"></i>Language & Type</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Languages Used</label>
                        <div class="row g-2">
                            <?php 
                            $current_languages = [];
                            if (!empty($edit_school['languages_used'])) {
                                $current_languages = json_decode($edit_school['languages_used'], true);
                                if (!is_array($current_languages)) $current_languages = [];
                            }
                            $language_options = ['English', 'Malay', 'Chinese'];
                            foreach ($language_options as $lang):
                                $checked = in_array($lang, $current_languages) ? 'checked' : '';
                            ?>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="languages_<?php echo strtolower($lang); ?>" value="1" id="lang_<?php echo strtolower($lang); ?>" <?php echo $checked; ?>>
                                    <label class="form-check-label" for="lang_<?php echo strtolower($lang); ?>">
                                        <?php echo $lang; ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">School Type</label>
                        <select class="form-select" name="school_type">
                            <option value="">Select Type</option>
                            <option value="Private" <?php echo (isset($edit_school['school_type']) && $edit_school['school_type'] == 'Private') ? 'selected' : ''; ?>>Private</option>
                            <option value="Public" <?php echo (isset($edit_school['school_type']) && $edit_school['school_type'] == 'Public') ? 'selected' : ''; ?>>Public</option>
                            <option value="International" <?php echo (isset($edit_school['school_type']) && $edit_school['school_type'] == 'International') ? 'selected' : ''; ?>>International</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Available Seats (Optional)</label>
                        <input type="number" class="form-control" name="available_seats" 
                               value="<?php echo isset($edit_school['available_seats']) ? intval($edit_school['available_seats']) : ''; ?>" 
                               min="0" placeholder="Number of available seats">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Main School Image</label>
                        <input type="file" class="form-control" name="image_upload" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                    </div>
                    
                    <?php if ($edit_school): ?>
                    <div class="mb-3">
                        <label class="form-label">Main Image Preview</label>
                        <?php 
                        // Check for school image in various formats
                        $current_image = '../assets/images/placeholders/school-default.jpg';
                        if (!empty($edit_school['image']) && file_exists('../assets/images/schools/' . $edit_school['image'])) {
                            $current_image = '../assets/images/schools/' . $edit_school['image'];
                        } else {
                            // Check for school image in various formats (jpg, jpeg, png, gif, webp)
                            $image_formats = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                            foreach ($image_formats as $format) {
                                $image_path = '../assets/images/schools/school-' . $edit_school['id'] . '.' . $format;
                                if (file_exists($image_path)) {
                                    $current_image = $image_path;
                                    break;
                                }
                            }
                        }
                        ?>
                        <div class="text-center p-2 border rounded bg-light">
                            <img src="<?php echo $current_image; ?>" alt="School Image" class="img-fluid" style="max-height: 150px; width: auto;">
                        </div>
                        <small class="text-muted d-block mt-2">
                            Current: <code><?php echo basename($current_image); ?></code>
                        </small>
                    </div>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    
                    <div class="mb-3">
                        <label class="form-label">School Photos Gallery <span class="badge bg-info"><?php echo $edit_school ? count($school_photos) : 0; ?>/10</span></label>
                        <input type="file" class="form-control" name="photos[]" multiple accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" max="10">
                    </div>
                    
                    <?php if (!empty($school_photos)): ?>
                    <div class="mb-3">
                        <label class="form-label">Current Photos</label>
                        <div class="row g-2">
                            <?php foreach ($school_photos as $photo): ?>
                            <div class="col-6 col-md-4">
                                <div class="position-relative border rounded p-2 bg-light">
                                    <img src="../uploads/schools/<?php echo htmlspecialchars($photo['photo_path']); ?>" 
                                         alt="Photo" 
                                         class="img-fluid w-100" 
                                         style="height: 100px; object-fit: cover;">
                                    <a href="?delete_photo=<?php echo $photo['id']; ?>" 
                                       class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" 
                                       onclick="return confirm('Delete this photo?');"
                                       title="Delete">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- FAQs Section -->
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="fas fa-question-circle me-2 text-primary"></i>Frequently Asked Questions (Optional)</h6>
                    
                    <?php 
                    // Get admin-created FAQs for pre-filling (only when editing)
                    $admin_faqs = [];
                    if ($edit_school) {
                        foreach ($school_faqs as $faq) {
                            if ($faq['created_by_user_id'] === null) {
                                $admin_faqs[] = $faq;
                            }
                        }
                    }
                    // Fill up to 3 FAQs
                    while (count($admin_faqs) < 3) {
                        $admin_faqs[] = ['question' => '', 'answer' => ''];
                    }
                    ?>
                    
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                    <?php $current_faq = $admin_faqs[$i - 1] ?? ['question' => '', 'answer' => '']; ?>
                    <div class="card mb-3 border-light">
                        <div class="card-body p-3">
                            <h6 class="text-primary mb-2">FAQ <?php echo $i; ?></h6>
                            <div class="mb-2">
                                <label class="form-label small">Question</label>
                                <input type="text" class="form-control form-control-sm" name="faq_question_<?php echo $i; ?>" 
                                       value="<?php echo htmlspecialchars($current_faq['question'] ?? ''); ?>" 
                                       placeholder="Enter question...">
                            </div>
                            <div class="mb-0">
                                <label class="form-label small">Answer</label>
                                <textarea class="form-control form-control-sm" name="faq_answer_<?php echo $i; ?>" 
                                          rows="2" placeholder="Enter answer..."><?php echo htmlspecialchars($current_faq['answer'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                    
                    <!-- Hidden field for manual filename override (optional) -->
                    <input type="hidden" name="image" value="<?php echo htmlspecialchars($edit_school['image'] ?? ''); ?>">
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i><span id="submitButtonText">Add School</span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
        // Open school modal for add/edit
        function openSchoolModal(schoolId) {
            const modal = new bootstrap.Modal(document.getElementById('schoolModal'));
            const modalTitle = document.getElementById('schoolModalLabel');
            const submitButtonText = document.getElementById('submitButtonText');
            const schoolIdInput = document.getElementById('school_id');
            const form = document.getElementById('schoolForm');
            
            if (schoolId) {
                // Edit mode - redirect to page with edit parameter
                window.location.href = 'schools.php?edit=' + schoolId;
            } else {
                // Add mode - clear form and show modal
                // First, update URL to remove edit parameter without page reload
                if (window.location.search.includes('edit=') || window.location.search.includes('updated=') || window.location.search.includes('added=')) {
                    const newUrl = window.location.pathname + (window.location.search.replace(/[?&]edit=\d+/, '').replace(/[?&]updated=\d+/, '').replace(/[?&]added=\d+/, '') || '');
                    window.history.pushState({}, '', newUrl);
                }
                
                // Clear all form fields
                form.reset();
                schoolIdInput.value = '';
                
                // Clear all text inputs, textareas, and selects
                const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="number"], input[type="url"], textarea, select');
                inputs.forEach(input => {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = false;
                    } else {
                        input.value = '';
                    }
                });
                
                // Clear checkboxes
                const checkboxes = form.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                // Clear file inputs
                const fileInputs = form.querySelectorAll('input[type="file"]');
                fileInputs.forEach(fileInput => {
                    fileInput.value = '';
                });
                
                // Clear image previews
                const imagePreviewSection = form.querySelector('.mb-3:has(.text-center.p-2.border.rounded.bg-light)');
                if (imagePreviewSection) {
                    const imagePreview = imagePreviewSection.querySelector('.text-center.p-2.border.rounded.bg-light');
                    if (imagePreview) {
                        const img = imagePreview.querySelector('img');
                        if (img) {
                            img.src = '../assets/images/placeholders/school-default.jpg';
                        }
                    }
                    const previewText = imagePreviewSection.querySelector('.text-muted.d-block.mt-2');
                    if (previewText) {
                        previewText.innerHTML = 'Current: <code>school-default.jpg</code>';
                    }
                }
                
                // Clear photo gallery previews
                const photoGallerySections = form.querySelectorAll('.mb-3');
                photoGallerySections.forEach(section => {
                    const label = section.querySelector('label');
                    if (label && label.textContent.includes('Current Photos')) {
                        const photoGallery = section.querySelector('.row.g-2');
                        if (photoGallery) {
                            photoGallery.innerHTML = '';
                        }
                        section.style.display = 'none';
                    }
                });
                
                // Clear custom field inputs
                const customFieldInputs = form.querySelectorAll('.custom-field-input');
                customFieldInputs.forEach(input => {
                    input.value = '';
                    input.disabled = true;
                });
                
                // Uncheck custom field checkboxes
                const customFieldCheckboxes = form.querySelectorAll('.custom-field-checkbox');
                customFieldCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                // Clear operating hours picker if it exists
                if (typeof adminOperatingHoursPicker !== 'undefined' && adminOperatingHoursPicker) {
                    adminOperatingHoursPicker.setValue({
                        days: [],
                        is24Hours: false,
                        isClosed: false,
                        timeSlots: []
                    });
                }
                
                // Clear map preview
                const mapFrame = document.getElementById('initialMapFrame');
                const mapPlaceholder = document.getElementById('mapPlaceholder');
                const iframeInput = document.getElementById('googleMapsIframe');
                const iframeSrcField = document.getElementById('googleMapsIframeSrc');
                
                if (mapFrame) {
                    mapFrame.src = '';
                }
                if (iframeInput) {
                    iframeInput.value = '';
                }
                if (iframeSrcField) {
                    iframeSrcField.value = '';
                }
                if (mapPlaceholder && mapFrame) {
                    mapFrame.parentElement.innerHTML = '<div class="text-muted" id="mapPlaceholder"><i class="fas fa-map fa-3x mb-3"></i><p>Map preview will appear here after pasting iframe code</p></div>';
                }
                
                // Clear any existing edit data from PHP
                // Force clear all value attributes that might be set by PHP
                const allInputs = form.querySelectorAll('input, textarea, select');
                allInputs.forEach(input => {
                    if (input.type !== 'checkbox' && input.type !== 'radio' && input.type !== 'file' && input.type !== 'hidden' && input.id !== 'school_id') {
                        input.value = '';
                    }
                });
                
                modalTitle.textContent = 'Add New School';
                submitButtonText.textContent = 'Add School';
                modal.show();
            }
        }
        
        // Extract coordinates from Google Maps iframe embed code
        function extractCoordinatesFromIframe(iframeCode) {
            if (!iframeCode || typeof iframeCode !== 'string') {
                return null;
            }
            
            try {
                // Method 1: Extract from pb parameter (common Google Maps embed format)
                // Format: https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d...!2dLONGITUDE!3dLATITUDE
                let match = iframeCode.match(/3d(-?\d+\.?\d*)/);
                if (match) {
                    const lat = parseFloat(match[1]);
                    match = iframeCode.match(/2d(-?\d+\.?\d*)/);
                    if (match) {
                        const lng = parseFloat(match[1]);
                        if (!isNaN(lat) && !isNaN(lng)) {
                            return { lat: lat, lng: lng };
                        }
                    }
                }
                
                // Method 2: Extract from ll parameter
                // Format: ll=LATITUDE,LONGITUDE
                match = iframeCode.match(/ll=(-?\d+\.?\d*),(-?\d+\.?\d*)/);
                if (match) {
                    const lat = parseFloat(match[1]);
                    const lng = parseFloat(match[2]);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        return { lat: lat, lng: lng };
                    }
                }
                
                // Method 3: Extract from q parameter with coordinates
                // Format: q=LATITUDE,LONGITUDE
                match = iframeCode.match(/q=(-?\d+\.?\d*),(-?\d+\.?\d*)/);
                if (match) {
                    const lat = parseFloat(match[1]);
                    const lng = parseFloat(match[2]);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        return { lat: lat, lng: lng };
                    }
                }
                
                // Method 4: Try to extract from src attribute
                match = iframeCode.match(/src=["']([^"']+)["']/);
                if (match) {
                    const src = match[1];
                    // Try ll parameter
                    let coordMatch = src.match(/ll=(-?\d+\.?\d*),(-?\d+\.?\d*)/);
                    if (coordMatch) {
                        const lat = parseFloat(coordMatch[1]);
                        const lng = parseFloat(coordMatch[2]);
                        if (!isNaN(lat) && !isNaN(lng)) {
                            return { lat: lat, lng: lng };
                        }
                    }
                    // Try q parameter
                    coordMatch = src.match(/q=(-?\d+\.?\d*),(-?\d+\.?\d*)/);
                    if (coordMatch) {
                        const lat = parseFloat(coordMatch[1]);
                        const lng = parseFloat(coordMatch[2]);
                        if (!isNaN(lat) && !isNaN(lng)) {
                            return { lat: lat, lng: lng };
                        }
                    }
                }
                
                return null;
            } catch (error) {
                console.error('Error extracting coordinates:', error);
                return null;
            }
        }
        
        // Function to view map on Google Maps
        function viewMapOnGoogleMaps() {
            const iframeSrcField = document.getElementById('googleMapsIframeSrc');
            const iframeInput = document.getElementById('googleMapsIframe');
            let mapUrl = '';
            
            if (iframeSrcField && iframeSrcField.value) {
                // Extract coordinates from iframe src
                const iframeSrc = iframeSrcField.value;
                const coords = extractCoordinatesFromIframe(iframeSrc);
                if (coords) {
                    mapUrl = `https://www.google.com/maps?q=${coords.lat},${coords.lng}`;
                } else {
                    // If no coordinates, try to use the iframe src directly
                    mapUrl = iframeSrc.replace('/embed', '').replace('embed?', '?');
                }
            } else if (iframeInput && iframeInput.value) {
                // Extract from iframe code
                const coords = extractCoordinatesFromIframe(iframeInput.value);
                if (coords) {
                    mapUrl = `https://www.google.com/maps?q=${coords.lat},${coords.lng}`;
                }
            }
            
            if (mapUrl) {
                window.open(mapUrl, '_blank');
            } else {
                // Fallback to Malaysia
                window.open('https://www.google.com/maps?q=Malaysia', '_blank');
            }
        }
        
        // Update map preview with iframe
        function updateMapPreviewFromIframe(iframeCode) {
            const initialMapFrame = document.getElementById('initialMapFrame');
            const initialMapPreview = document.getElementById('initialMapPreview');
            const latitudeField = document.getElementById('latitude');
            const longitudeField = document.getElementById('longitude');
            
            if (!iframeCode || iframeCode.trim().length === 0) {
                return;
            }
            
            // Extract iframe src URL - use the EXACT src from the pasted iframe
            let iframeSrc = null;
            const srcMatch = iframeCode.match(/src=["']([^"']+)["']/);
            if (srcMatch) {
                // Use the exact src URL from the iframe code
                iframeSrc = srcMatch[1];
            } else if (iframeCode.includes('google.com/maps')) {
                // If user pasted just the URL without iframe tags
                iframeSrc = iframeCode.trim();
                // If it's not a full URL, add https://
                if (!iframeSrc.startsWith('http')) {
                    iframeSrc = 'https://' + iframeSrc;
                }
            }
            
            if (iframeSrc) {
                // Save the iframe src to hidden field so it's submitted with the form
                const iframeSrcField = document.getElementById('googleMapsIframeSrc');
                if (iframeSrcField) {
                    iframeSrcField.value = iframeSrc;
                }
                
                // Create or update the iframe in the preview
                if (!initialMapFrame && initialMapPreview) {
                    // Create iframe if it doesn't exist
                    const previewContainer = initialMapPreview.querySelector('div');
                    if (previewContainer) {
                        previewContainer.innerHTML = '';
                        const newIframe = document.createElement('iframe');
                        newIframe.id = 'initialMapFrame';
                        newIframe.src = iframeSrc;
                        newIframe.width = '100%';
                        newIframe.height = '300';
                        newIframe.style.border = '0';
                        newIframe.style.maxWidth = '100%';
                        newIframe.setAttribute('allowfullscreen', '');
                        newIframe.setAttribute('loading', 'lazy');
                        newIframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
                        previewContainer.appendChild(newIframe);
                    }
                } else if (initialMapFrame) {
                    // Update existing iframe
                    initialMapFrame.src = '';
                    setTimeout(function() {
                        initialMapFrame.src = iframeSrc;
                    }, 50);
                }
            }
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Handle iframe paste
            const iframeInput = document.getElementById('googleMapsIframe');
            if (iframeInput) {
                // Update on paste
                iframeInput.addEventListener('paste', function(e) {
                    setTimeout(() => {
                        updateMapPreviewFromIframe(this.value);
                    }, 100);
                });
                
                // Update on input change
                iframeInput.addEventListener('input', function() {
                    updateMapPreviewFromIframe(this.value);
                });
                
                // Update on blur
                iframeInput.addEventListener('blur', function() {
                    updateMapPreviewFromIframe(this.value);
                });
                
                // Initialize if there's already a value or if there's a saved iframe src
                const iframeSrcField = document.getElementById('googleMapsIframeSrc');
                if (iframeInput.value) {
                    updateMapPreviewFromIframe(iframeInput.value);
                } else if (iframeSrcField && iframeSrcField.value) {
                    // If form has saved iframe src, use it to populate and update map
                    const savedSrc = iframeSrcField.value;
                    iframeInput.value = '<iframe src="' + savedSrc + '" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
                    updateMapPreviewFromIframe(iframeInput.value);
                }
            }
            
            // Fee structure - no dynamic fields needed anymore
        });
        </script>

<script>
// Initialize operating hours picker
document.addEventListener('DOMContentLoaded', function() {
    let adminOperatingHoursPicker = null;
    if (document.getElementById('adminOperatingHours')) {
        <?php
        // Load existing operating hours if available
        $existing_hours = null;
        if (!empty($edit_school['operating_hours'])) {
            $existing_hours = json_decode($edit_school['operating_hours'], true);
        }
        ?>
        adminOperatingHoursPicker = new OperatingHoursPicker('adminOperatingHours', {
            namePrefix: 'operating_hours'
        });
        <?php if ($existing_hours): ?>
        adminOperatingHoursPicker.setValue(<?php echo json_encode($existing_hours); ?>);
        <?php endif; ?>
    }
    
    // Collect operating hours data before form submission
    const adminForm = document.querySelector('form[method="POST"]');
    if (adminForm && adminOperatingHoursPicker) {
        adminForm.addEventListener('submit', function(e) {
            // Collect operating hours data
            const hoursValue = adminOperatingHoursPicker.getValue();
            
            // Clear existing hidden inputs
            const existingInputs = this.querySelectorAll('input[name^="operating_hours"]');
            existingInputs.forEach(input => input.remove());
            
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
        });
    }
    
    // Handle vendor owner type radio buttons
    const vendorOwnerTypeRadios = document.querySelectorAll('input[name="vendor_owner_type"]');
    const vendorAccountNumberGroup = document.getElementById('vendorAccountNumberGroup');
    
    if (vendorOwnerTypeRadios.length > 0 && vendorAccountNumberGroup) {
        vendorOwnerTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'vendor') {
                    vendorAccountNumberGroup.style.display = 'block';
                } else {
                    vendorAccountNumberGroup.style.display = 'none';
                    document.getElementById('vendor_owner_account_number').value = '';
                }
            });
        });
    }
    
    // State and City autocomplete
    const stateInput = document.getElementById('state_input');
    const cityInput = document.getElementById('city_input');
    const stateList = document.getElementById('state_list');
    const cityList = document.getElementById('city_list');
    
    if (stateInput && cityInput && stateList && cityList) {
        // Load all unique states from database
        let allStates = [];
        let allCities = {};
        
        // Fetch states and cities from database via AJAX
        fetch('../api/get_locations.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allStates = data.states || [];
                    allCities = data.cities || {};
                    
                    // Populate state datalist
                    allStates.forEach(state => {
                        const option = document.createElement('option');
                        option.value = state;
                        stateList.appendChild(option);
                    });
                    
                    // If editing and state exists, populate cities
                    if (stateInput.value && allCities[stateInput.value]) {
                        populateCities(stateInput.value);
                    }
                }
            })
            .catch(error => console.error('Error loading locations:', error));
        
        // State input autocomplete
        stateInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const filteredStates = allStates.filter(state => 
                state.toLowerCase().startsWith(query)
            );
            
            // Update datalist
            stateList.innerHTML = '';
            filteredStates.forEach(state => {
                const option = document.createElement('option');
                option.value = state;
                stateList.appendChild(option);
            });
            
            // Populate cities if state is selected
            if (this.value && allCities[this.value]) {
                populateCities(this.value);
            } else {
                cityList.innerHTML = '';
            }
        });
        
        // City input autocomplete
        cityInput.addEventListener('input', function() {
            const state = stateInput.value;
            if (!state || !allCities[state]) return;
            
            const query = this.value.toLowerCase();
            const filteredCities = allCities[state].filter(city => 
                city.toLowerCase().startsWith(query)
            );
            
            // Update datalist
            cityList.innerHTML = '';
            filteredCities.forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                cityList.appendChild(option);
            });
        });
        
        function populateCities(state) {
            if (!allCities[state]) return;
            
            cityList.innerHTML = '';
            allCities[state].forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                cityList.appendChild(option);
            });
        }
    }
});
</script>

<?php if ($show_edit_modal && $edit_school): ?>
<script>
// Auto-show modal if editing
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('schoolModal');
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
    
    // Update modal title and button text
    document.getElementById('schoolModalLabel').textContent = 'Edit School';
    document.getElementById('submitButtonText').textContent = 'Update School';
    
    // Ensure backdrop is removed when modal is hidden
    modalElement.addEventListener('hidden.bs.modal', function() {
        // Remove any lingering backdrop
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        
        // Remove modal-open class from body
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    });
});
</script>
<?php endif; ?>

<script>
// Ensure backdrop is removed when modal is closed
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('schoolModal');
    if (modalElement) {
        // Close modal if added parameter is present (after successful add)
        if (window.location.search.includes('added=')) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
            // Remove added parameter from URL
            const newUrl = window.location.pathname + window.location.search.replace(/[?&]added=\d+/, '');
            window.history.replaceState({}, '', newUrl);
        }
        // Handle modal hidden event
        modalElement.addEventListener('hidden.bs.modal', function() {
            // Remove any lingering backdrop
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Remove modal-open class from body
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        });
        
        // Also handle when modal is being hidden
        modalElement.addEventListener('hide.bs.modal', function() {
            // Clean up any issues before hiding
            const backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length > 1) {
                // Remove extra backdrops
                for (let i = 1; i < backdrops.length; i++) {
                    backdrops[i].remove();
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
