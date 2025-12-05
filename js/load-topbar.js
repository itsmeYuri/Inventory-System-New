// Load Top Bar Component - Non-blocking
(function() {
    'use strict';
    
    let topbarLoaded = false;

    async function loadTopbar() {
        if (topbarLoaded) return;
        topbarLoaded = true;
        
        try {
            // Always enforce light theme globally
            document.documentElement.classList.remove('dark');
            try { localStorage.setItem('theme', 'light'); } catch (e) {}
            console.log('Loading topbar...');
            const response = await fetch('../components/topbar.html');
            if (!response.ok) {
                throw new Error(`Failed to load topbar: ${response.status} ${response.statusText}`);
            }
            const html = await response.text();
            
            // Check if topbar already exists
            const existingTopbar = document.getElementById('topbar');
            if (existingTopbar) {
                console.log('Topbar already exists, skipping load');
                return;
            }
            
            // Create a temporary container
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // Get the topbar element - clone it to ensure it's a fresh node
            const topbarTemplate = tempDiv.querySelector('#topbar');
            if (!topbarTemplate) {
                console.error('Topbar element not found in HTML');
                return;
            }
            
            // Get configuration from script tag data attributes or global config
            const scriptTag = document.querySelector('script[src*="load-topbar.js"]');
            const pageTitle = scriptTag?.dataset?.title || window.topbarConfig?.title || 'Pharmacy Dashboard';
            const pageDescription = scriptTag?.dataset?.description || window.topbarConfig?.description || 'Welcome back! Monitor your medicine inventory and operations.';
            const searchPlaceholder = scriptTag?.dataset?.searchPlaceholder || window.topbarConfig?.searchPlaceholder || 'Search medicines...';
            
            // Update title and description in the template first
            const titleElement = topbarTemplate.querySelector('#topbarTitle');
            const descriptionElement = topbarTemplate.querySelector('#topbarDescription');
            const searchInput = topbarTemplate.querySelector('#globalSearch');
            
            if (titleElement) {
                titleElement.textContent = pageTitle;
            }
            if (descriptionElement) {
                descriptionElement.textContent = pageDescription;
            }
            if (searchInput) {
                searchInput.placeholder = searchPlaceholder;
            }
            
            // Clone the node to ensure it's properly detached from tempDiv
            const topbar = topbarTemplate.cloneNode(true);
            
            // Find the main content wrapper - use a retry mechanism
            let container = document.querySelector('.main-content-wrapper');
            
            function insertTopbar() {
                if (!container) {
                    container = document.querySelector('.main-content-wrapper');
                }
                
                if (container) {
                    // Check if topbar is already a child
                    if (container.contains(topbar)) {
                        console.log('Topbar already inserted');
                        return true;
                    }
                    
                    // Insert at the very beginning, before any other content
                    const firstChild = container.firstChild;
                    if (firstChild) {
                        container.insertBefore(topbar, firstChild);
                    } else {
                        container.appendChild(topbar);
                    }
                    console.log('Topbar inserted successfully into main-content-wrapper');
                    return true;
                }
                return false;
            }
            
            if (!insertTopbar()) {
                // Retry with interval
                let retries = 0;
                const maxRetries = 20;
                const retryInterval = setInterval(() => {
                    retries++;
                    if (insertTopbar()) {
                        clearInterval(retryInterval);
                    } else if (retries >= maxRetries) {
                        clearInterval(retryInterval);
                        // Fallback: try to find main element
                        const mainElement = document.querySelector('main');
                        if (mainElement && !mainElement.contains(topbar)) {
                            mainElement.insertBefore(topbar, mainElement.firstChild);
                            console.log('Topbar inserted into main element (fallback)');
                        } else {
                            // Last resort: insert at beginning of body
                            const bodyFirstChild = document.body.firstChild;
                            if (bodyFirstChild && !document.body.contains(topbar)) {
                                document.body.insertBefore(topbar, bodyFirstChild);
                            } else if (!document.body.contains(topbar)) {
                                document.body.appendChild(topbar);
                            }
                            console.log('Topbar inserted into body (last resort)');
                        }
                    }
                }, 50);
            }
            
            console.log('Topbar loaded successfully');
            
            // Initialize clock if not already initialized
            if (!window.clockInitialized) {
                initializeClock();
                window.clockInitialized = true;
            }
            
            // Theme toggle removed; ensure light theme remains active
            if (!window.themeToggleInitialized) {
                window.themeToggleInitialized = true;
            }
            
        } catch (error) {
            console.error('Error loading topbar:', error);
        }
    }
    
    function initializeClock() {
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const clockElement = document.getElementById('liveClock');
            if (clockElement) {
                clockElement.textContent = timeString;
            }
        }
        
        updateClock();
        setInterval(updateClock, 1000);
    }
    
    function initializeThemeToggle() {
        // Theme toggle removed; keep light mode
        document.documentElement.classList.remove('dark');
        try { localStorage.setItem('theme', 'light'); } catch (e) {}
    }
    
    // Load when DOM is ready - use multiple strategies to ensure it loads
    function initTopbar() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                // Small delay to ensure all elements are in place
                setTimeout(loadTopbar, 50);
            });
        } else {
            // DOM already loaded, but wait a bit for dynamic content
            setTimeout(loadTopbar, 50);
        }
    }
    
    // Try to load immediately
    initTopbar();
    
    // Also try on window load as fallback
    window.addEventListener('load', function() {
        if (!document.getElementById('topbar')) {
            console.log('Topbar not found after window load, retrying...');
            topbarLoaded = false; // Reset flag to allow retry
            loadTopbar();
        }
    });
})();
