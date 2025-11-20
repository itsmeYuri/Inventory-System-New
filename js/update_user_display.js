/**
 * Update User Display in Sidebar
 * This script updates the user name and email in the sidebar from localStorage
 * Should be included in all HTML pages that have a sidebar
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get user info from localStorage
    const userFullName = localStorage.getItem('userFullName') || 'User';
    const userEmail = localStorage.getItem('userEmail') || 'user@example.com';
    
    // Update user display name
    const userDisplayName = document.getElementById('userDisplayName');
    if (userDisplayName) {
        userDisplayName.textContent = userFullName;
    }
    
    // Update user display email
    const userDisplayEmail = document.getElementById('userDisplayEmail');
    if (userDisplayEmail) {
        userDisplayEmail.textContent = userEmail;
    }
});

