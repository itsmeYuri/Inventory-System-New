/**
 * Page Protection Script
 * Adds role-based access control to HTML pages
 * Include this script in pages that need protection
 */

(function() {
    'use strict';

    // Wait for role utils to load
    function initPageProtection() {
        // Check if RoleUtils is available
        if (typeof window.RoleUtils === 'undefined') {
            // Retry after a short delay
            setTimeout(initPageProtection, 100);
            return;
        }

        const currentPage = window.location.pathname.split('/').pop() || '';
        const pageName = currentPage.replace('.html', '').replace('.php', '');
        const role = window.RoleUtils.getUserRole();

        // If supplier is on dashboard.html, redirect them to supplier_dashboard.html
        if (role === 'supplier' && (pageName === 'dashboard' || currentPage === 'dashboard.html')) {
            window.location.href = 'supplier_dashboard.html';
            return;
        }

        // Check page access
        if (window.RoleUtils.canAccessPage && !window.RoleUtils.canAccessPage(pageName)) {
            // If supplier tries to access non-supplier page, redirect to supplier dashboard
            if (role === 'supplier') {
                window.location.href = 'supplier_dashboard.html';
                return;
            }
            
            // For other roles, redirect to no-access page
            window.location.href = '../pages/no-access.php';
            return;
        }

        // Hide supplier management elements for supplier role
        
        if (role === 'supplier') {
            // Hide "Add Supplier" buttons and links for suppliers
            const addSupplierButtons = document.querySelectorAll(
                'button[onclick*="suppliers_management"], button[onclick*="supplier"], a[href*="suppliers_management"]'
            );
            addSupplierButtons.forEach(btn => {
                const text = (btn.textContent || '').toLowerCase();
                const onclick = (btn.getAttribute('onclick') || '').toLowerCase();
                const href = (btn.getAttribute('href') || '').toLowerCase();
                
                if (text.includes('add supplier') || 
                    text.includes('supplier') && text.includes('add') ||
                    onclick.includes('suppliers_management') ||
                    onclick.includes('supplier') && onclick.includes('management') ||
                    href.includes('suppliers_management')) {
                    btn.style.display = 'none';
                    const parent = btn.closest('div, li, section');
                    if (parent && parent.querySelectorAll('button, a').length === 1) {
                        parent.style.display = 'none';
                    }
                }
            });
        }

        // Hide elements with data-hide-for-roles attribute
        document.querySelectorAll('[data-hide-for-roles]').forEach(el => {
            const hideForRoles = (el.getAttribute('data-hide-for-roles') || '').toLowerCase().split(',').map(r => r.trim());
            if (hideForRoles.includes(role)) {
                el.style.display = 'none';
            }
        });

        // Hide user management for non-admin users
        if (role !== 'admin') {
            // Hide user management buttons in settings modals
            const userManagementButtons = document.querySelectorAll(
                'button[onclick*="UserManagement"], button[onclick*="user_management"], button[onclick*="openUserManagementModal"]'
            );
            userManagementButtons.forEach(btn => {
                const text = (btn.textContent || '').toLowerCase();
                const onclick = (btn.getAttribute('onclick') || '').toLowerCase();
                
                if (text.includes('user management') || onclick.includes('usermanagement') || onclick.includes('user_management')) {
                    btn.style.display = 'none';
                    const parent = btn.closest('div');
                    if (parent && parent.querySelectorAll('button').length === 1) {
                        parent.style.display = 'none';
                    }
                }
            });
        }

        console.log('Page protection initialized for:', pageName);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPageProtection);
    } else {
        initPageProtection();
    }
})();

