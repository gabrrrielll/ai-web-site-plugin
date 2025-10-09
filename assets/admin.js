// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function () {
    console.log('AI Web Site Admin: Page loaded');

    // Handle settings form submission
    var settingsForms = document.querySelectorAll('form[action*="admin-post.php"]');
    settingsForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            console.log('AI Web Site Admin: Settings form submitted');
            console.log('Form action:', form.action);
            console.log('Form data:', Object.fromEntries(new FormData(form).entries())); // Log all form data

            // Let the form submit normally
            return true;
        });
    });

    // Handle add subdomain form submission
    var addSubdomainForm = document.getElementById('add-subdomain-form');
    if (addSubdomainForm) {
        addSubdomainForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var submitBtn = this.querySelector('input[type="submit"]');
            var originalText = submitBtn.value;

            // Get form data
            var subdomainInput = document.getElementById('new_subdomain');
            var subdomain = subdomainInput ? subdomainInput.value.trim() : '';
            var domain = aiWebSite.options.main_domain; // Get main domain from PHP-generated options

            if (!subdomain) {
                alert('Please enter a subdomain name');
                return;
            }

            // Validate subdomain format
            if (!/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$/.test(subdomain)) {
                alert('Subdomain can only contain letters, numbers, and hyphens. It cannot start or end with a hyphen.');
                return;
            }

            // Show loading state
            if (typeof aiWebSite !== 'undefined') {
                submitBtn.value = aiWebSite.strings.creating;
            }
            submitBtn.disabled = true;

            // Make AJAX request using fetch
            if (typeof aiWebSite !== 'undefined') {
                fetch(aiWebSite.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'create_subdomain',
                        nonce: aiWebSite.nonce,
                        subdomain: subdomain,
                        domain: domain
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            showNotice('success', 'Subdomain created successfully: ' + subdomain + '.' + domain);

                            // Clear form
                            if (subdomainInput) {
                                subdomainInput.value = '';
                            }

                            // Reload page to show new subdomain
                            setTimeout(function () {
                                location.reload();
                            }, 1000);
                        } else {
                            // Show error message
                            showNotice('error', 'Error creating subdomain: ' + (data.data || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        showNotice('error', 'Network error occurred');
                    })
                    .finally(() => {
                        // Reset button
                        submitBtn.value = originalText;
                        submitBtn.disabled = false;
                    });
            }
        });
    }

    // Handle delete subdomain
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('delete-subdomain')) {
            e.preventDefault();

            if (typeof aiWebSite !== 'undefined' && !confirm(aiWebSite.strings.confirmDelete)) {
                return;
            }

            var button = e.target;
            var subdomain = button.dataset.subdomain;
            var domain = button.dataset.domain;
            var originalText = button.textContent;

            // Show loading state
            if (typeof aiWebSite !== 'undefined') {
                button.textContent = aiWebSite.strings.deleting;
            }
            button.disabled = true;

            // Make AJAX request using fetch
            if (typeof aiWebSite !== 'undefined') {
                fetch(aiWebSite.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'delete_subdomain',
                        nonce: aiWebSite.nonce,
                        subdomain: subdomain,
                        domain: domain
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            showNotice('success', 'Subdomain deleted successfully');

                            // Remove row from table
                            var row = button.closest('tr');
                            if (row) {
                                row.style.opacity = '0';
                                setTimeout(function () {
                                    row.remove();
                                }, 300);
                            }

                            // Reload page to show updated list
                            setTimeout(function () {
                                location.reload();
                            }, 1000);
                        } else {
                            // Show error message
                            showNotice('error', 'Error deleting subdomain: ' + (data.data || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        showNotice('error', 'Network error occurred');
                    })
                    .finally(() => {
                        // Reset button
                        button.textContent = originalText;
                        button.disabled = false;
                    });
            }
        }
    });

    // Handle test connection button
    document.addEventListener('click', function (e) {
        if (e.target.name === 'test_connection') {
            e.preventDefault();

            var button = e.target;
            var originalText = button.value;

            // Show loading state
            if (typeof aiWebSite !== 'undefined') {
                button.value = aiWebSite.strings.testing;
            }
            button.disabled = true;

            // Submit the form to test connection
            var form = button.closest('form');
            var actionInput = form.querySelector('input[name="action"][formaction]');
            if (actionInput) {
                form.action = actionInput.getAttribute('formaction');
            }
            form.submit();
        }
    });

    // Function to show admin notices
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = document.createElement('div');
        notice.className = 'notice ' + noticeClass + ' is-dismissible';
        notice.innerHTML = '<p>' + message + '</p>';

        // Insert notice after the page title
        var title = document.querySelector('.wrap h1');
        if (title && title.parentNode) {
            title.parentNode.insertBefore(notice, title.nextSibling);
        }

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            notice.style.opacity = '0';
            setTimeout(function () {
                if (notice.parentNode) {
                    notice.parentNode.removeChild(notice);
                }
            }, 300);
        }, 5000);
    }

    // Handle form validation
    var subdomainInput = document.getElementById('new_subdomain');
    if (subdomainInput) {
        subdomainInput.addEventListener('input', function () {
            var value = this.value;

            // Validate subdomain format in real-time
            if (value && !/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$/.test(value)) {
                this.classList.add('error');
            } else {
                this.classList.remove('error');
            }
        });
    }

    // Add CSS for error state
    var style = document.createElement('style');
    style.type = 'text/css';
    style.innerHTML = '.error { border-color: #dc3232 !important; }';
    document.head.appendChild(style);

    // UMP License Activation
    var activateButton = document.getElementById('activate_ump_license');
    var statusDiv = document.getElementById('ump_license_status');

    if (activateButton && statusDiv) {
        activateButton.addEventListener('click', function () {
            var button = this;
            var originalText = button.textContent;

            // Disable button and show loading
            button.disabled = true;
            button.textContent = aiWebSite.strings.activating || 'Activating...';
            statusDiv.innerHTML = '<div style="color: #0073aa;">Activating UMP license...</div>';

            // Make AJAX request
            fetch(aiWebSite.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'activate_ump_license',
                    nonce: aiWebSite.nonce
                })
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        var message = data.data.message || 'UMP license activated successfully!';
                        statusDiv.innerHTML = '<div style="color: #46b450;">✓ ' + message + '</div>';

                        // If there's a redirect URL, show it as a link
                        if (data.data.redirect_url) {
                            statusDiv.innerHTML += '<div style="margin-top: 10px;"><a href="' + data.data.redirect_url + '" class="button button-primary" target="_blank">Go to UMP License Page</a></div>';
                        }
                    } else {
                        statusDiv.innerHTML = '<div style="color: #dc3232;">✗ ' + (data.data || 'Failed to activate UMP license.') + '</div>';
                    }
                })
                .catch(function (error) {
                    console.error('Error:', error);
                    statusDiv.innerHTML = '<div style="color: #dc3232;">✗ Error activating UMP license.</div>';
                })
                .finally(function () {
                    // Re-enable button
                    button.disabled = false;
                    button.textContent = originalText;
                });
        });
    }

    // Tab functionality
    var tabLinks = document.querySelectorAll('.nav-tab');
    var tabContents = document.querySelectorAll('.tab-content');

    // Function to activate a specific tab
    function activateTab(targetTab) {
        // Remove active class from all tabs and contents
        tabLinks.forEach(function (link) {
            link.classList.remove('nav-tab-active');
        });
        tabContents.forEach(function (content) {
            content.classList.remove('active');
        });

        // Add active class to target tab and corresponding content
        var targetLink = document.querySelector('.nav-tab[data-tab="' + targetTab + '"]');
        var targetContent = document.getElementById(targetTab);
        
        if (targetLink && targetContent) {
            targetLink.classList.add('nav-tab-active');
            targetContent.classList.add('active');
        }
    }

    // Handle tab clicks
    tabLinks.forEach(function (tabLink) {
        tabLink.addEventListener('click', function (e) {
            e.preventDefault();

            var targetTab = this.getAttribute('data-tab');
            
            // Update URL hash
            window.location.hash = targetTab;
            
            // Activate the tab
            activateTab(targetTab);
        });
    });

    // On page load, check URL hash and activate corresponding tab
    function initializeTabFromHash() {
        var hash = window.location.hash.substring(1); // Remove the # character
        
        if (hash) {
            // Check if the hash corresponds to a valid tab
            var targetContent = document.getElementById(hash);
            if (targetContent && targetContent.classList.contains('tab-content')) {
                activateTab(hash);
            }
        }
    }

    // Initialize tab from URL hash on page load
    initializeTabFromHash();

    // Handle browser back/forward buttons
    window.addEventListener('hashchange', function() {
        initializeTabFromHash();
    });

    // Copy shortcode functionality
    var copyButtons = document.querySelectorAll('.copy-shortcode');
    copyButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var shortcode = this.getAttribute('data-shortcode');

            // Create temporary textarea to copy text
            var textarea = document.createElement('textarea');
            textarea.value = shortcode;
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');

                // Show success feedback
                var originalText = this.textContent;
                this.textContent = 'Copied!';
                this.style.backgroundColor = '#46b450';
                this.style.color = 'white';

                setTimeout(function () {
                    button.textContent = originalText;
                    button.style.backgroundColor = '';
                    button.style.color = '';
                }, 2000);

            } catch (err) {
                console.error('Failed to copy shortcode:', err);
                alert('Failed to copy shortcode. Please copy manually: ' + shortcode);
            }

            document.body.removeChild(textarea);
        });
    });

    // ========================================
    // WEBSITE MANAGEMENT FUNCTIONALITY
    // ========================================

    // Handle add subdomain for existing websites
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('ai-add-subdomain')) {
            e.preventDefault();
            
            var button = e.target;
            var form = button.closest('.ai-subdomain-form');
            var siteId = form.dataset.siteId;
            var input = form.querySelector('.ai-subdomain-input');
            var messageSpan = form.querySelector('.ai-subdomain-message');
            var subdomain = input.value.trim();
            
            if (!subdomain) {
                showSubdomainMessage(messageSpan, 'error', 'Please enter a subdomain name');
                return;
            }
            
            // Validate subdomain format
            if (!/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$/.test(subdomain)) {
                showSubdomainMessage(messageSpan, 'error', 'Invalid subdomain format. Use only letters, numbers, and hyphens.');
                return;
            }
            
            // Show loading state
            button.disabled = true;
            button.textContent = 'Adding...';
            showSubdomainMessage(messageSpan, '', 'Adding subdomain...');
            
            // Make AJAX request
            fetch('/wp-json/ai-web-site/v1/add-subdomain', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.aiWebSiteUserSites?.nonce || ''
                },
                body: JSON.stringify({
                    website_id: siteId,
                    subdomain_name: subdomain
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSubdomainMessage(messageSpan, 'success', 'Subdomain added successfully!');
                    input.value = '';
                    
                    // Reload page after 2 seconds to show updated URL
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showSubdomainMessage(messageSpan, 'error', data.message || 'Failed to add subdomain');
                }
            })
            .catch(error => {
                console.error('Error adding subdomain:', error);
                showSubdomainMessage(messageSpan, 'error', 'Network error occurred');
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = 'Add Subdomain';
            });
        }
    });
    
    // Handle delete website
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('ai-delete-site')) {
            e.preventDefault();
            
            var button = e.target;
            var siteId = button.dataset.siteId;
            
            if (!confirm('Are you sure you want to delete this website? This action cannot be undone.')) {
                return;
            }
            
            // Show loading state
            button.disabled = true;
            button.textContent = 'Deleting...';
            
            // Make AJAX request
            fetch('/wp-json/ai-web-site/v1/delete-website/' + siteId, {
                method: 'DELETE',
                credentials: 'include',
                headers: {
                    'X-WP-Nonce': window.aiWebSiteUserSites?.nonce || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove row with animation
                    var row = button.closest('tr');
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-100%)';
                    
                    setTimeout(() => {
                        row.remove();
                        
                        // Check if table is empty
                        var tbody = row.closest('tbody');
                        if (tbody && tbody.children.length === 0) {
                            // Show empty state
                            var table = tbody.closest('table');
                            var emptyMessage = document.createElement('p');
                            emptyMessage.textContent = 'You have not created any websites yet. Start by saving your configuration in the editor.';
                            table.parentNode.insertBefore(emptyMessage, table);
                            table.style.display = 'none';
                        }
                    }, 300);
                } else {
                    alert('Failed to delete website: ' + (data.message || 'Unknown error'));
                    button.disabled = false;
                    button.textContent = 'Delete Site';
                }
            })
            .catch(error => {
                console.error('Error deleting website:', error);
                alert('Network error occurred');
                button.disabled = false;
                button.textContent = 'Delete Site';
            });
        }
    });
    
    // Helper function to show subdomain messages
    function showSubdomainMessage(element, type, message) {
        if (!element) return;
        
        element.textContent = message;
        element.className = 'ai-subdomain-message';
        
        if (type === 'success') {
            element.classList.add('success');
        } else if (type === 'error') {
            element.classList.add('error');
        }
        
        // Auto-hide success messages after 3 seconds
        if (type === 'success') {
            setTimeout(() => {
                element.textContent = '';
                element.className = 'ai-subdomain-message';
            }, 3000);
        }
    }
    
    // Real-time subdomain validation
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('ai-subdomain-input')) {
            var input = e.target;
            var value = input.value.trim();
            var messageSpan = input.closest('.ai-subdomain-form').querySelector('.ai-subdomain-message');
            
            if (value && !/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$/.test(value)) {
                input.classList.add('error');
                showSubdomainMessage(messageSpan, 'error', 'Invalid format. Use letters, numbers, and hyphens only.');
            } else {
                input.classList.remove('error');
                if (messageSpan && messageSpan.classList.contains('error')) {
                    showSubdomainMessage(messageSpan, '', '');
                }
            }
        }
    });

});
