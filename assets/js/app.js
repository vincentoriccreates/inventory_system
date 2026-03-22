/* ============================================================
   InventoryPro — Main JavaScript
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    // ── Sidebar Toggle ──────────────────────────────────────
    const sidebar = document.getElementById('sidebar');
    const mainWrapper = document.getElementById('mainWrapper');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const topbarToggle = document.getElementById('topbarToggle');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
                mainWrapper.classList.toggle('expanded');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            }
        });
    }

    if (topbarToggle && sidebar) {
        topbarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-open');
        });
    }

    // Restore sidebar state
    if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 768) {
        sidebar && sidebar.classList.add('collapsed');
        mainWrapper && mainWrapper.classList.add('expanded');
    }

    // Close sidebar on outside click (mobile)
    document.addEventListener('click', function (e) {
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('mobile-open')) {
            if (!sidebar.contains(e.target) && e.target !== topbarToggle) {
                sidebar.classList.remove('mobile-open');
            }
        }
    });

    // ── Auto-dismiss alerts ─────────────────────────────────
    document.querySelectorAll('.alert').forEach(function (el) {
        setTimeout(function () {
            el.style.opacity = '0';
            el.style.transition = 'opacity .5s';
            setTimeout(function () { el.remove(); }, 500);
        }, 4000);
    });

    // ── Modal ───────────────────────────────────────────────
    document.querySelectorAll('[data-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const modalId = btn.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.add('active');
        });
    });

    document.querySelectorAll('.modal-close, [data-dismiss="modal"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.modal-overlay').classList.remove('active');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('active');
        });
    });

    // ── Confirm Delete ──────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm') || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    // ── Search/Filter Table ─────────────────────────────────
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
});

// ── AJAX Barcode Lookup ──────────────────────────────────────
function lookupBarcode(barcode, callback) {
    if (!barcode || barcode.length < 4) return;
    fetch(BASE_URL + 'ajax/lookup_barcode.php?barcode=' + encodeURIComponent(barcode))
        .then(r => r.json())
        .then(data => callback(data))
        .catch(() => callback(null));
}

// ── POS / Stock-In barcode field setup ─────────────────────
function setupBarcodeField(inputId, fillFn) {
    const input = document.getElementById(inputId);
    if (!input) return;

    let debounce;
    input.addEventListener('input', function () {
        clearTimeout(debounce);
        const val = this.value.trim();
        debounce = setTimeout(() => {
            if (val.length >= 4) {
                lookupBarcode(val, function (data) {
                    if (data && data.found) {
                        fillFn(data.item);
                        showScanFlash(input, true);
                    } else if (val.length >= 8) {
                        showScanFlash(input, false);
                    }
                });
            }
        }, 300);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            lookupBarcode(this.value.trim(), function (data) {
                if (data && data.found) {
                    fillFn(data.item);
                    showScanFlash(input, true);
                }
            });
        }
    });
}

function showScanFlash(input, success) {
    input.style.borderColor = success ? '#10b981' : '#ef4444';
    input.style.background = success ? '#d1fae5' : '#fee2e2';
    setTimeout(() => {
        input.style.borderColor = '';
        input.style.background = '';
    }, 1000);
}

// ── Number formatting ───────────────────────────────────────
function formatCurrency(n) {
    return '₱' + parseFloat(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Auto-detect base path relative to current page
const BASE_URL = (function() {
    const path = window.location.pathname;
    // Get everything up to and including the last slash
    return path.substring(0, path.lastIndexOf('/') + 1);
})();
