jQuery(document).ready(function($) {
    
    // Event form handling
    $('#aef-event-form').on('submit', function(e) {
        e.preventDefault();
        saveEvent();
    });

    // Event type selection handler
    $('#event_name').on('change', function() {
        const selectedOption = $(this).find(':selected');
        const eventData = JSON.parse(selectedOption.attr('data-context') || '[]');
        const eventKey = selectedOption.val();
        
        updateEventDescription(eventKey);
        updateAvailableVariables(eventData);
    });

    // Test API call button
    $('#test-api-call').on('click', function(e) {
        e.preventDefault();
        testApiCall();
    });

    // Event management buttons
    $('.edit-event').on('click', function() {
        const eventId = $(this).data('event-id');
        editEvent(eventId);
    });

    $('.delete-event').on('click', function() {
        const eventId = $(this).data('event-id');
        if (confirm(aef_ajax.strings.confirm_delete)) {
            deleteEvent(eventId);
        }
    });

    $('.toggle-event').on('click', function() {
        const eventId = $(this).data('event-id');
        toggleEvent(eventId);
    });

    // Log management buttons
    $('.view-log-details').on('click', function() {
        const logId = $(this).data('log-id');
        viewLogDetails(logId);
    });

    $('.retry-failed-call').on('click', function() {
        const logId = $(this).data('log-id');
        retryFailedCall(logId);
    });

    // Modal handling
    $(document).on('click', '.aef-modal-close, .aef-modal', function(e) {
        if (e.target === this) {
            $('.aef-modal').hide();
        }
    });

    function saveEvent() {
        const formData = new FormData();
        const form = $('#aef-event-form')[0];
        
        // Get all form data
        $(form).find('input, select, textarea').each(function() {
            const input = $(this);
            const name = input.attr('name');
            const type = input.attr('type');
            
            if (type === 'checkbox') {
                formData.append(name, input.is(':checked') ? '1' : '0');
            } else {
                formData.append(name, input.val());
            }
        });
        
        formData.append('action', 'aef_save_event');
        formData.append('nonce', aef_ajax.nonce);

        // Add loading state
        const form$ = $('#aef-event-form');
        form$.addClass('aef-loading');

        $.ajax({
            url: aef_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                form$.removeClass('aef-loading');
                
                if (response.success) {
                    showMessage(aef_ajax.strings.saved, 'success');
                    location.reload(); // Reload to show updated list
                } else {
                    showMessage(response.data, 'error');
                }
            },
            error: function() {
                form$.removeClass('aef-loading');
                showMessage('An error occurred while saving the event.', 'error');
            }
        });
    }

    function testApiCall() {
        const endpoint = $('#api_endpoint').val();
        const method = $('#http_method').val();
        const headers = $('#headers').val();
        const payload = $('#payload_template').val();

        if (!endpoint) {
            showMessage('Please enter an API endpoint first.', 'error');
            return;
        }

        const button = $('#test-api-call');
        button.prop('disabled', true).text(aef_ajax.strings.loading);

        $.ajax({
            url: aef_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aef_test_api_call',
                nonce: aef_ajax.nonce,
                endpoint: endpoint,
                method: method,
                headers: headers,
                payload: payload
            },
            success: function(response) {
                button.prop('disabled', false).text('Test API Call');
                
                if (response.success) {
                    showTestResult(true, response.data);
                } else {
                    showTestResult(false, response.data);
                }
            },
            error: function() {
                button.prop('disabled', false).text('Test API Call');
                showTestResult(false, 'Network error occurred');
            }
        });
    }

    function deleteEvent(eventId) {
        $.ajax({
            url: aef_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aef_delete_event',
                nonce: aef_ajax.nonce,
                event_id: eventId
            },
            success: function(response) {
                if (response.success) {
                    $('tr[data-event-id="' + eventId + '"]').fadeOut(function() {
                        $(this).remove();
                    });
                    showMessage(response.data, 'success');
                } else {
                    showMessage(response.data, 'error');
                }
            },
            error: function() {
                showMessage('An error occurred while deleting the event.', 'error');
            }
        });
    }

    function toggleEvent(eventId) {
        const button = $('.toggle-event[data-event-id="' + eventId + '"]');
        const originalText = button.text();
        
        button.prop('disabled', true).text(aef_ajax.strings.loading);

        $.ajax({
            url: aef_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aef_toggle_event',
                nonce: aef_ajax.nonce,
                event_id: eventId
            },
            success: function(response) {
                button.prop('disabled', false);
                
                if (response.success) {
                    const row = $('tr[data-event-id="' + eventId + '"]');
                    const statusCell = row.find('.aef-status');
                    
                    // Update status display
                    statusCell.text(response.data.status_text);
                    statusCell.removeClass('aef-status-active aef-status-inactive');
                    statusCell.addClass(response.data.status ? 'aef-status-active' : 'aef-status-inactive');
                    
                    // Update button text
                    button.text(response.data.status ? 'Disable' : 'Enable');
                    
                    showMessage('Event status updated successfully.', 'success');
                } else {
                    button.text(originalText);
                    showMessage(response.data, 'error');
                }
            },
            error: function() {
                button.prop('disabled', false).text(originalText);
                showMessage('An error occurred while updating the event status.', 'error');
            }
        });
    }

    function retryFailedCall(logId) {
        const button = $('.retry-failed-call[data-log-id="' + logId + '"]');
        const originalText = button.text();
        
        button.prop('disabled', true).text(aef_ajax.strings.loading);

        $.ajax({
            url: aef_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aef_retry_failed_call',
                nonce: aef_ajax.nonce,
                log_id: logId
            },
            success: function(response) {
                button.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    showMessage(response.data, 'success');
                    // Optionally reload the page to show updated status
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage(response.data, 'error');
                }
            },
            error: function() {
                button.prop('disabled', false).text(originalText);
                showMessage('An error occurred while retrying the API call.', 'error');
            }
        });
    }

    function updateEventDescription(eventKey) {
        const eventDescriptions = {
            'user_register': 'Triggered when a new user registers on your site.',
            'wp_login': 'Triggered when a user successfully logs in.',
            'wp_logout': 'Triggered when a user logs out.',
            'profile_update': 'Triggered when a user updates their profile information.',
            'publish_post': 'Triggered when a post is published.',
            'wp_insert_comment': 'Triggered when a new comment is added and approved.'
        };

        const description = eventDescriptions[eventKey] || '';
        $('#event-description').text(description);
    }

    function updateAvailableVariables(variables) {
        const container = $('#available-variables');
        
        if (variables && variables.length > 0) {
            let html = '<strong>Available Variables:</strong><br>';
            variables.forEach(function(variable) {
                html += '<code>{{' + variable + '}}</code> ';
            });
            html += '<br><small>Additional system variables: <code>{{timestamp}}</code>, <code>{{site_url}}</code>, <code>{{site_name}}</code></small>';
            container.html(html).show();
        } else {
            container.hide();
        }
    }

    function showTestResult(success, data) {
        // Remove existing test results
        $('.aef-test-result').remove();
        
        const resultClass = success ? 'success' : 'error';
        const title = success ? aef_ajax.strings.test_success : aef_ajax.strings.test_failed;
        
        let resultHtml = '<div class="aef-test-result ' + resultClass + '">';
        resultHtml += '<strong>' + title + '</strong>';
        
        if (success && data) {
            resultHtml += '<p>Response Code: ' + data.response_code + '</p>';
            if (data.response_body) {
                resultHtml += '<p>Response Body:</p><pre>' + escapeHtml(data.response_body) + '</pre>';
            }
            if (data.sent_data) {
                resultHtml += '<p>Sent Data:</p><pre>' + escapeHtml(data.sent_data) + '</pre>';
            }
        } else if (!success) {
            resultHtml += '<p>' + escapeHtml(data) + '</p>';
        }
        
        resultHtml += '</div>';
        
        $('#aef-event-form').append(resultHtml);
        
        // Scroll to result
        $('html, body').animate({
            scrollTop: $('.aef-test-result').offset().top - 50
        }, 500);
    }

    function showMessage(message, type) {
        // Create WordPress-style admin notice
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + escapeHtml(message) + '</p></div>');
        
        // Add dismiss button functionality
        notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
        
        // Insert after the page title
        $('.wrap h1').after(notice);
        
        // Handle dismiss button
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        });
        
        // Auto-dismiss success messages
        if (type === 'success') {
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Scroll to notice
        $('html, body').animate({
            scrollTop: notice.offset().top - 50
        }, 300);
    }

    function viewLogDetails(logId) {
        // This would fetch and display detailed log information in a modal
        // For now, just show a placeholder
        showModal('Log Details', '<p>Detailed log view for log ID: ' + logId + '</p><p><em>This feature will be implemented based on your specific needs.</em></p>');
    }

    function showModal(title, content) {
        let modal = $('.aef-modal');
        
        if (modal.length === 0) {
            modal = $('<div class="aef-modal"><div class="aef-modal-content"><div class="aef-modal-header"><h2></h2><button class="aef-modal-close">&times;</button></div><div class="aef-modal-body"></div></div></div>');
            $('body').append(modal);
        }
        
        modal.find('.aef-modal-header h2').text(title);
        modal.find('.aef-modal-body').html(content);
        modal.show();
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});