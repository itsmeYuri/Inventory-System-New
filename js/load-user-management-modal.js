// Load User Management Modal Component - Non-blocking
(function() {
    'use strict';
    
    let userManagementModalLoaded = false;

    async function loadUserManagementModal() {
        if (userManagementModalLoaded) return;
        userManagementModalLoaded = true;
        
        try {
            const response = await fetch('../components/user_management_modal.html');
            if (!response.ok) {
                throw new Error(`Failed to load user management modal: ${response.status} ${response.statusText}`);
            }
            const html = await response.text();
            
            // Create a temporary container
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // Get all modals
            const userManagementModal = tempDiv.querySelector('#userManagementModal');
            const addUserModal = tempDiv.querySelector('#addUserModal');
            const editUserModal = tempDiv.querySelector('#editUserModal');
            
            if (!userManagementModal) {
                console.error('User management modal not found in HTML');
                return;
            }
            
            // Remove any existing modals first
            const existingUserModal = document.getElementById('userManagementModal');
            const existingAddModal = document.getElementById('addUserModal');
            const existingEditModal = document.getElementById('editUserModal');
            if (existingUserModal) existingUserModal.remove();
            if (existingAddModal) existingAddModal.remove();
            if (existingEditModal) existingEditModal.remove();
            
            // Insert modals into body
            document.body.appendChild(userManagementModal);
            if (addUserModal) document.body.appendChild(addUserModal);
            if (editUserModal) document.body.appendChild(editUserModal);
            
            // Load user management modal JavaScript (only if not already loaded)
            if (!document.querySelector('script[src="../js/user-management-modal.js"]')) {
                const script = document.createElement('script');
                script.src = '../js/user-management-modal.js';
                script.async = true;
                script.onload = function() {
                    console.log('User management modal JavaScript loaded');
                };
                script.onerror = function() {
                    console.error('Failed to load user-management-modal.js');
                };
                document.body.appendChild(script);
            } else {
                console.log('User management modal JavaScript already loaded');
            }
        } catch (error) {
            console.error('Error loading user management modal:', error);
        }
    }

    // Load user management modal after page scripts have initialized
    function scheduleUserManagementModalLoad() {
        if (window.requestIdleCallback) {
            requestIdleCallback(() => {
                setTimeout(loadUserManagementModal, 500);
            }, { timeout: 2000 });
        } else {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    setTimeout(loadUserManagementModal, 500);
                });
            } else {
                setTimeout(loadUserManagementModal, 500);
            }
        }
    }
    
    scheduleUserManagementModalLoad();
})();

