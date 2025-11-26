<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';
$page_title = t('directory_title');
require_once 'includes/header.php';

// Get user info if logged in
$user_info = [];
$is_logged_in = isLoggedIn();
if ($is_logged_in) {
    $user_info = getUserInfo($_SESSION['user_id']);
}
$is_vendor_user = $is_logged_in && (($user_info['role'] ?? '') === 'vendor');
$is_vendor_approved = $is_vendor_user && (($user_info['vendor_status'] ?? '') === 'approved');

// Get filters
$filters = [];
$search_term = '';
$nearby = false;
$user_lat = null;
$user_lng = null;

// Handle sorting
$rating_categories = ['location_rating', 'service_rating', 'facilities_rating', 'cleanliness_rating', 'value_rating', 'education_rating', 'overall_rating'];
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'name';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';

// For rating categories, default to DESC (highest first) if not specified
if (in_array($sort_by, $rating_categories) && !isset($_GET['sort_order'])) {
    $sort_order = 'DESC';
}

// Level filter
if (isset($_GET['level']) && !empty($_GET['level'])) {
    $filters['level'] = $_GET['level'];
}

// State/City filter
if (isset($_GET['state']) && !empty($_GET['state'])) {
    $filters['state'] = $_GET['state'];
}
if (isset($_GET['city']) && !empty($_GET['city'])) {
    $filters['city'] = $_GET['city'];
}

// School type filter
if (isset($_GET['school_type']) && !empty($_GET['school_type'])) {
    $filters['school_type'] = $_GET['school_type'];
}

// Highlights filter (multi-select)
if (isset($_GET['highlights']) && is_array($_GET['highlights'])) {
    $filters['highlights'] = array_map('intval', $_GET['highlights']);
}

// Facilities filter (multi-select)
if (isset($_GET['facilities']) && is_array($_GET['facilities'])) {
    $filters['facilities'] = array_map('intval', $_GET['facilities']);
}

// Languages filter (multi-select)
if (isset($_GET['languages']) && is_array($_GET['languages'])) {
    $filters['languages'] = $_GET['languages'];
}

// Budget filter (dollar sign or free)
if (isset($_GET['fee_type']) && !empty($_GET['fee_type'])) {
    $filters['fee_type'] = $_GET['fee_type'];
}

// Minimum rating filter
if (isset($_GET['min_rating']) && !empty($_GET['min_rating'])) {
    $filters['min_rating'] = floatval($_GET['min_rating']);
}

// Search term
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

// Nearby filter (GPS)
if (isset($_GET['lat']) && isset($_GET['lng']) && !empty($_GET['lat']) && !empty($_GET['lng'])) {
    $user_lat = floatval($_GET['lat']);
    $user_lng = floatval($_GET['lng']);
    $nearby = true;
}

// Get schools
$schools = [];
if ($nearby && $user_lat && $user_lng) {
    $schools = getNearbySchools($user_lat, $user_lng);
} else {
    $schools = getAllSchools($filters, $sort_by, $sort_order);
    
    // Filter by search term if provided
    if (!empty($search_term)) {
        $schools = array_filter($schools, function($school) use ($search_term) {
            return stripos($school['name'], $search_term) !== false || 
                   stripos($school['address'], $search_term) !== false;
        });
    }
}

$all_highlights = getAllHighlights();
$all_facilities = getAllFacilities();
$language_options = ['English', 'Malay', 'Chinese'];

$locationFiltersActive = !empty($filters['level'] ?? null) || !empty($filters['state'] ?? null) || !empty($filters['city'] ?? null);
$filterKeys = ['school_type', 'fee_type', 'min_rating', 'languages', 'highlights', 'facilities'];
$filterFiltersActive = false;
foreach ($filterKeys as $key) {
    if (!empty($filters[$key] ?? null)) {
        $filterFiltersActive = true;
        break;
    }
}
$sortActive = ($sort_by !== 'name') || (strtoupper($sort_order) !== 'ASC');

if (!function_exists('renderHiddenInputs')) {
    function renderHiddenInputs(array $excludeKeys = []) {
        foreach ($_GET as $key => $value) {
            if (in_array($key, $excludeKeys, true)) {
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $val) {
                    echo '<input type="hidden" name="' . htmlspecialchars($key) . '[]" value="' . htmlspecialchars($val) . '">' . PHP_EOL;
                }
            } else {
                echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">' . PHP_EOL;
            }
        }
    }
}
?>

<!-- Directory Header Section with Design Image -->
<?php 
$directory_bg = getDesignImage('designpic3');
$directory_section_style = '';
if ($directory_bg) {
    $directory_section_style = 'background-image: url(' . htmlspecialchars($directory_bg) . '); background-size: cover; background-position: center; ';
}
$directory_section_style .= 'padding: 40px 0 60px 0 !important; min-height: 200px !important;';
?>
<section class="directory-header-section text-center mb-0" style="<?php echo $directory_section_style; ?>">
    <div class="hero-overlay"></div>
    <div class="container position-relative" style="z-index: 10;">
        <div class="hero-text-container" style="padding: 20px 50px 10px 50px !important;">
            <h1 class="hero-title" style="margin-bottom: 0 !important;">
                <i class="fas fa-list me-2"></i><?php echo t('directory_title'); ?>
            </h1>
        </div>
    </div>
</section>

