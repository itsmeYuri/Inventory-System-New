# Refactored Collapsible Sidebar - Complete Code Snippet

This document contains the complete refactored sidebar code that can be pasted into any page in your project.

## Features
- ✅ Collapses to 60px width by default
- ✅ Expands to 250px on hover
- ✅ All menu items remain visible and scrollable
- ✅ Logout button always stays at bottom
- ✅ Flexible column layout with flexbox
- ✅ No hardcoded margins
- ✅ Works on 1366x768 screens
- ✅ Modern, clean design

## CSS Styles (Add to `<head>` section)

```html
<style>
    /* Collapsible Sidebar Styles */
    .sidebar-collapsible {
        width: 60px;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
    }

    .sidebar-collapsible:hover {
        width: 250px;
    }

    .sidebar-collapsible:hover .sidebar-menu-text,
    .sidebar-collapsible:hover .sidebar-logo-text,
    .sidebar-collapsible:hover .sidebar-user-info,
    .sidebar-collapsible:hover .sidebar-theme-label {
        opacity: 1;
        visibility: visible;
    }

    .sidebar-container {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100vh;
        padding: 0;
    }

    /* Top Section */
    .sidebar-top {
        flex-shrink: 0;
        border-bottom: 1px solid rgba(229, 231, 235, 0.5);
    }

    .dark .sidebar-top {
        border-bottom-color: rgba(55, 65, 81, 0.5);
    }

    .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 12px;
    }

    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .sidebar-logo-text {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s ease;
        white-space: nowrap;
        overflow: hidden;
    }

    .sidebar-close-btn {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s ease;
    }

    .sidebar-collapsible:hover .sidebar-close-btn {
        opacity: 1;
        visibility: visible;
    }

    .sidebar-user {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 12px;
        border-bottom: 1px solid rgba(229, 231, 235, 0.5);
    }

    .dark .sidebar-user {
        border-bottom-color: rgba(55, 65, 81, 0.5);
    }

    .sidebar-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(59, 130, 246, 0.2);
        flex-shrink: 0;
    }

    .dark .sidebar-avatar {
        border-color: rgba(59, 130, 246, 0.3);
    }

    .sidebar-user-info {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s ease;
        min-width: 0;
        flex: 1;
    }

    /* Middle Section - Navigation (Scrollable) */
    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 8px 0;
        min-height: 0;
    }

    .sidebar-nav::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-nav::-webkit-scrollbar-track {
        background: transparent;
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(156, 163, 175, 0.3);
        border-radius: 2px;
    }

    .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: rgba(156, 163, 175, 0.5);
    }

    .dark .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(75, 85, 99, 0.3);
    }

    .dark .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: rgba(75, 85, 99, 0.5);
    }

    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 0 8px;
    }

    .sidebar-menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        color: #6b7280;
        border-radius: 12px;
        transition: all 0.2s ease;
        white-space: nowrap;
        min-width: 0;
    }

    .dark .sidebar-menu-item {
        color: #d1d5db;
    }

    .sidebar-menu-item:hover {
        background-color: rgba(243, 244, 246, 0.8);
        color: #1f2937;
    }

    .dark .sidebar-menu-item:hover {
        background-color: rgba(31, 41, 55, 0.8);
        color: #f9fafb;
    }

    .sidebar-menu-item-active {
        background-color: #3b82f6;
        color: #ffffff;
    }

    .dark .sidebar-menu-item-active {
        background-color: #2563eb;
    }

    .sidebar-icon {
        width: 20px;
        flex-shrink: 0;
        text-align: center;
        font-size: 18px;
    }

    .sidebar-menu-text {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s ease;
        white-space: nowrap;
        overflow: hidden;
    }

    /* Bottom Section */
    .sidebar-bottom {
        flex-shrink: 0;
        padding: 16px 12px;
        border-top: 1px solid rgba(229, 231, 235, 0.5);
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .dark .sidebar-bottom {
        border-top-color: rgba(55, 65, 81, 0.5);
    }

    .sidebar-theme-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 4px;
    }

    .sidebar-theme-label {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s ease;
        font-size: 14px;
        font-weight: 500;
        color: #6b7280;
        white-space: nowrap;
    }

    .dark .sidebar-theme-label {
        color: #d1d5db;
    }

    .sidebar-logout-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
        padding: 12px;
        text-align: left;
        font-size: 14px;
        font-weight: 500;
        color: #dc2626;
        background: transparent;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .sidebar-logout-btn:hover {
        background-color: rgba(254, 242, 242, 0.8);
    }

    .dark .sidebar-logout-btn:hover {
        background-color: rgba(127, 29, 29, 0.3);
    }

    /* Main Content Wrapper */
    .main-content-wrapper {
        margin-left: 60px;
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    #sidebar:hover ~ .main-content-wrapper {
        margin-left: 250px;
    }

    /* Mobile adjustments */
    @media (max-width: 1023px) {
        .sidebar-collapsible {
            width: 250px;
        }

        .sidebar-menu-text,
        .sidebar-logo-text,
        .sidebar-user-info,
        .sidebar-theme-label {
            opacity: 1;
            visibility: visible;
        }

        .main-content-wrapper {
            margin-left: 0;
        }

        #sidebar:hover ~ .main-content-wrapper {
            margin-left: 0;
        }
    }

    /* Ensure sidebar doesn't collapse on touch devices when expanded */
    @media (hover: none) {
        .sidebar-collapsible {
            width: 250px;
        }

        .sidebar-menu-text,
        .sidebar-logo-text,
        .sidebar-user-info,
        .sidebar-theme-label {
            opacity: 1;
            visibility: visible;
        }
    }
</style>
```

