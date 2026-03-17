/**
 * Admin Logs Configuration Page JavaScript
 */
define(['jquery'], function($) {
    'use strict';

    return {
        /**
         * Initialize configuration page functionality
         */
        initConfiguration: function() {
            // Tab switching functionality
            $('.config-tab-button').on('click', function() {
                var tabName = $(this).data('tab');
                
                // Remove active class from all tabs and contents
                $('.config-tab-button').removeClass('active');
                $('.config-tab-content').removeClass('active');
                
                // Add active class to clicked tab and corresponding content
                $(this).addClass('active');
                $('#tab-' + tabName).addClass('active');
            });
            
            // Function to toggle period field visibility
            function togglePeriodField(selectId, groupId) {
                var $select = $(selectId);
                var $group = $(groupId);
                
                if ($select.val() == '1') {
                    $group.removeClass('hidden');
                } else {
                    $group.addClass('hidden');
                }
            }
            
            // Initialize period field visibility on page load
            togglePeriodField('#login_attempts_auto_cleaning', '#login_attempts_period_group');
            togglePeriodField('#actions_log_auto_cleaning', '#actions_log_period_group');
            togglePeriodField('#role_user_log_auto_cleaning', '#role_user_log_period_group');
            
            // Handle changes to auto-cleaning dropdowns
            $('#login_attempts_auto_cleaning').on('change', function() {
                togglePeriodField('#login_attempts_auto_cleaning', '#login_attempts_period_group');
            });
            
            $('#actions_log_auto_cleaning').on('change', function() {
                togglePeriodField('#actions_log_auto_cleaning', '#actions_log_period_group');
            });
            
            $('#role_user_log_auto_cleaning').on('change', function() {
                togglePeriodField('#role_user_log_auto_cleaning', '#role_user_log_period_group');
            });
            
            // Support form submission - Popup form handler
            $(document).on('submit', '#support-form', function(e) {
                // Prevent default form submission
                e.preventDefault();
                
                // Extract form data
                var email = $('#support_email, #email').val() || '';
                var phone = $('#support_phone, #phone').val() || '';
                var query = $('#support_message, #query, #message').val() || '';
                
                // Build GET URL with query parameters
                var baseUrl = $('#support-form').data('submit-url') || 
                              window.location.origin + '/admin/adminlogs/support/index';
                var url = new URL(baseUrl);
                url.searchParams.append('email', email);
                url.searchParams.append('phone', phone);
                url.searchParams.append('query', query);
                
                // Show loading state (optional)
                var $submitBtn = $(this).find('button[type="submit"], input[type="submit"]');
                var originalText = $submitBtn.text() || $submitBtn.val();
                $submitBtn.prop('disabled', true).text('Submitting...').val('Submitting...');
                
                // Make asynchronous HTTP GET request
                fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    // Handle response - show success/error message
                    var message = data.message || (data.success ? 'Your query has been submitted successfully.' : 'An error occurred.');
                    var messageClass = data.success ? 'success' : 'error';
                    
                    // Display message (you can customize this based on your UI)
                    showSupportMessage(message, messageClass);
                    
                    // Reset form on success
                    if (data.success) {
                        $('#support-form')[0].reset();
                        
                        // Auto-close popup after 5 seconds
                        setTimeout(function() {
                            closeSupportPopup();
                        }, 5000);
                    }
                })
                .catch(function(error) {
                    // Handle error
                    console.error('Support form submission error:', error);
                    showSupportMessage('An error occurred while submitting your query. Please try again later.', 'error');
                })
                .finally(function() {
                    // Restore button state
                    $submitBtn.prop('disabled', false).text(originalText).val(originalText);
                });
            });
            
            // Helper function to show support form messages
            function showSupportMessage(message, type) {
                // Remove existing messages
                $('.support-form-message').remove();
                
                // Create message element
                var $message = $('<div class="support-form-message support-form-message-' + type + '">' + message + '</div>');
                
                // Insert message (adjust selector based on your form structure)
                var $form = $('#support-form');
                if ($form.length) {
                    $form.prepend($message);
                    
                    // Auto-remove message after 5 seconds
                    setTimeout(function() {
                        $message.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 5000);
                } else {
                    // Fallback: show alert if form not found
                    alert(message);
                }
            }
            
            // Helper function to close support popup
            function closeSupportPopup() {
                // Close popup/modal (adjust selector based on your popup structure)
                $('.support-popup, .support-modal, #support-popup, #support-modal').fadeOut(function() {
                    $(this).remove();
                });
                $('.support-popup-overlay, .support-modal-overlay').fadeOut(function() {
                    $(this).remove();
                });
            }
        }
    };
});

