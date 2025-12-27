<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get statistics for dashboard
$stats = [];

// Total Managers
$stmt = $pdo->query("SELECT COUNT(*) as total FROM managers");
$stats['total_managers'] = $stmt->fetchColumn();

// Active Managers
$stmt = $pdo->query("SELECT COUNT(*) as active FROM managers WHERE status = 'active'");
$stats['active_managers'] = $stmt->fetchColumn();

// Inactive Managers
$stmt = $pdo->query("SELECT COUNT(*) as inactive FROM managers WHERE status = 'inactive'");
$stats['inactive_managers'] = $stmt->fetchColumn();

// Total Companies
$stmt = $pdo->query("SELECT COUNT(*) as total FROM companies WHERE status = 'active'");
$stats['total_companies'] = $stmt->fetchColumn();

// Get managers list with their companies
$stmt = $pdo->query("
    SELECT 
        m.id,
        m.name,
        m.email,
        m.status,
        m.last_login,
        m.created_at,
        c.company_name,
        c.company_code
    FROM managers m
    LEFT JOIN companies c ON m.company_id = c.id
    ORDER BY m.created_at DESC
");
$managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get companies for dropdown
$stmt = $pdo->query("SELECT id, company_name FROM companies WHERE status = 'active' ORDER BY company_name");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle add manager form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_manager'])) {
    try {
        // Validate email doesn't already exist
        $stmt = $pdo->prepare("SELECT id FROM managers WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Manager with this email already exists!";
        } else {
            $pdo->beginTransaction();
            
            // Insert new manager
            $stmt = $pdo->prepare("
                INSERT INTO managers 
                (company_id, name, email, password, status, created_at)
                VALUES (?, ?, ?, ?, 'active', NOW())
            ");
            
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $_POST['company_id'],
                $_POST['name'],
                $_POST['email'],
                $hashed_password
            ]);
            
            $manager_id = $pdo->lastInsertId();
            
            // Log the activity
            $stmt = $pdo->prepare("
                INSERT INTO admin_activity_logs 
                (admin_id, action, target_type, target_id, description, ip_address, user_agent)
                VALUES (?, 'create', 'manager', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['admin_id'],
                $manager_id,
                "Created new manager: {$_POST['name']}",
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
            
            $pdo->commit();
            
            // Refresh page to show new data
            header("Location: manager.php?success=1");
            exit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error adding manager: " . $e->getMessage();
    }
}

// Handle manager status toggle
if (isset($_GET['toggle_status'])) {
    $manager_id = intval($_GET['id']);
    $action = $_GET['toggle_status'];
    
    $new_status = ($action == 'deactivate') ? 'inactive' : 'active';
    
    $stmt = $pdo->prepare("UPDATE managers SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $manager_id]);
    
    // Log the activity
    $stmt = $pdo->prepare("
        INSERT INTO admin_activity_logs 
        (admin_id, action, target_type, target_id, description, ip_address, user_agent)
        VALUES (?, ?, 'manager', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['admin_id'],
        $action,
        $manager_id,
        "Changed manager status to: $new_status",
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    header("Location: manager.php");
    exit();
}

// Handle delete manager
if (isset($_GET['delete'])) {
    $manager_id = intval($_GET['id']);
    
    try {
        $pdo->beginTransaction();
        
        // Get manager name for logging
        $stmt = $pdo->prepare("SELECT name FROM managers WHERE id = ?");
        $stmt->execute([$manager_id]);
        $manager_name = $stmt->fetchColumn();
        
        // Delete manager
        $stmt = $pdo->prepare("DELETE FROM managers WHERE id = ?");
        $stmt->execute([$manager_id]);
        
        // Log the activity
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs 
            (admin_id, action, target_type, target_id, description, ip_address, user_agent)
            VALUES (?, 'delete', 'manager', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            'delete',
            $manager_id,
            "Deleted manager: $manager_name",
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        $pdo->commit();
        
        header("Location: manager.php?deleted=1");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting manager: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GearGuard | Manager Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-600: #475569;
            --radius-lg: 16px;
            --radius-md: 12px;
            --shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: #f8fafc; color: var(--dark); overflow-x: hidden; }

        /* --- SIDEBAR --- */
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: var(--sidebar); color: white; padding: 2rem 1.5rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; }
        .logo { font-size: 1.5rem; font-weight: 800; color: white; display: flex; align-items: center; gap: 10px; margin-bottom: 3rem; }
        .logo span { color: var(--primary-light); }
        .nav-menu { list-style: none; flex: 1; }
        .nav-link { text-decoration: none; color: inherit; display: block; }
        .nav-item { padding: 12px 16px; margin-bottom: 8px; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; gap: 12px; color: #94a3b8; transition: 0.2s; }
        .nav-item:hover { background: rgba(255,255,255,0.05); color: white; }
        .nav-link.active .nav-item { background: rgba(99, 102, 241, 0.12); color: #ffffff; border-left: 4px solid var(--primary); }

        /* --- CONTENT --- */
        .main-container { flex: 1; padding: 2rem 3rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; }
        .welcome-text h1 { font-size: 1.875rem; font-weight: 700; }
        .welcome-text p { color: var(--gray-600); margin-top: 4px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2.5rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: var(--radius-lg); box-shadow: var(--shadow); border: 1px solid var(--gray-100); transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-label { color: var(--gray-600); font-size: 0.875rem; font-weight: 500; }
        .stat-value { font-size: 2rem; font-weight: 800; margin: 0.5rem 0; display: block; }
        .stat-trend { font-size: 0.75rem; font-weight: 600; padding: 4px 8px; border-radius: 20px; }
        .trend-up { background: #dcfce7; color: var(--success); }

        .content-card { background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow); padding: 1.5rem; border: 1px solid var(--gray-100); margin-bottom: 2rem; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { text-align: left; padding: 1rem; color: var(--gray-600); border-bottom: 1px solid var(--gray-100); font-size: 0.8rem; text-transform: uppercase; }
        td { padding: 1.25rem 1rem; border-bottom: 1px solid var(--gray-100); font-size: 0.95rem; }

        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-active { background: #dcfce7; color: var(--success); }
        .badge-inactive { background: #fee2e2; color: var(--danger); }
        .badge-pending { background: #fef3c7; color: var(--warning); }

        .btn-add { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: var(--radius-md); cursor: pointer; font-weight: 600; transition: 0.3s; }
        .btn-add:hover { background: var(--primary-dark); box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4); }

        .action-buttons { display: flex; gap: 10px; }
        .btn-edit { color: var(--primary); background: none; border: 1px solid var(--primary); padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
        .btn-delete { color: var(--danger); background: none; border: 1px solid var(--danger); padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
        .btn-status { color: var(--warning); background: none; border: 1px solid var(--warning); padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }

        /* --- MODAL & FORM STYLING --- */
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; width: 500px; max-width: 90%; padding: 2.5rem; border-radius: var(--radius-lg); position: relative; box-shadow: var(--shadow); animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .modal-close { position: absolute; top: 20px; right: 20px; cursor: pointer; background: var(--gray-100); border: none; width: 30px; height: 30px; border-radius: 50%; font-weight: bold; }
        
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--gray-600); margin-bottom: 6px; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid var(--gray-200); border-radius: 8px; outline: none; transition: 0.2s; }
        .form-group input:focus, .form-group select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
        
        .submit-btn { width: 100%; background: var(--primary); color: white; padding: 14px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; margin-top: 10px; transition: 0.3s; }
        .submit-btn:hover { background: var(--primary-dark); }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .search-bar {
            padding: 10px 16px;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
            width: 300px;
            outline: none;
        }
        
        .last-login {
            font-size: 0.85rem;
            color: var(--gray-600);
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
            <a href="manager.php" class="nav-link active">
                <li class="nav-item">👤 Manager</li>
            </a>
            <a href="companies.php" class="nav-link">
                <li class="nav-item">🏢 Companies</li>
            </a>
            <a href="auditlog.php" class="nav-link">
                <li class="nav-item">📜 Audit Logs</li>
            </a>
            <a href="logout.php" class="nav-link">
                <li class="nav-item">Logout</li>
            </a>
        </ul>
    </aside>

    <main class="main-container">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Manager added successfully!
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Manager deleted successfully!
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <header>
            <div class="welcome-text">
                <h1>Manager Management</h1>
                <p>Manage company managers and their access permissions</p>
            </div>
            <button class="btn-add" onclick="openModal()">
                <i class="fas fa-plus"></i> Add New Manager
            </button>
        </header>

        <section class="stats-grid">
            <div class="stat-card">
                <span class="stat-label">Total Managers</span>
                <span class="stat-value"><?php echo $stats['total_managers']; ?></span>
                <span class="stat-trend trend-up">↑ 5 new this month</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Active Managers</span>
                <span class="stat-value" style="color: var(--success)"><?php echo $stats['active_managers']; ?></span>
                <span class="stat-trend trend-up">All systems operational</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Inactive Managers</span>
                <span class="stat-value" style="color: var(--danger)"><?php echo $stats['inactive_managers']; ?></span>
                <span class="stat-trend" style="background: #f1f5f9; color: var(--gray-600)">Requires attention</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Active Companies</span>
                <span class="stat-value" style="color: var(--primary)"><?php echo $stats['total_companies']; ?></span>
                <span class="stat-trend trend-up">↑ 3 new companies</span>
            </div>
        </section>

        <section class="content-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3>All Managers</h3>
                <input type="text" placeholder="Search managers..." class="search-bar" id="searchInput">
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Manager Name</th>
                        <th>Email</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="managerTable">
                    <?php foreach ($managers as $manager): ?>
                    <tr>
                        <td>
                            <b><?php echo htmlspecialchars($manager['name']); ?></b><br>
                            <span class="last-login">
                                Created: <?php echo date('M d, Y', strtotime($manager['created_at'])); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($manager['email']); ?></td>
                        <td>
                            <?php if ($manager['company_name']): ?>
                                <b><?php echo htmlspecialchars($manager['company_name']); ?></b><br>
                                <span class="last-login">Code: <?php echo htmlspecialchars($manager['company_code']); ?></span>
                            <?php else: ?>
                                <span style="color: var(--gray-600);">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $manager['status']; ?>">
                                <?php echo ucfirst($manager['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($manager['last_login']): ?>
                                <?php echo date('M d, Y H:i', strtotime($manager['last_login'])); ?>
                            <?php else: ?>
                                <span style="color: var(--gray-600);">Never logged in</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($manager['status'] == 'active'): ?>
                                    <a href="manager.php?toggle_status=deactivate&id=<?php echo $manager['id']; ?>" 
                                       class="btn-status" title="Deactivate Manager">
                                        <i class="fas fa-pause"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="manager.php?toggle_status=activate&id=<?php echo $manager['id']; ?>" 
                                       class="btn-status" title="Activate Manager">
                                        <i class="fas fa-play"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="#" 
                                   onclick="confirmDelete(<?php echo $manager['id']; ?>, '<?php echo addslashes($manager['name']); ?>')" 
                                   class="btn-delete" title="Delete Manager">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<div class="modal" id="managerModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <h2 style="margin-bottom: 20px;">Add New Manager</h2>
        
        <form method="POST" action="manager.php" id="managerForm">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" name="name" placeholder="Enter manager's full name" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" placeholder="manager@company.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" placeholder="Create a secure password" required minlength="6">
                <small style="color: var(--gray-600); font-size: 0.8rem;">Minimum 6 characters</small>
            </div>

            <div class="form-group">
                <label for="company_id">Assign to Company</label>
                <select name="company_id" required>
                    <option value="">Select a Company</option>
                    <?php foreach ($companies as $company): ?>
                    <option value="<?php echo $company['id']; ?>">
                        <?php echo htmlspecialchars($company['company_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" name="add_manager" class="submit-btn">
                <i class="fas fa-user-plus"></i> Register Manager
            </button>
        </form>
    </div>
</div>

<script>
    function openModal() { 
        document.getElementById('managerModal').style.display = 'flex'; 
        document.getElementById('managerForm').reset();
    }
    
    function closeModal() { 
        document.getElementById('managerModal').style.display = 'none'; 
    }
    
    function confirmDelete(id, name) {
        if (confirm(`Are you sure you want to delete manager "${name}"? This action cannot be undone.`)) {
            window.location.href = `manager.php?delete=1&id=${id}`;
        }
    }
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#managerTable tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
    
    // Close modal when clicking outside
    document.getElementById('managerModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeModal();
        }
    });
</script>

</body>
</html>