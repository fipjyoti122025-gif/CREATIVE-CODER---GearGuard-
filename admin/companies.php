<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get companies list
$stmt = $pdo->query("
    SELECT 
        c.id,
        c.company_code,
        c.company_name,
        c.industry,
        c.company_size,
        c.status,
        c.created_at,
        cc.email as contact_email,
        cc.phone as contact_phone,
        cc.state,
        cc.city
    FROM companies c
    LEFT JOIN company_contacts cc ON c.id = cc.company_id
    ORDER BY c.created_at DESC
");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define static states list (instead of querying empty table)
$states = [
    'Gujarat',
    'Maharashtra', 
    'Karnataka',
    'Delhi',
    'Tamil Nadu',
    'Uttar Pradesh',
    'West Bengal',
    'Rajasthan',
    'Telangana',
    'Andhra Pradesh',
    'Kerala',
    'Madhya Pradesh'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_company'])) {
    try {
        $pdo->beginTransaction();
        
        // Generate unique company code
        $company_code = 'GG' . strtoupper(substr(uniqid(), -8));
        
        // IMPORTANT: Check if admin_id exists in admins table
        $admin_id = $_SESSION['admin_id'];
        $checkAdmin = $pdo->prepare("SELECT id FROM admins WHERE id = ?");
        $checkAdmin->execute([$admin_id]);
        
        if (!$checkAdmin->fetch()) {
            // Admin doesn't exist, set to NULL or create a default admin
            $admin_id = null;
        }
        
        // Insert company
        $stmt = $pdo->prepare("
            INSERT INTO companies 
            (company_code, company_name, industry, company_size, description, status, created_by_admin, created_at)
            VALUES (?, ?, ?, ?, ?, 'active', ?, NOW())
        ");
        
        $result = $stmt->execute([
            $company_code,
            $_POST['company_name'],
            $_POST['industry'],
            $_POST['company_size'],
            $_POST['description'] ?? '',
            $admin_id
        ]);
        
        if (!$result) {
            throw new Exception("Failed to insert company: " . implode(", ", $stmt->errorInfo()));
        }
        
        $company_id = $pdo->lastInsertId();
        
        // Insert company contacts
        $stmt = $pdo->prepare("
            INSERT INTO company_contacts 
            (company_id, email, phone, website, address, state, city)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $company_id,
            $_POST['email'],
            $_POST['phone'],
            $_POST['website'] ?? '',
            $_POST['address'] ?? '',
            $_POST['state'],
            $_POST['city']
        ]);
        
        if (!$result) {
            throw new Exception("Failed to insert company contacts: " . implode(", ", $stmt->errorInfo()));
        }
        
        // Log the activity (only if admin exists)
        if ($admin_id) {
            $stmt = $pdo->prepare("
                INSERT INTO admin_activity_logs 
                (admin_id, action, target_type, target_id, description, ip_address, user_agent)
                VALUES (?, 'create', 'company', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $admin_id,
                $company_id,
                "Added new company: {$_POST['company_name']}",
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        
        $pdo->commit();
        
        // Refresh page to show new data
        header("Location: companies.php?success=1");
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
    if (isset($_SESSION['admin_id'])) {
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
    }
    
    header("Location: companies.php");
    exit();
}

// Handle delete company
if (isset($_GET['delete'])) {
    $company_id = intval($_GET['id']);
    
    try {
        $pdo->beginTransaction();
        
        // Get company name for logging
        $stmt = $pdo->prepare("SELECT company_name FROM companies WHERE id = ?");
        $stmt->execute([$company_id]);
        $company_name = $stmt->fetchColumn();
        
        // First delete company_contacts (due to foreign key)
        $stmt = $pdo->prepare("DELETE FROM company_contacts WHERE company_id = ?");
        $stmt->execute([$company_id]);
        
        // Now delete company
        $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
        $stmt->execute([$company_id]);
        
        // Log the activity
        if (isset($_SESSION['admin_id'])) {
            $stmt = $pdo->prepare("
                INSERT INTO admin_activity_logs 
                (admin_id, action, target_type, target_id, description, ip_address, user_agent)
                VALUES (?, 'delete', 'company', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['admin_id'],
                'delete',
                $company_id,
                "Deleted company: $company_name",
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
        }
        
        $pdo->commit();
        
        header("Location: companies.php?deleted=1");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting company: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GearGuard | Company Directory</title>
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
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-600: #475569;
            --radius-lg: 16px;
            --radius-md: 12px;
            --shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: #f8fafc; color: var(--dark); overflow-x: hidden; }

        /* Sidebar & Layout */
        .layout { display: flex; min-height: 100vh; }
        
        /* ================= SIDEBAR ================= */
        .sidebar {
            width: 280px;
            background: var(--sidebar);
            color: white;
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 3rem;
        }

        .logo span {
            color: var(--primary-light);
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex: 1;
        }

        .nav-link {
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .nav-item {
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #94a3b8;
            transition: 0.2s ease;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
        }

        .nav-link.active .nav-item {
            background: rgba(99, 102, 241, 0.12);
            color: #ffffff;
            border-left: 4px solid var(--primary);
        }

        /* Main Content */
        .main-container { flex: 1; padding: 2rem 3rem; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; }
        .content-card { 
            background: white; 
            border-radius: var(--radius-lg); 
            box-shadow: var(--shadow); 
            padding: 1.5rem; 
            border: 1px solid var(--gray-100); 
        }
        
        /* Table */
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { text-align: left; padding: 1rem; color: var(--gray-600); border-bottom: 1px solid var(--gray-100); font-size: 0.85rem; text-transform: uppercase; }
        td { padding: 1.25rem 1rem; border-bottom: 1px solid var(--gray-100); font-size: 0.95rem; }

        /* Badges */
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-active { background: #dcfce7; color: var(--success); }
        .badge-inactive { background: #fef3c7; color: var(--warning); }
        .badge-suspended { background: #fee2e2; color: var(--danger); }
        .badge-small { background: #e0f2fe; color: #0369a1; }
        .badge-medium { background: #ede9fe; color: var(--primary); }
        .badge-large { background: #dcfce7; color: var(--success); }

        /* Buttons */
        .btn { 
            padding: 12px 24px; 
            border-radius: var(--radius-md); 
            font-weight: 600; 
            cursor: pointer; 
            border: none; 
            transition: 0.3s; 
        }
        .btn-primary { 
            background: var(--primary); 
            color: white; 
        }
        .btn-primary:hover { 
            background: var(--primary-light); 
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3); 
        }
        .btn-ghost { 
            background: transparent; 
            color: var(--gray-600); 
            border: 1px solid var(--gray-200); 
        }
        .btn-ghost:hover { 
            background: var(--gray-100); 
        }

        .action-buttons { display: flex; gap: 10px; }
        .btn-action { 
            padding: 6px 12px; 
            border-radius: 6px; 
            font-size: 0.85rem; 
            cursor: pointer; 
            border: none; 
            background: none; 
            color: var(--gray-600); 
        }
        .btn-edit:hover { color: var(--primary); }
        .btn-delete:hover { color: var(--danger); }
        .btn-status:hover { color: var(--warning); }

        /* Multi-Step Modal */
        .modal { 
            display: none; 
            position: fixed; 
            inset: 0; 
            background: rgba(15, 23, 42, 0.6); 
            backdrop-filter: blur(4px); 
            align-items: center; 
            justify-content: center; 
            z-index: 1000; 
        }
        .modal-content { 
            background: white; 
            width: 600px; 
            max-width: 90%; 
            max-height: 90vh;
            overflow-y: auto;
            padding: 2.5rem; 
            border-radius: var(--radius-lg); 
            position: relative;
            animation: slideUp 0.3s ease; 
        }
        @keyframes slideUp { 
            from { transform: translateY(20px); opacity: 0; } 
            to { transform: translateY(0); opacity: 1; } 
        }
        
        .step-indicator { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 2rem; 
            position: relative;
        }
        .step { 
            flex: 1; 
            height: 4px; 
            background: var(--gray-200); 
            margin: 0 5px; 
            border-radius: 2px; 
            position: relative; 
        }
        .step.active { background: var(--primary); }
        .step-label { 
            position: absolute; 
            top: 10px; 
            font-size: 0.7rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            color: var(--gray-600); 
            width: 100px; 
            text-align: center;
            left: 50%;
            transform: translateX(-50%);
        }

        .form-step { display: none; }
        .form-step.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        input, select, textarea { 
            width: 100%; 
            padding: 12px; 
            margin-bottom: 15px; 
            border: 1px solid var(--gray-200); 
            border-radius: 8px; 
            outline: none; 
            font-size: 0.95rem;
        }
        input:focus, select:focus, textarea:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); 
        }
        
        .review-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 8px 0; 
            border-bottom: 1px dashed var(--gray-200); 
            font-size: 0.9rem; 
        }
        .review-row span:first-child { color: var(--gray-600); font-weight: 600; }

        /* ================= MODAL CLOSE BUTTON ================= */
        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #f1f5f9;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.25s ease;
        }

        .modal-close:hover {
            background: #fee2e2;
            color: #b91c1c;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            font-size: 0.95rem;
        }

        .company-info {
            font-size: 0.85rem;
            color: var(--gray-600);
            margin-top: 4px;
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
            <a href="companies.php" class="nav-link active">
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
                <i class="fas fa-check-circle"></i> Company added successfully!
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Company deleted successfully!
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <header>
            <div>
                <h1>Company Directory</h1>
                <p>Maintain complete company profiles</p>
            </div>
            <button class="btn btn-primary" onclick="openWizard()">
                <i class="fas fa-plus"></i> Add Company
            </button>
        </header>

        <section class="content-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3>All Companies</h3>
                <input type="text" placeholder="Search companies..." class="search-bar" id="searchInput">
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Industry</th>
                        <th>Size</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="companyTableBody">
                    <?php foreach ($companies as $company): ?>
                    <tr>
                        <td>
                            <b><?php echo htmlspecialchars($company['company_name']); ?></b><br>
                            <span class="company-info">Code: <?php echo htmlspecialchars($company['company_code']); ?></span><br>
                            <span class="company-info">Added: <?php echo date('M d, Y', strtotime($company['created_at'])); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($company['industry'] ?? 'Not specified'); ?></td>
                        <td>
                            <?php if ($company['company_size']): ?>
                            <span class="badge badge-<?php echo strtolower($company['company_size']); ?>">
                                <?php echo htmlspecialchars($company['company_size']); ?>
                            </span>
                            <?php else: ?>
                            <span style="color: var(--gray-600);">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($company['city'] && $company['state']): ?>
                                <?php echo htmlspecialchars($company['city']); ?>, <?php echo htmlspecialchars($company['state']); ?>
                            <?php else: ?>
                                <span style="color: var(--gray-600);">Not specified</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $company['status']; ?>">
                                <?php echo ucfirst($company['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($company['status'] == 'active'): ?>
                                    <a href="companies.php?toggle_status=suspend&id=<?php echo $company['id']; ?>" 
                                       class="btn-action btn-status" title="Suspend Company">
                                        <i class="fas fa-pause"></i>
                                    </a>
                                <?php elseif ($company['status'] == 'suspended'): ?>
                                    <a href="companies.php?toggle_status=activate&id=<?php echo $company['id']; ?>" 
                                       class="btn-action btn-status" title="Activate Company">
                                        <i class="fas fa-play"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="#" 
                                   onclick="confirmDelete(<?php echo $company['id']; ?>, '<?php echo addslashes($company['company_name']); ?>')" 
                                   class="btn-action btn-delete" title="Delete Company">
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

<div class="modal" id="wizardModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeWizard()">✕</button>
        <div class="step-indicator">
            <div class="step active" id="s1"><span class="step-label">Basic Details</span></div>
            <div class="step" id="s2"><span class="step-label">Contact Info</span></div>
            <div class="step" id="s3"><span class="step-label">Final Review</span></div>
        </div>

        <form method="POST" action="companies.php" id="onboardingForm">
            <div class="form-step active" id="step1">
                <h2 style="margin-bottom: 15px;">Basic Company Details</h2>
                
                <input type="text" name="company_name" id="b_name" placeholder="e.g. GearGuard Technologies Pvt Ltd" required>
                
                <input type="text" id="b_code" placeholder="e.g. GG-IND-001" disabled style="background: var(--gray-100); color: var(--gray-600);">
                <small style="color: var(--gray-600); font-size: 0.8rem; margin-top: -10px; margin-bottom: 15px; display: block;">
                    Company code will be generated automatically
                </small>
                
                <select name="industry" id="b_industry" required>
                    <option value="">Select Industry</option>
                    <option value="Technology">Technology</option>
                    <option value="Manufacturing">Manufacturing</option>
                    <option value="Healthcare">Healthcare</option>
                    <option value="Finance">Finance</option>
                    <option value="Retail">Retail</option>
                    <option value="Education">Education</option>
                    <option value="Logistics">Logistics</option>
                    <option value="Construction">Construction</option>
                    <option value="Real Estate">Real Estate</option>
                    <option value="Hospitality">Hospitality</option>
                </select>

                <select name="company_size" id="b_size" required>
                    <option value="">Select Company Size</option>
                    <option value="Small">Small (1-50 employees)</option>
                    <option value="Medium">Medium (51-200 employees)</option>
                    <option value="Large">Large (201+ employees)</option>
                </select>

                <textarea name="description" id="b_desc" placeholder="Brief description of company (optional)" rows="3"></textarea>

                <div style="display:flex; justify-content:flex-end;">
                    <button type="button" class="btn btn-primary" onclick="nextStep(2)">
                        Next: Contact Details →
                    </button>
                </div>
            </div>

            <div class="form-step" id="step2">
                <h2 style="margin-bottom: 15px;">Company Contact Details</h2>

                <input type="email" name="email" id="c_email" placeholder="admin@company.com" required>

                <input type="text" name="phone" id="c_phone" placeholder="+91 98765 43210" required>

                <input type="text" name="website" id="c_web" placeholder="https://www.company.com">

                <textarea name="address" id="c_address" placeholder="Street, Area, Landmark" rows="2" required></textarea>

                <select name="state" id="state" required>
                    <option value="">Select State</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option>
                    <?php endforeach; ?>
                    <option value="Other">Other</option>
                </select>

                <select name="city" id="city" required>
                    <option value="">Select City</option>
                </select>

                <div style="display:flex; justify-content:space-between;">
                    <button type="button" class="btn btn-ghost" onclick="nextStep(1)">← Back</button>
                    <button type="button" class="btn btn-primary" onclick="generateReview()">
                        Next: Review →
                    </button>
                </div>
            </div>

            <div class="form-step" id="step3">
                <h2 style="margin-bottom: 15px;">Final Review</h2>
                <div id="reviewContent" style="background:var(--gray-100); padding:1.5rem; border-radius:8px; margin-bottom:1.5rem;">
                    <!-- Review content will be generated here -->
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <button type="button" class="btn btn-ghost" onclick="nextStep(2)">← Edit Details</button>
                    <button type="submit" name="add_company" class="btn btn-primary" style="background:var(--success)">
                        <i class="fas fa-check"></i> Submit & Onboard
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Cities data for 12 Indian states
    const citiesByState = {
        'Gujarat': ["Ahmedabad", "Surat", "Vadodara", "Rajkot", "Gandhinagar", "Bhavnagar", "Jamnagar"],
        'Maharashtra': ["Mumbai", "Pune", "Nagpur", "Nashik", "Aurangabad", "Solapur", "Amravati"],
        'Karnataka': ["Bengaluru", "Mysuru", "Hubli", "Mangalore", "Belgaum", "Davanagere"],
        'Delhi': ["New Delhi", "Dwarka", "Noida", "Gurgaon", "Faridabad"],
        'Tamil Nadu': ["Chennai", "Coimbatore", "Madurai", "Salem", "Tiruchirappalli"],
        'Uttar Pradesh': ["Lucknow", "Kanpur", "Ghaziabad", "Agra", "Varanasi", "Meerut"],
        'West Bengal': ["Kolkata", "Howrah", "Durgapur", "Asansol", "Siliguri"],
        'Rajasthan': ["Jaipur", "Jodhpur", "Udaipur", "Kota", "Ajmer", "Bikaner"],
        'Telangana': ["Hyderabad", "Warangal", "Nizamabad", "Khammam", "Karimnagar"],
        'Andhra Pradesh': ["Visakhapatnam", "Vijayawada", "Guntur", "Nellore", "Kurnool"],
        'Kerala': ["Thiruvananthapuram", "Kochi", "Kozhikode", "Thrissur", "Malappuram"],
        'Madhya Pradesh': ["Bhopal", "Indore", "Gwalior", "Jabalpur", "Ujjain"],
        'Other': ["Other"]
    };

    // Populate cities based on state selection
    document.getElementById("state").addEventListener("change", function() {
        const citySel = document.getElementById("city");
        citySel.innerHTML = '<option value="">Select City</option>';
        
        if (this.value === "Other") {
            citySel.innerHTML += '<option value="Other">Other (please specify)</option>';
            citySel.disabled = false;
        } else if (citiesByState[this.value]) {
            citiesByState[this.value].forEach(ct => {
                citySel.innerHTML += `<option value="${ct}">${ct}</option>`;
            });
            citySel.disabled = false;
        } else {
            citySel.innerHTML += '<option value="">No cities available</option>';
            citySel.disabled = true;
        }
    });

    // Multi-step form functions
    function openWizard() { 
        document.getElementById('wizardModal').style.display = 'flex'; 
        nextStep(1);
        // Reset form
        document.getElementById('onboardingForm').reset();
        // Generate preview code
        document.getElementById('b_code').value = 'GG' + Math.random().toString(36).substr(2, 6).toUpperCase();
    }
    
    function closeWizard() { 
        document.getElementById('wizardModal').style.display = 'none'; 
    }
    
    function nextStep(step) {
        // Hide all steps
        document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        
        // Show current step
        document.getElementById('step' + step).classList.add('active');
        
        // Update step indicator
        for(let i = 1; i <= step; i++) {
            document.getElementById('s' + i).classList.add('active');
        }
    }

    function generateReview() {
        const content = document.getElementById('reviewContent');
        const state = document.getElementById('state').value;
        const city = document.getElementById('city').value;
        
        content.innerHTML = `
            <p style="font-weight:700; color:var(--primary); margin-bottom:10px;">Company Details:</p>
            <div class="review-row"><span>Name:</span><span>${document.getElementById('b_name').value}</span></div>
            <div class="review-row"><span>Industry:</span><span>${document.getElementById('b_industry').value}</span></div>
            <div class="review-row"><span>Company Size:</span><span>${document.getElementById('b_size').value}</span></div>
            ${document.getElementById('b_desc').value ? `<div class="review-row"><span>Description:</span><span>${document.getElementById('b_desc').value.substring(0, 50)}...</span></div>` : ''}
            
            <p style="font-weight:700; color:var(--primary); margin:15px 0 10px 0;">Contact Information:</p>
            <div class="review-row"><span>Email:</span><span>${document.getElementById('c_email').value}</span></div>
            <div class="review-row"><span>Phone:</span><span>${document.getElementById('c_phone').value}</span></div>
            ${document.getElementById('c_web').value ? `<div class="review-row"><span>Website:</span><span>${document.getElementById('c_web').value}</span></div>` : ''}
            <div class="review-row"><span>Address:</span><span>${document.getElementById('c_address').value.substring(0, 50)}...</span></div>
            <div class="review-row"><span>Location:</span><span>${city}, ${state}</span></div>
        `;
        
        nextStep(3);
    }

    // Form submission
    document.getElementById('onboardingForm').onsubmit = function(e) {
        // Form will be submitted normally via PHP
        // Add loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitBtn.disabled = true;
    };

    function confirmDelete(id, name) {
        if (confirm(`Are you sure you want to delete company "${name}"? This will also delete all associated contacts. This action cannot be undone.`)) {
            window.location.href = `companies.php?delete=1&id=${id}`;
        }
    }

    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#companyTableBody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    // Close modal on ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeWizard();
        }
    });

    // Close modal when clicking outside
    document.getElementById('wizardModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeWizard();
        }
    });
</script>

</body>
</html>