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

});
