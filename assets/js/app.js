/**
 * Main Application JavaScript
 * Library Management System
 */

// ============================================
// DOM Ready
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initSidebar();
    initDropdowns();
    initNotifications();
    initForms();
    initModals();
    initConfirmDialogs();
    initToastClose();
});

// ============================================
// Sidebar
// ============================================
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const body = document.body;
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        });
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            sidebar.classList.remove('open');
            body.style.overflow = '';
        });
    }
    
    // Close sidebar on outside click (mobile)
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            const isSidebar = sidebar.contains(e.target);
            const isToggle = toggleBtn && toggleBtn.contains(e.target);
            if (!isSidebar && !isToggle && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                body.style.overflow = '';
            }
        }
    });
    
    // Sidebar toggle (submenu)
    const toggles = document.querySelectorAll('.nav-link-toggle');
    toggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const target = document.getElementById(targetId);
            if (target) {
                const isExpanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', !isExpanded);
                target.classList.toggle('show');
            }
        });
    });
}

// ============================================
// Dropdowns
// ============================================
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(function(dropdown) {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            // Toggle on click
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = dropdown.classList.contains('show');
                
                // Close all other dropdowns
                document.querySelectorAll('.dropdown.show').forEach(function(d) {
                    if (d !== dropdown) {
                        d.classList.remove('show');
                    }
                });
                
                dropdown.classList.toggle('show');
            });
            
            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
            
            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            });
        }
    });
}

// ============================================
// Notifications (Toast)
// ============================================
function showToast(message, type = 'info', duration = 5000) {
    const container = document.querySelector('.toast-container') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span>${message}</span>
        <button class="toast-close">&times;</button>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after duration
    const timeout = setTimeout(function() {
        removeToast(toast);
    }, duration);
    
    // Close button
    const closeBtn = toast.querySelector('.toast-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            clearTimeout(timeout);
            removeToast(toast);
        });
    }
    
    // Close on click
    toast.addEventListener('click', function(e) {
        if (e.target === toast) {
            clearTimeout(timeout);
            removeToast(toast);
        }
    });
    
    return toast;
}

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

function removeToast(toast) {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(100%)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(function() {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 300);
}

function initToastClose() {
    document.querySelectorAll('.toast .toast-close').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const toast = this.closest('.toast');
            if (toast) {
                removeToast(toast);
            }
        });
    });
}

// ============================================
// Forms
// ============================================
function initForms() {
    // Auto-remove invalid state on input
    document.querySelectorAll('.form-control').forEach(function(input) {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
            const feedback = this.closest('.form-group').querySelector('.text-danger');
            if (feedback) {
                feedback.textContent = '';
            }
        });
        
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && this.value.trim() === '') {
                this.classList.add('is-invalid');
                const feedback = this.closest('.form-group').querySelector('.text-danger');
                if (feedback) {
                    feedback.textContent = 'This field is required';
                }
            }
        });
    });
    
    // Form validation on submit
    document.querySelectorAll('form[data-validate]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const inputs = this.querySelectorAll('.form-control[required]');
            
            inputs.forEach(function(input) {
                if (input.value.trim() === '') {
                    input.classList.add('is-invalid');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showToast('Please fill in all required fields', 'warning');
            }
        });
    });
}

// ============================================
// Modals
// ============================================
function initModals() {
    // Open modal triggers
    document.querySelectorAll('[data-modal]').forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                openModal(modal);
            }
        });
    });
    
    // Close modal triggers
    document.querySelectorAll('[data-dismiss="modal"]').forEach(function(closeBtn) {
        closeBtn.addEventListener('click', function() {
            const modal = this.closest('.modal-backdrop');
            if (modal) {
                closeModal(modal);
            }
        });
    });
    
    // Close on backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
        backdrop.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this);
            }
        });
    });
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.show').forEach(function(backdrop) {
                closeModal(backdrop);
            });
        }
    });
}

