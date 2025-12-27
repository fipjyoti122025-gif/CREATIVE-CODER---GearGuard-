<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Pagination settings
$limit = 20; // Number of logs per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Build WHERE clause for filters
$whereConditions = [];
$params = [];

// Filter by action type
if (isset($_GET['action_type']) && $_GET['action_type'] != '') {
    $whereConditions[] = "al.action = ?";
    $params[] = $_GET['action_type'];
}

// Filter by target type
if (isset($_GET['target_type']) && $_GET['target_type'] != '') {
    $whereConditions[] = "al.target_type = ?";
    $params[] = $_GET['target_type'];
}

// Filter by date range
if (isset($_GET['date_from']) && $_GET['date_from'] != '') {
    $whereConditions[] = "DATE(al.created_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && $_GET['date_to'] != '') {
    $whereConditions[] = "DATE(al.created_at) <= ?";
    $params[] = $_GET['date_to'];
}

// Filter by admin
if (isset($_GET['admin_id']) && $_GET['admin_id'] != '') {
    $whereConditions[] = "al.admin_id = ?";
    $params[] = $_GET['admin_id'];
}

// Search filter
if (isset($_GET['search']) && $_GET['search'] != '') {
    $search = '%' . $_GET['search'] . '%';
    $whereConditions[] = "(al.description LIKE ? OR a.name LIKE ? OR al.action LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// Build WHERE clause
$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) FROM admin_activity_logs al 
               LEFT JOIN admins a ON al.admin_id = a.id 
               $whereClause";
$stmt = $pdo->prepare($countQuery);
if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}
$totalLogs = $stmt->fetchColumn();
$totalPages = ceil($totalLogs / $limit);

// Get logs with pagination
$query = "
    SELECT 
        al.*,
        a.name as admin_name,
        a.email as admin_email,
        DATE_FORMAT(al.created_at, '%M %d, %Y') as log_date,
        DATE_FORMAT(al.created_at, '%h:%i:%s %p') as log_time
    FROM admin_activity_logs al 
    LEFT JOIN admins a ON al.admin_id = a.id 
    $whereClause
    ORDER BY al.created_at DESC 
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);

// Bind WHERE clause parameters
$paramIndex = 1;
foreach ($params as $value) {
    $stmt->bindValue($paramIndex, $value);
    $paramIndex++;
}