## HTML Structure (Replace existing sidebar)

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
                    <a href="dashboard.html" class="sidebar-menu-item">
                        <i class="fas fa-chart-pie sidebar-icon"></i>
                        <span class="sidebar-menu-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="user_management.html" class="sidebar-menu-item">
                        <i class="fas fa-users sidebar-icon"></i>
                        <span class="sidebar-menu-text">User Management</span>
                    </a>
                </li>
                <li>
                    <a href="orders_management.html" class="sidebar-menu-item">
                        <i class="fas fa-prescription-bottle sidebar-icon"></i>
                        <span class="sidebar-menu-text">Orders</span>
                    </a>
                </li>
                <li>
                    <a href="batches_management.html" class="sidebar-menu-item">
                        <i class="fas fa-boxes sidebar-icon"></i>
                        <span class="sidebar-menu-text">Batches</span>
                    </a>
                </li>
                <li>
                    <a href="inventory_management.html" class="sidebar-menu-item sidebar-menu-item-active">
                        <i class="fas fa-capsules sidebar-icon"></i>
                        <span class="sidebar-menu-text">Inventory List</span>
                    </a>
                </li>
                <li>
                    <a href="reports_analytics.html" class="sidebar-menu-item">
                        <i class="fas fa-chart-bar sidebar-icon"></i>
                        <span class="sidebar-menu-text">Reports</span>
                    </a>
                </li>
                <li>
                    <a href="suppliers_management.html" class="sidebar-menu-item">
                        <i class="fas fa-truck-medical sidebar-icon"></i>
                        <span class="sidebar-menu-text">Suppliers</span>
                    </a>
                </li>
                <li>
                    <a href="archive.html" class="sidebar-menu-item">
                        <i class="fas fa-archive sidebar-icon"></i>
                        <span class="sidebar-menu-text">Archive</span>
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

## Important Notes

1. **Active Menu Item**: Update the `sidebar-menu-item-active` class to the current page's menu item.

2. **Main Content Wrapper**: Change your main content container from `lg:ml-64` to use the `main-content-wrapper` class.

3. **Mobile Behavior**: On mobile devices (screens < 1024px), the sidebar will always be 250px wide and fully visible when toggled.

4. **Touch Devices**: On devices without hover capability, the sidebar will default to 250px width.

5. **JavaScript**: The existing mobile toggle JavaScript will continue to work without modifications.

## Usage Instructions

1. Copy the CSS styles into the `<head>` section of your HTML file.
2. Replace your existing sidebar HTML with the new structure.
3. Update the main content wrapper class from `lg:ml-64` to `main-content-wrapper`.
4. Update the active menu item class (`sidebar-menu-item-active`) for each page.
5. Test on different screen sizes to ensure proper behavior.