<div class="container mt-0 mb-4 page-content" style="margin-top: -50px !important;">
    <div class="row g-4">
        <!-- Filter Sidebar -->
        <div class="col-lg-3 d-none d-lg-block">
            <div class="card shadow-soft sticky-top" style="top: 100px;">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="directory.php" id="filterForm">
                        <?php
                        // Preserve search and sort params
                        if (!empty($search_term)): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                        <?php endif; ?>
                        <?php if (isset($_GET['sort_by'])): ?>
                        <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($_GET['sort_by']); ?>">
                        <?php endif; ?>
                        <?php if (isset($_GET['sort_order'])): ?>
                        <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($_GET['sort_order']); ?>">
                        <?php endif; ?>
                        
                        <!-- Level Filter -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">School Level</label>
                            <select class="form-select form-select-sm" name="level" onchange="this.form.submit()">
                                <option value="">All Levels</option>
                                <option value="Kindergarten" <?php echo (isset($filters['level']) && $filters['level'] == 'Kindergarten') ? 'selected' : ''; ?>>Kindergarten</option>
                                <option value="Primary" <?php echo (isset($filters['level']) && $filters['level'] == 'Primary') ? 'selected' : ''; ?>>Primary</option>
                                <option value="Secondary" <?php echo (isset($filters['level']) && $filters['level'] == 'Secondary') ? 'selected' : ''; ?>>Secondary</option>
                            </select>
                        </div>
                        
                        <!-- State/City Filter -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">State</label>
                            <input type="text" class="form-control form-control-sm" name="state" placeholder="e.g., Selangor, Kuala Lumpur, Johor" value="<?php echo isset($filters['state']) ? htmlspecialchars($filters['state']) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">City</label>
                            <input type="text" class="form-control form-control-sm" name="city" placeholder="e.g., Petaling Jaya, Shah Alam" value="<?php echo isset($filters['city']) ? htmlspecialchars($filters['city']) : ''; ?>">
                        </div>
                        
                        <!-- School Type Filter -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">School Type</label>
                            <select class="form-select form-select-sm" name="school_type" onchange="this.form.submit()">
                                <option value="">All Types</option>
                                <option value="Private" <?php echo (isset($filters['school_type']) && $filters['school_type'] == 'Private') ? 'selected' : ''; ?>>Private</option>
                                <option value="Public" <?php echo (isset($filters['school_type']) && $filters['school_type'] == 'Public') ? 'selected' : ''; ?>>Public</option>
                                <option value="International" <?php echo (isset($filters['school_type']) && $filters['school_type'] == 'International') ? 'selected' : ''; ?>>International</option>
                            </select>
                        </div>
                        
                        <!-- Budget Filter -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Budget</label>
                            <select class="form-select form-select-sm" name="fee_type" onchange="this.form.submit()">
                                <option value="">All Budgets</option>
                                <option value="free" <?php echo (isset($filters['fee_type']) && $filters['fee_type'] == 'free') ? 'selected' : ''; ?>>Free / Sponsored</option>
                                <option value="1" <?php echo (isset($filters['fee_type']) && $filters['fee_type'] == '1') ? 'selected' : ''; ?>>$ (Budget Estimate)</option>
                                <option value="2" <?php echo (isset($filters['fee_type']) && $filters['fee_type'] == '2') ? 'selected' : ''; ?>>$$ (Budget Estimate)</option>
                                <option value="3" <?php echo (isset($filters['fee_type']) && $filters['fee_type'] == '3') ? 'selected' : ''; ?>>$$$ (Budget Estimate)</option>
                                <option value="4" <?php echo (isset($filters['fee_type']) && $filters['fee_type'] == '4') ? 'selected' : ''; ?>>$$$$ (Budget Estimate)</option>
                                <option value="5" <?php echo (isset($filters['fee_type']) && $filters['fee_type'] == '5') ? 'selected' : ''; ?>>$$$$$ (Budget Estimate)</option>
                            </select>
                        </div>
                        
                        <!-- Minimum Rating Filter -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Minimum Rating</label>
                            <select class="form-select form-select-sm" name="min_rating" onchange="this.form.submit()">
                                <option value="">Any Rating</option>
                                <option value="4.5" <?php echo (isset($filters['min_rating']) && $filters['min_rating'] == 4.5) ? 'selected' : ''; ?>>4.5+ Stars</option>
                                <option value="4.0" <?php echo (isset($filters['min_rating']) && $filters['min_rating'] == 4.0) ? 'selected' : ''; ?>>4.0+ Stars</option>
                                <option value="3.5" <?php echo (isset($filters['min_rating']) && $filters['min_rating'] == 3.5) ? 'selected' : ''; ?>>3.5+ Stars</option>
                                <option value="3.0" <?php echo (isset($filters['min_rating']) && $filters['min_rating'] == 3.0) ? 'selected' : ''; ?>>3.0+ Stars</option>
                            </select>
                        </div>
                        
                        <!-- Languages Filter -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Languages</label>
                            <?php 
                            $selected_languages = isset($filters['languages']) ? $filters['languages'] : [];
                            foreach ($language_options as $lang):
                                $checked = in_array($lang, $selected_languages) ? 'checked' : '';
                            ?>
                            <div class="form-check form-check-sm">
                                <input class="form-check-input" type="checkbox" name="languages[]" value="<?php echo $lang; ?>" id="filter_lang_<?php echo strtolower($lang); ?>" <?php echo $checked; ?> onchange="this.form.submit()">
                                <label class="form-check-label" for="filter_lang_<?php echo strtolower($lang); ?>">
                                    <?php echo $lang; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Highlights Filter -->
                        <?php if (!empty($all_highlights)): ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Highlights</label>
                            <div class="filter-scroll" style="max-height: 200px; overflow-y: auto;">
                                <?php 
                                $selected_highlights = isset($filters['highlights']) ? $filters['highlights'] : [];
                                foreach ($all_highlights as $highlight):
                                    $checked = in_array($highlight['id'], $selected_highlights) ? 'checked' : '';
                                ?>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input" type="checkbox" name="highlights[]" value="<?php echo $highlight['id']; ?>" id="filter_highlight_<?php echo $highlight['id']; ?>" <?php echo $checked; ?> onchange="this.form.submit()">
                                    <label class="form-check-label" for="filter_highlight_<?php echo $highlight['id']; ?>">
                                        <i class="<?php echo htmlspecialchars($highlight['icon']); ?> me-1 text-primary small"></i>
                                        <small><?php echo htmlspecialchars($highlight['name']); ?></small>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Facilities Filter -->
                        <?php if (!empty($all_facilities)): ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Facilities</label>
                            <div class="filter-scroll" style="max-height: 200px; overflow-y: auto;">
                                <?php 
                                $selected_facilities = isset($filters['facilities']) ? $filters['facilities'] : [];
                                foreach ($all_facilities as $facility):
                                    $checked = in_array($facility['id'], $selected_facilities) ? 'checked' : '';
                                ?>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="<?php echo $facility['id']; ?>" id="filter_facility_<?php echo $facility['id']; ?>" <?php echo $checked; ?> onchange="this.form.submit()">
                                    <label class="form-check-label" for="filter_facility_<?php echo $facility['id']; ?>">
                                        <i class="<?php echo htmlspecialchars($facility['icon']); ?> me-1 text-primary small"></i>
                                        <small><?php echo htmlspecialchars($facility['name']); ?></small>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search me-1"></i>Apply Filters
                            </button>
                            <a href="directory.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-redo me-1"></i>Reset All
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="col-lg-9">
            <!-- Mobile Filter Controls -->
            <div class="d-lg-none w-100">
                <div class="mobile-filter-bar">
                    <button class="mobile-filter-button<?php echo $locationFiltersActive ? ' active' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#mobileFilterPanelLocation" aria-expanded="false" aria-controls="mobileFilterPanelLocation">
                        <i class="fas fa-map-marker-alt"></i>
                        Location
                        <?php if ($locationFiltersActive): ?><span class="filter-indicator"></span><?php endif; ?>
                    </button>
                    <button class="mobile-filter-button<?php echo $filterFiltersActive ? ' active' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#mobileFilterPanelFilters" aria-expanded="false" aria-controls="mobileFilterPanelFilters">
                        <i class="fas fa-sliders-h"></i>
                        Filters
                        <?php if ($filterFiltersActive): ?><span class="filter-indicator"></span><?php endif; ?>
                    </button>
                    <button class="mobile-filter-button<?php echo $sortActive ? ' active' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#mobileFilterPanelSort" aria-expanded="false" aria-controls="mobileFilterPanelSort">
                        <i class="fas fa-sort"></i>
                        Sort
                        <?php if ($sortActive): ?><span class="filter-indicator"></span><?php endif; ?>
                    </button>
                </div>
            </div>
            
            <div id="mobileFilterOverlay" class="mobile-filter-overlay d-lg-none"></div>
            <div id="mobileFilterAccordion" class="mobile-filter-accordion d-lg-none">
                <!-- Location Panel -->
                <div class="collapse mobile-filter-panel" id="mobileFilterPanelLocation" data-bs-parent="#mobileFilterAccordion">
                    <div class="mobile-filter-panel-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Location & Level</h5>
                            <button type="button" class="btn-close" data-bs-toggle="collapse" data-bs-target="#mobileFilterPanelLocation" aria-label="Close"></button>
                        </div>
                        <form action="directory.php" method="GET" class="mobile-filter-form">
                            <?php renderHiddenInputs(['level', 'state', 'city']); ?>
                            <div class="mb-3">
                                <label class="form-label mobile-filter-section-title">School Level</label>
                                <select class="form-select form-select-sm" name="level">
                                    <option value="">All Levels</option>
                                    <option value="Kindergarten" <?php echo (isset($filters['level']) && $filters['level'] == 'Kindergarten') ? 'selected' : ''; ?>>Kindergarten</option>
                                    <option value="Primary" <?php echo (isset($filters['level']) && $filters['level'] == 'Primary') ? 'selected' : ''; ?>>Primary</option>
                                    <option value="Secondary" <?php echo (isset($filters['level']) && $filters['level'] == 'Secondary') ? 'selected' : ''; ?>>Secondary</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label mobile-filter-section-title">State</label>
                                <input type="text" class="form-control form-control-sm" name="state" placeholder="e.g., Selangor" value="<?php echo isset($filters['state']) ? htmlspecialchars($filters['state']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label mobile-filter-section-title">City</label>
                                <input type="text" class="form-control form-control-sm" name="city" placeholder="e.g., Petaling Jaya" value="<?php echo isset($filters['city']) ? htmlspecialchars($filters['city']) : ''; ?>">
                            </div>
                            <div class="mobile-filter-actions">
                                <a href="directory.php" class="btn btn-outline-secondary">Reset All</a>
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Filters Panel -->
                <div class="collapse mobile-filter-panel" id="mobileFilterPanelFilters" data-bs-parent="#mobileFilterAccordion">
                    <div class="mobile-filter-panel-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Filters</h5>
                            <button type="button" class="btn-close" data-bs-toggle="collapse" data-bs-target="#mobileFilterPanelFilters" aria-label="Close"></button>
                        </div>
                        <form action="directory.php" method="GET" class="mobile-filter-form">
                            <?php renderHiddenInputs(['school_type', 'fee_type', 'min_rating', 'languages', 'highlights', 'facilities']); ?>
                            
                            <div class="mb-3">
                                <label class="form-label mobile-filter-section-title">School Type</label>
                                <select class="form-select form-select-sm" name="school_type">
                                    <option value="">All Types</option>
                                    <option value="Private" <?php echo (isset($filters['school_type']) && $filters['school_type'] == 'Private') ? 'selected' : ''; ?>>Private</option>
                                    <option value="Public" <?php echo (isset($filters['school_type']) && $filters['school_type'] == 'Public') ? 'selected' : ''; ?>>Public</option>
                                    <option value="International" <?php echo (isset($filters['school_type']) && $filters['school_type'] == 'International') ? 'selected' : ''; ?>>International</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label mobile-filter-section-title">Budget</label>
                                <select class="form-select form-select-sm" name="fee_type">
                                    <option value="">All Budgets</option>
                                    <option value="free" <?php echo (isset($filters['fee_type']) && $filters['fee_type'] == 'free') ? 'selected' : ''; ?>>Free / Sponsored</option>
                                    <option value="1" <?php echo (isset($filters['fee_type']) && $filters['fee_type'] == '1') ? 'selected' : ''; ?>>$ (Budget Estimate)</option>
                                    <option value="2" <?php echo (isset($filters['fee_type']) && $filters['fee_type'] == '2') ? 'selected' : ''; ?>>$$ (Budget Estimate)</option>
                                    <option value="3" <?php echo (isset($filters['fee_type']) && $filters['fee_type'] == '3') ? 'selected' : ''; ?>>$$$ (Budget Estimate)</option>
                                    <option value="4" <?php echo (isset($filters['fee_type']) && $filters['fee_type'] == '4') ? 'selected' : ''; ?>>$$$$ (Budget Estimate)</option>
                                    <option value="5" <?php echo (isset($filters['fee_type']) && $filters['fee_type'] == '5') ? 'selected' : ''; ?>>$$$$$ (Budget Estimate)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label mobile-filter-section-title">Minimum Rating</label>
                                <select class="form-select form-select-sm" name="min_rating">
                                    <option value="">Any Rating</option>
                                    <option value="4.5" <?php echo (isset($filters['min_rating']) && $filters['min_rating'] == 4.5) ? 'selected' : ''; ?>>4.5+ Stars</option>
                                    <option value="4.0" <?php echo (isset($filters['min_rating']) && $filters['min_rating'] == 4.0) ? 'selected' : ''; ?>>4.0+ Stars</option>
                                    <option value="3.5" <?php echo (isset($filters['min_rating']) && $filters['min_rating'] == 3.5) ? 'selected' : ''; ?>>3.5+ Stars</option>
                                    <option value="3.0" <?php echo (isset($filters['min_rating']) && $filters['min_rating'] == 3.0) ? 'selected' : ''; ?>>3.0+ Stars</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label mobile-filter-section-title">Languages</label>
                                <div class="mobile-filter-scroll">
                                    <?php 
                                    $selected_languages = isset($filters['languages']) ? $filters['languages'] : [];
                                    foreach ($language_options as $lang):
                                        $checked = in_array($lang, $selected_languages) ? 'checked' : '';
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="languages[]" value="<?php echo $lang; ?>" id="mobile_filter_lang_<?php echo strtolower($lang); ?>" <?php echo $checked; ?>>
                                        <label class="form-check-label" for="mobile_filter_lang_<?php echo strtolower($lang); ?>">
                                            <?php echo $lang; ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($all_highlights)): ?>
                            <div class="mb-3">
                                <label class="form-label mobile-filter-section-title">Highlights</label>
                                <div class="mobile-filter-scroll">
                                    <?php 
                                    $selected_highlights = isset($filters['highlights']) ? $filters['highlights'] : [];
                                    foreach ($all_highlights as $highlight):
                                        $checked = in_array($highlight['id'], $selected_highlights) ? 'checked' : '';
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="highlights[]" value="<?php echo $highlight['id']; ?>" id="mobile_filter_highlight_<?php echo $highlight['id']; ?>" <?php echo $checked; ?>>
                                        <label class="form-check-label" for="mobile_filter_highlight_<?php echo $highlight['id']; ?>">
                                            <i class="<?php echo htmlspecialchars($highlight['icon']); ?> me-1 text-primary"></i>
                                            <?php echo htmlspecialchars($highlight['name']); ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($all_facilities)): ?>
                            <div class="mb-3">
                                <label class="form-label mobile-filter-section-title">Facilities</label>
                                <div class="mobile-filter-scroll">
                                    <?php 
                                    $selected_facilities = isset($filters['facilities']) ? $filters['facilities'] : [];
                                    foreach ($all_facilities as $facility):
                                        $checked = in_array($facility['id'], $selected_facilities) ? 'checked' : '';
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="facilities[]" value="<?php echo $facility['id']; ?>" id="mobile_filter_facility_<?php echo $facility['id']; ?>" <?php echo $checked; ?>>
                                        <label class="form-check-label" for="mobile_filter_facility_<?php echo $facility['id']; ?>">
                                            <i class="<?php echo htmlspecialchars($facility['icon']); ?> me-1 text-primary"></i>
                                            <?php echo htmlspecialchars($facility['name']); ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mobile-filter-actions">
                                <a href="directory.php" class="btn btn-outline-secondary">Reset All</a>
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Sort Panel -->
                <div class="collapse mobile-filter-panel" id="mobileFilterPanelSort" data-bs-parent="#mobileFilterAccordion">
                    <div class="mobile-filter-panel-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Sort Options</h5>
                            <button type="button" class="btn-close" data-bs-toggle="collapse" data-bs-target="#mobileFilterPanelSort" aria-label="Close"></button>
                        </div>
                        <form action="directory.php" method="GET" class="mobile-filter-form">
                            <?php renderHiddenInputs(['sort_by', 'sort_order']); ?>
                            
                            <div class="mb-3">
                                <label class="form-label mobile-filter-section-title">Sort By</label>
                                <select class="form-select form-select-sm" name="sort_by">
                                    <option value="name" <?php echo ($sort_by == 'name') ? 'selected' : ''; ?>>Alphabetical</option>
                                    <option value="overall_rating" <?php echo ($sort_by == 'overall_rating') ? 'selected' : ''; ?>>Overall Rating</option>
                                    <option value="location_rating" <?php echo ($sort_by == 'location_rating') ? 'selected' : ''; ?>>Location Rating</option>
                                    <option value="cleanliness_rating" <?php echo ($sort_by == 'cleanliness_rating') ? 'selected' : ''; ?>>Cleanliness Rating</option>
                                    <option value="education_rating" <?php echo ($sort_by == 'education_rating') ? 'selected' : ''; ?>>Education Rating</option>
                                    <option value="service_rating" <?php echo ($sort_by == 'service_rating') ? 'selected' : ''; ?>>Service Rating</option>
                                    <option value="facilities_rating" <?php echo ($sort_by == 'facilities_rating') ? 'selected' : ''; ?>>Facilities Rating</option>
                                    <option value="value_rating" <?php echo ($sort_by == 'value_rating') ? 'selected' : ''; ?>>Value Rating</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label mobile-filter-section-title">Order</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sort_order" id="sortOrderDesc" value="DESC" <?php echo (strtoupper($sort_order) == 'DESC') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="sortOrderDesc">
                                            Highest First
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sort_order" id="sortOrderAsc" value="ASC" <?php echo (strtoupper($sort_order) == 'ASC') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="sortOrderAsc">
                                            Lowest/ A-Z
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mobile-filter-actions">
                                <a href="directory.php" class="btn btn-outline-secondary">Reset All</a>
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
    <!-- Results Count and Sorting -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <p class="mb-0 text-muted fw-semibold">
            <i class="fas fa-school me-2"></i><?php echo t('directory_found'); ?> <span class="text-primary fw-bold"><?php echo count($schools); ?></span> <?php echo t('directory_schools'); ?>
        </p>

        <!-- Sorting Options for Desktop -->
        <div class="d-none d-lg-flex gap-2 flex-wrap align-items-center">
            <!-- Reset Button -->
            <?php if (isset($_GET['sort_by']) || isset($_GET['sort_order']) || isset($_GET['level']) || isset($_GET['search'])): ?>
            <a href="directory.php" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-redo me-2"></i><?php echo t('directory_reset'); ?>
            </a>
            <?php endif; ?>

            <!-- Rating Sort Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="ratingSortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-star me-2"></i><?php echo t('directory_sort_by_rating'); ?>
                </button>
                <ul class="dropdown-menu" aria-labelledby="ratingSortDropdown">
                    <?php 
                    $get_next_order = function($category) use ($sort_by, $sort_order) {
                        if ($sort_by == $category) {
                            return ($sort_order == 'DESC') ? 'ASC' : 'DESC';
                        }
                        return 'DESC';
                    };

                    $get_category_label = function($category) {
                        switch($category) {
                            case 'overall_rating': return t('review_overall');
                            case 'location_rating': return t('review_location');
                            case 'cleanliness_rating': return t('review_cleanliness');
                            case 'education_rating': return t('review_education');
                            case 'service_rating': return t('review_service');
                            case 'facilities_rating': return t('review_facilities');
                            case 'value_rating': return t('review_value');
                            default: return '';
                        }
                    };

                    $categories_list = [
                        'overall_rating',
                        'location_rating',
                        'cleanliness_rating',
                        'education_rating',
                        'service_rating',
                        'facilities_rating',
                        'value_rating'
                    ];

                    foreach ($categories_list as $category):
                        $next_order = $get_next_order($category);
                        $is_current = ($sort_by == $category);
                        $order_label = ($next_order == 'DESC') ? t('directory_highest') : t('directory_lowest');
                        $category_label = $get_category_label($category);

                        $query_params = [];
                        if (isset($_GET['level']) && !empty($_GET['level'])) {
                            $query_params['level'] = $_GET['level'];
                        }
                        if (isset($_GET['search']) && !empty($_GET['search'])) {
                            $query_params['search'] = $_GET['search'];
                        }
                        $query_params['sort_by'] = $category;
                        $query_params['sort_order'] = $next_order;
                    ?>
                    <li><a class="dropdown-item <?php echo $is_current ? 'active' : ''; ?>" href="?<?php echo http_build_query($query_params); ?>">
                        <?php echo $category_label; ?> (<?php echo $order_label; ?>)<?php echo $is_current ? ' <i class="fas fa-check text-primary"></i>' : ''; ?>
                    </a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Alphabetical Sort Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="alphaSortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-sort-alpha-down me-2"></i><?php echo t('directory_sort_alphabetical'); ?>
                </button>
                <ul class="dropdown-menu" aria-labelledby="alphaSortDropdown">
                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'name', 'sort_order' => 'ASC'])); ?>">
                        <i class="fas fa-sort-alpha-down me-2"></i><?php echo t('directory_ascending'); ?> (<?php echo t('directory_a_to_z'); ?>)
                    </a></li>
                    <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'name', 'sort_order' => 'DESC'])); ?>">
                        <i class="fas fa-sort-alpha-up me-2"></i><?php echo t('directory_descending'); ?> (<?php echo t('directory_z_to_a'); ?>)
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Schools List - Horizontal Layout (Agoda-style) -->
    <?php if (!empty($schools)): ?>
        <?php global $conn; ?>
        <div class="school-list-horizontal">
            <?php foreach ($schools as $index => $school): 
                // Get additional school information
                $school_highlights = getSchoolHighlights($school['id']);
                $school_facilities = getSchoolFacilities($school['id']);
                $school_languages = !empty($school['languages_used']) ? json_decode($school['languages_used'], true) : [];
                $school_rating = getSchoolAverageRating($school['id']);
                $special_label = !empty($school['special_label']) ? $school['special_label'] : '';

                // Determine vendor ownership similar to school details page
                $has_vendor_owner = false;
                $vendor_owner_account_number = intval($school['vendor_owner_account_number'] ?? 0);
                $is_current_user_owner = false;
                if ($vendor_owner_account_number) {
                    $owner_check = $conn->prepare("SELECT id, role, vendor_status FROM users WHERE unique_number = ? AND role = 'vendor' AND vendor_status = 'approved'");
                    if ($owner_check) {
                        $owner_check->bind_param('i', $vendor_owner_account_number);
                        $owner_check->execute();
                        $owner_result = $owner_check->get_result();
                        if ($owner_result && $owner_result->num_rows > 0) {
                            $has_vendor_owner = true;
                            if ($is_logged_in && !empty($user_info)) {
                                $current_user_account_number = intval($user_info['unique_number'] ?? 0);
                                if ($current_user_account_number && $current_user_account_number === $vendor_owner_account_number) {
                                    $is_current_user_owner = true;
                                }
                            }
                        }
                        $owner_check->close();
                    }
                }
            ?>
            <div class="school-container-horizontal mb-4" style="animation: fadeInUp 0.6s ease <?php echo $index * 0.1; ?>s backwards;">
                <a href="school-details.php?id=<?php echo $school['id']; ?><?php 
                    // Preserve sorting parameters when viewing school details
                    $detail_params = [];
                    if (isset($_GET['sort_by']) && !empty($_GET['sort_by'])) {
                        $detail_params['sort_by'] = $_GET['sort_by'];
                    }
                    if (isset($_GET['sort_order']) && !empty($_GET['sort_order'])) {
                        $detail_params['sort_order'] = $_GET['sort_order'];
                    }
                    if (isset($_GET['level']) && !empty($_GET['level'])) {
                        $detail_params['level'] = $_GET['level'];
                    }
                    if (isset($_GET['search']) && !empty($_GET['search'])) {
                        $detail_params['search'] = $_GET['search'];
                    }
                    if (!empty($detail_params)) {
                        echo '&' . http_build_query($detail_params);
                    }
                ?>" class="school-container-link" style="text-decoration: none; color: inherit; display: block;">
                <div class="card shadow-sm h-100 school-container-card">
                    <div class="row g-0">
                        <!-- Left: School Image -->
                        <div class="col-md-4 col-lg-3 position-relative">
                            <div class="school-image-wrapper position-relative h-100">
                                <img src="<?php echo getSchoolImage($school['id'], $school['image'] ?? null); ?>" 
                                     alt="<?php echo htmlspecialchars($school['name']); ?>" 
                                     class="school-image-horizontal img-fluid w-100 h-100"
                                     style="object-fit: cover; min-height: 250px;"
                                     loading="lazy">
                                
                                <!-- Special Label (Top Left of Photo - Blue Award Style) -->
                                <?php if (!empty($special_label)): ?>
                                <div class="position-absolute top-0 start-0 m-2 special-label-wrapper">
                                    <span class="badge special-label-award" 
                                          data-bs-toggle="tooltip" 
                                          data-bs-placement="top" 
                                          title="<?php echo htmlspecialchars($special_label); ?>">
                                        <i class="fas fa-trophy me-1"></i><span class="special-label-text"><?php echo htmlspecialchars($special_label); ?></span>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Collection Icon (Top Right of Photo - Homepage Style) -->
                                <button type="button" class="school-card-heart-btn school-collection-btn" 
                                        data-school-id="<?php echo $school['id']; ?>" 
                                        aria-label="Save to Collection"
                                        onclick="event.stopPropagation(); event.preventDefault();">
                                    <i class="fas fa-heart"></i>
                                </button>
                                
                                <!-- Level Badge (Bottom Left of Photo - Always Show) -->
                                <div class="position-absolute bottom-0 start-0 m-2 school-level-badge-bottom">
                                    <span class="badge school-level-badge"><?php echo htmlspecialchars($school['level']); ?></span>
                                </div>
                                
                                <?php if (isset($school['distance'])): ?>
                                <div class="position-absolute bottom-0 end-0 m-2">
                                    <span class="badge bg-info">
                                        <i class="fas fa-ruler me-1"></i><?php echo number_format($school['distance'], 1); ?> km
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Right: School Information -->
                        <div class="col-md-8 col-lg-9">
                            <div class="card-body d-flex flex-column h-100 p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="flex-grow-1">
                                        <!-- School Name -->
                                        <h5 class="mb-2 fw-bold"><?php echo htmlspecialchars($school['name']); ?></h5>
                                        
                                        <!-- Star Rating Below Name -->
                                        <?php if ($school_rating['count'] > 0): 
                                            $stars = round($school_rating['rating']);
                                        ?>
                                        <div class="mb-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $stars): ?>
                                                    <i class="fas fa-star text-warning" style="font-size: 0.9rem;"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star text-muted" style="font-size: 0.9rem;"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Location -->
                                        <p class="text-muted mb-1 small">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($school['address']); ?>
                                        </p>
                                        
                                        <!-- Highlights Label -->
                                        <?php if (!empty($school_highlights)): ?>
                                        <div class="mb-2">
                                            <span class="badge bg-light text-dark border">
                                                <i class="fas fa-tag me-1"></i>Highlights
                                            </span>
                                            <?php 
                                            $highlight_count = 0;
                                            foreach ($school_highlights as $highlight):
                                                if ($highlight_count < 3): // Show first 3
                                            ?>
                                                <span class="badge bg-warning text-dark ms-1"><?php echo htmlspecialchars($highlight['name']); ?></span>
                                            <?php 
                                                $highlight_count++;
                                                endif;
                                            endforeach; 
                                            if (count($school_highlights) > 3):
                                            ?>
                                                <span class="badge bg-secondary ms-1">+<?php echo count($school_highlights) - 3; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Rating and Reviews (Right Side) -->
                                    <?php if ($school_rating['count'] > 0): ?>
                                    <div class="text-end ms-3">
                                        <div class="school-rating-score-horizontal">
                                            <div class="d-flex align-items-baseline justify-content-end gap-1 mb-1">
                                                <span class="rating-number-large fw-bold text-primary" style="font-size: 2rem; line-height: 1;"><?php echo number_format($school_rating['rating'], 1); ?></span>
                                                <span class="text-muted" style="font-size: 1rem;">/5</span>
                                            </div>
                                            <div class="reviews-count-container d-flex align-items-center justify-content-end gap-1" onclick="event.stopPropagation();" 
                                                 onmouseover="showReviewTooltip(<?php echo $school['id']; ?>, event)" 
                                                 onmouseout="hideReviewTooltip()">
                                                <span class="text-primary reviews-count-text" 
                                                      data-school-id="<?php echo $school['id']; ?>"
                                                      style="cursor: pointer; text-decoration: underline; font-size: 0.75rem;"
                                                      onclick="event.stopPropagation();">
                                                    <i class="fas fa-check me-1"></i><?php echo $school_rating['count']; ?> reviews
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- I'm Interested Button (Bottom) -->
                                <div class="mt-auto pt-3 border-top" onclick="event.stopPropagation();">
                                    <?php if (!$is_current_user_owner): ?>
                                        <?php if ($has_vendor_owner): ?>
                                            <button type="button" 
                                                    class="btn btn-link text-primary p-0 text-decoration-none fw-bold im-interested-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#interestModal<?php echo $school['id']; ?>"
                                                    style="font-size: 0.95rem;"
                                                    onclick="event.stopPropagation(); event.preventDefault();">
                                                <i class="fas fa-handshake me-1"></i>I'm interested
                                            </button>
                                        <?php else: ?>
                                            <div class="d-flex justify-content-between gap-3 flex-wrap">
                                                <button type="button" class="btn btn-link text-primary p-0 fw-bold text-decoration-none" onclick="openSchoolModal(event, <?php echo $school['id']; ?>, 'suggest_edit');">
                                                    <i class="fas fa-edit me-1"></i>Suggest an edit
                                                </button>
                                                <button type="button" class="btn btn-link text-primary p-0 fw-bold text-decoration-none" onclick="openSchoolModal(event, <?php echo $school['id']; ?>, 'own_business');">
                                                    <i class="fas fa-briefcase me-1"></i>Own this business?
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </a>
                
                <?php if ($has_vendor_owner && !$is_current_user_owner): ?>
                <!-- Interest Modal for each school -->
                <div class="modal fade" id="interestModal<?php echo $school['id']; ?>" tabindex="-1" aria-labelledby="interestModalLabel<?php echo $school['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="interestModalLabel<?php echo $school['id']; ?>"><?php echo t('interest_title'); ?> - <?php echo htmlspecialchars($school['name']); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form class="interest-form" data-school-id="<?php echo $school['id']; ?>">
                                    <input type="hidden" name="school_id" value="<?php echo $school['id']; ?>">
                                    <div class="mb-3">
                                        <label for="name<?php echo $school['id']; ?>" class="form-label"><?php echo t('interest_name'); ?> *</label>
                                        <input type="text" class="form-control" id="name<?php echo $school['id']; ?>" name="name" required value="<?php echo htmlspecialchars($user_info['name'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="contact_number<?php echo $school['id']; ?>" class="form-label"><?php echo t('interest_contact'); ?> *</label>
                                        <input type="tel" class="form-control" id="contact_number<?php echo $school['id']; ?>" name="contact_number" required value="<?php echo htmlspecialchars($user_info['contact'] ?? $user_info['phone_number'] ?? ''); ?>"<?php echo $is_logged_in && !empty($user_info) ? ' readonly' : ''; ?>>
                                        <?php if ($is_logged_in && !empty($user_info)): ?>
                                            <small class="text-muted">This field is automatically filled from your account.</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email<?php echo $school['id']; ?>" class="form-label"><?php echo t('interest_email'); ?> *</label>
                                        <input type="email" class="form-control" id="email<?php echo $school['id']; ?>" name="email" required value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="child_year_of_birth<?php echo $school['id']; ?>" class="form-label">Child's Year of Birth *</label>
                                        <input type="number" class="form-control" id="child_year_of_birth<?php echo $school['id']; ?>" name="child_year_of_birth" min="1990" max="<?php echo date('Y'); ?>" required placeholder="e.g., 2020">
                                        <small class="text-muted">Enter the year your child was born</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="message<?php echo $school['id']; ?>" class="form-label"><?php echo t('interest_message'); ?></label>
                                        <textarea class="form-control" id="message<?php echo $school['id']; ?>" name="message" rows="3" placeholder="Any additional information or questions..."></textarea>
                                    </div>
                                    <div class="modal-footer p-0 pt-3">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('btn_close'); ?></button>
                                        <button type="submit" class="btn btn-primary"><?php echo t('interest_submit'); ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i><?php echo t('directory_no_schools'); ?>
        </div>
    <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* School Rating Display - Modern Design */
