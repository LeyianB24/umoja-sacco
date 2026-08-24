/**
 * Main Project Scripts - Extracted from inline JS
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize common components like tooltips or sidebar toggles here if needed
});

// Accountant Functions
function confirmAction(action, id) {
    if(confirm('Proceed to ' + action + ' this application?')) {
        const actionInput = document.getElementById('form_action');
        const idInput = document.getElementById('form_loan_id');
        const form = document.getElementById('actionForm');
        
        if(actionInput && idInput && form) {
            actionInput.value = action;
            idInput.value = id;
            form.submit();
        }
    }
}
function openRejectModal(id) {
    const el = document.getElementById('reject_loan_id');
    const modal = document.getElementById('rejectModal');
    if(el && modal) {
        el.value = id;
        new bootstrap.Modal(modal).show();
    }
}
function openDisburseModal(id, amount) {
    const idEl = document.getElementById('disburse_loan_id');
    const amtEl = document.getElementById('disburse_amount');
    const modal = document.getElementById('disburseModal');
    
    if(idEl && amtEl && modal) {
        idEl.value = id;
        amtEl.innerText = amount.toLocaleString();
        new bootstrap.Modal(modal).show();
    }
}

// SuperAdmin Functions
function initSuperAdminFeatures() {
    // Sidebar Toggler
    const toggleBtn = document.getElementById('mobileSidebarToggle');
    const wrapper = document.getElementById('wrapper');
    if(toggleBtn && wrapper) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            wrapper.classList.toggle('toggled');
        });
    }

    // Real-time Search
    const searchInput = document.getElementById('adminSearch');
    if(searchInput) {
        searchInput.addEventListener('keyup', function() {
            let searchText = this.value.toLowerCase();
            let rows = document.querySelectorAll('#adminTable tbody tr.admin-row');
            rows.forEach(row => {
                let name = row.querySelector('.admin-name').textContent.toLowerCase();
                let user = row.querySelector('.admin-user').textContent.toLowerCase();
                let email = row.querySelector('.admin-email').textContent.toLowerCase();
                if(name.includes(searchText) || user.includes(searchText) || email.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Role Filter
    const roleItems = document.querySelectorAll('#roleFilter .dropdown-item');
    if(roleItems.length > 0) {
        roleItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                let filter = this.getAttribute('data-filter');
                let rows = document.querySelectorAll('#adminTable tbody tr.admin-row');
                
                // Update active state
                document.querySelectorAll('#roleFilter .dropdown-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');

                rows.forEach(row => {
                    if(filter === 'all' || row.getAttribute('data-role') === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
    }
}

function openEditModal(data) {
    const idEl = document.getElementById('edit_id');
    const nameEl = document.getElementById('edit_name');
    const emailEl = document.getElementById('edit_email');
    const roleEl = document.getElementById('edit_role');
    const modal = document.getElementById('editAdminModal');
    
    if(idEl && modal) {
        idEl.value = data.admin_id;
        nameEl.value = data.full_name;
        emailEl.value = data.email;
        roleEl.value = data.role;
        new bootstrap.Modal(modal).show();
    }
}

// Hook into DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    initSuperAdminFeatures();
    initExpenseChart();
    initWelfareChart();
});

function initExpenseChart() {
    const ctx = document.getElementById('expenseChart');
    if(ctx) {
        // Read data from data attributes (assumes set on canvas or a wrapper)
        const container = ctx.parentElement; // or ctx itself
        // Actually, let's look for separate data elements or just expect the canvas has data attributes
        const labels = JSON.parse(ctx.getAttribute('data-labels') || '[]');
        const values = JSON.parse(ctx.getAttribute('data-values') || '[]');

        if(labels.length > 0 && typeof Chart !== 'undefined') {
            const forestDark = '#0d3935';
            const forestMid = '#1a4d48';
            const lime = '#bef264';
            const teal = '#20c997';
            const amber = '#f59e0b';
            const red = '#ef4444';

            new Chart(ctx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: [forestDark, lime, forestMid, teal, amber, red],
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'right', 
                            labels: { 
                                color: '#64748b', 
                                boxWidth: 12, 
                                font: { family: "'Outfit', sans-serif", size: 11 } 
                            } 
                        }
                    },
                    cutout: '75%'
                }
            });
        }
    }
}

function initWelfareChart() {
    const ctx = document.getElementById('welfareChart');
    if(ctx) {
        const labels = JSON.parse(ctx.getAttribute('data-labels') || '[]');
        const values = JSON.parse(ctx.getAttribute('data-values') || '[]');

        if(labels.length > 0 && typeof Chart !== 'undefined') {
            const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(208, 243, 93, 0.6)'); // Brand Lime (Hope UI)
            gradient.addColorStop(1, 'rgba(208, 243, 93, 0.0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Contributions (KES)',
                        data: values,
                        borderColor: '#0F2E25', // Forest Green
                        backgroundColor: gradient,
                        borderWidth: 2,
                        pointBackgroundColor: '#D0F35D', // Lime dots
                        pointBorderColor: '#0F2E25',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 12,
                            titleFont: { family: 'Plus Jakarta Sans', size: 13 },
                            bodyFont: { family: 'Plus Jakarta Sans', size: 14, weight: 'bold' },
                            callbacks: {
                                label: function(context) {
                                    return 'KES ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { borderDash: [5, 5], color: 'rgba(0,0,0,0.05)' },
                            ticks: { 
                                callback: function(value) { return value / 1000 + 'k'; },
                                font: { family: 'Plus Jakarta Sans' }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Plus Jakarta Sans' } }
                        }
                    }
                }
            });
        }
    }
    
    // Interactive Table Search for Welfare
    const searchInput = document.getElementById('tableSearch');
    if(searchInput) {
        searchInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            const activeTabPane = document.querySelector('.tab-pane.active');
            if(activeTabPane) {
                const table = activeTabPane.querySelector('table');
                if(table) {
                    const rows = table.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(query) ? '' : 'none';
                    });
                }
            }
        });
        
        // Reset on tab switch
        const tabEls = document.querySelectorAll('button[data-bs-toggle="pill"]');
        tabEls.forEach(tab => {
            tab.addEventListener('shown.bs.tab', () => {
                 searchInput.value = '';
                 const tables = document.querySelectorAll('.tab-pane table');
                 tables.forEach(t => t.querySelectorAll('tbody tr').forEach(r => r.style.display = ''));
            })
        });
    }
}

// Update DOMContentLoaded to include initWelfareChart
document.addEventListener('DOMContentLoaded', function() {
    initSuperAdminFeatures();
    initExpenseChart();
    initWelfareChart();
});

// Toast System
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container-custom');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container-custom';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast-custom ${type}`;
    
    let icon = 'bi-check-circle-fill text-success';
    if(type === 'error') icon = 'bi-exclamation-triangle-fill text-danger';
    if(type === 'info') icon = 'bi-info-circle-fill text-info';

    toast.innerHTML = `
        <i class="bi ${icon} toast-icon"></i>
        <div class="toast-message">${message}</div>
    `;

    container.appendChild(toast);

    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 100);

    // Auto remove
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 500);
    }, 4000);
}
