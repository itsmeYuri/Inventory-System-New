# Sidebar Template for All Pages

This document contains the complete sidebar template that needs to be applied to all pages.

## CSS (Add to <head> section)

[Copy the CSS from inventory_management.html lines 12-322]

## HTML Sidebar Structure

Replace the entire sidebar section with this structure, updating the active menu item class based on the current page:

```html
<!-- Sidebar Overlay -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 hidden lg:hidden"></div>

<!-- Sidebar -->
<aside id="sidebar" class="sidebar-collapsible fixed left-0 top-0 h-full bg-white dark:bg-gray-900 shadow-xl border-r border-border-light dark:border-gray-700 z-50 transform -translate-x-full lg:translate-x-0 transition-all duration-300">
    <div class="sidebar-container">
        <!-- Top Section: Logo + User Profile -->
        <div class="sidebar-top">
            <!-- Logo Section -->
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="w-10 h-10 bg-primary-100 dark:bg-primary-900 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-pills text-primary-600 dark:text-primary-400 text-xl"></i>
                    </div>
                    <div class="sidebar-logo-text">
                        <h2 class="text-lg font-semibold text-text-primary dark:text-gray-100 whitespace-nowrap">PHARMACY</h2>
                        <p class="text-xs text-text-tertiary dark:text-gray-400 whitespace-nowrap">Inventory System</p>
                    </div>
                </div>
                <button id="closeSidebar" class="sidebar-close-btn lg:hidden text-text-tertiary hover:text-text-secondary dark:text-gray-400 dark:hover:text-gray-200 transition-colors duration-200">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <!-- User Profile Section -->
            <div class="sidebar-user">
                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=2940&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="User Avatar" class="sidebar-avatar" onerror="this.src='https://images.pexels.com/photos/220453/pexels-photo-220453.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2'; this.onerror=null;" />
                <div class="sidebar-user-info">
                    <p class="text-sm font-medium text-text-primary dark:text-gray-100 truncate" id="userDisplayName">User</p>
                    <p class="text-xs text-text-tertiary dark:text-gray-400 truncate" id="userDisplayEmail">user@example.com</p>
                </div>
            </div>
        </div>

        <!-- Middle Section: Navigation Links (Scrollable) -->
        <nav class="sidebar-nav">
            <ul class="sidebar-menu">
                <li>
                    <a href="dashboard.html" class="sidebar-menu-item [ADD sidebar-menu-item-active IF ON DASHBOARD]">
                        <i class="fas fa-chart-pie sidebar-icon"></i>
                        <span class="sidebar-menu-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="orders_management.html" class="sidebar-menu-item [ADD sidebar-menu-item-active IF ON ORDERS]">
                        <i class="fas fa-prescription-bottle sidebar-icon"></i>
                        <span class="sidebar-menu-text">Orders</span>
                    </a>
                </li>
                <li>
                    <a href="batches_management.html" class="sidebar-menu-item [ADD sidebar-menu-item-active IF ON BATCHES]">
                        <i class="fas fa-boxes sidebar-icon"></i>
                        <span class="sidebar-menu-text">Batches</span>
                    </a>
                </li>
                <li>
                    <a href="inventory_management.html" class="sidebar-menu-item [ADD sidebar-menu-item-active IF ON INVENTORY]">
                        <i class="fas fa-capsules sidebar-icon"></i>
                        <span class="sidebar-menu-text">Inventory List</span>
                    </a>
                </li>
                <li>
                    <a href="reports_analytics.html" class="sidebar-menu-item [ADD sidebar-menu-item-active IF ON REPORTS]">
                        <i class="fas fa-chart-bar sidebar-icon"></i>
                        <span class="sidebar-menu-text">Reports</span>
                    </a>
                </li>
                <li>
                    <a href="suppliers_management.html" class="sidebar-menu-item [ADD sidebar-menu-item-active IF ON SUPPLIERS]">
                        <i class="fas fa-truck-medical sidebar-icon"></i>
                        <span class="sidebar-menu-text">Suppliers</span>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" onclick="openSettings()" class="sidebar-menu-item">
                        <i class="fas fa-cog sidebar-icon"></i>
                        <span class="sidebar-menu-text">Settings</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Bottom Section: Theme Toggle + Logout -->
        <div class="sidebar-bottom">
            <div class="sidebar-theme-toggle">
                <span class="sidebar-theme-label">Dark Mode</span>
                <button id="themeToggle" class="relative inline-flex h-6 w-11 items-center rounded-full bg-border-light dark:bg-primary transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-200">
                    <span id="themeToggleButton" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-200 translate-x-1 dark:translate-x-6"></span>
                </button>
            </div>
            <button id="logoutBtn" class="sidebar-logout-btn">
                <i class="fas fa-sign-out-alt sidebar-icon"></i>
                <span class="sidebar-menu-text">Logout</span>
            </button>
        </div>
    </div>
</aside>

<!-- Main Content -->
<div class="main-content-wrapper min-h-screen bg-background dark:bg-gray-900">
```