.school-rating-display {
    padding: 0.75rem 1rem;
    background: linear-gradient(135deg, rgba(79, 163, 247, 0.08), rgba(255, 217, 102, 0.08));
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.school-rating-score .rating-number {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--primary-blue);
    line-height: 1;
    text-shadow: 0 2px 4px rgba(79, 163, 247, 0.15);
}

.school-rating-stars {
    line-height: 1;
}

.school-rating-stars i {
    font-size: 1rem;
    margin: 0 1px;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
}

.school-rating-display .text-muted {
    font-size: 0.85rem;
    font-weight: 500;
}

/* Heart Icon Button - Matching "I'M INTERESTED" button style */
.school-card-heart-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ffd700 0%, #ffb347 50%, #ff9800 100%);
    border: none;
    color: #2c3e50;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 5;
    box-shadow: 0 2px 12px rgba(255, 193, 7, 0.5), 0 0 20px rgba(255, 193, 7, 0.3);
    transition: all 0.3s ease;
}

.school-card-heart-btn:hover {
    background: linear-gradient(135deg, #ffed4e 0%, #ffd54f 50%, #ffb300 100%);
    color: #1a252f;
    transform: scale(1.15);
    box-shadow: 0 4px 16px rgba(255, 193, 7, 0.6), 0 0 30px rgba(255, 193, 7, 0.4);
}

.school-card-heart-btn:active {
    transform: scale(0.95);
    box-shadow: 0 2px 8px rgba(255, 193, 7, 0.4), 0 0 15px rgba(255, 193, 7, 0.3);
}

/* School Card Labels */
.school-card-labels {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 8px;
}

.school-card-labels .badge {
    font-size: 0.75rem;
    padding: 4px 8px;
    font-weight: 500;
}

/* Horizontal School Container Styles (Agoda-style) */
.school-container-horizontal {
    transition: var(--transition);
}

.school-container-link {
    text-decoration: none !important;
    color: inherit !important;
    display: block;
}

.school-container-link:hover {
    text-decoration: none !important;
    color: inherit !important;
}

.school-container-link:hover .school-container-card {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg) !important;
}

