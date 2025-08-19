/**
 * SPECIALPROGRAM2025 - Main JavaScript
 * ===================================
 */

// Global app object
window.SpecialProgram = {
    // Configuration
    config: {
        baseUrl: window.location.origin,
        apiUrl: window.location.origin + '/api',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        debug: false
    },

    // Initialize the application
    init: function() {
        this.setupCSRF();
        this.setupAjax();
        this.setupVoting();
        this.setupForms();
        this.setupModals();
        this.setupToasts();
        this.setupCounters();
        this.bindEvents();

        console.log('SpecialProgram2025 initialized');
    },

    // Setup CSRF token for all AJAX requests
    setupCSRF: function() {
        if (this.config.csrfToken) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': this.config.csrfToken
                }
            });
        }
    },

    // Setup global AJAX handlers
    setupAjax: function() {
        $(document).ajaxStart(function() {
            $('.loading').show();
        }).ajaxStop(function() {
            $('.loading').hide();
        }).ajaxError(function(event, xhr, settings, thrownError) {
            console.error('AJAX Error:', thrownError);
            SpecialProgram.showToast('Có lỗi xảy ra. Vui lòng thử lại!', 'error');
        });
    },

    // Setup voting functionality
    setupVoting: function() {
        $(document).on('click', '.vote-btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const contestantId = $btn.data('contestant-id');

            if ($btn.hasClass('loading')) return;

            $btn.addClass('loading').prop('disabled', true);

            fetch('/api/vote', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': SpecialProgram.config.csrfToken
                },
                body: JSON.stringify({ contestant_id: contestantId })
            })
            .then(function(res) {
                if (!res.ok) {
                    return { ok: false, error: 'http_error', status: res.status };
                }
                return res.json().catch(function(){ return { ok: false, error: 'parse_error' }; });
            })
            .then(function(response) {
                if (response && response.ok) {
                    SpecialProgram.showToast('Bình chọn thành công!', 'success');
                    // Tăng count hiện tại +1 nếu có phần tử hiển thị
                    const $countEl = $(".vote-count[data-contestant-id='" + contestantId + "']");
                    if ($countEl.length) {
                        const current = parseInt($countEl.text()) || 0;
                        SpecialProgram.updateVoteCount(contestantId, current + 1);
                    }
                    $btn.text('Đã bình chọn').removeClass('btn-primary').addClass('btn-success');
                } else {
                    // Hiển thị thông điệp theo mã lỗi nếu có
                    var msg = 'Bình chọn thất bại!';
                    if (response && response.status && response.status >= 500) msg = 'Máy chủ bận, vui lòng thử lại!';
                    if (response && response.error === 'unauthorized_or_invalid') msg = 'Bạn cần đăng nhập để bình chọn!';
                    if (response && response.error === 'vote_limit') msg = 'Bạn đã đạt giới hạn bình chọn hôm nay!';
                    if (response && response.error === 'contestant_not_found') msg = 'Không tìm thấy thí sinh!';
                    if (response && response.error === 'parse_error') msg = 'Phản hồi không hợp lệ, thử lại sau!';
                    SpecialProgram.showToast(msg, 'error');
                }
            })
            .catch(function() {
                SpecialProgram.showToast('Có lỗi xảy ra khi bình chọn!', 'error');
            })
            .finally(function() {
                $btn.removeClass('loading').prop('disabled', false);
            });
        });
    },

    // Update vote count in UI
    updateVoteCount: function(contestantId, newCount) {
        $(`.vote-count[data-contestant-id="${contestantId}"]`).text(newCount);

        // Update progress bars if exist
        const $progress = $(`.vote-progress[data-contestant-id="${contestantId}"]`);
        if ($progress.length) {
            const maxVotes = Math.max(...$('.vote-count').map(function() {
                return parseInt($(this).text()) || 0;
            }).get());
            const percentage = maxVotes > 0 ? (newCount / maxVotes) * 100 : 0;
            $progress.css('width', percentage + '%');
        }
    },

    // Setup form handling
    setupForms: function() {
        // Auto-submit forms with data-auto-submit
        $(document).on('change', '[data-auto-submit]', function() {
            $(this).closest('form').submit();
        });

        // Confirm before form submission
        $(document).on('submit', '[data-confirm]', function(e) {
            const message = $(this).data('confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });

        // AJAX form submission
        $(document).on('submit', '.ajax-form', function(e) {
            e.preventDefault();

            const $form = $(this);
            const formData = new FormData(this);

            $form.find('.btn').prop('disabled', true);

            $.ajax({
                url: $form.attr('action') || window.location.href,
                method: $form.attr('method') || 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done(function(response) {
                if (response.success) {
                    SpecialProgram.showToast(response.message || 'Thành công!', 'success');

                    // Redirect if specified
                    if (response.redirect) {
                        setTimeout(() => {
                            window.location.href = response.redirect;
                        }, 1000);
                    }

                    // Reset form if specified
                    if (response.reset) {
                        $form[0].reset();
                    }
                } else {
                    SpecialProgram.showToast(response.message || 'Có lỗi xảy ra!', 'error');
                }
            })
            .fail(function() {
                SpecialProgram.showToast('Có lỗi xảy ra khi gửi form!', 'error');
            })
            .always(function() {
                $form.find('.btn').prop('disabled', false);
            });
        });
    },

    // Setup modal functionality
    setupModals: function() {
        // Open modal
        $(document).on('click', '[data-modal]', function(e) {
            e.preventDefault();
            const modalId = $(this).data('modal');
            $(`#${modalId}`).addClass('show');
        });

        // Close modal
        $(document).on('click', '.modal-close, .modal-backdrop', function() {
            $(this).closest('.modal').removeClass('show');
        });

        // Prevent modal close when clicking inside modal content
        $(document).on('click', '.modal-content', function(e) {
            e.stopPropagation();
        });
    },

    // Setup toast notifications
    setupToasts: function() {
        // Create toast container if not exists
        if (!$('#toast-container').length) {
            $('body').append('<div id="toast-container" class="toast-container"></div>');
        }
    },

    // Show toast notification
    showToast: function(message, type = 'info', duration = 3000) {
        const toast = $(`
            <div class="toast toast-${type}">
                <div class="toast-content">
                    <span class="toast-message">${message}</span>
                    <button class="toast-close">&times;</button>
                </div>
            </div>
        `);

        $('#toast-container').append(toast);

        // Show toast
        setTimeout(() => {
            toast.addClass('show');
        }, 100);

        // Auto hide toast
        setTimeout(() => {
            this.hideToast(toast);
        }, duration);

        // Manual close
        toast.on('click', '.toast-close', () => {
            this.hideToast(toast);
        });
    },

    // Hide toast notification
    hideToast: function(toast) {
        toast.removeClass('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    },

    // Setup counters and animations
    setupCounters: function() {
        // Animate numbers
        $('.animate-counter').each(function() {
            const $this = $(this);
            const target = parseInt($this.text());
            const duration = 2000;
            const increment = target / (duration / 16);
            let current = 0;

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    $this.text(target);
                    clearInterval(timer);
                } else {
                    $this.text(Math.floor(current));
                }
            }, 16);
        });
    },

    // Bind global events
    bindEvents: function() {
        // Search functionality
        $(document).on('input', '.search-input', function() {
            const query = $(this).val().toLowerCase();
            const target = $(this).data('target');

            $(target).each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(query));
            });
        });

        // Toggle functionality
        $(document).on('click', '[data-toggle]', function() {
            const target = $(this).data('toggle');
            $(target).toggle();
        });

        // Copy to clipboard
        $(document).on('click', '[data-copy]', function() {
            const text = $(this).data('copy');
            navigator.clipboard.writeText(text).then(() => {
                SpecialProgram.showToast('Đã sao chép!', 'success', 1000);
            });
        });

        // Smooth scroll
        $(document).on('click', 'a[href^="#"]', function(e) {
            const target = $($(this).attr('href'));
            if (target.length) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 500);
            }
        });

        // Back to top button
        $(window).scroll(function() {
            if ($(this).scrollTop() > 300) {
                $('.back-to-top').fadeIn();
            } else {
                $('.back-to-top').fadeOut();
            }
        });

        $(document).on('click', '.back-to-top', function() {
            $('html, body').animate({scrollTop: 0}, 500);
        });
    },

    // Utility functions
    utils: {
        // Format number with commas
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },

        // Format date
        formatDate: function(date, format = 'dd/mm/yyyy') {
            const d = new Date(date);
            const day = String(d.getDate()).padStart(2, '0');
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const year = d.getFullYear();

            return format.replace('dd', day).replace('mm', month).replace('yyyy', year);
        },

        // Debounce function
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // Generate random ID
        generateId: function() {
            return Math.random().toString(36).substr(2, 9);
        }
    }
};

// Auto-initialize when DOM is ready
$(document).ready(function() {
    SpecialProgram.init();
});

// Admin specific functionality
if (window.location.pathname.includes('/admin')) {
    $(document).ready(function() {
        // Admin dashboard auto-refresh
        if ($('.admin-dashboard').length) {
            setInterval(function() {
                $('.auto-refresh').each(function() {
                    const url = $(this).data('refresh-url');
                    if (url) {
                        $(this).load(url);
                    }
                });
            }, 30000); // Refresh every 30 seconds
        }

        // Data tables
        if ($.fn.DataTable) {
            $('.data-table').DataTable({
                responsive: true,
                pageLength: 25,
                language: {
                    url: '/assets/js/datatables-vi.json'
                }
            });
        }

        // Charts
        if (window.Chart && $('.chart').length) {
            $('.chart').each(function() {
                const $chart = $(this);
                const type = $chart.data('type') || 'line';
                const data = $chart.data('chart-data');

                new Chart($chart[0], {
                    type: type,
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            });
        }
    });
}
