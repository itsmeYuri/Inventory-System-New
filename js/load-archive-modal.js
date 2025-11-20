// Load Archive Modal Component - Non-blocking
(function() {
    'use strict';
    
    let archiveModalLoaded = false;

    async function loadArchiveModal() {
        if (archiveModalLoaded) return;
        archiveModalLoaded = true;
        
        try {
            const response = await fetch('../components/archive_modal.html');
            if (!response.ok) {
                throw new Error(`Failed to load archive modal: ${response.status} ${response.statusText}`);
            }
            const html = await response.text();
            
            // Create a temporary container
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // Get the archive modal
            const archiveModal = tempDiv.querySelector('#archiveModal');
            
            if (!archiveModal) {
                console.error('Archive modal not found in HTML');
                return;
            }
            
            // Remove any existing archive modal first
            const existingModal = document.getElementById('archiveModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Insert archive modal into body
            document.body.appendChild(archiveModal);
            
            // Load archive modal JavaScript (only if not already loaded)
            if (!document.querySelector('script[src="../js/archive-modal.js"]')) {
                const script = document.createElement('script');
                script.src = '../js/archive-modal.js';
                script.async = true;
                script.onload = function() {
                    console.log('Archive modal JavaScript loaded');
                };
                script.onerror = function() {
                    console.error('Failed to load archive-modal.js');
                };
                document.body.appendChild(script);
            } else {
                console.log('Archive modal JavaScript already loaded');
            }
        } catch (error) {
            console.error('Error loading archive modal:', error);
        }
    }

    // Load archive modal after page scripts have initialized
    function scheduleArchiveModalLoad() {
        if (window.requestIdleCallback) {
            requestIdleCallback(() => {
                setTimeout(loadArchiveModal, 500);
            }, { timeout: 2000 });
        } else {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    setTimeout(loadArchiveModal, 500);
                });
            } else {
                setTimeout(loadArchiveModal, 500);
            }
        }
    }
    
    scheduleArchiveModalLoad();
})();