.school-container-card {
    transition: var(--transition);
    cursor: pointer;
}

.school-image-wrapper {
    overflow: hidden;
    border-radius: var(--border-radius) 0 0 var(--border-radius);
}

.school-image-horizontal {
    transition: transform 0.3s ease;
}

.school-container-horizontal:hover .school-image-horizontal {
    transform: scale(1.05);
}

.school-collection-btn {
    transition: all 0.3s ease;
    z-index: 10;
    position: relative;
}

.school-collection-btn:hover {
    transform: scale(1.1);
    background-color: rgba(255, 255, 255, 0.9) !important;
}

/* Prevent clicks on buttons from triggering container link */
.school-collection-btn,
.im-interested-btn,
.reviews-count-text {
    position: relative;
    z-index: 5;
}

.school-container-link .school-collection-btn,
.school-container-link .im-interested-btn {
    pointer-events: auto;
}

.school-collection-btn.active .fas.fa-heart {
    color: #dc3545 !important;
}

.school-rating-score-horizontal {
    text-align: right;
}

.rating-number-large {
    color: var(--primary-orange) !important;
    font-size: 2rem !important;
    line-height: 1 !important;
    font-weight: 700 !important;
}

.reviews-count-container {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
}

.reviews-count-text {
    font-size: 0.75rem !important;
    transition: color 0.2s ease;
    white-space: nowrap;
}

