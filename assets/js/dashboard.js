/**
 * Dashboard JavaScript
 * Library Management System
 */

// ============================================
// Chart Initialization
// ============================================
function initDashboardCharts(data) {
    // Books borrowed by month
    if (data.borrowedByMonth) {
        const ctx1 = document.getElementById('borrowedByMonthChart');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: data.borrowedByMonth.labels,
                    datasets: [{
                        label: 'Books Borrowed',
                        data: data.borrowedByMonth.values,
                        backgroundColor: 'rgba(102, 126, 234, 0.6)',
                        borderColor: '#667eea',
                        borderWidth: 2,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    }
    
    // New members by month
    if (data.newMembersByMonth) {
        const ctx2 = document.getElementById('newMembersChart');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: data.newMembersByMonth.labels,
                    datasets: [{
                        label: 'New Members',
                        data: data.newMembersByMonth.values,
                        backgroundColor: 'rgba(72, 187, 120, 0.2)',
                        borderColor: '#48bb78',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    }
    
    // Books by category (pie chart)
    if (data.booksByCategory) {
        const ctx3 = document.getElementById('booksByCategoryChart');
        if (ctx3) {
            const colors = [
                '#667eea', '#48bb78', '#fc8181', '#f6ad55',
                '#63b3ed', '#9f7aea', '#f687b3', '#68d391',
                '#f6ad55', '#63b3ed', '#a0aec0', '#4a5568'
            ];
            
            new Chart(ctx3, {
                type: 'doughnut',
                data: {
                    labels: data.booksByCategory.labels,
                    datasets: [{
                        data: data.booksByCategory.values,
                        backgroundColor: colors.slice(0, data.booksByCategory.labels.length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 12,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        }
                    }
                }
            });
        }
    }
    
    // Most borrowed books
    if (data.mostBorrowedBooks) {
        const ctx4 = document.getElementById('mostBorrowedChart');
        if (ctx4) {
            new Chart(ctx4, {
                type: 'horizontalBar',
                data: {
                    labels: data.mostBorrowedBooks.labels,
                    datasets: [{
                        label: 'Times Borrowed',
                        data: data.mostBorrowedBooks.values,
                        backgroundColor: 'rgba(246, 173, 85, 0.7)',
                        borderColor: '#f6ad55',
                        borderWidth: 2,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    }
    
    // Fine collection
    if (data.fineCollection) {
        const ctx5 = document.getElementById('fineCollectionChart');
        if (ctx5) {
            new Chart(ctx5, {
                type: 'bar',
                data: {
                    labels: data.fineCollection.labels,
                    datasets: [{
                        label: 'Fines Collected',
                        data: data.fineCollection.values,
                        backgroundColor: 'rgba(252, 129, 129, 0.6)',
                        borderColor: '#fc8181',
                        borderWidth: 2,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    }
}

// ============================================
// Auto-refresh Dashboard Stats
// ============================================
function initDashboardRefresh(interval = 60000) {
    const refreshStats = function() {
        const statsContainer = document.querySelector('.dashboard-stats');
        if (statsContainer) {
            ajaxRequest((window.APP_URL || '') + '/api/dashboard-stats.php', 'GET')
                .then(function(data) {
                    if (data.success) {
                        updateStats(data.stats);
                    }
                })
                .catch(function(error) {
                    console.error('Error refreshing stats:', error);
                });
        }
    };

    refreshStats();
    setInterval(refreshStats, interval);
}

function updateStats(stats) {
    const statElements = document.querySelectorAll('.stat-value');
    const statMap = {
        'total-books': stats.total_books,
        'total-copies': stats.total_copies,
        'available-copies': stats.available_copies,
        'borrowed-books': stats.borrowed_books,
        'overdue-books': stats.overdue_books,
        'total-members': stats.total_members,
        'active-members': stats.active_members,
        'total-users': stats.total_users,
        'pending-reservations': stats.pending_reservations,
        'outstanding-fines': stats.outstanding_fines,
        'total-fines-collected': stats.total_fines_collected
    };
    
    statElements.forEach(function(element) {
        const id = element.id;
        if (id && statMap[id] !== undefined) {
            // Animate the change
            const oldValue = parseInt(element.textContent.replace(/[^0-9.]/g, ''));
            const newValue = statMap[id];
            
            if (oldValue !== newValue) {
                animateValue(element, oldValue, newValue, 500);
            }
        }
    });
}

function animateValue(element, start, end, duration) {
    const startTime = performance.now();
    const isCurrency = element.textContent.includes('$');
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const current = Math.round(start + (end - start) * progress);
        
        if (isCurrency) {
            element.textContent = '$' + current.toFixed(2);
        } else {
            element.textContent = current;
        }
        
        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            if (isCurrency) {
                element.textContent = '$' + end.toFixed(2);
            } else {
                element.textContent = end;
            }
        }
    }
    
    requestAnimationFrame(update);
}

// ============================================
// Recent Activity Auto-refresh
// ============================================
function initRecentActivityRefresh(interval = 30000) {
    const refreshActivity = function() {
        const activityContainer = document.querySelector('.recent-activity');
        if (activityContainer) {
            ajaxRequest((window.APP_URL || '') + '/api/recent-activity.php', 'GET')
                .then(function(data) {
                    if (data.success) {
                        updateRecentActivity(activityContainer, data.activities);
                    }
                })
                .catch(function(error) {
                    console.error('Error refreshing activity:', error);
                });
        }
    };

    refreshActivity();
    setInterval(refreshActivity, interval);
}

function updateRecentActivity(container, activities) {
    const tbody = container.querySelector('tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    activities.forEach(function(activity) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${activity.date}</td>
            <td>${activity.type}</td>
            <td>${activity.description}</td>
            <td>${activity.user}</td>
        `;
        tbody.appendChild(tr);
    });
}

// ============================================
// Initialize Dashboard
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on a dashboard page
    if (document.querySelector('.dashboard-stats')) {
        // Get chart data from the page
        const chartDataElement = document.getElementById('chartData');
        if (chartDataElement) {
            try {
                const data = JSON.parse(chartDataElement.textContent);
                initDashboardCharts(data);
            } catch (e) {
                console.error('Error parsing chart data:', e);
            }
        }
        
        // Initialize auto-refresh
        initDashboardRefresh(15000);
        initRecentActivityRefresh(15000);
    }
});