function openModal(modal) {
    const backdrop = modal.closest('.modal-backdrop') || modal.parentElement;
    backdrop.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(backdrop) {
    backdrop.classList.remove('show');
    document.body.style.overflow = '';
}

// ============================================
// Confirm Dialogs
// ============================================
function initConfirmDialogs() {
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

// ============================================
// AJAX Helper
// ============================================
function ajaxRequest(url, method = 'GET', data = null, headers = {}) {
    return new Promise(function(resolve, reject) {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        // Add CSRF token if available
        const csrfToken = document.querySelector('input[name="csrf_token"]');
        if (csrfToken) {
            xhr.setRequestHeader('X-CSRF-Token', csrfToken.value);
        }
        
        // Custom headers
        for (const key in headers) {
            xhr.setRequestHeader(key, headers[key]);
        }
        
        if (data && typeof data === 'object') {
            xhr.setRequestHeader('Content-Type', 'application/json');
        }
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error(xhr.statusText));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error'));
        };
        
        const body = data && typeof data === 'object' ? JSON.stringify(data) : data;
        xhr.send(body);
    });
}

// ============================================
// Search Autocomplete
// ============================================
function initAutocomplete(inputSelector, url, options = {}) {
    const inputs = document.querySelectorAll(inputSelector);
    
    inputs.forEach(function(input) {
        let timeout = null;
        let resultsContainer = null;
        
        input.addEventListener('input', function() {
            const query = this.value.trim();
            
            clearTimeout(timeout);
            
            if (query.length < 2) {
                if (resultsContainer) {
                    resultsContainer.remove();
                    resultsContainer = null;
                }
                return;
            }
            
            timeout = setTimeout(function() {
                performAutocomplete(input, query, url, options);
            }, 300);
        });
        
        input.addEventListener('blur', function() {
            setTimeout(function() {
                if (resultsContainer) {
                    resultsContainer.remove();
                    resultsContainer = null;
                }
            }, 200);
        });
        
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && resultsContainer) {
                resultsContainer.remove();
                resultsContainer = null;
            }
        });
    });
}

function performAutocomplete(input, query, url, options) {
    ajaxRequest(url + '?q=' + encodeURIComponent(query), 'GET')
        .then(function(data) {
            let results = data;
            if (options.transform) {
                results = options.transform(data);
            }
            
            const container = document.createElement('div');
            container.className = 'autocomplete-results';
            container.style.cssText = `
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                max-height: 300px;
                overflow-y: auto;
                z-index: 1000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                margin-top: 4px;
            `;
            
            if (results.length === 0) {
                container.innerHTML = '<div class="autocomplete-item" style="padding: 10px 16px; color: #a0aec0;">No results found</div>';
            } else {
                results.forEach(function(item) {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.style.cssText = `
                        padding: 10px 16px;
                        cursor: pointer;
                        transition: background 0.2s;
                    `;
                    div.textContent = item.label || item.name || item.title || item.text;
                    
                    if (options.itemTemplate) {
                        div.innerHTML = options.itemTemplate(item);
                    }
                    
                    div.addEventListener('mouseenter', function() {
                        this.style.background = '#f7fafc';
                    });
                    
                    div.addEventListener('mouseleave', function() {
                        this.style.background = 'transparent';
                    });
                    
                    div.addEventListener('click', function() {
                        if (options.onSelect) {
                            options.onSelect(item, input);
                        } else {
                            input.value = item.label || item.name || item.title || item.text;
                        }
                        container.remove();
                    });
                    
                    container.appendChild(div);
                });
            }
            
            // Position the container
            const rect = input.getBoundingClientRect();
            container.style.width = rect.width + 'px';
            
            // Remove old container
            const oldContainer = input.parentElement.querySelector('.autocomplete-results');
            if (oldContainer) {
                oldContainer.remove();
            }
            
            input.parentElement.style.position = 'relative';
            input.parentElement.appendChild(container);
        })
        .catch(function(error) {
            console.error('Autocomplete error:', error);
        });
}