.reviews-count-text:hover {
    color: var(--primary-orange-dark) !important;
}

.reviews-count-text i {
    font-size: 0.7rem;
}

/* Review Tooltip - Detailed Breakdown with Progress Bars (2-Column Layout) */
.review-tooltip {
    position: fixed;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15), 0 2px 8px rgba(0, 0, 0, 0.1);
    z-index: 9999;
    min-width: 280px;
    max-width: 320px;
    display: none;
}

.review-tooltip::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-top: 8px solid white;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
}

.review-tooltip.show {
    display: block;
    animation: tooltipFadeIn 0.2s ease;
}

@keyframes tooltipFadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.review-tooltip-header {
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
    font-size: 0.85rem;
    padding-bottom: 6px;
    border-bottom: 1px solid #e0e0e0;
}

.review-tooltip-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 12px;
}

.review-tooltip-category {
    margin-bottom: 0;
}

.review-tooltip-category-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
    font-size: 0.75rem;
    color: #555;
}

.review-tooltip-category-name {
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
}

.review-tooltip-category-name i {
    font-size: 0.65rem;
}

.review-tooltip-category-score {
    font-weight: 600;
    color: var(--primary-blue);
    font-size: 0.8rem;
}

.review-tooltip-bar-container {
    position: relative;
    height: 6px;
    background-color: #e9ecef;
    border-radius: 3px;
    overflow: visible;
}

