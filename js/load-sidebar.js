// Load Sidebar Component - Non-blocking
(function() {
    'use strict';
    
    // Don't block page initialization
    let sidebarLoaded = false;

    async function loadSidebar() {
        if (sidebarLoaded) return;
        sidebarLoaded = true;
        try {
            console.log('Loading sidebar...');
            const response = await fetch('../components/sidebar.html');
            if (!response.ok) {
                throw new Error(`Failed to load sidebar: ${response.status} ${response.statusText}`);
            }
            const html = await response.text();
            
            // Extract style tag content and insert into head
            const styleMatch = html.match(/<style>([\s\S]*?)<\/style>/);
            if (styleMatch && styleMatch[1]) {
                const styleElement = document.createElement('style');
                styleElement.id = 'sidebar-styles';
                styleElement.textContent = styleMatch[1];
                // Remove existing sidebar styles if any
                const existingStyles = document.getElementById('sidebar-styles');
                if (existingStyles) {
                    existingStyles.remove();
                }
                document.head.appendChild(styleElement);
                console.log('Sidebar styles loaded');
            }
            
            // Remove style tag from HTML before inserting
            const htmlWithoutStyle = html.replace(/<style>[\s\S]*?<\/style>/g, '');
            
            // Create a temporary container
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = htmlWithoutStyle;
            
            // Insert sidebar and overlay at the beginning of body
            const sidebarOverlay = tempDiv.querySelector('#sidebarOverlay');
            const sidebar = tempDiv.querySelector('#sidebar');
            
            if (!sidebarOverlay) {
                console.error('Sidebar overlay not found in HTML');
            }
            if (!sidebar) {
                console.error('Sidebar not found in HTML');
            }
            
            if (sidebarOverlay && sidebar) {
                // Remove any existing sidebar first
                const existingSidebar = document.getElementById('sidebar');
                const existingOverlay = document.getElementById('sidebarOverlay');
                if (existingSidebar) existingSidebar.remove();
                if (existingOverlay) existingOverlay.remove();
                
                document.body.insertBefore(sidebarOverlay, document.body.firstChild);
                document.body.insertBefore(sidebar, document.body.firstChild);
                console.log('Sidebar inserted into DOM');
                
                // Load role utilities first if not already loaded
                if (!document.querySelector('script[src="../js/role-utils.js"]')) {
                    const roleUtilsScript = document.createElement('script');
                    roleUtilsScript.src = '../js/role-utils.js';
                    roleUtilsScript.async = false; // Load synchronously before sidebar
                    document.head.appendChild(roleUtilsScript);
                }

                // Load sidebar JavaScript (only if not already loaded)
                if (!document.querySelector('script[src="../js/sidebar.js"]')) {
                    const script = document.createElement('script');
                    script.src = '../js/sidebar.js';
                    script.async = true; // Load asynchronously to not block page
                    script.onload = function() {
                        console.log('Sidebar JavaScript loaded');
                    };
                    script.onerror = function() {
                        console.error('Failed to load sidebar.js');
                    };
                    document.body.appendChild(script);
                } else {
                    console.log('Sidebar JavaScript already loaded');
                    // Re-initialize sidebar if script already exists
                    if (typeof initSidebar === 'function') {
                        setTimeout(() => initSidebar(), 200);
                    }
                }
            } else {
                console.error('Sidebar elements not found in loaded HTML');
            }
        } catch (error) {
            console.error('Error loading sidebar:', error);
            // Fallback: try to show error message
            const errorDiv = document.createElement('div');
            errorDiv.style.cssText = 'position: fixed; top: 10px; right: 10px; background: red; color: white; padding: 10px; z-index: 9999; border-radius: 8px;';
            errorDiv.textContent = 'Sidebar failed to load: ' + error.message;
            document.body.appendChild(errorDiv);
            setTimeout(() => errorDiv.remove(), 5000);
        }
    }

    // Load sidebar after page scripts have initialized
    // Use requestIdleCallback if available, otherwise use setTimeout with longer delay
    function scheduleSidebarLoad() {
        if (window.requestIdleCallback) {
            requestIdleCallback(() => {
                setTimeout(loadSidebar, 500);
            }, { timeout: 2000 });
        } else {
            // Fallback: wait longer to ensure page scripts run first
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    setTimeout(loadSidebar, 500);
                });
            } else {
                setTimeout(loadSidebar, 500);
            }
        }
    }
    
    scheduleSidebarLoad();
})();

