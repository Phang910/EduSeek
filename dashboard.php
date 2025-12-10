<?php
$page_title = 'Dashboard';
require_once '../config.php';
require_once '../db.php';
require_once 'includes/header.php';

// Get statistics
$total_schools = $conn->query("SELECT COUNT(*) as count FROM schools")->fetch_assoc()['count'];
$total_requests = $conn->query("SELECT COUNT(*) as count FROM school_requests")->fetch_assoc()['count'];
$pending_requests = $conn->query("SELECT COUNT(*) as count FROM school_requests WHERE status = 'Pending'")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];

// Get edit suggestions statistics
// Check if school_edit_suggestions table exists
$check_table = $conn->query("SHOW TABLES LIKE 'school_edit_suggestions'");
if ($check_table->num_rows > 0) {
    $total_edit_suggestions = $conn->query("SELECT COUNT(*) as count FROM school_edit_suggestions")->fetch_assoc()['count'];
    $pending_edit_suggestions = $conn->query("SELECT COUNT(*) as count FROM school_edit_suggestions WHERE status = 'pending'")->fetch_assoc()['count'];
} else {
    $total_edit_suggestions = 0;
    $pending_edit_suggestions = 0;
}
$check_table->close();

// Get recent schools
$recent_schools = $conn->query("SELECT * FROM schools ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Get recent pending requests only
$recent_requests = $conn->query("SELECT sr.*, u.name as user_name FROM school_requests sr LEFT JOIN users u ON sr.user_id = u.id WHERE sr.status = 'Pending' ORDER BY sr.submitted_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Get recent edit suggestions
$check_table_recent = $conn->query("SHOW TABLES LIKE 'school_edit_suggestions'");
if ($check_table_recent->num_rows > 0) {
    $recent_edit_suggestions = $conn->query("SELECT ses.*, s.name as school_name, u.name as user_name FROM school_edit_suggestions ses LEFT JOIN schools s ON ses.school_id = s.id LEFT JOIN users u ON ses.user_id = u.id ORDER BY ses.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
} else {
    $recent_edit_suggestions = [];
}
$check_table_recent->close();
?>

<h1 class="mb-4">Dashboard</h1>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <a href="schools.php" class="text-decoration-none">
            <div class="card bg-primary text-white" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-school me-2"></i>Total Schools
                    </h5>
                    <h2 class="mb-0"><?php echo $total_schools; ?></h2>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-3">
        <a href="requests.php" class="text-decoration-none">
            <div class="card bg-warning text-dark" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-clipboard-list me-2"></i>Business Requests
                    </h5>
                    <h2 class="mb-0"><?php echo $total_requests; ?></h2>
                    <small>Pending: <?php echo $pending_requests; ?></small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-3">
        <a href="manage_edit_suggestions.php" class="text-decoration-none">
            <div class="card bg-info text-white" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-edit me-2"></i>Edit Suggestions
                    </h5>
                    <h2 class="mb-0"><?php echo $total_edit_suggestions; ?></h2>
                    <small>Pending: <?php echo $pending_edit_suggestions; ?></small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 mb-3">
        <a href="manage_users.php" class="text-decoration-none">
            <div class="card bg-success text-white" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-users me-2"></i>Total Users
                    </h5>
                    <h2 class="mb-0"><?php echo $total_users; ?></h2>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Schools</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_schools)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Level</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_schools as $school): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($school['name']); ?></td>
                                    <td><span class="badge bg-primary"><?php echo $school['level']; ?></span></td>
                                    <td><?php echo date('M j, Y', strtotime($school['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="schools.php" class="btn btn-sm btn-primary">View All Schools</a>
                <?php else: ?>
                    <p class="text-muted">No schools yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Requests</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_requests)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>School Name</th>
                                    <th>Level</th>
                                    <th>Submitted By</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['name']); ?></td>
                                    <td><span class="badge bg-primary"><?php echo $request['level']; ?></span></td>
                                    <td>
                                        <?php if (!empty($request['user_name'])): ?>
                                            <?php echo htmlspecialchars($request['user_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Guest</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($request['submitted_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="requests.php" class="btn btn-sm btn-warning">View All Requests</a>
                <?php else: ?>
                    <p class="text-muted">No requests yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Edit Suggestions</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_edit_suggestions)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>School</th>
                                    <th>Edit Type</th>
                                    <th>Status</th>
                                    <th>Submitted By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_edit_suggestions as $suggestion): ?>
                                <tr>
                                    <td>
                                        <a href="schools.php?edit=<?php echo $suggestion['school_id']; ?>" target="_blank">
                                            <?php echo htmlspecialchars($suggestion['school_name'] ?? 'N/A'); ?>
                                        </a>
                                    </td>
                                    <td><span class="badge bg-info"><?php echo ucfirst($suggestion['edit_type']); ?></span></td>
                                    <td>
                                        <?php
                                        $status_class = 'secondary';
                                        if ($suggestion['status'] === 'approved') $status_class = 'success';
                                        elseif ($suggestion['status'] === 'rejected') $status_class = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>"><?php echo ucfirst($suggestion['status']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($suggestion['user_name'])): ?>
                                            <?php echo htmlspecialchars($suggestion['user_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Guest</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($suggestion['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="manage_edit_suggestions.php" class="btn btn-sm btn-info">View All Suggestions</a>
                <?php else: ?>
                    <p class="text-muted">No edit suggestions yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