.review-tooltip-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--primary-blue) 0%, #5a9fd8 100%);
    border-radius: 3px;
    transition: width 0.3s ease;
}

.review-tooltip-marker {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-left: 3px solid transparent;
    border-right: 3px solid transparent;
    border-top: 5px solid #333;
    margin-left: -3px;
}

.review-tooltip-footer {
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid #e0e0e0;
    font-size: 0.7rem;
    color: #666;
    text-align: center;
    grid-column: 1 / -1;
}

.review-tooltip-footer i {
    font-size: 0.65rem;
    margin-right: 4px;
}

.im-interested-btn {
    transition: all 0.2s ease;
}

.im-interested-btn:hover {
    color: var(--primary-orange) !important;
    transform: translateX(5px);
}

@media (max-width: 768px) {
    .school-container-horizontal .row {
        flex-direction: column;
    }
    
    .school-image-wrapper {
        border-radius: var(--border-radius) var(--border-radius) 0 0;
        min-height: 200px !important;
    }
    
    .school-rating-score-horizontal {
        text-align: left;
        margin-top: 10px;
    }
    
    .school-rating-display {
        text-align: center !important;
    }
    
    .school-rating-display .d-flex {
        justify-content: center !important;
    }
    
    .school-rating-score .rating-number {
        font-size: 1.5rem;
    }
    
    .school-rating-stars i {
        font-size: 0.9rem;
    }
    
    .school-card-heart-btn {
        width: 35px;
        height: 35px;
        font-size: 16px;
    }
}

