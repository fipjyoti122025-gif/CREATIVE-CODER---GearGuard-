<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get dashboard statistics
$stats = [];

// Total Companies
$stmt = $pdo->query("SELECT COUNT(*) as total FROM companies");
$stats['total_companies'] = $stmt->fetchColumn();

// Active Companies
$stmt = $pdo->query("SELECT COUNT(*) as active FROM companies WHERE status = 'active'");
$stats['active_companies'] = $stmt->fetchColumn();

// Suspended Companies
$stmt = $pdo->query("SELECT COUNT(*) as suspended FROM companies WHERE status = 'suspended'");
$stats['suspended_companies'] = $stmt->fetchColumn();

// Total Managers
$stmt = $pdo->query("SELECT COUNT(*) as total FROM managers WHERE status = 'active'");
$stats['total_managers'] = $stmt->fetchColumn();

// Get companies list with their managers
$stmt = $pdo->query("
    SELECT 
        c.id,
        c.company_code,
        c.company_name,
        c.status as company_status,
        c.company_size,
        m.name as manager_name,
        m.email as manager_email,
        m.status as manager_status
    FROM companies c
    LEFT JOIN managers m ON c.id = m.company_id
    ORDER BY c.created_at DESC
    LIMIT 10
");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get growth chart data (companies created per month)
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%b') as month,
        COUNT(*) as count
    FROM companies 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY created_at
");
$growthData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get company size distribution
$stmt = $pdo->query("
    SELECT 
        company_size as size,
        COUNT(*) as count
    FROM companies 
    WHERE company_size IS NOT NULL
    GROUP BY company_size
");
$sizeData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_company'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Generate unique company code
        $company_code = 'GG' . strtoupper(uniqid());
        
        // Insert company
        $stmt = $pdo->prepare("
            INSERT INTO companies 
            (company_code, company_name, company_size, status, created_by_admin, created_at)
            VALUES (?, ?, ?, 'active', ?, NOW())
        ");
        $stmt->execute([
            $company_code,
            $_POST['company_name'],
            $_POST['company_size'],
            $_SESSION['admin_id']
        ]);
        
        $company_id = $pdo->lastInsertId();
        
        // Insert manager
        $stmt = $pdo->prepare("
            INSERT INTO managers 
            (company_id, name, email, password, status, created_at)
            VALUES (?, ?, ?, ?, 'active', NOW())
        ");
        $hashed_password = password_hash('temp123', PASSWORD_DEFAULT);
        $stmt->execute([
            $company_id,
            $_POST['manager_name'],
            $_POST['manager_email'],
            $hashed_password
        ]);
        
        // Log the activity
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs 
            (admin_id, action, target_type, target_id, description, ip_address, user_agent)
            VALUES (?, 'create', 'company', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            $company_id,
            "Created new company: {$_POST['company_name']}",
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
        
        $pdo->commit();
        
        // Refresh page to show new data
        header("Location: index.php?success=1");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error adding company: " . $e->getMessage();
    }
}

// Handle company status toggle
if (isset($_GET['toggle_status'])) {
    $company_id = intval($_GET['id']);
    $action = $_GET['toggle_status'];
    
    $new_status = ($action == 'suspend') ? 'suspended' : 'active';
    
    $stmt = $pdo->prepare("UPDATE companies SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $company_id]);
    
    // Log the activity
    $stmt = $pdo->prepare("
        INSERT INTO admin_activity_logs 
        (admin_id, action, target_type, target_id, description, ip_address, user_agent)
        VALUES (?, ?, 'company', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['admin_id'],
        $action,
        $company_id,
        "Changed company status to: $new_status",
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GearGuard | Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Sidebar Styling */
        .layout { display: flex; min-height: 100vh; }
        .sidebar { 
            width: 280px; background: var(--sidebar); color: white; 
            padding: 2rem 1.5rem; display: flex; flex-direction: column;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .logo { font-size: 1.5rem; font-weight: 800; color: white; display: flex; align-items: center; gap: 10px; margin-bottom: 3rem; }
        .logo span { color: var(--primary-light); }

        .nav-menu { list-style: none; flex: 1; }
        .nav-item { 
            padding: 12px 16px; margin-bottom: 8px; border-radius: var(--radius-md);
            cursor: pointer; display: flex; align-items: center; gap: 12px;
            color: #94a3b8; transition: 0.2s;
        }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.05); color: white; }
        .nav-item.active { border-left: 4px solid var(--primary); background: rgba(99, 102, 241, 0.1); }

        /* Main Content */
        .main-container { flex: 1; padding: 2rem 3rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; }
        .welcome-text h1 { font-size: 1.875rem; font-weight: 700; letter-spacing: -0.025em; }
        .welcome-text p { color: var(--gray-600); margin-top: 4px; }

        /* KPI Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2.5rem; }
        .stat-card { 
            background: white; padding: 1.5rem; border-radius: var(--radius-lg);
            box-shadow: var(--shadow); border: 1px solid var(--gray-100);
            transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-label { color: var(--gray-600); font-size: 0.875rem; font-weight: 500; }
        .stat-value { font-size: 2rem; font-weight: 800; margin: 0.5rem 0; display: block; }
        .stat-trend { font-size: 0.75rem; font-weight: 600; padding: 4px 8px; border-radius: 20px; }
        .trend-up { background: #dcfce7; color: var(--success); }

        /* Tables & Sections */
        .content-card { 
            background: white; border-radius: var(--radius-lg); 
            box-shadow: var(--shadow); padding: 1.5rem; border: 1px solid var(--gray-100);
        }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 1rem; color: var(--gray-600); font-weight: 600; border-bottom: 1px solid var(--gray-100); }
        td { padding: 1.25rem 1rem; border-bottom: 1px solid var(--gray-100); font-size: 0.95rem; }
        
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-small { background: #e0f2fe; color: #0369a1; }
        .badge-medium { background: #ede9fe; color: var(--primary); }
        .badge-large { background: #dcfce7; color: var(--success); }
        .badge-active { background: #dcfce7; color: var(--success); }
        .badge-suspended { background: #fee2e2; color: var(--danger); }

        /* Buttons & Inputs */
        .btn-add { 
            background: var(--primary); color: white; border: none; padding: 12px 24px;
            border-radius: var(--radius-md); cursor: pointer; font-weight: 600; transition: 0.3s;
        }
        .btn-add:hover { background: var(--primary-dark); box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4); }

        /* Modal styling */
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; width: 450px; padding: 2.5rem; border-radius: var(--radius-lg); animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* Chart Section */
        .charts-grid { display: grid; grid-template-columns: 2fr 1.2fr; gap: 1.5rem; margin-bottom: 2.5rem; }
        
        .nav-link {
            text-decoration: none;
            display: block; /* Makes the anchor fill the width */
            color: inherit;
        }
        
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
    </style>
</head>
<body>

<div class="layout">
<aside class="sidebar">
  <div class="logo">🛡️ Gear<span>Guard</span></div>

  <ul class="nav-menu">
    <a href="index.php" class="nav-link">
      <li class="nav-item active">📊 Dashboard</li>
    </a>

    <a href="manager.php" class="nav-link">
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
                Company onboarded successfully! Default password for manager: <strong>temp123</strong>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <header>
            <div class="welcome-text">
                <h1>Platform Overview</h1>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Super Admin'); ?></p>
            </div>
            <button class="btn-add" onclick="toggleModal(true)">+ Onboard New Client</button>
        </header>

        <section class="stats-grid">
            <div class="stat-card">
                <span class="stat-label">Total Companies</span>
                <span class="stat-value" id="count-total"><?php echo $stats['total_companies']; ?></span>
                <span class="stat-trend trend-up">↑ 12% growth</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Active Companies</span>
                <span class="stat-value" id="count-active" style="color: var(--success)"><?php echo $stats['active_companies']; ?></span>
                <span class="stat-trend trend-up">↑ 4 new today</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Suspended Companies</span>
                <span class="stat-value" id="count-suspended" style="color: var(--danger)"><?php echo $stats['suspended_companies']; ?></span>
                <span class="stat-trend" style="background: #f1f5f9; color: var(--gray-600)">Stable</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Total Managers</span>
                <span class="stat-value" id="count-managers"><?php echo $stats['total_managers']; ?></span>
                <span class="stat-trend trend-up">↑ 8% this week</span>
            </div>
        </section>

        <section class="charts-grid">
            <div class="content-card">
                <h3>Onboarding Growth</h3>
                <canvas id="growthChart" height="150"></canvas>
            </div>
            <div class="content-card">
                <h3>Company Size Distribution</h3>
                <canvas id="sizeChart" height="250"></canvas>
            </div>
        </section>

        <section class="content-card">
            <div class="section-header">
                <h3>Company Directory</h3>
                <input type="text" placeholder="Search companies..." style="padding: 8px 16px; border-radius: 8px; border: 1px solid var(--gray-200); outline: none;">
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Code</th>
                        <th>Manager</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="companyTableBody">
                    <?php foreach ($companies as $company): ?>
                    <tr>
                        <td style="font-weight: 700;"><?php echo htmlspecialchars($company['company_name']); ?></td>
                        <td><?php echo htmlspecialchars($company['company_code']); ?></td>
                        <td><?php echo htmlspecialchars($company['manager_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if ($company['company_size']): ?>
                            <span class="badge badge-<?php echo strtolower($company['company_size']); ?>">
                                <?php echo htmlspecialchars($company['company_size']); ?>
                            </span>
                            <?php else: ?>
                            <span class="badge" style="background: var(--gray-100); color: var(--gray-600);">Not Set</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?php echo $company['company_status']; ?>">
                            <?php echo ucfirst($company['company_status']); ?>
                        </span></td>
                        <td>
                            <?php if ($company['company_status'] == 'active'): ?>
                                <a href="index.php?toggle_status=suspend&id=<?php echo $company['id']; ?>" 
                                   style="color:var(--warning); text-decoration:none; font-weight:600;">
                                    Suspend
                                </a>
                            <?php else: ?>
                                <a href="index.php?toggle_status=activate&id=<?php echo $company['id']; ?>" 
                                   style="color:var(--success); text-decoration:none; font-weight:600;">
                                    Activate
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<div class="modal" id="addCompanyModal">
    <div class="modal-content">
        <h2 style="margin-bottom: 1.5rem;">Onboard New Client</h2>
        <form method="POST" action="index.php">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom: 5px; font-weight: 600;">Company Legal Name</label>
                <input type="text" name="company_name" required style="width:100%; padding:12px; border-radius:8px; border:1px solid var(--gray-200);">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom: 5px; font-weight: 600;">Manager Full Name</label>
                <input type="text" name="manager_name" required style="width:100%; padding:12px; border-radius:8px; border:1px solid var(--gray-200);">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom: 5px; font-weight: 600;">Manager Email</label>
                <input type="email" name="manager_email" required style="width:100%; padding:12px; border-radius:8px; border:1px solid var(--gray-200);">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; margin-bottom: 5px; font-weight: 600;">Company Size</label>
                <select name="company_size" style="width:100%; padding:12px; border-radius:8px; border:1px solid var(--gray-200);">
                    <option value="Small">Small (1-50 employees)</option>
                    <option value="Medium">Medium (51-200 employees)</option>
                    <option value="Large">Large (201+ employees)</option>
                </select>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="toggleModal(false)" style="flex:1; padding:12px; border-radius:8px; border:none; cursor:pointer;">Cancel</button>
                <button type="submit" name="add_company" class="btn-add" style="flex:2;">Confirm Onboarding</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Initialize Charts
    function initCharts() {
        // Growth Chart
        const growthData = {
            labels: [<?php foreach ($growthData as $row) echo "'" . $row['month'] . "', "; ?>],
            datasets: [{
                label: 'New Companies',
                data: [<?php foreach ($growthData as $row) echo $row['count'] . ", "; ?>],
                borderColor: '#6366f1',
                tension: 0.4,
                fill: true,
                backgroundColor: 'rgba(99, 102, 241, 0.1)'
            }]
        };

        const ctxGrowth = document.getElementById('growthChart').getContext('2d');
        new Chart(ctxGrowth, {
            type: 'line',
            data: growthData,
            options: { responsive: true, plugins: { legend: { display: false } } }
        });

        // Company Size Distribution Chart
        const sizeData = {
            labels: [<?php foreach ($sizeData as $row) echo "'" . $row['size'] . "', "; ?>],
            datasets: [{
                data: [<?php foreach ($sizeData as $row) echo $row['count'] . ", "; ?>],
                backgroundColor: ['#60a5fa', '#818cf8', '#34d399']
            }]
        };

        const ctxSize = document.getElementById('sizeChart').getContext('2d');
        new Chart(ctxSize, {
            type: 'doughnut',
            data: sizeData,
            options: { 
                cutout: '75%', 
                plugins: { 
                    legend: { 
                        position: 'bottom',
                        labels: {
                            padding: 20
                        }
                    } 
                }
            }
        });
    }

    function toggleModal(show) {
        document.getElementById('addCompanyModal').style.display = show ? 'flex' : 'none';
    }

    window.onload = () => {
        initCharts();
    };
</script>

</body>
</html>
