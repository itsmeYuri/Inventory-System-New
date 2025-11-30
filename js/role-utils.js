/**
 * Role-Based Access Control Utilities
 * Provides functions to check user roles and filter content based on permissions
 */

(function() {
    'use strict';

    /**
     * Get current user role from localStorage
     * @returns {string} User role (admin, employee, supplier) or empty string
     */
    function getUserRole() {
        return (localStorage.getItem('userRole') || '').toLowerCase();
    }

    /**
     * Check if current user is admin
     * @returns {boolean}
     */
    function isAdmin() {
        return getUserRole() === 'admin';
    }

    /**
     * Check if current user is employee
     * @returns {boolean}
     */
    function isUser() {
        return getUserRole() === 'employee';
    }

    /**
     * Check if current user is supplier
     * @returns {boolean}
     */
    function isSupplier() {
        return getUserRole() === 'supplier';
    }

    /**
     * Check if user can access a specific page
     * @param {string} pageName - Name of the page
     * @returns {boolean}
     */
    function canAccessPage(pageName) {
        const role = getUserRole();
        const page = pageName.toLowerCase();

        // Supplier portal pages (supplier_dashboard, supplier_orders, etc.)
        const supplierPortalPages = ['supplier_dashboard', 'supplier_orders', 'supplier_products', 'supplier_analytics'];
        
        // Suppliers management page (for admin/user to manage suppliers)
        const suppliersManagementPage = 'suppliers_management';
        
        // User management page
        const userManagementPages = ['user_management'];

        if (role === 'admin') {
            // Admin can access all except supplier portal pages
            return !supplierPortalPages.some(sp => page.includes(sp));
        } else if (role === 'employee') {
            // Employee can access all except supplier portal pages and user management
            return !supplierPortalPages.some(sp => page.includes(sp)) && 
                   !userManagementPages.some(ump => page.includes(ump));
        } else if (role === 'supplier') {
            // Supplier can access supplier portal pages, dashboard (for redirect), but NOT suppliers_management or other admin pages
            if (page.includes(suppliersManagementPage) || page.includes('user_management')) {
                return false;
            }
            // Allow supplier portal pages and dashboard (as entry point)
            if (page === 'dashboard' || page === 'dashboard.html') {
                // Redirect will happen, but allow access to prevent access denied
                return true;
            }
            return supplierPortalPages.some(sp => page.includes(sp));
        }

        // Default: deny access for unknown roles
        return false;
    }

    /**
     * Check if user can see a specific table
     * @param {string} tableName - Name/identifier of the table
     * @returns {boolean}
     */
    function canSeeTable(tableName) {
        const role = getUserRole();
        const table = tableName.toLowerCase();

        // Supplier-related tables
        const supplierTables = ['suppliers', 'supplier'];

        if (role === 'admin' || role === 'employee') {
            // Admin and employee CAN see supplier tables
            return true;
        } else if (role === 'supplier') {
            // Supplier can only see supplier tables
            return supplierTables.some(st => table.includes(st));
        }

        // Default: allow for unknown roles (fallback)
        return true;
    }

    /**
     * Redirect user to appropriate page if they don't have access
     * @param {string} pageName - Name of the current page
     */
    function checkPageAccess(pageName) {
        if (!canAccessPage(pageName)) {
            // Redirect to no-access page or dashboard
            window.location.href = '../pages/no-access.php';
        }
    }

    /**
     * Hide elements based on role
     * @param {string} selector - CSS selector for elements to hide
     * @param {Function} condition - Function that returns true if element should be hidden
     */
    function hideByRole(selector, condition) {
        const elements = document.querySelectorAll(selector);
        elements.forEach(el => {
            if (condition()) {
                el.style.display = 'none';
            }
        });
    }

    /**
     * Show elements based on role
     * @param {string} selector - CSS selector for elements to show
     * @param {Function} condition - Function that returns true if element should be shown
     */
    function showByRole(selector, condition) {
        const elements = document.querySelectorAll(selector);
        elements.forEach(el => {
            if (condition()) {
                el.style.display = '';
            } else {
                el.style.display = 'none';
            }
        });
    }

    // Export functions to global scope
    window.RoleUtils = {
        getUserRole,
        isAdmin,
        isUser,
        isSupplier,
        canAccessPage,
        canSeeTable,
        checkPageAccess,
        hideByRole,
        showByRole
    };

    console.log('Role utilities loaded');
})();