.review-card {
    display: inline-flex;
    width: 100%;
    margin-bottom: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    break-inside: avoid;
    -webkit-column-break-inside: avoid;
    column-break-inside: avoid;
    flex-direction: column;
}

/* Reviews Masonry Layout (3-Column) - Dianping Style */
.reviews-masonry {
    column-count: 3;
    column-gap: 24px;
    margin-top: 30px;
}

.reviews-section {
    background-color: #f8f9fa;
}
</style>

<!-- Review Tooltip Container -->
<div id="reviewTooltip" class="review-tooltip">
    <div class="review-tooltip-header">Rating Breakdown</div>
    <div id="reviewTooltipContent"></div>
</div>

<script>
// Fix modal flashing - prevent rapid open/close
document.addEventListener('DOMContentLoaded', function() {
    // Prevent modal flashing by disabling rapid clicks
    let modalOpening = false;
    const modalButtons = document.querySelectorAll('[data-bs-toggle="modal"]');
    modalButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (modalOpening) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            modalOpening = true;
            setTimeout(() => {
                modalOpening = false;
            }, 300);
        });
    });
    
    // Handle interest form submissions
    const interestForms = document.querySelectorAll('.interest-form');
    
    interestForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            
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
                    alert(data.message || 'Failed to submit interest. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    });
    
    // Collection button handlers (homepage style)
    // Check collection status for all schools on page load
    <?php if (isLoggedIn()): ?>
    const collectionButtons = document.querySelectorAll('.school-collection-btn');
    collectionButtons.forEach(btn => {
        const schoolId = btn.dataset.schoolId;
        
        // Check if school is in collection
        fetch(`api_collection.php?action=check&school_id=${schoolId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.in_collection) {
                    btn.classList.add('active');
                }
            })
            .catch(error => console.error('Error checking collection:', error));
        
        // Add click handler
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const schoolId = this.dataset.schoolId;
            const isActive = this.classList.contains('active');
            
            // Toggle collection
            const action = isActive ? 'remove' : 'add';
            
            // Add animation class for feedback
            this.classList.add(action === 'add' ? 'adding' : 'removing');
            setTimeout(() => {
                this.classList.remove('adding', 'removing');
            }, 600);
            
            fetch('api_collection.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=${action}&school_id=${schoolId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (action === 'add') {
                        this.classList.add('active');
                        showCollectionToast('success', 'School added to collection!');
                    } else {
                        this.classList.remove('active');
                        showCollectionToast('success', 'School removed from collection!');
                    }
                } else {
                    showCollectionToast('error', data.message || 'Operation failed');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showCollectionToast('error', 'An error occurred. Please try again.');
            });
        });
    });
    <?php else: ?>
    // If not logged in, show login prompt
    const collectionButtons = document.querySelectorAll('.school-collection-btn');
    collectionButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (confirm('Please login to save schools to your collection. Would you like to login now?')) {
                window.location.href = 'login.php';
            }
        });
    });
    <?php endif; ?>

    // Mobile filter panels (mobile only)
    const mobileFilterPanels = document.querySelectorAll('#mobileFilterAccordion .mobile-filter-panel');
    const mobileFilterOverlay = document.getElementById('mobileFilterOverlay');
    if (mobileFilterPanels.length && mobileFilterOverlay) {
        mobileFilterPanels.forEach(panel => {
            panel.addEventListener('show.bs.collapse', () => {
                mobileFilterPanels.forEach(other => {
                    if (other !== panel) {
                        bootstrap.Collapse.getOrCreateInstance(other, { toggle: false }).hide();
                    }
                });
                mobileFilterOverlay.classList.add('active');
                document.body.classList.add('mobile-filter-open');
            });

            panel.addEventListener('hidden.bs.collapse', () => {
                const hasOpen = Array.from(mobileFilterPanels).some(p => p.classList.contains('show'));
                if (!hasOpen) {
                    mobileFilterOverlay.classList.remove('active');
                    document.body.classList.remove('mobile-filter-open');
                }
            });
        });

        mobileFilterOverlay.addEventListener('click', () => {
            mobileFilterPanels.forEach(panel => {
                bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false }).hide();
            });
        });
    }
});

function openSchoolModal(event, schoolId, modal) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    const baseUrl = '<?php echo rtrim(SITE_URL, '/'); ?>/school-details.php';
    const url = new URL(baseUrl);
    url.searchParams.set('id', schoolId);
    url.searchParams.set('open_modal', modal);
    window.location.href = url.toString();
}

// Review Tooltip Functions
let reviewTooltipTimer = null;

function showReviewTooltip(schoolId, event) {
    const tooltip = document.getElementById('reviewTooltip');
    const tooltipContent = document.getElementById('reviewTooltipContent');
    
    if (!tooltip || !tooltipContent) return;
    
    // Clear any existing timer
    if (reviewTooltipTimer) {
        clearTimeout(reviewTooltipTimer);
    }
    
    // Fetch rating breakdown
    fetch(`load_reviews.php?action=get_rating_breakdown&school_id=${schoolId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.breakdown) {
                let html = '';
                const categories = [
                    { key: 'cleanliness_rating', name: 'Cleanliness', icon: 'broom' },
                    { key: 'facilities_rating', name: 'Facilities', icon: 'building' },
                    { key: 'location_rating', name: 'Location', icon: 'map-marker-alt' },
                    { key: 'service_rating', name: 'Service', icon: 'bell' },
                    { key: 'value_rating', name: 'Value for Money', icon: 'dollar-sign' },
                    { key: 'education_rating', name: 'Education Quality', icon: 'graduation-cap' }
                ];
                
                // Get all categories with ratings
                const categoriesWithRatings = categories
                    .map(cat => ({
                        ...cat,
                        rating: parseFloat(data.breakdown[cat.key] ?? 0)
                    }));
                
                // Sort by rating (highest first) and take all
                const sortedCategories = categoriesWithRatings
                    .sort((a, b) => b.rating - a.rating);
                
                // Build HTML for 2-column layout
                sortedCategories.forEach(cat => {
                    const percentage = Math.max(0, Math.min((cat.rating / 5) * 100, 100));
                    const markerPosition = percentage;
                    
                    html += `<div class="review-tooltip-category">
                        <div class="review-tooltip-category-label">
                            <span class="review-tooltip-category-name">
                                <i class="fas fa-${cat.icon}"></i>${cat.name}
                            </span>
                            <span class="review-tooltip-category-score">${cat.rating.toFixed(1)}</span>
                        </div>
                        <div class="review-tooltip-bar-container">
                            <div class="review-tooltip-bar" style="width: ${percentage}%;"></div>
                            <div class="review-tooltip-marker" style="left: ${markerPosition}%;"></div>
                        </div>
                    </div>`;
                });
                
                // Add footer with average rating info
                let footerHtml = '';
                if (sortedCategories.length > 0) {
                    const avgRating = parseFloat(data.overall_rating ?? 0);
                    const reviewCount = parseInt(data.review_count ?? 0, 10);
                    const countLabel = reviewCount > 0 ? ` • Based on ${reviewCount} review${reviewCount === 1 ? '' : 's'}` : '';
                    footerHtml = `<div class="review-tooltip-footer">
                        <i class="fas fa-info-circle"></i>Average rating: ${avgRating.toFixed(1)}/5${countLabel}
                    </div>`;
                }
                
                // Wrap content in grid container with footer
                tooltipContent.innerHTML = `<div class="review-tooltip-content">${html}${footerHtml}</div>`;
                
                // Position tooltip - center it relative to the trigger element
                const rect = event.target.getBoundingClientRect();
                const tooltipWidth = 300; // Adjusted for smaller size
                const tooltipHeight = tooltip.offsetHeight || 200;
                
                // Position tooltip above the element with arrow pointing down
                let left = rect.left + (rect.width / 2) - (tooltipWidth / 2);
                let top = rect.bottom + 10;
                
                // Adjust if tooltip goes off screen
                if (left < 10) left = 10;
                if (left + tooltipWidth > window.innerWidth - 10) {
                    left = window.innerWidth - tooltipWidth - 10;
                }
                
                tooltip.style.left = left + 'px';
                tooltip.style.top = top + 'px';
                tooltip.classList.add('show');
            }
        })
        .catch(error => {
            console.error('Error loading rating breakdown:', error);
        });
}

function hideReviewTooltip() {
    const tooltip = document.getElementById('reviewTooltip');
    if (tooltip) {
        reviewTooltipTimer = setTimeout(() => {
            tooltip.classList.remove('show');
        }, 200);
    }
}

// Initialize Bootstrap tooltips for special labels
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips for special labels
    const specialLabelTooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    specialLabelTooltips.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Collection Toast Notification Function
function showCollectionToast(type, message) {
    // Remove existing toast if any
    const existingToast = document.querySelector('.collection-toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `collection-toast ${type}`;
    
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    toast.innerHTML = `
        <i class="fas ${icon} toast-icon"></i>
        <span class="toast-message">${message}</span>
        <button type="button" class="btn-close" onclick="this.parentElement.remove()" aria-label="Close"></button>
    `;
    
    // Add to body
    document.body.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.animation = 'toastSlideIn 0.3s ease reverse';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }
    }, 3000);
}
</script>

<?php require_once 'includes/footer.php'; ?>
