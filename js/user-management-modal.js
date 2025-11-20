// User Management Modal Functionality
(function() {
    'use strict';
    
    // User Management Modal - Full Functionality
    const currentUserEmail = localStorage.getItem('userEmail') || 'admin@inventory.com';
    let allUsersModal = [];
    let filteredUsersModal = [];

    // Escape HTML
    function escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Load users
    async function loadUsersModal() {
        const tbody = document.getElementById('usersTableBodyModal');
        if (!tbody) return;
        
        tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"><i class="fas fa-spinner fa-spin text-2xl mb-2"></i><p>Loading users...</p></td></tr>';
        
        try {
            const response = await fetch('../api/user_management.php?action=get_all');
            const data = await response.json();
            
            if (data.success && data.users) {
                allUsersModal = data.users.filter(user => user.email !== currentUserEmail);
                applyFiltersModal();
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-8 text-center text-red-500"><p>Error loading users</p></td></tr>';
            }
        } catch (error) {
            console.error('Error loading users:', error);
            tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-8 text-center text-red-500"><p>Error loading users</p></td></tr>';
        }
    }
    
    // Render users table
    function renderUsersModal(users) {
        const tbody = document.getElementById('usersTableBodyModal');
        if (!tbody) return;
        
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"><i class="fas fa-users text-4xl opacity-50 mb-2"></i><p>No users found</p></td></tr>';
            return;
        }
        
        tbody.innerHTML = users.map(user => `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150">
                <td class="px-6 py-4 whitespace-nowrap">
                    <input type="checkbox" class="user-checkbox-modal h-4 w-4 text-primary-600 focus:ring-primary-200 border-border-medium dark:border-gray-500 rounded" data-user-id="${user.user_id}" onchange="updateSelectedCountModal()">
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">${escapeHtml(user.full_name || 'N/A')}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500 dark:text-gray-400">${escapeHtml(user.email || 'N/A')}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500 dark:text-gray-400">${escapeHtml(user.username || 'N/A')}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-500 dark:text-gray-400">${escapeHtml(user.employee_id || 'N/A')}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 py-1 text-xs font-medium rounded-full ${user.role === 'admin' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : user.role === 'supplier' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'}">${escapeHtml((user.role || 'user').toUpperCase())}</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    ${user.status !== undefined ? `
                    ${user.status === 'locked' ? `
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                            <i class="fas fa-lock mr-1"></i> Locked
                        </span>
                    ` : user.status === 'active' ? `
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            <i class="fas fa-circle text-xs mr-1"></i> Active
                        </span>
                    ` : user.status === 'offline' ? `
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                            <i class="fas fa-circle text-xs mr-1 opacity-50"></i> Offline
                        </span>
                    ` : `
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                            <i class="fas fa-circle text-xs mr-1"></i> ${escapeHtml(user.status || 'inactive')}
                        </span>
                    `}
                    ` : `<span class="text-sm text-gray-500 dark:text-gray-400">N/A</span>`}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <div class="flex items-center justify-center space-x-2">
                        <button onclick="editUserModal(${user.user_id})" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 rounded-lg transition-colors ${user.status === 'locked' ? 'opacity-50 cursor-not-allowed' : ''}" title="Edit User" ${user.status === 'locked' ? 'disabled' : ''}>
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="changeUserRoleModal(${user.user_id}, '${user.role || 'user'}')" class="p-2 text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900 rounded-lg transition-colors ${user.status === 'locked' ? 'opacity-50 cursor-not-allowed' : ''}" title="Change Role" ${user.status === 'locked' ? 'disabled' : ''}>
                            <i class="fas fa-user-tag"></i>
                        </button>
                        ${user.status === 'locked' ? `
                            <button onclick="unlockUserAccountModal(${user.user_id})" class="p-2 text-green-600 hover:bg-green-50 dark:hover:bg-green-900 rounded-lg transition-colors" title="Unlock Account">
                                <i class="fas fa-unlock"></i>
                            </button>
                        ` : `
                            <button onclick="lockUserAccountModal(${user.user_id}, '${escapeHtml(user.full_name || user.email)}')" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900 rounded-lg transition-colors" title="Lock Account">
                                <i class="fas fa-lock"></i>
                            </button>
                        `}
                        <button onclick="resetUserPasswordModal(${user.user_id})" class="p-2 text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900 rounded-lg transition-colors ${user.status === 'locked' ? 'opacity-50 cursor-not-allowed' : ''}" title="Reset Password" ${user.status === 'locked' ? 'disabled' : ''}>
                            <i class="fas fa-key"></i>
                        </button>
                        <button onclick="deleteUserModal(${user.user_id}, '${escapeHtml(user.full_name || user.email)}')" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900 rounded-lg transition-colors ${user.status === 'locked' ? 'opacity-50 cursor-not-allowed' : ''}" title="Delete User" ${user.status === 'locked' ? 'disabled' : ''}>
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Apply filters
    function applyFiltersModal() {
        const searchTerm = document.getElementById('userSearchModal')?.value.toLowerCase() || '';
        const roleFilter = document.getElementById('roleFilterModal')?.value || '';
        const statusFilter = document.getElementById('statusFilterModal')?.value || '';
        
        filteredUsersModal = allUsersModal.filter(user => {
            const matchesSearch = !searchTerm || 
                (user.full_name && user.full_name.toLowerCase().includes(searchTerm)) ||
                (user.email && user.email.toLowerCase().includes(searchTerm)) ||
                (user.username && user.username.toLowerCase().includes(searchTerm));
            
            const matchesRole = !roleFilter || (user.role || 'user') === roleFilter;
            const matchesStatus = !statusFilter || (user.status || 'active') === statusFilter;
            
            return matchesSearch && matchesRole && matchesStatus;
        });
        
        renderUsersModal(filteredUsersModal);
        updateCountsModal();
    }
    
    // Update counts
    function updateCountsModal() {
        const resultsCount = document.getElementById('resultsCountModal');
        const totalCount = document.getElementById('totalCountModal');
        if (resultsCount) resultsCount.textContent = filteredUsersModal.length;
        if (totalCount) totalCount.textContent = allUsersModal.length;
        updateSelectedCountModal();
    }
    
    // Update selected count
    function updateSelectedCountModal() {
        const selectedCheckboxes = document.querySelectorAll('.user-checkbox-modal:checked');
        const selectedCount = document.getElementById('selectedCountModal');
        if (selectedCount) selectedCount.textContent = selectedCheckboxes.length;
        
        // Enable/disable bulk action buttons
        const bulkActivateBtn = document.getElementById('bulkActivateBtnModal');
        const bulkDeactivateBtn = document.getElementById('bulkDeactivateBtnModal');
        
        if (bulkActivateBtn && bulkDeactivateBtn) {
            const hasSelection = selectedCheckboxes.length > 0;
            bulkActivateBtn.disabled = !hasSelection;
            bulkDeactivateBtn.disabled = !hasSelection;
        }
    }
    
    // Get selected user IDs
    function getSelectedUserIdsModal() {
        return Array.from(document.querySelectorAll('.user-checkbox-modal:checked'))
            .map(cb => cb.dataset.userId);
    }
    
    // Toggle user status
    async function toggleUserStatusModal(userId, isActive) {
        const status = isActive ? 'active' : 'inactive';
        
        try {
            const response = await fetch('../api/user_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_status',
                    user_id: userId,
                    status: status
                })
            });
            
            const data = await response.json();
            
            if (!data.success) {
                if (data.error && data.error.includes('Status column')) {
                    alert('Status feature not available. The status column will be created on first use.');
                } else {
                    alert('Failed to update user status: ' + (data.error || 'Unknown error'));
                }
                loadUsersModal();
            }
        } catch (error) {
            console.error('Error updating status:', error);
            alert('Error updating user status');
            loadUsersModal();
        }
    }
    
    // Bulk activate
    async function bulkActivateModal() {
        const userIds = getSelectedUserIdsModal();
        if (userIds.length === 0) return;
        
        if (!confirm(`Activate ${userIds.length} user(s)?`)) return;
        
        try {
            await Promise.all(userIds.map(id => 
                toggleUserStatusModal(id, true)
            ));
            loadUsersModal();
        } catch (error) {
            console.error('Error in bulk activate:', error);
            alert('Error activating users');
        }
    }
    
    // Bulk deactivate
    async function bulkDeactivateModal() {
        const userIds = getSelectedUserIdsModal();
        if (userIds.length === 0) return;
        
        if (!confirm(`Deactivate ${userIds.length} user(s)?`)) return;
        
        try {
            await Promise.all(userIds.map(id => 
                toggleUserStatusModal(id, false)
            ));
            loadUsersModal();
        } catch (error) {
            console.error('Error in bulk deactivate:', error);
            alert('Error deactivating users');
        }
    }

    // Edit user
    async function editUserModal(userId) {
        try {
            const response = await fetch(`../api/user_management.php?action=get_user&user_id=${userId}`);
            const data = await response.json();
            
            if (data.success && data.user) {
                const user = data.user;
                const editFullName = document.getElementById('edit_full_name');
                const editEmail = document.getElementById('edit_email');
                const editUsername = document.getElementById('edit_username');
                const editUserId = document.getElementById('edit_user_id');
                const editModal = document.getElementById('editUserModal');
                
                if (editFullName) editFullName.value = user.full_name || '';
                if (editEmail) editEmail.value = user.email || '';
                if (editUsername) editUsername.value = user.username || '';
                if (editUserId) editUserId.value = user.user_id;
                if (editModal) editModal.classList.remove('hidden');
            } else {
                alert('Failed to load user data');
            }
        } catch (error) {
            console.error('Error loading user:', error);
            alert('Error loading user data');
        }
    }

    // Delete user
    async function deleteUserModal(userId, userName) {
        if (!confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
            return;
        }
        
        try {
            const response = await fetch('../api/user_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'delete_user',
                    user_id: userId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('User deleted successfully');
                loadUsersModal();
            } else {
                alert(data.error || 'Failed to delete user');
            }
        } catch (error) {
            console.error('Error deleting user:', error);
            alert('Error deleting user');
        }
    }

    // Change user role
    async function changeUserRoleModal(userId, currentRole) {
        const roles = ['user', 'supplier', 'admin'];
        const currentIndex = roles.indexOf(currentRole);
        const nextIndex = (currentIndex + 1) % roles.length;
        const newRole = roles[nextIndex];
        
        if (!confirm(`Change user role from ${currentRole.toUpperCase()} to ${newRole.toUpperCase()}?`)) {
            return;
        }
        
        try {
            const response = await fetch('../api/user_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_role',
                    user_id: userId,
                    role: newRole
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                loadUsersModal();
            } else {
                alert(data.error || 'Failed to update role');
            }
        } catch (error) {
            console.error('Error updating role:', error);
            alert('Error updating role');
        }
    }

    // Reset user password
    async function resetUserPasswordModal(userId) {
        const newPassword = prompt('Enter new password (minimum 8 characters):');
        
        if (!newPassword) {
            return;
        }
        
        if (newPassword.length < 8) {
            alert('Password must be at least 8 characters long');
            return;
        }
        
        if (!confirm('Are you sure you want to reset this user\'s password?')) {
            return;
        }
        
        try {
            const response = await fetch('../api/user_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'reset_password',
                    user_id: userId,
                    new_password: newPassword
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Password reset successfully');
            } else {
                alert(data.error || 'Failed to reset password');
            }
        } catch (error) {
            console.error('Error resetting password:', error);
            alert('Error resetting password');
        }
    }

    // Lock user account
    async function lockUserAccountModal(userId, userName) {
        if (!confirm(`Are you sure you want to LOCK the account for ${userName}?\n\nLocked users cannot login until unlocked by an admin.`)) {
            return;
        }
        
        try {
            const response = await fetch('../api/user_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'lock_account',
                    user_id: userId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Account locked successfully');
                loadUsersModal();
            } else {
                alert(data.error || 'Failed to lock account');
            }
        } catch (error) {
            console.error('Error locking account:', error);
            alert('Error locking account');
        }
    }

    // Unlock user account
    async function unlockUserAccountModal(userId) {
        if (!confirm('Are you sure you want to UNLOCK this account?\n\nThe user will be able to login again.')) {
            return;
        }
        
        try {
            const response = await fetch('../api/user_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'unlock_account',
                    user_id: userId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Account unlocked successfully');
                loadUsersModal();
            } else {
                alert(data.error || 'Failed to unlock account');
            }
        } catch (error) {
            console.error('Error unlocking account:', error);
            alert('Error unlocking account');
        }
    }

    // Open User Management Modal
    function openUserManagementModal() {
        // Close settings modal if open
        const settingsModal = document.getElementById('settingsModal');
        if (settingsModal) {
            settingsModal.classList.add('hidden');
        }
        
        const userManagementModal = document.getElementById('userManagementModal');
        if (!userManagementModal) {
            console.error('User management modal not found. Make sure load-user-management-modal.js is loaded.');
            return;
        }
        
        // Open User Management modal with smooth transition
        setTimeout(() => {
            userManagementModal.classList.remove('hidden');
            // Load users when modal opens
            if (typeof loadUsersModal === 'function') {
                loadUsersModal();
            }
        }, 150); // Small delay for smooth transition
    }

    // Initialize user management modal when DOM is ready
    function initUserManagementModal() {
        const userManagementModal = document.getElementById('userManagementModal');
        if (!userManagementModal) {
            console.warn('User management modal not found in DOM');
            return;
        }

        // Close User Management Modal
        const closeBtn = document.getElementById('closeUserManagementModal');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                userManagementModal.classList.add('hidden');
            });
        }

        // Close User Management modal on backdrop click
        userManagementModal.addEventListener('click', (e) => {
            if (e.target.id === 'userManagementModal') {
                userManagementModal.classList.add('hidden');
            }
        });

        // Create user form
        const createUserForm = document.getElementById('createUserForm');
        if (createUserForm) {
            createUserForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                const formMessage = document.getElementById('formMessage');
                
                try {
                    const response = await fetch('../api/user_management.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'create_user',
                            full_name: formData.get('full_name'),
                            email: formData.get('email'),
                            username: formData.get('username'),
                            role: formData.get('user_role')
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        if (formMessage) {
                            formMessage.className = 'p-3 rounded-lg text-sm bg-success-50 text-success-700';
                            formMessage.textContent = 'User created successfully!';
                            formMessage.classList.remove('hidden');
                        }
                        e.target.reset();
                        const addUserModal = document.getElementById('addUserModal');
                        if (addUserModal) addUserModal.classList.add('hidden');
                        loadUsersModal();
                        if (formMessage) setTimeout(() => formMessage.classList.add('hidden'), 3000);
                    } else {
                        if (formMessage) {
                            formMessage.className = 'p-3 rounded-lg text-sm bg-error-50 text-error-700';
                            formMessage.textContent = data.error || 'Failed to create user';
                            formMessage.classList.remove('hidden');
                        }
                    }
                } catch (error) {
                    console.error('Error creating user:', error);
                    if (formMessage) {
                        formMessage.className = 'p-3 rounded-lg text-sm bg-error-50 text-error-700';
                        formMessage.textContent = 'Error creating user';
                        formMessage.classList.remove('hidden');
                    }
                }
            });
        }

        // Edit user form
        const updateUserForm = document.getElementById('updateUserForm');
        if (updateUserForm) {
            updateUserForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                const formMessage = document.getElementById('editFormMessage');
                
                try {
                    const response = await fetch('../api/user_management.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'update_user',
                            user_id: formData.get('edit_user_id'),
                            full_name: formData.get('edit_full_name'),
                            email: formData.get('edit_email'),
                            username: formData.get('edit_username')
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        if (formMessage) {
                            formMessage.className = 'p-3 rounded-lg text-sm bg-success-50 text-success-700';
                            formMessage.textContent = 'User updated successfully!';
                            formMessage.classList.remove('hidden');
                        }
                        const editUserModal = document.getElementById('editUserModal');
                        if (editUserModal) editUserModal.classList.add('hidden');
                        loadUsersModal();
                        if (formMessage) setTimeout(() => formMessage.classList.add('hidden'), 3000);
                    } else {
                        if (formMessage) {
                            formMessage.className = 'p-3 rounded-lg text-sm bg-error-50 text-error-700';
                            formMessage.textContent = data.error || 'Failed to update user';
                            formMessage.classList.remove('hidden');
                        }
                    }
                } catch (error) {
                    console.error('Error updating user:', error);
                    if (formMessage) {
                        formMessage.className = 'p-3 rounded-lg text-sm bg-error-50 text-error-700';
                        formMessage.textContent = 'Error updating user';
                        formMessage.classList.remove('hidden');
                    }
                }
            });
        }

        // Event listeners for User Management Modal
        const addUserBtn = document.getElementById('addUserBtnModal');
        if (addUserBtn) {
            addUserBtn.addEventListener('click', () => {
                const addUserModal = document.getElementById('addUserModal');
                if (addUserModal) addUserModal.classList.remove('hidden');
            });
        }

        const closeAddUserModal = document.getElementById('closeAddUserModal');
        if (closeAddUserModal) {
            closeAddUserModal.addEventListener('click', () => {
                const addUserModal = document.getElementById('addUserModal');
                if (addUserModal) addUserModal.classList.add('hidden');
            });
        }

        const cancelAddUser = document.getElementById('cancelAddUser');
        if (cancelAddUser) {
            cancelAddUser.addEventListener('click', () => {
                const addUserModal = document.getElementById('addUserModal');
                if (addUserModal) addUserModal.classList.add('hidden');
            });
        }

        const closeEditUserModal = document.getElementById('closeEditUserModal');
        if (closeEditUserModal) {
            closeEditUserModal.addEventListener('click', () => {
                const editUserModal = document.getElementById('editUserModal');
                if (editUserModal) editUserModal.classList.add('hidden');
            });
        }

        const cancelEditUser = document.getElementById('cancelEditUser');
        if (cancelEditUser) {
            cancelEditUser.addEventListener('click', () => {
                const editUserModal = document.getElementById('editUserModal');
                if (editUserModal) editUserModal.classList.add('hidden');
            });
        }

        // Close modals on backdrop click
        const addUserModal = document.getElementById('addUserModal');
        if (addUserModal) {
            addUserModal.addEventListener('click', (e) => {
                if (e.target.id === 'addUserModal') {
                    addUserModal.classList.add('hidden');
                }
            });
        }

        const editUserModal = document.getElementById('editUserModal');
        if (editUserModal) {
            editUserModal.addEventListener('click', (e) => {
                if (e.target.id === 'editUserModal') {
                    editUserModal.classList.add('hidden');
                }
            });
        }

        // Filter event listeners
        const userSearch = document.getElementById('userSearchModal');
        if (userSearch) {
            userSearch.addEventListener('input', applyFiltersModal);
        }

        const roleFilter = document.getElementById('roleFilterModal');
        if (roleFilter) {
            roleFilter.addEventListener('change', applyFiltersModal);
        }

        const statusFilter = document.getElementById('statusFilterModal');
        if (statusFilter) {
            statusFilter.addEventListener('change', applyFiltersModal);
        }

        const clearFiltersBtn = document.getElementById('clearFiltersBtnModal');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => {
                if (userSearch) userSearch.value = '';
                if (roleFilter) roleFilter.value = '';
                if (statusFilter) statusFilter.value = '';
                applyFiltersModal();
            });
        }
        
        const bulkActivateBtn = document.getElementById('bulkActivateBtnModal');
        if (bulkActivateBtn) {
            bulkActivateBtn.addEventListener('click', bulkActivateModal);
        }

        const bulkDeactivateBtn = document.getElementById('bulkDeactivateBtnModal');
        if (bulkDeactivateBtn) {
            bulkDeactivateBtn.addEventListener('click', bulkDeactivateModal);
        }
        
        const selectAll = document.getElementById('selectAllModal');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.user-checkbox-modal');
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateSelectedCountModal();
            });
        }
    }

    // Make functions globally available
    window.openUserManagementModal = openUserManagementModal;
    window.loadUsersModal = loadUsersModal;
    window.updateSelectedCountModal = updateSelectedCountModal;
    window.editUserModal = editUserModal;
    window.deleteUserModal = deleteUserModal;
    window.changeUserRoleModal = changeUserRoleModal;
    window.resetUserPasswordModal = resetUserPasswordModal;
    window.lockUserAccountModal = lockUserAccountModal;
    window.unlockUserAccountModal = unlockUserAccountModal;

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(initUserManagementModal, 100);
        });
    } else {
        setTimeout(initUserManagementModal, 100);
    }
})();