## Settings Modal (Add before closing </body> tag)

```html
<!-- Settings Modal -->
<div id="settingsModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl max-w-md w-full animation-slide-up">
        <div class="flex items-center justify-between p-6 border-b border-border-light dark:border-gray-700">
            <h2 class="text-xl font-semibold text-text-primary dark:text-gray-100">Settings</h2>
            <button id="closeSettingsModal" class="text-text-tertiary dark:text-gray-400 hover:text-text-secondary dark:hover:text-gray-200 transition-colors duration-200">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <a href="user_management.html" class="flex items-center space-x-4 p-4 bg-surface-hover dark:bg-gray-800 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-all duration-200 cursor-pointer group">
                <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900 rounded-xl flex items-center justify-center group-hover:bg-primary-200 dark:group-hover:bg-primary-800 transition-colors duration-200">
                    <i class="fas fa-users text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-base font-semibold text-text-primary dark:text-gray-100">User Management</h3>
                    <p class="text-sm text-text-secondary dark:text-gray-400">Manage system users and permissions</p>
                </div>
                <i class="fas fa-chevron-right text-text-tertiary dark:text-gray-400"></i>
            </a>
            <a href="archive.html" class="flex items-center space-x-4 p-4 bg-surface-hover dark:bg-gray-800 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-all duration-200 cursor-pointer group">
                <div class="w-12 h-12 bg-warning-100 dark:bg-warning-900 rounded-xl flex items-center justify-center group-hover:bg-warning-200 dark:group-hover:bg-warning-800 transition-colors duration-200">
                    <i class="fas fa-archive text-warning-600 dark:text-warning-400 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-base font-semibold text-text-primary dark:text-gray-100">Archive</h3>
                    <p class="text-sm text-text-secondary dark:text-gray-400">View archived items, orders, and medicines</p>
                </div>
                <i class="fas fa-chevron-right text-text-tertiary dark:text-gray-400"></i>
            </a>
        </div>
    </div>
</div>
```

## JavaScript (Add to DOMContentLoaded or script section)

```javascript
// Settings Modal
function openSettings() {
    document.getElementById('settingsModal').classList.remove('hidden');
}
window.openSettings = openSettings;

document.getElementById('closeSettingsModal')?.addEventListener('click', () => {
    document.getElementById('settingsModal').classList.add('hidden');
});

// Close modal on backdrop click
document.getElementById('settingsModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'settingsModal') {
        document.getElementById('settingsModal').classList.add('hidden');
    }
});
```

## Pages to Update

1. ✅ inventory_management.html - DONE
2. dashboard.html
3. batches_management.html
4. orders_management.html
5. reports_analytics.html
6. suppliers_management.html
7. archive.html

## Notes

- Remove "User Management" and "Archive" from sidebar menu
- Add them to Settings modal instead
- Update main content wrapper from `lg:ml-64` to `main-content-wrapper`
- Set active menu item class (`sidebar-menu-item-active`) based on current page

