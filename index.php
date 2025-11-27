<?php
require_once 'config.php';

// If already logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    $role = $_SESSION['role'];
    if ($role === 'admin') {
        redirect('admin/dashboard.php');
    } elseif ($role === 'security') {
        redirect('security/dashboard.php');
    } else {
        redirect('user/dashboard.php');
    }
}

// Get public statistics
$stats_sql = "SELECT 
              COUNT(*) as total_reports,
              COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_reports,
              COUNT(DISTINCT reporter_id) as total_users
              FROM reports";
$stats = $pdo->query($stats_sql)->fetch();

// Get recent announcements (public)
$announcements_sql = "SELECT * FROM announcements 
                      WHERE is_published = TRUE 
                      AND publish_date <= NOW()
                      AND (expire_date IS NULL OR expire_date > NOW())
                      ORDER BY priority DESC, created_at DESC
                      LIMIT 3";
$announcements = $pdo->query($announcements_sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Informasi Keamanan Kampus</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Premium Font Stack -->
    <link href="https://fonts.googleapis.com/css2?family=Clash+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Satoshi:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Soft Blue Palette */
            --primary-soft: #6C9BCF;
            --primary-light: #8BB4DB;
            --primary-lighter: #B8D4EC;
            --primary-dark: #5681B3;
            --accent-gold: #D4AF37;
            --accent-rose: #E8B4B8;
            --accent-mint: #B8E8D4;
            
            /* Premium Gradients */
            --luxury-gradient: linear-gradient(135deg, #6C9BCF 0%, #8BB4DB 50%, #B8D4EC 100%);
            --gold-gradient: linear-gradient(135deg, #D4AF37 0%, #F4E5A8 100%);
            --mint-gradient: linear-gradient(135deg, #B8E8D4 0%, #D4F5E8 100%);
            --elegant-gradient: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            
            /* Backgrounds */
            --body-bg: #f5f9ff;
            --card-bg: #ffffff;
            
            /* Text Colors */
            --text-primary: #2c3e50;
            --text-secondary: #5a6c7d;
            --text-light: #95a5a6;
            --text-elegant: #34495e;
            
            /* Glass & Shadow */
            --glass-white: rgba(255, 255, 255, 0.95);
            --glass-blue: rgba(108, 155, 207, 0.08);
            --shadow-elegant: 0 10px 40px rgba(108, 155, 207, 0.12);
            --shadow-hover: 0 20px 60px rgba(108, 155, 207, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Satoshi', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--body-bg);
            color: var(--text-primary);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Elegant Floating Orbs Background */
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.15;
            z-index: 0;
            animation: floatOrb 30s ease-in-out infinite;
            pointer-events: none;
        }

        body::before {
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, #6C9BCF, transparent);
            top: -300px;
            right: -300px;
        }

        body::after {
            width: 550px;
            height: 550px;
            background: radial-gradient(circle, #B8D4EC, transparent);
            bottom: -250px;
            left: -250px;
            animation-delay: -15s;
        }

        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(40px, -40px) scale(1.15); }
            66% { transform: translate(-30px, 30px) scale(0.9); }
        }
        
        /* Premium Navigation */
        .navbar {
            background: var(--glass-white) !important;
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-bottom: 1px solid rgba(108, 155, 207, 0.15);
            box-shadow: 0 4px 20px rgba(108, 155, 207, 0.1);
            padding: 1.2rem 0;
            position: sticky;
            top: 0;
            z-index: 1030;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .navbar-brand {
            font-family: 'Clash Grotesk', serif;
            font-weight: 700;
            font-size: 1.4rem;
            color: var(--primary-soft) !important;
            letter-spacing: -0.5px;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            color: var(--primary-dark) !important;
            transform: scale(1.03);
        }

        .navbar .nav-link {
            font-family: 'Satoshi', sans-serif;
            color: var(--text-secondary) !important;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
            position: relative;
        }

        .navbar .nav-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: var(--luxury-gradient);
            transition: width 0.3s ease;
        }

        .navbar .nav-link:hover {
            color: var(--primary-soft) !important;
        }

        .navbar .nav-link:hover::before {
            width: 70%;
        }

        .btn-login-nav {
            background: var(--luxury-gradient) !important;
            border: none !important;
            color: white !important;
            padding: 0.6rem 1.5rem !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(108, 155, 207, 0.3);
        }

        .btn-login-nav:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 155, 207, 0.4);
        }
        
        /* Hero Section - Premium */
        .hero-section {
            background: var(--luxury-gradient);
            padding: 120px 0 80px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1), transparent);
            border-radius: 50%;
            top: -200px;
            right: -200px;
            animation: floatOrb 20s ease-in-out infinite;
        }
        
        .hero-title {
            font-family: 'Clash Grotesk', serif;
            font-size: clamp(2.5rem, 6vw, 3.5rem);
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 0 4px 20px rgba(0,0,0,0.15);
            letter-spacing: -1.5px;
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-family: 'Satoshi', sans-serif;
            font-size: clamp(1.1rem, 3vw, 1.35rem);
            font-weight: 400;
            margin-bottom: 2.5rem;
            opacity: 0.95;
            line-height: 1.7;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .btn-hero {
            font-family: 'Satoshi', sans-serif;
            padding: 1rem 2.5rem;
            font-size: 1.05rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-radius: 14px;
            margin: 10px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border: 2px solid transparent;
        }
        
        .btn-hero:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.25);
        }

        .btn-hero.btn-light {
            background: white;
            color: var(--primary-soft);
        }

        .btn-hero.btn-outline-light {
            background: transparent;
            border-color: white;
            color: white;
        }

        .btn-hero.btn-outline-light:hover {
            background: white;
            color: var(--primary-soft);
        }
        
        /* Premium Stats Cards */
        .stats-card {
            background: var(--glass-white);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(108, 155, 207, 0.15);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            margin: 20px 0;
            box-shadow: var(--shadow-elegant);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--luxury-gradient);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .stats-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
        }

        .stats-card:hover::before {
            transform: scaleX(1);
        }
        
        .stats-icon {
            font-size: 2.8rem;
            margin-bottom: 1.2rem;
            opacity: 0.9;
        }
        
        .stats-number {
            font-family: 'Geist Mono', monospace;
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--primary-soft);
            letter-spacing: -1px;
        }
        
        .stats-label {
            font-family: 'Satoshi', sans-serif;
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-top: 0.5rem;
        }
        
        /* Features Section */
        .features-section {
            background: white;
            padding: 100px 0;
            position: relative;
            z-index: 1;
        }

        .section-title {
            font-family: 'Clash Grotesk', serif;
            font-size: clamp(2rem, 5vw, 2.5rem);
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            letter-spacing: -1px;
        }

        .section-subtitle {
            font-family: 'Satoshi', sans-serif;
            font-size: 1.15rem;
            color: var(--text-secondary);
            font-weight: 400;
        }
        
        .feature-card {
            text-align: center;
            padding: 2.5rem 2rem;
            border-radius: 20px;
            background: var(--glass-blue);
            border: 1px solid rgba(108, 155, 207, 0.1);
            margin: 15px 0;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--luxury-gradient);
            opacity: 0;
            transition: opacity 0.5s ease;
            z-index: 0;
        }

        .feature-card > * {
            position: relative;
            z-index: 1;
        }
        
        .feature-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-hover);
        }

        .feature-card:hover::before {
            opacity: 1;
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--primary-soft);
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            color: white;
            transform: scale(1.1);
        }

        .feature-card h4 {
            font-family: 'Clash Grotesk', serif;
            font-weight: 600;
            font-size: 1.3rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
            transition: color 0.3s ease;
        }

        .feature-card p {
            font-family: 'Satoshi', sans-serif;
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.7;
            transition: color 0.3s ease;
        }

        .feature-card:hover h4,
        .feature-card:hover p {
            color: white;
        }
        
        /* Announcement Card */
        .announcement-card {
            background: white;
            border: 1px solid rgba(108, 155, 207, 0.15);
            border-left: 4px solid var(--primary-soft);
            padding: 1.8rem;
            margin: 15px 0;
            border-radius: 16px;
            box-shadow: var(--shadow-elegant);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .announcement-card:hover {
            transform: translateX(8px);
            box-shadow: var(--shadow-hover);
        }

        .announcement-card h5 {
            font-family: 'Clash Grotesk', serif;
            font-weight: 600;
            font-size: 1.15rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .announcement-card p {
            font-family: 'Satoshi', sans-serif;
            color: var(--text-secondary);
            line-height: 1.7;
        }

        /* CTA Section - Updated */
        .cta-section {
            background: #7FA8C9;
            padding: 100px 0;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255,255,255,0.08), transparent);
            border-radius: 50%;
            bottom: -250px;
            left: -250px;
        }

        .cta-section h2 {
            font-family: 'Clash Grotesk', serif;
            font-size: clamp(2rem, 5vw, 2.8rem);
            font-weight: 700;
            margin-bottom: 1.5rem;
            letter-spacing: -1px;
        }

        .cta-section p {
            font-family: 'Satoshi', sans-serif;
            font-size: 1.15rem;
            opacity: 0.95;
            margin-bottom: 2rem;
        }

        .btn-cta {
            background: white !important;
            color: #7FA8C9 !important;
            padding: 0.9rem 2.5rem !important;
            font-size: 1.05rem;
            font-weight: 600;
            border-radius: 50px !important;
            border: none !important;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.25);
        }
        
        /* Footer - Updated */
        .footer {
            background: #3e5364;
            color: white;
            padding: 60px 0 30px;
            margin-top: 0;
            position: relative;
        }

        .footer h5, .footer h6 {
            font-family: 'Clash Grotesk', serif;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: white;
        }

        .footer p, .footer li {
            font-family: 'Satoshi', sans-serif;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.8;
        }

        .footer a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .footer a:hover {
            color: var(--primary-light);
            transform: translateX(4px);
        }

        .footer .list-unstyled li {
            margin-bottom: 0.5rem;
        }

        .footer hr {
            border-color: rgba(255, 255, 255, 0.2);
            margin: 2rem 0;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 1.5rem;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 0 60px;
            }

            .features-section {
                padding: 60px 0;
            }

            .stats-card {
                margin-bottom: 1rem;
            }

            .cta-section {
                padding: 60px 0;
            }

            .footer {
                padding: 40px 0 20px;
            }
        }

        /* Smooth Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-shield-alt me-2"></i>
                Campus Security
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: var(--primary-soft);">
                <i class="fas fa-bars" style="color: var(--primary-soft);"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#beranda">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#fitur">Fitur</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pengumuman">Pengumuman</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="btn btn-login-nav" href="login.php">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center animate-fade" id="beranda">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-12">
                    <i class="fas fa-shield-alt" style="font-size: 5rem; margin-bottom: 2rem; opacity: 0.95;"></i>
                    <h1 class="hero-title">Sistem Informasi<br>Keamanan Kampus</h1>
                    <p class="hero-subtitle">
                        Laporkan kejadian keamanan dengan cepat dan mudah.<br>
                        Kami siap membantu menjaga keamanan kampus bersama-sama.
                    </p>
                    <div class="mt-4">
                        <a href="login.php" class="btn btn-light btn-hero">
                            <i class="fas fa-sign-in-alt me-2"></i>Masuk ke Sistem
                        </a>
                        <a href="#fitur" class="btn btn-outline-light btn-hero">
                            <i class="fas fa-info-circle me-2"></i>Pelajari Lebih Lanjut
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mt-5 pt-4">
                <div class="col-md-4">
                    <div class="stats-card">
                        <i class="fas fa-file-alt stats-icon text-primary"></i>
                        <div class="stats-number"><?= number_format($stats['total_reports']) ?></div>
                        <div class="stats-label">Total Laporan</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <i class="fas fa-check-circle stats-icon text-success"></i>
                        <div class="stats-number"><?= number_format($stats['resolved_reports']) ?></div>
                        <div class="stats-label">Laporan Selesai</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <i class="fas fa-users stats-icon text-info"></i>
                        <div class="stats-number"><?= number_format($stats['total_users']) ?></div>
                        <div class="stats-label">Pengguna Aktif</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="fitur">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Fitur Unggulan</h2>
                <p class="section-subtitle">Sistem yang dirancang untuk kemudahan dan keamanan Anda</p>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-mobile-alt feature-icon"></i>
                        <h4>Lapor Cepat</h4>
                        <p>Laporkan kejadian keamanan kapan saja, dimana saja dengan mudah dan cepat.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-clock feature-icon"></i>
                        <h4>Real-Time Tracking</h4>
                        <p>Pantau status laporan Anda secara real-time dengan notifikasi otomatis.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-user-shield feature-icon"></i>
                        <h4>Respon Cepat</h4>
                        <p>Petugas keamanan siap merespon laporan Anda dengan cepat dan profesional.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-camera feature-icon"></i>
                        <h4>Upload Bukti</h4>
                        <p>Lampirkan foto atau dokumen sebagai bukti untuk memperkuat laporan.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-bell feature-icon"></i>
                        <h4>Notifikasi</h4>
                        <p>Dapatkan notifikasi setiap ada update pada laporan yang Anda buat.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-chart-line feature-icon"></i>
                        <h4>Statistik</h4>
                        <p>Admin dapat melihat statistik dan analitik keamanan kampus secara lengkap.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Announcements Section -->
    <?php if (!empty($announcements)): ?>
    <section class="py-5" id="pengumuman" style="background: var(--body-bg);">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Pengumuman Terbaru</h2>
                <p class="section-subtitle">Informasi penting untuk sivitas akademika</p>
            </div>

            <div class="row">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="col-md-12">
                        <div class="announcement-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <?php
                                    $type_icons = [
                                        'info' => 'info-circle text-primary',
                                        'warning' => 'exclamation-triangle text-warning',
                                        'emergency' => 'exclamation-circle text-danger',
                                        'maintenance' => 'tools text-secondary'
                                    ];
                                    $icon = $type_icons[$announcement['type']] ?? 'bell text-muted';
                                    ?>
                                    <h5>
                                        <i class="fas fa-<?= $icon ?> me-2"></i>
                                        <?= htmlspecialchars($announcement['title']) ?>
                                    </h5>
                                    <p class="mb-2"><?= nl2br(htmlspecialchars($announcement['content'])) ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= date('d M Y', strtotime($announcement['publish_date'])) ?>
                                    </small>
                                </div>
                                <?php if ($announcement['priority'] === 'high'): ?>
                                    <span class="badge bg-danger">PENTING</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="cta-section text-center">
        <div class="container position-relative">
            <h2 class="mb-3">Siap Membuat Laporan?</h2>
            <p class="mb-4">Login sekarang dan laporkan kejadian keamanan dengan mudah</p>
            <a href="login.php" class="btn btn-cta">
                <i class="fas fa-sign-in-alt me-2"></i>Login Sekarang
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-shield-alt me-2"></i>Sistem Keamanan Kampus</h5>
                    <p>
                        Platform terintegrasi untuk melaporkan dan mengelola kejadian keamanan di lingkungan kampus.
                    </p>
                </div>
                <div class="col-md-4 mb-4">
                    <h6>Link Cepat</h6>
                    <ul class="list-unstyled">
                        <li><a href="#beranda"><i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>Beranda</a></li>
                        <li><a href="#fitur"><i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>Fitur</a></li>
                        <li><a href="#pengumuman"><i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>Pengumuman</a></li>
                        <li><a href="login.php"><i class="fas fa-chevron-right me-2" style="font-size: 0.7rem;"></i>Login</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h6>Kontak Darurat</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-phone me-2"></i>(021) 123-4567</li>
                        <li><i class="fas fa-envelope me-2"></i>security@kampus.ac.id</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i>Kampus Utama</li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="footer-bottom">
                <p class="mb-0">
                    &copy; <?= date('Y') ?> Sistem Informasi Keamanan Kampus. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Smooth Scroll -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scroll
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        const offset = 80;
                        const elementPosition = target.getBoundingClientRect().top;
                        const offsetPosition = elementPosition + window.pageYOffset - offset;

                        window.scrollTo({
                            top: offsetPosition,
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>