// Bind LIMIT and OFFSET as integers
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique actions for filter dropdown
$stmt = $pdo->query("SELECT DISTINCT action FROM admin_activity_logs ORDER BY action");
$actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique target types for filter dropdown
$stmt = $pdo->query("SELECT DISTINCT target_type FROM admin_activity_logs WHERE target_type IS NOT NULL ORDER BY target_type");
$targetTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get admins for filter dropdown
$stmt = $pdo->query("SELECT id, name FROM admins WHERE status = 'active' ORDER BY name");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Time', 'Admin', 'Action', 'Target Type', 'Target ID', 'Description', 'IP Address']);
    
    $exportQuery = "SELECT 
        DATE(created_at) as date,
        TIME(created_at) as time,
        (SELECT name FROM admins WHERE id = admin_id) as admin,
        action,
        target_type,
        target_id,
        description,
        ip_address
    FROM admin_activity_logs 
    ORDER BY created_at DESC";
    
    $stmt = $pdo->query($exportQuery);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GearGuard | Audit Logs</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --dark: #0f172a;
            --sidebar: #1e293b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #0ea5e9;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-600: #475569;
            --radius-lg: 16px;
            --radius-md: 12px;
            --shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: #f8fafc; color: var(--dark); overflow-x: hidden; }

        /* Sidebar Styling */
        .layout { display: flex; min-height: 100vh; }
        .sidebar { 
            width: 280px; background: var(--sidebar); color: white; 
            padding: 2rem 1.5rem; display: flex; flex-direction: column;
            position: sticky; top: 0; height: 100vh;
        }
        .logo { font-size: 1.5rem; font-weight: 800; color: white; display: flex; align-items: center; gap: 10px; margin-bottom: 3rem; }
        .logo span { color: var(--primary-light); }

        .nav-menu { list-style: none; flex: 1; }
        .nav-link { text-decoration: none; color: inherit; display: block; }
        .nav-item { 
            padding: 12px 16px; margin-bottom: 8px; border-radius: var(--radius-md);
            cursor: pointer; display: flex; align-items: center; gap: 12px;
            color: #94a3b8; transition: 0.2s;
        }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: white; }
        .nav-link.active .nav-item { 
            border-left: 4px solid var(--primary); 
            background: rgba(99, 102, 241, 0.12); 
            color: white; 
        }

        /* Main Content */
        .main-container { flex: 1; padding: 2rem 3rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; }
        .welcome-text h1 { font-size: 1.875rem; font-weight: 700; letter-spacing: -0.025em; }
        .welcome-text p { color: var(--gray-600); margin-top: 4px; }

        /* Filters */
        .filter-bar {
            display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;
            background: white; padding: 1.5rem; border-radius: var(--radius-md);
            border: 1px solid var(--gray-200); box-shadow: var(--shadow);
        }
        .filter-select, .filter-date, .filter-search {
            padding: 10px 16px; border-radius: 8px; border: 1px solid var(--gray-200);
            background: white; outline: none; font-size: 0.9rem; color: var(--gray-600);
            transition: 0.2s;
        }
        .filter-select:focus, .filter-date:focus, .filter-search:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .filter-group {
            display: flex; gap: 10px; align-items: center;
        }
        .filter-label {
            font-size: 0.85rem; font-weight: 600; color: var(--gray-600);
            white-space: nowrap;
        }
        .btn-export {
            background: var(--success); color: white; border: none;
            padding: 10px 20px; border-radius: 8px; cursor: pointer;
            font-weight: 600; transition: 0.3s; display: flex;
            align-items: center; gap: 8px;
        }
        .btn-export:hover {
            background: #0da374; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3);
        }
        .btn-reset {
            background: var(--gray-200); color: var(--gray-600); border: none;
            padding: 10px 20px; border-radius: 8px; cursor: pointer;
            font-weight: 600; transition: 0.3s; display: flex;
            align-items: center; gap: 8px;
        }
        .btn-reset:hover {
            background: var(--gray-300);
        }

        /* Content Card */
        .content-card { 
            background: white; border-radius: var(--radius-lg); 
            box-shadow: var(--shadow); padding: 1.5rem; border: 1px solid var(--gray-100);
        }
        
        table { width: 100%; border-collapse: collapse; }
        th { 
            text-align: left; padding: 1rem; color: var(--gray-600); 
            font-weight: 600; border-bottom: 1px solid var(--gray-100); 
            font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; 
        }
        td { padding: 1.25rem 1rem; border-bottom: 1px solid var(--gray-100); font-size: 0.9rem; }
        
        /* Log Badges */
        .badge { 
            padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; 
            font-weight: 700; display: inline-block;
        }
        .type-create { background: #dcfce7; color: var(--success); }
        .type-update { background: #fef3c7; color: var(--warning); }
        .type-delete { background: #fee2e2; color: var(--danger); }
        .type-login { background: #dbeafe; color: #1d4ed8; }
        .type-logout { background: #f1f5f9; color: var(--gray-600); }
        .type-system { background: #e0e7ff; color: var(--primary); }

        .timestamp { color: var(--gray-600); font-size: 0.8rem; }
        .performer { display: flex; align-items: center; gap: 8px; font-weight: 600; }
        .avatar-sm { 
            width: 24px; height: 24px; border-radius: 50%; 
            background: var(--primary); color: white; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 0.7rem; font-weight: 600;
        }
        .description-cell {
            max-width: 300px; word-wrap: break-word;
        }

        /* Pagination */
        .pagination {
            display: flex; justify-content: space-between; align-items: center; 
            margin-top: 1.5rem; color: var(--gray-600); font-size: 0.9rem;
        }
        .page-btns { display: flex; gap: 5px; align-items: center; }
        .page-btn { 
            padding: 6px 12px; border-radius: 6px; border: 1px solid var(--gray-200); 
            background: white; cursor: pointer; color: var(--gray-600);
            transition: 0.2s;
        }
        .page-btn:hover { background: var(--gray-100); }
        .page-btn.active { 
            background: var(--primary); color: white; border-color: var(--primary); 
        }
        .page-btn.disabled { 
            opacity: 0.5; cursor: not-allowed; background: var(--gray-100); 
        }

        /* No results message */
        .no-results {
            text-align: center; padding: 3rem; color: var(--gray-600);
        }
        .no-results i {
            font-size: 3rem; margin-bottom: 1rem; color: var(--gray-300);
        }

        /* Loading indicator */
        .loading {
            text-align: center; padding: 2rem; color: var(--gray-600);
        }
        .loading i {
            font-size: 1.5rem; animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .layout { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: static; }
            .main-container { padding: 1.5rem; }
        }
        
        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; }
            .filter-group { width: 100%; }
            table { display: block; overflow-x: auto; }
        }
    </style>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <div class="logo">🛡️ Gear<span>Guard</span></div>
        <ul class="nav-menu">
            <a href="index.php" class="nav-link">
                <li class="nav-item">📊 Dashboard</li>
            </a>
            <a href="manager.php" class="nav-link">
                <li class="nav-item">👤 Manager</li>
            </a>
            <a href="companies.php" class="nav-link">
                <li class="nav-item">🏢 Companies</li>
            </a>
            <a href="auditlog.php" class="nav-link active">
                <li class="nav-item">📜 Audit Logs</li>
            </a>
            <a href="logout.php" class="nav-link">
                <li class="nav-item">Logout</li>
            </a>
        </ul>
        <div class="sidebar-footer" style="margin-top: auto; color: #64748b; font-size: 0.75rem;">
            v1.0.4-Stable
        </div>
    </aside>

    <main class="main-container">
        <header>
            <div class="welcome-text">
                <h1>System Audit Trail</h1>
                <p>Monitor administrative actions and security events</p>
            </div>
            <a href="auditlog.php?export=csv<?php echo isset($_SERVER['QUERY_STRING']) ? '&' . $_SERVER['QUERY_STRING'] : ''; ?>" 
               class="btn-export">
                <i class="fas fa-download"></i> Export CSV
            </a>
        </header>

        <form method="GET" action="auditlog.php" class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">Action:</span>
                <select name="action_type" class="filter-select">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $action): ?>
                    <option value="<?php echo htmlspecialchars($action); ?>" 
                        <?php echo (isset($_GET['action_type']) && $_GET['action_type'] == $action) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(ucfirst($action)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <span class="filter-label">Target:</span>
                <select name="target_type" class="filter-select">
                    <option value="">All Targets</option>
                    <?php foreach ($targetTypes as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>"
                        <?php echo (isset($_GET['target_type']) && $_GET['target_type'] == $type) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(ucfirst($type)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <span class="filter-label">Admin:</span>
                <select name="admin_id" class="filter-select">
                    <option value="">All Admins</option>
                    <?php foreach ($admins as $admin): ?>
                    <option value="<?php echo $admin['id']; ?>"
                        <?php echo (isset($_GET['admin_id']) && $_GET['admin_id'] == $admin['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($admin['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <span class="filter-label">From:</span>
                <input type="date" name="date_from" class="filter-date" 
                       value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
            </div>

            <div class="filter-group">
                <span class="filter-label">To:</span>
                <input type="date" name="date_to" class="filter-date" 
                       value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
            </div>

            <div class="filter-group" style="flex: 1;">
                <input type="text" name="search" class="filter-search" placeholder="Search logs..." 
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit" class="btn-export" style="background: var(--primary);">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="auditlog.php" class="btn-reset">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>

        <section class="content-card">
            <?php if (empty($logs)): ?>
            <div class="no-results">
                <i class="fas fa-clipboard-list"></i>
                <h3>No audit logs found</h3>
                <p>Try adjusting your filters or search terms</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Target</th>
                        <th>Admin</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        // Determine badge class based on action
                        $badgeClass = 'type-system';
                        if (strpos($log['action'], 'create') !== false || strpos($log['action'], 'add') !== false) {
                            $badgeClass = 'type-create';
                        } elseif (strpos($log['action'], 'update') !== false || strpos($log['action'], 'edit') !== false || 
                                  strpos($log['action'], 'toggle') !== false || strpos($log['action'], 'change') !== false) {
                            $badgeClass = 'type-update';
                        } elseif (strpos($log['action'], 'delete') !== false || strpos($log['action'], 'remove') !== false) {
                            $badgeClass = 'type-delete';
                        } elseif (strpos($log['action'], 'login') !== false) {
                            $badgeClass = 'type-login';
                        } elseif (strpos($log['action'], 'logout') !== false) {
                            $badgeClass = 'type-logout';
                        }
                        
                        // Get admin initials for avatar
                        $adminName = $log['admin_name'] ?? 'System';
                        $initials = strtoupper(substr($adminName, 0, 2));
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($log['log_date']); ?></div>
                            <div class="timestamp"><?php echo htmlspecialchars($log['log_time']); ?></div>
                        </td>
                        <td>
                            <span class="badge <?php echo $badgeClass; ?>">
                                <?php echo htmlspecialchars(strtoupper($log['action'])); ?>
                            </span>
                        </td>
                        <td class="description-cell">
                            <?php echo htmlspecialchars($log['description'] ?? 'No description'); ?>
                        </td>
                        <td>
                            <?php if ($log['target_type'] && $log['target_id']): ?>
                                <span style="font-weight: 500;">
                                    <?php echo htmlspecialchars(ucfirst($log['target_type'])); ?> #<?php echo $log['target_id']; ?>
                                </span>
                            <?php elseif ($log['target_type']): ?>
                                <span style="color: var(--gray-600);">
                                    <?php echo htmlspecialchars(ucfirst($log['target_type'])); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--gray-400);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="performer">
                                <div class="avatar-sm"><?php echo $initials; ?></div>
                                <?php echo htmlspecialchars($adminName); ?>
                            </div>
                        </td>
                        <td>
                            <span class="timestamp"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="pagination">
                <div>
                    Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $limit, $totalLogs); ?> of <?php echo $totalLogs; ?> results
                </div>
                <div class="page-btns">
                    <?php if ($page > 1): ?>
                        <a href="?<?php 
                            $queryParams = $_GET;
                            $queryParams['page'] = 1;
                            echo http_build_query($queryParams);
                        ?>" class="page-btn">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?<?php 
                            $queryParams = $_GET;
                            $queryParams['page'] = $page - 1;
                            echo http_build_query($queryParams);
                        ?>" class="page-btn">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled"><i class="fas fa-angle-double-left"></i></span>
                        <span class="page-btn disabled"><i class="fas fa-angle-left"></i></span>
                    <?php endif; ?>

                    <?php 
                    // Show page numbers
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <a href="?<?php 
                            $queryParams = $_GET;
                            $queryParams['page'] = $i;
                            echo http_build_query($queryParams);
                        ?>" class="page-btn <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php 
                            $queryParams = $_GET;
                            $queryParams['page'] = $page + 1;
                            echo http_build_query($queryParams);
                        ?>" class="page-btn">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?<?php 
                            $queryParams = $_GET;
                            $queryParams['page'] = $totalPages;
                            echo http_build_query($queryParams);
                        ?>" class="page-btn">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="page-btn disabled"><i class="fas fa-angle-right"></i></span>
                        <span class="page-btn disabled"><i class="fas fa-angle-double-right"></i></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
    // Set max date for date_to field to today
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        const dateToField = document.querySelector('input[name="date_to"]');
        if (dateToField) {
            dateToField.max = today;
        }
        
        // Set date_from max to date_to if date_to is set
        const dateFromField = document.querySelector('input[name="date_from"]');
        if (dateToField && dateFromField) {
            dateFromField.max = dateToField.value || today;
            dateToField.addEventListener('change', function() {
                dateFromField.max = this.value;
            });
        }
    });
    
    // Auto-submit form on filter changes (except search)
    document.querySelectorAll('.filter-select, .filter-date').forEach(element => {
        element.addEventListener('change', function() {
            this.form.submit();
        });
    });
</script>

</body>
</html>