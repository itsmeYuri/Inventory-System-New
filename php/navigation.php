<?php
/**
 * Reusable Navigation Component
 * 
 * This file contains the navigation sidebar that adapts based on user role.
 * Include this file in your pages to get role-based navigation.
 * 
 * Usage:
 *   require_once __DIR__ . '/navigation.php';
 *   render_navigation();
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
if (!$isLoggedIn) {
    header('Location: ../pages/login.html');
    exit;
}

$user_role = $_SESSION['role'] ?? null;
$user_name = $_SESSION['full_name'] ?? 'User';
$user_email = $_SESSION['user_email'] ?? $_SESSION['email'] ?? 'user@example.com';
$current_page = basename($_SERVER['PHP_SELF']);

/**
 * Check if a menu item should be visible for current role
 */
function can_see_menu_item($item_roles) {
    global $user_role;
    
    // Admin can see everything
    if ($user_role === 'admin') {
        return true;
    }
    
    // Check if user role is in allowed roles
    return in_array($user_role, $item_roles);
}

// Make function available globally
if (!function_exists('can_see_menu_item')) {
    function can_see_menu_item($item_roles) {
        global $user_role;
        if ($user_role === 'admin') {
            return true;
        }
        return in_array($user_role, $item_roles);
    }
}

/**
 * Get active class for menu item
 */
function get_active_class($page_name) {
    global $current_page;
    return ($current_page === $page_name) ? 'text-white bg-primary' : 'text-text-secondary hover:text-text-primary hover:bg-surface-hover';
}

/**
 * Render navigation sidebar
 */
function render_navigation() {
    global $user_role, $user_name, $user_email;
    ?>
    <!-- Sidebar Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 hidden lg:hidden"></div>
    
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed left-0 top-0 h-full w-64 bg-white dark:bg-gray-900 shadow-xl border-r border-border-light dark:border-gray-700 z-50 transform -translate-x-full lg:translate-x-0 transition-all duration-300">
        <!-- Sidebar Header -->
        <div class="flex items-center justify-between p-6 border-b border-border-light dark:border-gray-700">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-primary-100 dark:bg-primary-900 rounded-xl flex items-center justify-center">
                    <i class="fas fa-pills text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-text-primary dark:text-gray-100">PHARMACY</h2>
                    <p class="text-xs text-text-tertiary dark:text-gray-400">Inventory System</p>
                </div>
            </div>
            <button id="closeSidebar" class="lg:hidden text-text-tertiary hover:text-text-secondary dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-200">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- User Profile Section -->
        <div class="p-6 border-b border-border-light dark:border-gray-700">
            <div class="flex items-center space-x-3">
                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=2940&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" 
                     alt="User Avatar" 
                     class="w-12 h-12 rounded-full object-cover border-2 border-primary-200 dark:border-primary-700" 
                     onerror="this.src='https://images.pexels.com/photos/220453/pexels-photo-220453.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2'; this.onerror=null;" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-text-primary dark:text-gray-100 truncate" id="userDisplayName"><?php echo htmlspecialchars($user_name); ?></p>
                    <p class="text-xs text-text-tertiary dark:text-gray-400 truncate" id="userDisplayEmail"><?php echo htmlspecialchars($user_email); ?></p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 p-4">
            <ul class="space-y-2">
                <li>
                    <a href="dashboard.html" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?php echo get_active_class('dashboard.html'); ?> rounded-xl transition-all duration-200">
                        <i class="fas fa-chart-pie w-5"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="user_management.html" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?php echo get_active_class('user_management.html'); ?> rounded-xl transition-all duration-200">
                        <i class="fas fa-users w-5"></i>
                        <span>User Management</span>
                    </a>
                </li>

                <li>
                    <a href="medicine.php" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?php echo get_active_class('medicine.php'); ?> rounded-xl transition-all duration-200">
                        <i class="fas fa-capsules w-5"></i>
                        <span>Medicine Inventory</span>
                    </a>
                </li>

                <li>
                    <a href="suppliers_management.php" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?php echo get_active_class('suppliers_management.php'); ?> rounded-xl transition-all duration-200">
                        <i class="fas fa-truck-medical w-5"></i>
                        <span>Suppliers</span>
                    </a>
                </li>

                <li>
                    <a href="order.php" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?php echo get_active_class('order.php'); ?> rounded-xl transition-all duration-200">
                        <i class="fas fa-prescription-bottle w-5"></i>
                        <span>Orders</span>
                    </a>
                </li>

                <li>
                    <a href="report.php" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium <?php echo get_active_class('report.php'); ?> rounded-xl transition-all duration-200">
                        <i class="fas fa-chart-bar w-5"></i>
                        <span>Reports</span>
                    </a>
                </li>

                <li>
                    <a href="javascript:void(0)" onclick="openSettings()" class="flex items-center space-x-3 px-4 py-3 text-sm font-medium text-text-secondary hover:text-text-primary hover:bg-surface-hover rounded-xl transition-all duration-200">
                        <i class="fas fa-cog w-5"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Theme Toggle & Logout -->
        <div class="p-4 border-t border-border-light dark:border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <span class="text-sm text-text-tertiary dark:text-gray-400">Theme</span>
                <button id="themeToggle" class="relative inline-flex h-6 w-11 items-center rounded-full bg-gray-200 dark:bg-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                    <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform translate-x-1 dark:translate-x-6"></span>
                </button>
            </div>
            <button id="logoutBtn" class="w-full flex items-center justify-center space-x-2 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-colors">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
        </div>
    </aside>

    <script>
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const openSidebarBtn = document.getElementById('openSidebar');
            const closeSidebarBtn = document.getElementById('closeSidebar');

            if (openSidebarBtn) {
                openSidebarBtn.addEventListener('click', function() {
                    sidebar.classList.remove('-translate-x-full');
                    sidebarOverlay.classList.remove('hidden');
                });
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            }

            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', closeSidebar);
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }

            // Logout functionality
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to logout?')) {
                        fetch('../php/logout.php', {
                            method: 'GET',
                            credentials: 'same-origin'
                        }).then(() => {
                            localStorage.clear();
                            window.location.href = 'login.html';
                        }).catch(() => {
                            localStorage.clear();
                            window.location.href = 'login.html';
                        });
                    }
                });
            }
        });
    </script>
    <?php
}
?>

