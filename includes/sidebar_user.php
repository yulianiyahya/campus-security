<?php
require_once '../config.php';
check_login();
check_role(['user']);

$page_title = 'Bantuan & FAQ';

require_once '../includes/header.php';
require_once '../includes/navbar_user.php';
?>

<!-- Header -->
<div class="row mb-4">
    <div class="col">
        <h4 class="mb-1">
            <i class="fas fa-question-circle me-2"></i>Bantuan & FAQ
        </h4>
        <p class="text-muted mb-0">Pertanyaan yang sering diajukan seputar sistem keamanan kampus</p>
    </div>
</div>

<!-- Search Box -->
<div class="card mb-4">
    <div class="card-body">
        <div class="input-group input-group-lg">
            <span class="input-group-text">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" class="form-control" id="searchFAQ" 
                   placeholder="Cari pertanyaan...">
        </div>
    </div>
</div>

<div class="row">
    <!-- FAQ Categories -->
    <div class="col-lg-3 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>Kategori
                </h6>
            </div>
            <div class="list-group list-group-flush">
                <a href="#general" class="list-group-item list-group-item-action">
                    <i class="fas fa-info-circle me-2"></i>Umum
                </a>
                <a href="#report" class="list-group-item list-group-item-action">
                    <i class="fas fa-file-alt me-2"></i>Membuat Laporan
                </a>
                <a href="#status" class="list-group-item list-group-item-action">
                    <i class="fas fa-tasks me-2"></i>Status Laporan
                </a>
                <a href="#security" class="list-group-item list-group-item-action">
                    <i class="fas fa-shield-alt me-2"></i>Keamanan
                </a>
                <a href="#contact" class="list-group-item list-group-item-action">
                    <i class="fas fa-phone me-2"></i>Kontak
                </a>
            </div>
        </div>
    </div>

    <!-- FAQ Content -->
    <div class="col-lg-9">
        <!-- General -->
        <div class="card mb-4" id="general">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>Pertanyaan Umum
                </h5>
            </div>
            <div class="card-body">
                <div class="accordion" id="accordionGeneral">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#gen1">
                                Apa itu Sistem Keamanan Kampus?
                            </button>
                        </h2>
                        <div id="gen1" class="accordion-collapse collapse show" 
                             data-bs-parent="#accordionGeneral">
                            <div class="accordion-body">
                                Sistem Keamanan Kampus adalah platform online yang memungkinkan mahasiswa, 
                                dosen, dan staff untuk melaporkan insiden keamanan di lingkungan kampus 
                                secara cepat dan mudah. Sistem ini membantu tim keamanan kampus untuk 
                                merespon dan menangani masalah keamanan dengan lebih efektif.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#gen2">
                                Siapa yang bisa menggunakan sistem ini?
                            </button>
                        </h2>
                        <div id="gen2" class="accordion-collapse collapse" 
                             data-bs-parent="#accordionGeneral">
                            <div class="accordion-body">
                                Sistem ini dapat digunakan oleh seluruh civitas akademika kampus, 
                                termasuk mahasiswa, dosen, dan staff. Anda perlu login menggunakan 
                                akun kampus yang telah terdaftar untuk dapat membuat laporan.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#gen3">
                                Apakah laporan saya bersifat rahasia?
                            </button>
                        </h2>
                        <div id="gen3" class="accordion-collapse collapse" 
                             data-bs-parent="#accordionGeneral">
                            <div class="accordion-body">
                                Ya, identitas pelapor dijaga kerahasiaannya dan hanya dapat diakses 
                                oleh petugas keamanan yang berwenang. Informasi pribadi Anda tidak 
                                akan disebarluaskan kepada pihak lain tanpa izin.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report -->
        <div class="card mb-4" id="report">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i>Membuat Laporan
                </h5>
            </div>
            <div class="card-body">
                <div class="accordion" id="accordionReport">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#rep1">
                                Bagaimana cara membuat laporan?
                            </button>
                        </h2>
                        <div id="rep1" class="accordion-collapse collapse show" 
                             data-bs-parent="#accordionReport">
                            <div class="accordion-body">
                                <ol>
                                    <li>Klik tombol "Buat Laporan" di menu atau dashboard</li>
                                    <li>Pilih kategori insiden yang sesuai</li>
                                    <li>Pilih lokasi kejadian</li>
                                    <li>Isi judul dan deskripsi kejadian secara detail</li>
                                    <li>Pilih waktu kejadian dan tingkat prioritas</li>
                                    <li>Upload foto/dokumen pendukung (opsional)</li>
                                    <li>Klik "Kirim Laporan"</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#rep2">
                                Jenis insiden apa saja yang bisa dilaporkan?
                            </button>
                        </h2>
                        <div id="rep2" class="accordion-collapse collapse" 
                             data-bs-parent="#accordionReport">
                            <div class="accordion-body">
                                Anda dapat melaporkan berbagai jenis insiden keamanan seperti:
                                <ul>
                                    <li>Pencurian atau kehilangan barang</li>
                                    <li>Kerusakan fasilitas kampus</li>
                                    <li>Tindakan mencurigakan</li>
                                    <li>Akses tidak sah ke area terlarang</li>
                                    <li>Parkir ilegal</li>
                                    <li>Pelanggaran tata tertib kampus</li>
                                    <li>Situasi darurat/emergency</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#rep3">
                                Apakah saya bisa melampirkan foto atau dokumen?
                            </button>
                        </h2>
                        <div id="rep3" class="accordion-collapse collapse" 
                             data-bs-parent="#accordionReport">
                            <div class="accordion-body">
                                Ya, Anda dapat melampirkan foto atau dokumen pendukung dalam format 
                                JPG, PNG, atau PDF dengan ukuran maksimal 5MB per file. Bukti visual 
                                sangat membantu tim keamanan dalam menangani laporan Anda.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status -->
        <div class="card mb-4" id="status">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-tasks me-2"></i>Status Laporan
                </h5>
            </div>
            <div class="card-body">
                <div class="accordion" id="accordionStatus">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#stat1">
                                Apa arti dari setiap status laporan?
                            </button>
                        </h2>
                        <div id="stat1" class="accordion-collapse collapse show" 
                             data-bs-parent="#accordionStatus">
                            <div class="accordion-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <tr>
                                            <td><span class="badge bg-primary">Baru</span></td>
                                            <td>Laporan baru masuk dan menunggu peninjauan</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-info">Ditugaskan</span></td>
                                            <td>Laporan telah ditugaskan ke petugas keamanan</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-warning">Diproses</span></td>
                                            <td>Petugas sedang menangani laporan Anda</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-success">Selesai</span></td>
                                            <td>Laporan telah diselesaikan</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#stat2">
                                Bagaimana cara melihat status laporan saya?
                            </button>
                        </h2>
                        <div id="stat2" class="accordion-collapse collapse" 
                             data-bs-parent="#accordionStatus">
                            <div class="accordion-body">
                                Anda dapat melihat status laporan melalui menu "Laporan Saya" di dashboard. 
                                Klik pada nomor laporan untuk melihat detail lengkap termasuk update terbaru 
                                dari petugas keamanan.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact -->
        <div class="card" id="contact">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-phone me-2"></i>Kontak Darurat
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <i class="fas fa-phone fa-3x text-danger mb-3"></i>
                                <h5>Telepon</h5>
                                <h4 class="text-primary">(021) 555-0123</h4>
                                <p class="text-muted mb-0">24/7 Security Hotline</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <i class="fab fa-whatsapp fa-3x text-success mb-3"></i>
                                <h5>WhatsApp</h5>
                                <h4 class="text-success">0812-3456-7890</h4>
                                <a href="https://wa.me/628123456789" target="_blank" 
                                   class="btn btn-success btn-sm mt-2">
                                    Chat Sekarang
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Untuk Situasi Darurat:</strong> Hubungi langsung nomor telepon 
                    atau datang ke pos keamanan terdekat.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Search FAQ
document.getElementById('searchFAQ').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const accordionButtons = document.querySelectorAll('.accordion-button');
    
    accordionButtons.forEach(button => {
        const text = button.textContent.toLowerCase();
        const accordionItem = button.closest('.accordion-item');
        
        if (text.includes(searchTerm)) {
            accordionItem.style.display = 'block';
        } else {
            accordionItem.style.display = 'none';
        }
    });
});

// Smooth scroll for category links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>