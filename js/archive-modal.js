// Archive Modal Functionality
(function() {
    'use strict';
    
    // Archive Modal - Full Functionality
    const ARCHIVE_API = '../php/get_archives.php';
    const PROCESS_EXPIRED_API = '../php/process_and_archive_expired.php';

    // Escape HTML helper for archive modal
    function escapeHtmlArchive(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Escape HTML helper
    function escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Tab switching for Archive Modal
    function switchArchiveTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content-modal').forEach(content => {
            content.classList.add('hidden');
        });
        
        // Remove active class from all tabs
        document.querySelectorAll('.tab-button-modal').forEach(btn => {
            btn.classList.remove('active', 'border-primary', 'text-primary');
            btn.classList.add('border-transparent', 'text-text-secondary');
        });
        
        // Show selected tab content
        const contentEl = document.getElementById(`content${tabName}Modal`);
        if (contentEl) {
            contentEl.classList.remove('hidden');
        }
        
        // Add active class to selected tab
        const activeTab = document.getElementById(`tab${tabName}Modal`);
        if (activeTab) {
            activeTab.classList.add('active', 'border-primary', 'text-primary');
            activeTab.classList.remove('border-transparent', 'text-text-secondary');
        }
    }

    // Load archive data
    async function loadArchivesModal() {
        try {
            // First, process and archive any expired items
            try {
                await fetch(PROCESS_EXPIRED_API);
            } catch (error) {
                console.error('Error processing expired items:', error);
                // Continue even if processing fails
            }

            // Then load archive data
            const response = await fetch(`${ARCHIVE_API}?type=all`);
            const result = await response.json();

            if (result.success && result.data) {
                // Update counts
                const expiredCountEl = document.getElementById('expiredCountModal');
                const cancelledCountEl = document.getElementById('cancelledCountModal');
                if (expiredCountEl) expiredCountEl.textContent = result.data.counts.expired || 0;
                if (cancelledCountEl) cancelledCountEl.textContent = result.data.counts.cancelled || 0;

                // Render expired items
                renderExpiredItemsModal(result.data.items.expired || []);
                
                // Render cancelled orders
                renderCancelledOrdersModal(result.data.items.cancelled || []);

                // Load archived users via user management API
                try {
                    const usersRes = await fetch('../php/user_management.php?action=get_all');
                    const usersJson = await usersRes.json();
                    if (usersJson.success && Array.isArray(usersJson.users)) {
                        const archivedUsers = usersJson.users.filter(u => (u.status || '').toLowerCase() === 'archived');
                        const countEl = document.getElementById('archivedUsersCountModal');
                        if (countEl) countEl.textContent = archivedUsers.length;
                        renderArchivedUsersModal(archivedUsers);
                    } else {
                        renderArchivedUsersModal([]);
                    }
                } catch (e) {
                    console.error('Error loading archived users:', e);
                    renderArchivedUsersModal([]);
                }
            }
        } catch (error) {
            console.error('Error loading archives:', error);
        }
    }

    // Render expired items
    function renderExpiredItemsModal(items) {
        const tbody = document.getElementById('expiredItemsTableModal');
        if (!tbody) return;
        
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-text-tertiary dark:text-gray-500">No expired items found</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(item => `
            <tr class="border-b border-border-light dark:border-gray-700 hover:bg-surface-hover dark:hover:bg-gray-800">
                <td class="py-3 px-4">
                    <div>
                        <div class="font-medium text-text-primary dark:text-gray-100">${escapeHtml(item.medicine_name || 'N/A')}</div>
                        <div class="text-xs text-text-tertiary dark:text-gray-500">${escapeHtml(item.medicine_ndc || '')}</div>
                    </div>
                </td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${escapeHtml(item.batch_number || 'N/A')}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${item.quantity || 0}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${item.expiration_date || 'N/A'}</td>
                <td class="py-3 px-4">
                    <span class="px-2 py-1 bg-error-100 dark:bg-error-900 text-error-700 dark:text-error-300 rounded-full text-xs font-medium">
                        ${item.days_expired || 0} days
                    </span>
                </td>
                <td class="py-3 px-4 text-text-tertiary dark:text-gray-500 text-sm">${item.expired_at ? new Date(item.expired_at).toLocaleDateString() : 'N/A'}</td>
            </tr>
        `).join('');
    }

    // Render cancelled orders
    function renderCancelledOrdersModal(orders) {
        const tbody = document.getElementById('cancelledOrdersTableModal');
        if (!tbody) return;
        
        if (orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-text-tertiary dark:text-gray-500">No cancelled orders found</td></tr>';
            return;
        }

        tbody.innerHTML = orders.map(order => `
            <tr class="border-b border-border-light dark:border-gray-700 hover:bg-surface-hover dark:hover:bg-gray-800">
                <td class="py-3 px-4 font-medium text-text-primary dark:text-gray-100">#${order.original_id || order.id}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${escapeHtml(order.supplier_name || 'N/A')}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${order.order_date || 'N/A'}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">$${parseFloat(order.total_amount || 0).toFixed(2)}</td>
                <td class="py-3 px-4 text-text-secondary dark:text-gray-400">${order.item_count || 0} items</td>
                <td class="py-3 px-4 text-text-tertiary dark:text-gray-500 text-sm">${order.cancelled_at ? new Date(order.cancelled_at).toLocaleDateString() : 'N/A'}</td>
                <td class="py-3 px-4 text-text-tertiary dark:text-gray-500 text-sm">${escapeHtml(order.cancellation_reason || 'N/A')}</td>
            </tr>
        `).join('');
    }

    // Render archived users
    function renderArchivedUsersModal(users) {
        const tbody = document.getElementById('archivedUsersTableModal');
        if (!tbody) return;
        if (!users || users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-text-tertiary">No archived users</td></tr>';
            return;
        }

        tbody.innerHTML = users.map(u => `
            <tr class="border-b border-border-light hover:bg-surface-hover">
                <td class="py-3 px-4">${escapeHtml(u.full_name || 'N/A')}</td>
                <td class="py-3 px-4">${escapeHtml(u.email || 'N/A')}</td>
                <td class="py-3 px-4">${escapeHtml(u.username || 'N/A')}</td>
                <td class="py-3 px-4">${escapeHtml((u.role || 'employee').toUpperCase())}</td>
                <td class="py-3 px-4">${u.updated_at ? new Date(u.updated_at).toLocaleDateString() : '—'}</td>
                <td class="py-3 px-4 text-center">
                    <button class="px-3 py-1 border border-border-light rounded-lg hover:bg-white" onclick="restoreArchivedUser('${u.user_id}')">
                        Restore
                    </button>
                </td>
            </tr>
        `).join('');
    }

    // Restore archived user (sets status back to active)
    window.restoreArchivedUser = async function(userId) {
        try {
            const res = await fetch('../php/user_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                body: new URLSearchParams({ action: 'update_status', user_id: userId, status: 'active' })
            });
            const json = await res.json();
            if (json.success) {
                loadArchivesModal();
            } else {
                alert(json.error || 'Failed to restore user');
            }
        } catch (e) {
            console.error('Restore user error:', e);
            alert('Error restoring user');
        }
    }

    // Open Archive Modal
    function openArchiveModal() {
        // Close settings modal if open
        const settingsModal = document.getElementById('settingsModal');
        if (settingsModal) {
            settingsModal.classList.add('hidden');
        }
        
        const archiveModal = document.getElementById('archiveModal');
        if (!archiveModal) {
            console.error('Archive modal not found. Make sure load-archive-modal.js is loaded.');
            return;
        }
        
        setTimeout(() => {
            archiveModal.classList.remove('hidden');
            // Load archives when modal opens
            loadArchivesModal();
        }, 150);
    }

    // Initialize archive modal when DOM is ready
    function initArchiveModal() {
        const archiveModal = document.getElementById('archiveModal');
        if (!archiveModal) {
            console.warn('Archive modal not found in DOM');
            return;
        }

        try {
            archiveModal.style.left = '0';
            archiveModal.style.zIndex = '1000';
        } catch (e) {}

        // Close Archive Modal
        const closeBtn = document.getElementById('closeArchiveModal');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                archiveModal.classList.add('hidden');
            });
        }

        // Close Archive modal on backdrop click
        archiveModal.addEventListener('click', (e) => {
            if (e.target.id === 'archiveModal') {
                archiveModal.classList.add('hidden');
            }
        });

        // Tab switching event listeners
        const expiredTab = document.getElementById('tabExpiredModal');
        const cancelledTab = document.getElementById('tabCancelledModal');
        const usersTab = document.getElementById('tabUsersModal');
        
        if (expiredTab) {
            expiredTab.addEventListener('click', () => switchArchiveTab('Expired'));
        }
        if (cancelledTab) {
            cancelledTab.addEventListener('click', () => switchArchiveTab('Cancelled'));
        }
        if (usersTab) {
            usersTab.addEventListener('click', () => switchArchiveTab('Users'));
        }
    }

    // Make openArchiveModal globally available
    window.openArchiveModal = openArchiveModal;

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(initArchiveModal, 100);
        });
    } else {
        setTimeout(initArchiveModal, 100);
    }
})();

