// Supplier Sidebar Functionality
(function() {
    'use strict';

    // Make initSidebar globally available
    window.initSupplierSidebar = initSupplierSidebar;
    window.initSidebar = initSupplierSidebar; // Also expose as initSidebar for compatibility

    function initSupplierSidebar() {
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const openSidebarBtn = document.getElementById('openSidebar');
        const closeSidebarBtn = document.getElementById('closeSidebar');

        if (!sidebar || !sidebarOverlay) {
            console.warn('Sidebar elements not found, will retry...');
            setTimeout(initSupplierSidebar, 200);
            return;
        }

        console.log('Initializing supplier sidebar functionality');
        
        // Ensure sidebar is visible on desktop
        if (window.innerWidth > 1023) {
            sidebar.classList.remove('-translate-x-full');
        }

        // Open sidebar function
        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.remove('hidden');
        }

        // Close sidebar function
        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        }

        // Event listeners
        if (openSidebarBtn) {
            openSidebarBtn.addEventListener('click', openSidebar);
        }

        if (closeSidebarBtn) {
            closeSidebarBtn.addEventListener('click', closeSidebar);
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }

        // Stationary sidebar: always use full width on desktop
        function updateMainContentPadding(isExpanded) {
            try {
                const mainContent = document.querySelector('.main-content-wrapper');
                if (!mainContent) {
                    return;
                }
                
                if (window.innerWidth <= 1023) {
                    mainContent.style.paddingLeft = '0';
                    return;
                }
                
                requestAnimationFrame(() => {
                    mainContent.style.paddingLeft = '250px';
                });
            } catch (error) {
                console.warn('Error updating main content padding:', error);
            }
        }

        function initializePadding() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    setTimeout(() => updateMainContentPadding(false), 200);
                });
            } else {
                setTimeout(() => updateMainContentPadding(false), 200);
            }
        }

        initializePadding();

        // Hover listeners removed; sidebar is stationary

        function handleMobilePadding() {
            try {
                const mainContent = document.querySelector('.main-content-wrapper');
                if (mainContent) {
                    if (window.innerWidth <= 1023) {
                        mainContent.style.paddingLeft = '0';
                    } else {
                        updateMainContentPadding(false);
                    }
                }
            } catch (error) {
                console.warn('Error handling mobile padding:', error);
            }
        }

        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(handleMobilePadding, 100);
        });
        
        handleMobilePadding();

        window.openSidebar = openSidebar;
        window.closeSidebar = closeSidebar;

        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        
        if (typeof toggleTheme === 'function') {
            if (themeToggle) {
                themeToggle.addEventListener('click', toggleTheme);
            }
        } else {
            function toggleTheme() {
                const html = document.documentElement;
                const isDark = html.classList.contains('dark');
                
                if (isDark) {
                    html.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                } else {
                    html.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                }
                
                const toggle = document.getElementById('themeToggle');
                const toggleButton = document.getElementById('themeToggleButton');
                if (toggle && toggleButton) {
                    toggle.classList.toggle('bg-primary', !isDark);
                    toggle.classList.toggle('bg-border-light', isDark);
                    toggleButton.classList.toggle('translate-x-6', !isDark);
                    toggleButton.classList.toggle('translate-x-1', isDark);
                }
            }
            
            window.toggleTheme = toggleTheme;
            
            if (themeToggle) {
                themeToggle.addEventListener('click', toggleTheme);
            }
        }

        // Logout functionality
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to logout?')) {
                    localStorage.removeItem('supplierLoggedIn');
                    localStorage.removeItem('supplierId');
                    localStorage.removeItem('supplierName');
                    localStorage.removeItem('supplierEmail');
                    sessionStorage.removeItem('supplierLoggedIn');
                    fetch('../php/logout.php', { method: 'POST' }).then(() => {
                        window.location.href = 'login.html';
                    }).catch(() => {
                        window.location.href = 'login.html';
                    });
                }
            });
        }

        // Update supplier display
        function updateSupplierDisplay() {
            const supplierName = localStorage.getItem('supplierName') || 'Supplier';
            const supplierEmail = localStorage.getItem('supplierEmail') || 'supplier@example.com';
            
            const supplierDisplayName = document.getElementById('supplierDisplayName');
            const supplierDisplayEmail = document.getElementById('supplierDisplayEmail');
            
            if (supplierDisplayName) {
                supplierDisplayName.textContent = supplierName;
            }
            if (supplierDisplayEmail) {
                supplierDisplayEmail.textContent = supplierEmail;
            }
        }

        updateSupplierDisplay();

        // Set active menu item based on current page
        function setActiveMenuItem() {
            const currentPage = window.location.pathname.split('/').pop() || 'supplier_dashboard.html';
            const menuItems = document.querySelectorAll('.sidebar-menu-item[data-page]');
            
            menuItems.forEach(item => {
                item.classList.remove('sidebar-menu-item-active');
                const page = item.getAttribute('data-page');
                if (currentPage.includes(page) || 
                    (page === 'supplier_dashboard' && currentPage === 'supplier_dashboard.html') ||
                    (page === 'supplier_orders' && currentPage === 'supplier_orders.html') ||
                    (page === 'supplier_products' && currentPage === 'supplier_products.html') ||
                    (page === 'supplier_analytics' && currentPage === 'supplier_analytics.html')) {
                    item.classList.add('sidebar-menu-item-active');
                }
            });
        }

        setActiveMenuItem();

        // Load theme from localStorage
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark');
        }

        console.log('Supplier sidebar initialized successfully');
    }

    // Wait for DOM to be ready, then initialize
    function tryInit() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initSupplierSidebar, 50);
            });
        } else {
            setTimeout(initSupplierSidebar, 50);
        }
    }

    // Auto-initialize if sidebar elements exist
    function tryInit() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            // Sidebar already in DOM, initialize immediately
            setTimeout(initSupplierSidebar, 100);
        } else {
            // Wait for sidebar to be loaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(() => {
                        if (document.getElementById('sidebar')) {
                            initSupplierSidebar();
                        } else {
                            // Retry after a delay
                            setTimeout(tryInit, 200);
                        }
                    }, 100);
                });
            } else {
                setTimeout(() => {
                    if (document.getElementById('sidebar')) {
                        initSupplierSidebar();
                    } else {
                        // Retry after a delay
                        setTimeout(tryInit, 200);
                    }
                }, 100);
            }
        }
    }

    // Start initialization
    tryInit();
})();

