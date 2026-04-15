<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed layout-navbar-fixed" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title ?? "Janet's Quality Catering"; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="static/favicon.ico">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">
    
    <!-- Core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            /* Janet's Catering Lavender Theme */
            --bs-primary: #9370DB;
            --bs-primary-dark: #7B5FC7;
            --bs-secondary: #8592a3;
            --bs-success: #71dd37;
            --bs-info: #03c3ec;
            --bs-warning: #ffab00;
            --bs-danger: #ff3e1d;
            --bs-light: #fcfdfd;
            --bs-dark: #233446;
            
            /* Layout */
            --layout-menu-width: 260px;
            --layout-navbar-height: 64px;
            
            /* Light Theme - Lavender */
            --body-bg: #F0E6FA;
            --card-bg: #fff;
            --card-border: #D8C8E8;
            --heading-color: #3D3D5C;
            --body-color: #5A5A7A;
            --menu-bg: #fff;
            --menu-item-color: #5A5A7A;
            --menu-item-hover-bg: rgba(147, 112, 219, 0.1);
            --menu-item-active-color: #9370DB;
            --border-color: #D8C8E8;
        }

        [data-theme="dark"] {
            /* Dark Theme */
            --body-bg: #1A1A2E;
            --card-bg: #252540;
            --card-border: #3D3D5C;
            --heading-color: #E8E6F0;
            --body-color: #B8B8D0;
            --menu-bg: #252540;
            --menu-item-color: #B8B8D0;
            --menu-item-hover-bg: rgba(147, 112, 219, 0.2);
            --menu-item-active-color: #B896FF;
            --border-color: #3D3D5C;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: 'Public Sans', sans-serif;
            background-color: var(--body-bg);
            color: var(--body-color);
            min-height: 100vh;
            transition: background-color 0.3s, color 0.3s;
        }

        /* Dark mode text colors */
        [data-theme="dark"] .text-dark,
        [data-theme="dark"] h1,
        [data-theme="dark"] h2,
        [data-theme="dark"] h3,
        [data-theme="dark"] h4,
        [data-theme="dark"] h5,
        [data-theme="dark"] h6,
        [data-theme="dark"] .card-title,
        [data-theme="dark"] .page-title,
        [data-theme="dark"] strong,
        [data-theme="dark"] label,
        [data-theme="dark"] .form-label,
        [data-theme="dark"] th,
        [data-theme="dark"] .modal-title {
            color: var(--heading-color) !important;
        }

        [data-theme="dark"] p,
        [data-theme="dark"] span,
        [data-theme="dark"] td,
        [data-theme="dark"] .text-muted {
            color: var(--body-color) !important;
        }

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background: var(--card-bg);
            border-color: var(--border-color);
            color: var(--heading-color);
        }

        [data-theme="dark"] .form-control::placeholder {
            color: var(--body-color);
        }

        [data-theme="dark"] .table {
            color: var(--body-color);
        }

        [data-theme="dark"] .table thead th {
            background: var(--body-bg);
            color: var(--heading-color);
        }

        [data-theme="dark"] .dropdown-menu {
            background: var(--card-bg);
            border-color: var(--border-color);
        }

        [data-theme="dark"] .dropdown-item {
            color: var(--body-color);
        }

        [data-theme="dark"] .dropdown-item:hover {
            background: var(--menu-item-hover-bg);
        }

        /* ===================== Layout Wrapper ===================== */
        .layout-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* ===================== Sidebar Menu ===================== */
        .layout-menu {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--layout-menu-width);
            height: 100%;
            background: var(--menu-bg);
            border-right: 1px solid var(--border-color);
            z-index: 1040;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .app-brand {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            height: auto;
            border-bottom: 1px solid var(--border-color);
        }

        .app-brand-logo {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            overflow: hidden;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .app-brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .app-brand-text {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--heading-color);
            text-decoration: none;
            line-height: 1.3;
        }

        .app-brand-text span {
            display: block;
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--bs-primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .menu-inner {
            flex: 1;
            overflow-y: auto;
            padding: 0 0 20px;
        }

        .menu-inner::-webkit-scrollbar {
            width: 5px;
        }

        .menu-inner::-webkit-scrollbar-thumb {
            background: var(--bs-secondary);
            border-radius: 10px;
        }

        .menu-header {
            padding: 24px 24px 8px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--bs-secondary);
        }

        .menu-item {
            padding: 0 16px;
            margin-bottom: 2px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            border-radius: 8px;
            color: var(--menu-item-color);
            text-decoration: none;
            font-size: 0.9375rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .menu-link:hover {
            background: var(--menu-item-hover-bg);
            color: var(--menu-item-active-color);
        }

        .menu-link.active {
            background: linear-gradient(135deg, var(--bs-primary) 0%, #8B5CF6 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(147, 112, 219, 0.4);
        }

        .menu-link i {
            font-size: 1.25rem;
            margin-right: 12px;
            width: 22px;
            text-align: center;
        }

        .menu-divider {
            height: 1px;
            background: var(--border-color);
            margin: 16px 24px;
        }

        .menu-badge {
            font-size: 0.6875rem;
            padding: 0.25em 0.5em;
            background: var(--bs-danger);
            color: #fff;
            border-radius: 4px;
            margin-left: auto;
            font-weight: 600;
        }

        /* User Info in Sidebar */
        .sidebar-user {
            padding: 16px 20px;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
        }

        .sidebar-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--bs-primary) 0%, #8B5CF6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            overflow: hidden;
            flex-shrink: 0;
        }

        .sidebar-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-user-details {
            flex: 1;
            overflow: hidden;
        }

        .sidebar-user-name {
            font-weight: 600;
            color: var(--heading-color);
            font-size: 0.875rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            font-size: 0.75rem;
            color: var(--bs-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ===================== Content Wrapper ===================== */
        .layout-page {
            flex: 1;
            margin-left: var(--layout-menu-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }

        /* ===================== Navbar ===================== */
        .layout-navbar {
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            height: var(--layout-navbar-height);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            z-index: 1030;
            display: flex;
            align-items: center;
            padding: 0 24px;
        }

        .navbar-nav-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .menu-toggle {
            display: none;
            width: 36px;
            height: 36px;
            border: none;
            background: transparent;
            cursor: pointer;
            color: var(--heading-color);
            font-size: 1.25rem;
        }

        .search-container {
            position: relative;
        }

        .search-input {
            width: 200px;
            padding: 8px 16px 8px 40px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--body-bg);
            color: var(--body-color);
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--bs-primary);
            width: 280px;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--bs-secondary);
        }

        .navbar-nav-right {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
        }

        .nav-item-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: none;
            background: transparent;
            color: var(--body-color);
            font-size: 1.25rem;
            cursor: pointer;
            position: relative;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-item-icon:hover {
            background: var(--menu-item-hover-bg);
            color: var(--bs-primary);
        }

        .nav-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 18px;
            height: 18px;
            background: var(--bs-danger);
            color: #fff;
            border-radius: 50%;
            font-size: 0.625rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        /* User Dropdown */
        .nav-user {
            position: relative;
        }

        .nav-user-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 12px 6px 6px;
            border: none;
            background: transparent;
            cursor: pointer;
            border-radius: 50px;
            transition: all 0.2s;
        }

        .nav-user-toggle:hover {
            background: var(--menu-item-hover-bg);
        }

        .nav-user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--bs-primary) 0%, #8B5CF6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 0.875rem;
            overflow: hidden;
        }

        .nav-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .nav-user-info {
            text-align: left;
            display: none;
        }

        @media (min-width: 992px) {
            .nav-user-info {
                display: block;
            }
        }

        .nav-user-name {
            font-weight: 600;
            color: var(--heading-color);
            font-size: 0.875rem;
        }

        .nav-user-role {
            font-size: 0.75rem;
            color: var(--bs-secondary);
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 200px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s;
            z-index: 1050;
        }

        .nav-user.show .user-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            color: var(--body-color);
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .dropdown-item:hover {
            background: var(--menu-item-hover-bg);
            color: var(--bs-primary);
        }

        .dropdown-item i {
            font-size: 1.125rem;
            width: 20px;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--border-color);
            margin: 8px 0;
        }

        .dropdown-item.text-danger {
            color: var(--bs-danger);
        }

        .dropdown-item.text-danger:hover {
            background: rgba(255, 62, 29, 0.08);
        }

        /* ===================== Content ===================== */
        .content-wrapper {
            flex: 1;
            padding: 24px;
        }

        /* ===================== Cards ===================== */
        .card {
            background: var(--card-bg);
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(147, 112, 219, 0.08);
            margin-bottom: 24px;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--heading-color);
            margin: 0;
        }

        .card-body {
            padding: 24px;
        }

        /* ===================== Tables ===================== */
        .table {
            color: var(--body-color);
            margin-bottom: 0;
        }

        .table thead th {
            background: var(--body-bg);
            border-bottom: 1px solid var(--border-color);
            color: var(--heading-color);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 20px;
            font-weight: 600;
        }

        .table tbody td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background: var(--menu-item-hover-bg);
        }

        /* ===================== Buttons ===================== */
        .btn {
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9375rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--bs-primary) 0%, #8B5CF6 100%);
            border: none;
            color: #fff;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--bs-primary-dark) 0%, #7C3AED 100%);
            box-shadow: 0 4px 16px rgba(147, 112, 219, 0.4);
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--bs-success);
            border-color: var(--bs-success);
        }

        .btn-danger {
            background: var(--bs-danger);
            border-color: var(--bs-danger);
        }

        .btn-secondary {
            background: var(--bs-secondary);
            border-color: var(--bs-secondary);
        }

        .btn-label-primary {
            background: rgba(147, 112, 219, 0.15);
            color: var(--bs-primary);
            border: none;
        }

        .btn-label-primary:hover {
            background: var(--bs-primary);
            color: #fff;
        }

        .btn-label-danger {
            background: rgba(255, 62, 29, 0.15);
            color: var(--bs-danger);
            border: none;
        }

        .btn-label-danger:hover {
            background: var(--bs-danger);
            color: #fff;
        }

        .btn-icon {
            width: 38px;
            height: 38px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* ===================== Form Controls ===================== */
        .form-control,
        .form-select {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--heading-color);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.9375rem;
            transition: all 0.2s;
        }

        .form-control:focus,
        .form-select:focus {
            background: var(--card-bg);
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 3px rgba(147, 112, 219, 0.15);
            color: var(--heading-color);
        }

        .form-label {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--heading-color);
            margin-bottom: 6px;
        }

        /* ===================== Badges ===================== */
        .badge {
            font-weight: 500;
            padding: 0.45em 0.8em;
            font-size: 0.8125rem;
            border-radius: 6px;
        }

        .badge-primary { background: rgba(147, 112, 219, 0.15); color: var(--bs-primary); }
        .badge-success { background: rgba(113, 221, 55, 0.15); color: var(--bs-success); }
        .badge-warning { background: rgba(255, 171, 0, 0.15); color: var(--bs-warning); }
        .badge-danger { background: rgba(255, 62, 29, 0.15); color: var(--bs-danger); }
        .badge-info { background: rgba(3, 195, 236, 0.15); color: var(--bs-info); }
        .badge-secondary { background: rgba(133, 146, 163, 0.15); color: var(--bs-secondary); }

        /* ===================== Status Pills ===================== */
        .status-pill {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .status-confirmed {
            background: rgba(113, 221, 55, 0.15);
            color: var(--bs-success);
        }

        .status-pending {
            background: rgba(255, 171, 0, 0.15);
            color: var(--bs-warning);
        }

        .status-cancelled {
            background: rgba(255, 62, 29, 0.15);
            color: var(--bs-danger);
        }

        .status-completed {
            background: rgba(147, 112, 219, 0.15);
            color: var(--bs-primary);
        }

        /* ===================== Flash Messages ===================== */
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: none;
        }

        .alert-success {
            background: rgba(113, 221, 55, 0.15);
            color: var(--bs-success);
        }

        .alert-danger {
            background: rgba(255, 62, 29, 0.15);
            color: var(--bs-danger);
        }

        .alert-warning {
            background: rgba(255, 171, 0, 0.15);
            color: var(--bs-warning);
        }

        .alert-info {
            background: rgba(3, 195, 236, 0.15);
            color: var(--bs-info);
        }

        /* ===================== Modal ===================== */
        .modal-content {
            background: var(--card-bg);
            border: none;
            border-radius: 12px;
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 20px 24px;
        }

        .modal-title {
            font-weight: 600;
            color: var(--heading-color);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 16px 24px;
        }

        .btn-close {
            filter: var(--body-color);
        }

        [data-theme="dark"] .btn-close {
            filter: invert(1);
        }

        [data-theme="dark"] .modal-content {
            background: var(--card-bg);
        }

        /* ===================== Page Header ===================== */
        .page-header {
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 1.625rem;
            font-weight: 600;
            color: var(--heading-color);
            margin: 0;
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 8px 0 0;
        }

        .breadcrumb-item {
            font-size: 0.8125rem;
        }

        .breadcrumb-item a {
            color: var(--bs-primary);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: var(--bs-secondary);
        }

        /* ===================== Nav Tabs ===================== */
        .nav-tabs {
            border-bottom: 1px solid var(--border-color);
        }

        .nav-tabs .nav-link {
            color: var(--body-color);
            border: none;
            padding: 16px 24px;
        }

        .nav-tabs .nav-link:hover {
            border: none;
            color: var(--bs-primary);
        }

        .nav-tabs .nav-link.active {
            color: var(--bs-primary);
            background: transparent;
            border: none;
            border-bottom: 2px solid var(--bs-primary);
        }

        /* ===================== Responsive ===================== */
        @media (max-width: 1199px) {
            .layout-menu {
                transform: translateX(-100%);
            }

            .layout-menu.show {
                transform: translateX(0);
            }

            .layout-page {
                margin-left: 0;
            }

            .menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .layout-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1035;
            }

            .layout-overlay.show {
                display: block;
            }
        }

        @media (max-width: 576px) {
            .content-wrapper {
                padding: 16px;
            }

            .search-container {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="layout-wrapper">
        <!-- Overlay for mobile menu -->
        <div class="layout-overlay" onclick="toggleMenu()"></div>
        
        <!-- Sidebar Menu -->
        <aside class="layout-menu">
            <div class="app-brand">
                <div class="app-brand-logo">
                    <img src="static/images/logo.png" alt="Janet's Catering">
                </div>
                <a href="dashboard.php" class="app-brand-text">
                    Janet's Quality
                    <span>Catering Services</span>
                </a>
            </div>

            <div class="menu-inner">
                <div class="menu-header">Main</div>
                
                <div class="menu-item">
                    <a href="dashboard.php" class="menu-link <?php echo ($current_page === 'dashboard') ? 'active' : ''; ?>">
                        <i class="bx bx-home-circle"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="menu-header">Management</div>
                
                <div class="menu-item">
                    <a href="events.php" class="menu-link <?php echo ($current_page === 'events') ? 'active' : ''; ?>">
                        <i class="bx bx-calendar-event"></i>
                        <span>Events</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="inventory.php" class="menu-link <?php echo ($current_page === 'inventory') ? 'active' : ''; ?>">
                        <i class="bx bx-box"></i>
                        <span>Inventory</span>
                    </a>
                </div>

                <div class="menu-item">
                    <a href="categories.php" class="menu-link <?php echo ($current_page === 'categories') ? 'active' : ''; ?>">
                        <i class="bx bx-category"></i>
                        <span>Categories</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="backdrops.php" class="menu-link <?php echo ($current_page === 'backdrops') ? 'active' : ''; ?>">
                        <i class="bx bx-image"></i>
                        <span>Backdrops</span>
                    </a>
                </div>

                <?php if (isset($current_user) && $current_user['role'] === 'OWNER'): ?>
                <div class="menu-header">Reports</div>
                
                <div class="menu-item">
                    <a href="reports.php" class="menu-link <?php echo ($current_page === 'reports') ? 'active' : ''; ?>">
                        <i class="bx bx-file"></i>
                        <span>Reports</span>
                    </a>
                </div>
                <?php endif; ?>

                <div class="menu-divider"></div>

                <div class="menu-item">
                    <a href="profile.php" class="menu-link <?php echo ($current_page === 'profile') ? 'active' : ''; ?>">
                        <i class="bx bx-user-circle"></i>
                        <span>Profile</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="logout.php" class="menu-link text-danger">
                        <i class="bx bx-log-out"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>

            <!-- User Info -->
            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-avatar">
                        <?php 
                        if (isset($current_user) && !empty($current_user['profile_photo']) && file_exists($current_user['profile_photo'])): 
                        ?>
                            <img src="<?php echo htmlspecialchars($current_user['profile_photo']); ?>?t=<?php echo time(); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="sidebar-user-details">
                        <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                        <div class="sidebar-user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Layout Page -->
        <div class="layout-page">
            <!-- Navbar -->
            <nav class="layout-navbar">
                <div class="navbar-nav-left">
                    <button class="menu-toggle" onclick="toggleMenu()">
                        <i class="bx bx-menu"></i>
                    </button>
                    
                    <div class="search-container">
                        <i class="bx bx-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="Search...">
                    </div>
                </div>

                <div class="navbar-nav-right">
                    <!-- Theme Toggle -->
                    <button class="nav-item-icon" onclick="toggleTheme()" title="Toggle Theme">
                        <i class="bx bx-moon" id="themeIcon"></i>
                    </button>

                    <!-- Notifications -->
                    <button class="nav-item-icon" title="Notifications">
                        <i class="bx bx-bell"></i>
                        <span class="nav-badge">3</span>
                    </button>

                    <!-- User Dropdown -->
                    <div class="nav-user" id="userDropdown">
                        <button class="nav-user-toggle" onclick="toggleUserDropdown()">
                            <div class="nav-user-avatar">
                                <?php 
                                if (isset($current_user) && !empty($current_user['profile_photo']) && file_exists($current_user['profile_photo'])): 
                                ?>
                                    <img src="<?php echo htmlspecialchars($current_user['profile_photo']); ?>?t=<?php echo time(); ?>" alt="Profile">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="nav-user-info">
                                <div class="nav-user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                                <div class="nav-user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?></div>
                            </div>
                        </button>

                        <div class="user-dropdown">
                            <div class="dropdown-header">
                                <div class="nav-user-avatar">
                                    <?php 
                                    if (isset($current_user) && !empty($current_user['profile_photo']) && file_exists($current_user['profile_photo'])): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($current_user['profile_photo']); ?>?t=<?php echo time(); ?>" alt="Profile">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="nav-user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                                    <div class="nav-user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Admin'); ?></div>
                                </div>
                            </div>
                            <a href="profile.php" class="dropdown-item">
                                <i class="bx bx-user"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="profile.php?tab=security" class="dropdown-item">
                                <i class="bx bx-cog"></i>
                                <span>Settings</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item text-danger">
                                <i class="bx bx-power-off"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Content Wrapper -->
            <div class="content-wrapper">
                <?php 
                $flash = getFlash();
                if ($flash): 
                ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
