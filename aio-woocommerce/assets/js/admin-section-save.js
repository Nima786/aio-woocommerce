/**
 * Section-based save functionality
 * Each section has its own save button
 */

(function() {
    'use strict';
    
    // Check if required object exists
    if (typeof aioWcSectionSave === 'undefined') {
        console.error('❌ aioWcSectionSave is not defined. Script may not be loaded correctly.');
        return;
    }
    
    const { ajax_url, nonce, action } = aioWcSectionSave;
    
    // Store button handlers to avoid duplicates
    const attachedButtons = new WeakSet();
    
    function handleSectionSave(event, button) {
        // Prevent any default behavior
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Get button from parameter or event
        if (!button && event) {
            button = event.target.closest('.aio-wc-section-save-btn');
        }
        
        if (!button) {
            console.error('❌ Save button not found');
            return false;
        }
        
        // Prevent double-clicks
        if (button.disabled || button.classList.contains('saving')) {
            return false;
        }
        
        const section = button.closest('.aio-wc-card');
        const form = document.getElementById('aio-wc-settings-form');
        
        if (!section) {
            console.error('❌ Section (.aio-wc-card) not found');
            return false;
        }
        
        if (!form) {
            console.error('❌ Form (#aio-wc-settings-form) not found');
            return false;
        }
        
        // CRITICAL: Ensure cart_rules hidden field is up-to-date before collecting form data
        // Blur any focused input in the cart rules table to trigger its change handler
        // This ensures the hidden field is updated with the latest values
        const cartRulesHiddenField = document.getElementById('aio_wc_cart_rules');
        if (cartRulesHiddenField) {
            const tableBody = document.querySelector('.aio-wc-cart-rules__rows');
            if (tableBody) {
                // Find any focused input in the cart rules table
                const activeInput = tableBody.querySelector('input:focus');
                if (activeInput) {
                    // Blur it to trigger the change handler which updates the hidden field
                    activeInput.blur();
                    // Force a synchronous update by triggering the change event
                    activeInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }
        
        // Mark as saving
        button.disabled = true;
        button.classList.add('saving');
        const originalText = button.textContent;
        button.textContent = 'Saving...';
        
        // Show saving message
        showMessage(section, 'Saving...', 'info');
        
        // Collect form data
        const payload = collectFormData(form);
        
        // Fallback: Try to get cart_rules directly from the hidden field if not in payload
        if (!payload.cart_rules && cartRulesHiddenField && cartRulesHiddenField.value) {
            payload.cart_rules = cartRulesHiddenField.value;
        }
        
        const formData = buildFormData(payload);
        
        // Send AJAX request
        
        fetch(ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error('HTTP error! status: ' + response.status);
                    });
                }
                // Try to parse as JSON, but handle non-JSON responses gracefully
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error('Server returned non-JSON response. This may indicate a PHP error.');
                    });
                }
            })
            .then(data => {
                if (data && data.success) {
                    showMessage(section, 'Settings saved successfully!', 'success');
                    setTimeout(() => hideMessage(section), 3000);
                } else {
                    const errorMsg = data && data.data && data.data.message ? data.data.message : 'Error saving settings.';
                    showMessage(section, errorMsg, 'error');
                }
            })
            .catch(error => {
                showMessage(section, 'An error occurred while saving: ' + error.message, 'error');
                // Don't let errors cause page navigation or blank screen
                return false;
            })
            .finally(() => {
                button.disabled = false;
                button.classList.remove('saving');
                button.textContent = originalText;
            });
        
        return true;
    }
    
    function collectFormData(form) {
        const data = {};
        
        // Process all inputs except hidden inputs that are part of toggle switches
        // BUT include hidden inputs that are legitimate form fields (like cart_rules JSON)
        const visibleInputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
        const hiddenInputs = form.querySelectorAll('input[type="hidden"][name^="aio_wc_settings["]');
        
        // Filter out toggle switch hidden inputs
        // Toggle switches have a hidden input with value="no" followed by a checkbox with the same name
        // We want to exclude those hidden inputs because the checkbox will be processed instead
        // But we want to include legitimate hidden fields like cart_rules (which contains JSON data)
        const legitimateHiddenInputs = Array.from(hiddenInputs).filter(input => {
            const name = input.name;
            const value = input.value;
            
            // Always include cart_rules (it's a JSON string containing the rules)
            if (name === 'aio_wc_settings[cart_rules]') {
                return true;
            }
            
            // Check if this hidden input is part of a toggle switch
            // Toggle switches have: hidden input (value="no") + checkbox (same name)
            // If there's a checkbox with the same name, this is a toggle switch hidden input - exclude it
            const hasCheckbox = form.querySelector(`input[type="checkbox"][name="${name}"]`);
            if (hasCheckbox) {
                return false; // This is a toggle switch hidden input, exclude it
            }
            
            // Include all other hidden inputs (they might be legitimate form fields)
            return true;
        });
        
        const inputs = [...visibleInputs, ...legitimateHiddenInputs];
        
        inputs.forEach(input => {
            if (input.type === 'button' || input.type === 'submit') {
                return;
            }
            
            const name = input.name;
            if (!name || name.indexOf('aio_wc_settings[') !== 0) {
                return;
            }
            
            // Handle nested arrays like payment_gateway_zarinpal[enabled]
            // Try standard format: aio_wc_settings[parent][child]
            let nestedMatch = name.match(/^aio_wc_settings\[([^\]]+)\]\[([^\]]+)\](?:\[\])?$/);
            let parentField, childField;
            
            // If that doesn't match, try alternate format: aio_wc_settings[parent[child]]
            // This happens when PHP generates: aio_wc_settings[payment_gateway_zarinpal[enabled]]
            if (!nestedMatch) {
                const altMatch = name.match(/^aio_wc_settings\[([^\[]+)\[([^\]]+)\]\]$/);
                if (altMatch) {
                    parentField = altMatch[1];
                    childField = altMatch[2];
                    nestedMatch = altMatch; // Set to truthy so we process it
                }
            } else {
                parentField = nestedMatch[1];
                childField = nestedMatch[2];
            }
            
            if (nestedMatch) {
                if (!data[parentField]) {
                    data[parentField] = {};
                }
                
                if (input.type === 'checkbox') {
                    // For checkboxes, only set if checked (unchecked will be handled by hidden input or explicit 'no')
                    if (input.checked) {
                        data[parentField][childField] = input.value || 'yes';
                    } else {
                        // Explicitly set to 'no' for unchecked checkboxes
                        data[parentField][childField] = 'no';
                    }
                } else {
                    if (input.multiple) {
                        const selected = Array.from(input.selectedOptions || []).map(option => option.value);
                        data[parentField][childField] = selected.length > 0 ? selected : [];
                    } else {
                        const rawValue = input.getAttribute('data-raw-value');
                        data[parentField][childField] = rawValue !== null ? rawValue : (input.value || '');
                    }
                }
                return;
            }
            
            const match = name.match(/^aio_wc_settings\[([^\]]+)\](?:\[\])?$/);
            if (!match) {
                return;
            }
            
            const field = match[1];
            const isArrayField = /\[\]$/.test(name);
            
            if (input.type === 'checkbox') {
                if (input.checked) {
                    if (isArrayField) {
                        if (!Array.isArray(data[field])) {
                            data[field] = [];
                        }
                        data[field].push(input.value || 'yes');
                    } else {
                        data[field] = input.value || 'yes';
                    }
                } else {
                    if (!isArrayField) {
                        data[field] = 'no';
                    }
                }
            } else {
                if (input.multiple) {
                    const selected = Array.from(input.selectedOptions || []).map(option => option.value);
                    data[field] = selected;
                } else {
                    const rawValue = input.getAttribute('data-raw-value');
                    if (rawValue !== null) {
                        data[field] = rawValue;
                    } else {
                        data[field] = input.value || '';
                    }
                }
            }
        });
        
        // Second pass: Only process nested object fields (like payment_gateway_zarinpal[enabled])
        // IMPORTANT: Skip array fields (like cart_rule_excluded_tags[]) - they're handled correctly in first pass
        // Array fields: unchecked checkboxes should NOT send a value (which is what first pass does)
        const allCheckboxes = form.querySelectorAll('input[type="checkbox"][name^="aio_wc_settings["]');
        allCheckboxes.forEach(checkbox => {
            const name = checkbox.name;
            
            // CRITICAL: Skip array fields (they end with []) - these are handled correctly in first pass
            // Array fields like cart_rule_excluded_tags[] should only include checked items
            if (name.endsWith('[]')) {
                return; // Skip - first pass handles arrays correctly
            }
            
            // Try standard nested format: aio_wc_settings[parent][child] (NOT array, so no [] at end)
            let nestedMatch = name.match(/^aio_wc_settings\[([^\]]+)\]\[([^\]]+)\]$/);
            let parentField, childField;
            
            // If that doesn't match, try the alternate format: aio_wc_settings[parent[child]]
            // This happens when the PHP generates: aio_wc_settings[payment_gateway_zarinpal[enabled]]
            if (!nestedMatch) {
                const altMatch = name.match(/^aio_wc_settings\[([^\[]+)\[([^\]]+)\]\]$/);
                if (altMatch) {
                    parentField = altMatch[1];
                    childField = altMatch[2];
                    nestedMatch = altMatch; // Set to truthy so we process it
                }
            } else {
                parentField = nestedMatch[1];
                childField = nestedMatch[2];
            }
            
            if (nestedMatch) {
                // This is a nested object field (like payment_gateway_zarinpal[enabled] or allowed_roles[administrator])
                if (!data[parentField]) {
                    data[parentField] = {};
                }
                
                // Force update based on current checkbox state
                // This is critical for toggles - we need the current checked state
                // Explicitly set 'yes' or 'no' to ensure proper handling
                const value = checkbox.checked ? (checkbox.value || 'yes') : 'no';
                data[parentField][childField] = value;
                
                return;
            }
            
            // For non-array, non-nested fields (simple toggles like cart_minimum_enabled)
            const match = name.match(/^aio_wc_settings\[([^\]]+)\]$/);
            if (match) {
                const field = match[1];
                // Only process if this is a simple toggle (not an array field - we already checked)
                data[field] = checkbox.checked ? (checkbox.value || 'yes') : 'no';
            }
        });
        
        return data;
    }
    
    function buildFormData(payload) {
        const formData = new FormData();
        
        Object.keys(payload).forEach(key => {
            const value = payload[key];
            if (Array.isArray(value)) {
                // For arrays, send all values (or nothing if empty)
                // Empty arrays are handled server-side by checking if field exists
                // We always include array fields in payload, even if empty
                value.forEach(val => {
                    // Filter out empty strings (they shouldn't be in the array anyway)
                    if (val !== '') {
                        formData.append(`aio_wc_settings[${key}][]`, val);
                    }
                });
                // Note: If array is empty, nothing is appended, but the field is still in payload object
                // Server-side code checks if field exists in input to determine if it should be cleared
            } else if (typeof value === 'object' && value !== null) {
                Object.keys(value).forEach(subKey => {
                    const subValue = value[subKey];
                    if (Array.isArray(subValue)) {
                        subValue.forEach(val => {
                            formData.append(`aio_wc_settings[${key}][${subKey}][]`, val);
                        });
                    } else {
                        formData.append(`aio_wc_settings[${key}][${subKey}]`, subValue);
                    }
                });
            } else {
                formData.append(`aio_wc_settings[${key}]`, value);
            }
        });
        
        formData.append('action', action);
        formData.append('nonce', nonce);
        
        return formData;
    }
    
    function showMessage(section, message, type) {
        const existing = section.querySelector('.aio-wc-section-message');
        if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing);
        }
        
        const messageEl = document.createElement('div');
        messageEl.className = `aio-wc-section-message aio-wc-section-message--${type}`;
        messageEl.textContent = message;
        messageEl.setAttribute('role', 'status');
        messageEl.setAttribute('aria-live', 'polite');
        
        const actions = section.querySelector('.aio-wc-card__actions');
        if (actions) {
            actions.insertBefore(messageEl, actions.firstChild);
        } else {
            section.insertBefore(messageEl, section.firstChild);
        }
        
        requestAnimationFrame(() => {
            messageEl.classList.add('aio-wc-section-message--visible');
        });
    }
    
    function hideMessage(section) {
        const messageEl = section.querySelector('.aio-wc-section-message');
        if (!messageEl) {
            return;
        }
        
        messageEl.classList.remove('aio-wc-section-message--visible');
        setTimeout(() => {
            if (messageEl && messageEl.parentNode) {
                messageEl.parentNode.removeChild(messageEl);
            }
        }, 300);
    }
    
    function attachListeners() {
        const form = document.getElementById('aio-wc-settings-form');
        if (!form) {
            return;
        }
        
        // Find all save buttons in the entire document (including hidden tabs)
        const saveButtons = document.querySelectorAll('.aio-wc-section-save-btn');
        
        saveButtons.forEach((button) => {
            // Skip if already attached
            if (attachedButtons.has(button)) {
                return;
            }
            
            // Verify button is within the form
            if (!form.contains(button)) {
                return;
            }
            
            // Mark as attached
            attachedButtons.add(button);
            
            // Attach click handler directly to button
            button.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                handleSectionSave(event, button);
            });
        });
    }
    
    // Initialize when DOM is ready
    function init() {
        const form = document.getElementById('aio-wc-settings-form');
        if (!form) {
            console.error('Form #aio-wc-settings-form not found');
            return;
        }
        
        // Attach listeners immediately
        attachListeners();
        
       // Use event delegation on document as backup (catches everything, even dynamically added buttons)
       document.addEventListener('click', function(event) {
           const button = event.target.closest('.aio-wc-section-save-btn');
           if (button) {
               const form = document.getElementById('aio-wc-settings-form');
               if (form && form.contains(button)) {
                   event.preventDefault();
                   event.stopPropagation();
                   handleSectionSave(event, button);
               }
           }
       }, true); // Use capture phase to catch early, before other handlers
    }
    
    // Run initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Re-attach listeners after delays (for buttons in hidden tabs that load later)
    setTimeout(attachListeners, 100);
    setTimeout(attachListeners, 500);
    setTimeout(attachListeners, 1000);
    setTimeout(attachListeners, 2000); // Extra delay for slow loading
    
    // Re-attach when tab changes (payment gateways tab might be shown)
    window.addEventListener('hashchange', function() {
        setTimeout(attachListeners, 200);
    });
    
    // Re-attach when clicking tab links
    document.addEventListener('click', function(event) {
        if (event.target.closest('.aio-wc-sidebar__link')) {
            setTimeout(attachListeners, 300);
        }
    }, true);
})();
