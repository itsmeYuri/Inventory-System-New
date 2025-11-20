// Sidebar Functionality
(function() {
    'use strict';

    // Make initSidebar globally available
    window.initSidebar = initSidebar;

    function initSidebar() {
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const openSidebarBtn = document.getElementById('openSidebar');
        const closeSidebarBtn = document.getElementById('closeSidebar');

        if (!sidebar || !sidebarOverlay) {
            console.warn('Sidebar elements not found, will retry...');
            // Retry after a short delay if elements aren't ready
            setTimeout(initSidebar, 200);
            return;
        }

        console.log('Initializing sidebar functionality');
        
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

        // Dynamic padding for main content when sidebar is hovered
        function updateMainContentPadding(isExpanded) {
            try {
                const mainContent = document.querySelector('.main-content-wrapper');
                if (!mainContent) {
                    // Element not found yet, retry later
                    return;
                }
                
                // Check if mobile
                if (window.innerWidth <= 1023) {
                    mainContent.style.paddingLeft = '0';
                    return;
                }
                
                // Use requestAnimationFrame for smooth transitions
                requestAnimationFrame(() => {
                    if (isExpanded) {
                        mainContent.style.paddingLeft = '250px';
                    } else {
                        mainContent.style.paddingLeft = '60px';
                    }
                });
            } catch (error) {
                console.warn('Error updating main content padding:', error);
            }
        }

        // Initialize padding on load (with delay to ensure DOM is ready)
        function initializePadding() {
            // Wait for DOM to be fully ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    setTimeout(() => updateMainContentPadding(false), 200);
                });
            } else {
                setTimeout(() => updateMainContentPadding(false), 200);
            }
        }

        initializePadding();

        // Add hover listeners for dynamic padding (only on desktop)
        function setupHoverListeners() {
            if (window.innerWidth > 1023) {
                sidebar.addEventListener('mouseenter', () => {
                    updateMainContentPadding(true);
                }, { once: false });

                sidebar.addEventListener('mouseleave', () => {
                    updateMainContentPadding(false);
                }, { once: false });
            }
        }

        // Setup hover listeners after a short delay
        setTimeout(setupHoverListeners, 300);

        // Handle mobile - no padding on mobile
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

        // Debounce resize handler
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(handleMobilePadding, 100);
        });
        
        handleMobilePadding(); // Initial check

        // Make functions globally available
        window.openSidebar = openSidebar;
        window.closeSidebar = closeSidebar;

        // Theme toggle functionality
        const themeToggle = document.getElementById('themeToggle');
        const headerThemeToggle = document.getElementById('headerThemeToggle');
        
        // Get existing toggleTheme function or create one
        if (typeof toggleTheme === 'function') {
            if (themeToggle) {
                themeToggle.addEventListener('click', toggleTheme);
            }
            
            if (headerThemeToggle) {
                headerThemeToggle.addEventListener('click', toggleTheme);
            }
        } else {
            // Create basic theme toggle if it doesn't exist
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
                
                // Update toggle button
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
            
            if (headerThemeToggle) {
                headerThemeToggle.addEventListener('click', toggleTheme);
            }
        }

        // Logout functionality
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = '../php/logout.php';
                }
            });
        }

        // Update user display
        function updateUserDisplay() {
            const userName = localStorage.getItem('userName') || 'User';
            const userEmail = localStorage.getItem('userEmail') || 'user@example.com';
            
            const userDisplayName = document.getElementById('userDisplayName');
            const userDisplayEmail = document.getElementById('userDisplayEmail');
            
            if (userDisplayName) {
                userDisplayName.textContent = userName;
            }
            if (userDisplayEmail) {
                userDisplayEmail.textContent = userEmail;
            }
        }

        updateUserDisplay();

        // Set active menu item based on current page
        function setActiveMenuItem() {
            const currentPage = window.location.pathname.split('/').pop() || 'dashboard.html';
            const menuItems = document.querySelectorAll('.sidebar-menu-item[data-page]');
            
            menuItems.forEach(item => {
                item.classList.remove('sidebar-menu-item-active');
                const page = item.getAttribute('data-page');
                if (currentPage.includes(page) || 
                    (page === 'dashboard' && currentPage === 'dashboard.html') ||
                    (page === 'orders' && currentPage === 'orders_management.html') ||
                    (page === 'batches' && currentPage === 'batches_management.html') ||
                    (page === 'inventory' && currentPage === 'inventory_management.html') ||
                    (page === 'reports' && currentPage === 'reports_analytics.html') ||
                    (page === 'suppliers' && currentPage === 'suppliers_management.html')) {
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

        console.log('Sidebar initialized successfully');
    }

    // Wait for DOM to be ready, then initialize
    function tryInit() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                // Wait a bit more for sidebar to be loaded
                setTimeout(initSidebar, 50);
            });
        } else {
            // DOM ready, but sidebar might not be loaded yet
            setTimeout(initSidebar, 50);
        }
    }

    tryInit();
})();

