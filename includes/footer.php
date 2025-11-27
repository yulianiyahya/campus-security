<footer class="bg-light py-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <p class="mb-0">
                    <i class="fas fa-shield-alt text-primary"></i>
                    <strong>Campus Security System</strong>
                </p>
                <p class="text-muted small mb-0">Sistem Keamanan Kampus Terintegrasi</p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-0 text-muted small">
                    &copy; <?= date('Y') ?> Campus Security. All rights reserved.
                </p>
                <p class="mb-0 text-muted small">
                    <i class="fas fa-clock"></i> <?= date('d M Y, H:i') ?> WIB
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js (if needed) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom JS -->
<script>
    // ✅ FIXED: Auto-hide ONLY flash messages (success/error from session), NOT permanent alerts
    document.addEventListener('DOMContentLoaded', function() {
        // Hanya sembunyikan alert yang punya class .alert-flash (untuk flash message)
        // Dan hindari menyentuh .alert-resolution, .alert-officer, dll
        const flashAlerts = document.querySelectorAll('.alert.alert-flash');
        flashAlerts.forEach(function(alert) {
            // Auto-hide setelah 5 detik, TAPI hanya jika bukan halaman detail laporan
            if (!window.location.href.includes('report_detail.php')) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            }
        });

        // Confirm delete actions
        document.querySelectorAll('.btn-delete, .delete-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) {
                    e.preventDefault();
                }
            });
        });

        // File input preview
        document.querySelectorAll('input[type="file"]').forEach(function(input) {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const fileInfo = document.createElement('small');
                    fileInfo.className = 'text-muted d-block mt-1';
                    fileInfo.textContent = `File: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
                    
                    // Remove old file info if exists
                    const oldInfo = input.parentElement.querySelector('.file-info');
                    if (oldInfo) oldInfo.remove();
                    
                    fileInfo.classList.add('file-info');
                    input.parentElement.appendChild(fileInfo);
                }
            });
        });

        // Add loading spinner to buttons on submit
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
                }
            });
        });
    });
</script>

</body>
</html>