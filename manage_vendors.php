<?php
/**
 * Admin - Manage Vendor Registrations
 * View and approve/reject pending vendor registrations
 */

$page_title = 'Manage Vendor Registrations';
require_once '../config.php';
require_once '../db.php';
require_once 'includes/header.php';

$message = '';
$message_type = '';

// Handle vendor actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id > 0) {
        if ($action === 'approve') {
            // Get vendor info before approving
            $stmt = $conn->prepare("SELECT name, email, unique_number FROM users WHERE id = ? AND role = 'vendor'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $vendor = $result->fetch_assoc();
            $stmt->close();
            
            if ($vendor) {
                // Approve vendor
                $stmt = $conn->prepare("UPDATE users SET vendor_status = 'approved' WHERE id = ? AND role = 'vendor'");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    $message = 'Vendor account approved successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Error approving vendor: ' . $stmt->error;
                    $message_type = 'danger';
                }
                $stmt->close();
            } else {
                $message = 'Vendor not found';
                $message_type = 'danger';
            }
        } elseif ($action === 'reject') {
            // Get vendor info before rejecting
            $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'vendor'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $vendor = $result->fetch_assoc();
            $stmt->close();
            
            if ($vendor) {
                // Reject vendor
                $rejection_reason = trim($_POST['rejection_reason'] ?? '');
                
                $stmt = $conn->prepare("UPDATE users SET vendor_status = 'rejected' WHERE id = ? AND role = 'vendor'");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    $message = 'Vendor account rejected successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Error rejecting vendor: ' . $stmt->error;
                    $message_type = 'danger';
                }
                $stmt->close();
            } else {
                $message = 'Vendor not found';
                $message_type = 'danger';
            }
        }
    }
}

// Ensure columns exist
$check_role_col = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
$has_role_col = $check_role_col->num_rows > 0;
$check_role_col->close();

$check_status_col = $conn->query("SHOW COLUMNS FROM users LIKE 'vendor_status'");
$has_status_col = $check_status_col->num_rows > 0;
$check_status_col->close();

if (!$has_role_col || !$has_status_col) {
    if (!$has_role_col) {
        $conn->query("ALTER TABLE users ADD COLUMN role ENUM('user', 'vendor') DEFAULT 'user' AFTER email");
    }
    if (!$has_status_col) {
        $conn->query("ALTER TABLE users ADD COLUMN vendor_status ENUM('pending', 'approved', 'rejected') NULL AFTER role");
    }
}

// Get search parameter
$search_account = trim($_GET['search_account'] ?? '');
$status_filter = $_GET['status'] ?? 'all';

// Get all vendors with their status
$where_conditions = ["u.role = 'vendor'"];
$params = [];
$param_types = '';

if (!empty($search_account)) {
    $where_conditions[] = "u.unique_number = ?";
    $params[] = intval($search_account);
    $param_types .= 'i';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM school_requests sr WHERE sr.user_id = u.id) as requests_count,
        (SELECT COUNT(*) FROM school_requests sr WHERE sr.user_id = u.id AND sr.status = 'Approved') as approved_count
        FROM users u
        {$where_clause}
        ORDER BY u.created_at DESC";
        
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendors = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendors = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get linked schools for each vendor
foreach ($vendors as &$vendor) {
    $vendor_account_number = $vendor['unique_number'] ?? null;
    if ($vendor_account_number) {
        // Check if vendor_owner_account_number column exists
        $check_col = $conn->query("SHOW COLUMNS FROM schools LIKE 'vendor_owner_account_number'");
        if ($check_col->num_rows > 0) {
            $schools_stmt = $conn->prepare("SELECT id, name FROM schools WHERE vendor_owner_account_number = ? ORDER BY name ASC");
            $schools_stmt->bind_param("i", $vendor_account_number);
            $schools_stmt->execute();
            $schools_result = $schools_stmt->get_result();
            $vendor['linked_schools'] = $schools_result->fetch_all(MYSQLI_ASSOC);
            $schools_stmt->close();
        } else {
            $vendor['linked_schools'] = [];
        }
        $check_col->close();
    } else {
        $vendor['linked_schools'] = [];
    }
}
unset($vendor);

// Separate vendors by status
$pending_vendors = array_filter($vendors, function($v) {
    return ($v['vendor_status'] ?? null) === 'pending';
});
$approved_vendors = array_filter($vendors, function($v) {
    return ($v['vendor_status'] ?? null) === 'approved';
});
$rejected_vendors = array_filter($vendors, function($v) {
    return ($v['vendor_status'] ?? null) === 'rejected';
});
?>

<h1 class="mb-4">Manage Vendor Registrations</h1>
<p class="text-muted mb-4">Review and approve/reject vendor account registrations.</p>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Search Form -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="mb-3">
            <div class="row g-2">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search_account" placeholder="Search by account number..." value="<?php echo htmlspecialchars($search_account); ?>">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                        <?php if (!empty($search_account)): ?>
                            <a href="?status=<?php echo htmlspecialchars($status_filter); ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Vendor Tabs -->
<ul class="nav nav-tabs mb-4" id="vendorTab" role="tablist">
    <li class="nav-item" role="presentation">
        <a href="?status=all<?php echo !empty($search_account) ? '&search_account=' . urlencode($search_account) : ''; ?>" class="nav-link <?php echo $status_filter == 'all' ? 'active' : ''; ?>" id="all-tab" type="button" role="tab">
            <i class="fas fa-list me-2"></i>All <span class="badge bg-primary"><?php echo count($vendors); ?></span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a href="?status=pending<?php echo !empty($search_account) ? '&search_account=' . urlencode($search_account) : ''; ?>" class="nav-link <?php echo $status_filter == 'pending' ? 'active' : ''; ?>" id="pending-tab" type="button" role="tab">
            <i class="fas fa-clock me-2"></i>Pending <span class="badge bg-warning"><?php echo count($pending_vendors); ?></span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a href="?status=approved<?php echo !empty($search_account) ? '&search_account=' . urlencode($search_account) : ''; ?>" class="nav-link <?php echo $status_filter == 'approved' ? 'active' : ''; ?>" id="approved-tab" type="button" role="tab">
            <i class="fas fa-check-circle me-2"></i>Approved <span class="badge bg-success"><?php echo count($approved_vendors); ?></span>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a href="?status=rejected<?php echo !empty($search_account) ? '&search_account=' . urlencode($search_account) : ''; ?>" class="nav-link <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>" id="rejected-tab" type="button" role="tab">
            <i class="fas fa-times-circle me-2"></i>Rejected <span class="badge bg-danger"><?php echo count($rejected_vendors); ?></span>
        </a>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="vendorTabContent">
    <!-- All Vendors -->
    <div class="tab-pane fade <?php echo $status_filter == 'all' ? 'show active' : ''; ?>" id="all" role="tabpanel" aria-labelledby="all-tab">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Vendors (<?php echo count($vendors); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($vendors)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Account Number</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Phone Verified</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                    <th>Approved Schools</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendors as $vendor): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?php echo $vendor['unique_number'] ?? 'N/A'; ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($vendor['name']); ?></td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($vendor['email']); ?>">
                                            <?php echo htmlspecialchars($vendor['email']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if (!empty($vendor['phone_number'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($vendor['phone_number']); ?>">
                                                <?php echo htmlspecialchars($vendor['phone_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $phone_verified = $vendor['phone_verified'] ?? 0;
                                        $phone_meta = $phone_verified == 1
                                            ? ['badge' => 'badge-status-approved', 'icon' => 'fa-circle-check', 'label' => 'Verified']
                                            : ['badge' => 'badge-status-pending', 'icon' => 'fa-circle-exclamation', 'label' => 'Not Verified'];
                                        ?>
                                        <span class="badge badge-status <?php echo $phone_meta['badge']; ?>">
                                            <i class="fas <?php echo $phone_meta['icon']; ?>"></i>
                                            <?php echo $phone_meta['label']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($vendor['created_at'])); ?></td>
                                    <td>
                                        <?php
                                        $vendor_status = strtolower($vendor['vendor_status'] ?? 'pending');
                                        $status_map = [
                                            'approved' => ['badge' => 'badge-status-approved', 'icon' => 'fa-circle-check'],
                                            'pending' => ['badge' => 'badge-status-pending', 'icon' => 'fa-clock'],
                                            'rejected' => ['badge' => 'badge-status-rejected', 'icon' => 'fa-circle-xmark'],
                                        ];
                                        $status_meta = $status_map[$vendor_status] ?? ['badge' => 'badge-status-neutral', 'icon' => 'fa-circle-info'];
                                        ?>
                                        <span class="badge badge-status <?php echo $status_meta['badge']; ?>">
                                            <i class="fas <?php echo $status_meta['icon']; ?>"></i>
                                            <?php echo ucfirst($vendor_status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($vendor['linked_schools'])): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <?php foreach ($vendor['linked_schools'] as $school): ?>
                                                    <a href="schools.php?edit=<?php echo $school['id']; ?>" target="_blank" class="text-decoration-none">
                                                        <span class="badge bg-success"><?php echo htmlspecialchars($school['name']); ?></span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-stack">
                                            <a href="../profile.php?user_id=<?php echo $vendor['id']; ?>"
                                               target="_blank"
                                               class="btn btn-action btn-view"
                                               title="View vendor profile"
                                               aria-label="View vendor profile">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No vendors found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Pending Vendors -->
    <div class="tab-pane fade <?php echo $status_filter == 'pending' ? 'show active' : ''; ?>" id="pending" role="tabpanel" aria-labelledby="pending-tab">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Pending Vendor Registrations (<?php echo count($pending_vendors); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($pending_vendors)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Account Number</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Registered</th>
                                    <th>School Requests</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_vendors as $vendor): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?php echo $vendor['unique_number'] ?? 'N/A'; ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($vendor['name']); ?></td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($vendor['email']); ?>">
                                            <?php echo htmlspecialchars($vendor['email']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if (!empty($vendor['phone_number'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($vendor['phone_number']); ?>">
                                                <?php echo htmlspecialchars($vendor['phone_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $phone_verified = $vendor['phone_verified'] ?? 0;
                                        $phone_meta = $phone_verified == 1
                                            ? ['badge' => 'badge-status-approved', 'icon' => 'fa-circle-check', 'label' => 'Verified']
                                            : ['badge' => 'badge-status-pending', 'icon' => 'fa-circle-exclamation', 'label' => 'Not Verified'];
                                        ?>
                                        <span class="badge badge-status <?php echo $phone_meta['badge']; ?>">
                                            <i class="fas <?php echo $phone_meta['icon']; ?>"></i>
                                            <?php echo $phone_meta['label']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($vendor['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $vendor['requests_count']; ?></span>
                                    </td>
                                    <td>
                                        <div class="action-stack">
                                            <button type="button"
                                                    class="btn btn-action btn-approve"
                                                    onclick="approveVendor(<?php echo $vendor['id']; ?>, '<?php echo htmlspecialchars(addslashes($vendor['name'])); ?>')"
                                                    title="Approve vendor"
                                                    aria-label="Approve vendor">
                                                <i class="fas fa-circle-check"></i>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-action btn-reject"
                                                    onclick="rejectVendor(<?php echo $vendor['id']; ?>, '<?php echo htmlspecialchars(addslashes($vendor['name'])); ?>')"
                                                    title="Reject vendor"
                                                    aria-label="Reject vendor">
                                                <i class="fas fa-circle-xmark"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No pending vendor registrations.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Approved Vendors -->
    <div class="tab-pane fade <?php echo $status_filter == 'approved' ? 'show active' : ''; ?>" id="approved" role="tabpanel" aria-labelledby="approved-tab">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Approved Vendors (<?php echo count($approved_vendors); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($approved_vendors)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Account Number</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Phone Verified</th>
                                    <th>Registered</th>
                                    <th>Approved Schools</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approved_vendors as $vendor): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?php echo $vendor['unique_number'] ?? 'N/A'; ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($vendor['name']); ?></td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($vendor['email']); ?>">
                                            <?php echo htmlspecialchars($vendor['email']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if (!empty($vendor['phone_number'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($vendor['phone_number']); ?>">
                                                <?php echo htmlspecialchars($vendor['phone_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $phone_verified = $vendor['phone_verified'] ?? 0;
                                        $phone_meta = $phone_verified == 1
                                            ? ['badge' => 'badge-status-approved', 'icon' => 'fa-circle-check', 'label' => 'Verified']
                                            : ['badge' => 'badge-status-pending', 'icon' => 'fa-circle-exclamation', 'label' => 'Not Verified'];
                                        ?>
                                        <span class="badge badge-status <?php echo $phone_meta['badge']; ?>">
                                            <i class="fas <?php echo $phone_meta['icon']; ?>"></i>
                                            <?php echo $phone_meta['label']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($vendor['created_at'])); ?></td>
                                    <td>
                                        <?php if (!empty($vendor['linked_schools'])): ?>
                                            <div class="d-flex flex-column gap-1">
                                                <?php foreach ($vendor['linked_schools'] as $school): ?>
                                                    <a href="schools.php?edit=<?php echo $school['id']; ?>" target="_blank" class="text-decoration-none">
                                                        <span class="badge bg-success"><?php echo htmlspecialchars($school['name']); ?></span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-stack">
                                            <a href="../profile.php?user_id=<?php echo $vendor['id']; ?>"
                                               target="_blank"
                                               class="btn btn-action btn-view"
                                               title="View vendor profile"
                                               aria-label="View vendor profile">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No approved vendors yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Rejected Vendors -->
    <div class="tab-pane fade <?php echo $status_filter == 'rejected' ? 'show active' : ''; ?>" id="rejected" role="tabpanel" aria-labelledby="rejected-tab">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Rejected Vendor Registrations (<?php echo count($rejected_vendors); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($rejected_vendors)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Account Number</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Phone Verified</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rejected_vendors as $vendor): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?php echo $vendor['unique_number'] ?? 'N/A'; ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($vendor['name']); ?></td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($vendor['email']); ?>">
                                            <?php echo htmlspecialchars($vendor['email']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if (!empty($vendor['phone_number'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($vendor['phone_number']); ?>">
                                                <?php echo htmlspecialchars($vendor['phone_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $phone_verified = $vendor['phone_verified'] ?? 0;
                                        if ($phone_verified == 1): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Verified
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-times-circle me-1"></i>Not Verified
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($vendor['created_at'])); ?></td>
                                    <td>
                                        <div class="action-stack">
                                            <button type="button"
                                                    class="btn btn-action btn-approve"
                                                    onclick="approveVendor(<?php echo $vendor['id']; ?>, '<?php echo htmlspecialchars(addslashes($vendor['name'])); ?>')"
                                                    title="Approve vendor"
                                                    aria-label="Approve vendor">
                                                <i class="fas fa-circle-check"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No rejected vendor registrations.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Approve Vendor Modal -->
<div class="modal fade" id="approveVendorModal" tabindex="-1" aria-labelledby="approveVendorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="approveVendorModalLabel">
                    <i class="fas fa-check-circle me-2"></i>Approve Vendor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve vendor <strong id="approveVendorName"></strong>?</p>
                <p class="text-muted"><small>This will allow them to login and manage their school listings.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="approveVendorForm">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="user_id" id="approveVendorId">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Approve Vendor
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Vendor Modal -->
<div class="modal fade" id="rejectVendorModal" tabindex="-1" aria-labelledby="rejectVendorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rejectVendorModalLabel">
                    <i class="fas fa-times-circle me-2"></i>Reject Vendor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reject vendor <strong id="rejectVendorName"></strong>?</p>
                <div class="mb-3">
                    <label for="rejection_reason" class="form-label">Rejection Reason (Optional)</label>
                    <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" placeholder="Enter reason for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="rejectVendorForm">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="user_id" id="rejectVendorId">
                    <textarea name="rejection_reason" style="display:none;" id="rejectVendorReason"></textarea>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-2"></i>Reject Vendor
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function approveVendor(userId, userName) {
    document.getElementById('approveVendorId').value = userId;
    document.getElementById('approveVendorName').textContent = userName;
    const modal = new bootstrap.Modal(document.getElementById('approveVendorModal'));
    modal.show();
}

function rejectVendor(userId, userName) {
    document.getElementById('rejectVendorId').value = userId;
    document.getElementById('rejectVendorName').textContent = userName;
    const modal = new bootstrap.Modal(document.getElementById('rejectVendorModal'));
    modal.show();
}

// Handle rejection form submission
document.getElementById('rejectVendorForm').addEventListener('submit', function(e) {
    const reason = document.getElementById('rejection_reason').value;
    document.getElementById('rejectVendorReason').value = reason;
});
</script>

<?php require_once 'includes/footer.php'; ?>

