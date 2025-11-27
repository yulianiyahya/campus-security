<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Campus Security System'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- 🔥 Premium Font Stack — Now with Soft Blue Harmony -->
    <link href="https://fonts.googleapis.com/css2?family=Clash+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Satoshi:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Custom CSS - Soft Blue Luxury (Enhanced Typography) -->
    <style>
        :root {
            /* ✅ Original Soft Blue Palette — Preserved Exactly */
            --primary-soft: #6C9BCF;
            --primary-light: #8BB4DB;
            --primary-lighter: #B8D4EC;
            --primary-dark: #5681B3;
            --accent-gold: #D4AF37;
            --accent-rose: #E8B4B8;
            --accent-mint: #B8E8D4;
            
            /* Premium Gradients — Original */
            --luxury-gradient: linear-gradient(135deg, #6C9BCF 0%, #8BB4DB 50%, #B8D4EC 100%);
            --gold-gradient: linear-gradient(135deg, #D4AF37 0%, #F4E5A8 100%);
            --rose-gradient: linear-gradient(135deg, #E8B4B8 0%, #F5D6D9 100%);
            --mint-gradient: linear-gradient(135deg, #B8E8D4 0%, #D4F5E8 100%);
            --elegant-gradient: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            
            /* Sophisticated Backgrounds — Original */
            --body-bg: #f5f9ff;
            --card-bg: #ffffff;
            --sidebar-bg: #ffffff;
            --navbar-bg: #6C9BCF;
            
            /* Refined Text Colors — Enhanced for contrast & luxury */
            --text-primary: #2c3e50;
            --text-secondary: #5a6c7d;
            --text-light: #95a5a6;
            --text-elegant: #34495e;
            --text-gold: #8B6F25;
            
            /* Glass & Shadow — Original */
            --glass-white: rgba(255, 255, 255, 0.95);
            --glass-blue: rgba(108, 155, 207, 0.08);
            --shadow-elegant: 0 10px 40px rgba(108, 155, 207, 0.12);
            --shadow-hover: 0 20px 60px rgba(108, 155, 207, 0.2);
            
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            /* ✨ Upgraded Font Stack — But Colors Intact */
            font-family: 'Satoshi', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--body-bg);
            color: var(--text-primary);
            padding-top: 80px;
            overflow-x: hidden;
            position: relative;
            line-height: 1.6;
        }

        /* Elegant Floating Orbs Background — Preserved */
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.08;
            z-index: 0;
            animation: floatOrb 25s ease-in-out infinite;
            pointer-events: none;
        }

        body::before {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, #6C9BCF, transparent);
            top: -200px;
            right: -200px;
        }

        body::after {
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, #B8D4EC, transparent);
            bottom: -150px;
            left: -150px;
            animation-delay: -12s;
        }

        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }

        /* Luxurious Navbar — Soft Blue (Typography Enhanced) */
        .navbar {
            background: var(--navbar-bg) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: none;
            box-shadow: 0 4px 20px rgba(108, 155, 207, 0.25);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            padding: 1.2rem 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .navbar:hover {
            box-shadow: 0 6px 30px rgba(108, 155, 207, 0.35);
        }

        /* ✨ Typography Upgrade: Clash Grotesk for Brand Impact */
        .navbar-brand {
            font-family: 'Clash Grotesk', 'Playfair Display', serif;
            font-weight: 700;
            font-size: clamp(1.4rem, 4vw, 1.7rem);
            color: white !important;
            letter-spacing: -0.8px;
            transition: all 0.3s ease;
            position: relative;
            display: inline-block;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: white;
            transition: width 0.3s ease;
        }

        .navbar-brand:hover::after {
            width: 100%;
        }

        .navbar-brand:hover {
            transform: scale(1.03);
        }

        /* Satoshi for Nav Links — Crisper than Jakarta Sans */
        .navbar .nav-link {
            font-family: 'Satoshi', 'Plus Jakarta Sans', sans-serif;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            position: relative;
            padding: 0.5rem 1rem;
            letter-spacing: 0.3px;
        }

        .navbar .nav-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: var(--primary-soft);
            transition: width 0.3s ease;
        }

        .navbar .nav-link:hover {
            color: var(--primary-soft);
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .navbar .nav-link:hover::before {
            width: 80%;
        }

        /* Navbar Dropdown Fix */
        .navbar .dropdown-menu {
            background: var(--card-bg);
            border: 1px solid rgba(108, 155, 207, 0.15);
            border-radius: 14px;
            box-shadow: 0 8px 32px rgba(108, 155, 207, 0.15);
            padding: 0.5rem;
        }
        
        .navbar .dropdown-item {
            color: var(--text-primary);
            font-family: 'Satoshi', sans-serif;
            border-radius: 10px;
            padding: 0.7rem 1rem;
            transition: all 0.3s ease;
        }

        /* Premium Sidebar — Typography Enhanced */
        .sidebar {
            position: fixed;
            top: 80px;
            bottom: 0;
            left: 0;
            z-index: 100;
            width: var(--sidebar-width);
            padding: 2rem 1.5rem;
            background: var(--glass-white);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-right: 1px solid rgba(108, 155, 207, 0.15);
            overflow-y: auto;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 30px rgba(108, 155, 207, 0.08);
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(108, 155, 207, 0.05);
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: var(--luxury-gradient);
            border-radius: 10px;
        }

        /* Clash Grotesk Light for Sidebar Headings */
        .sidebar-heading {
            font-family: 'Clash Grotesk', 'Playfair Display', serif;
            font-weight: 300;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            padding: 1.5rem 1.2rem 0.8rem;
            color: var(--text-light);
            position: relative;
        }

        .sidebar-heading::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 1.2rem;
            width: 40px;
            height: 2px;
            background: var(--luxury-gradient);
        }

        .sidebar .nav-link {
            font-family: 'Satoshi', 'Plus Jakarta Sans', sans-serif;
            font-weight: 500;
            font-size: 0.94rem;
            color: var(--text-secondary);
            padding: 1rem 1.2rem;
            border-radius: 16px;
            margin: 0.3rem 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            letter-spacing: 0.2px;
        }

        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 0;
            background: var(--luxury-gradient);
            transition: height 0.3s ease;
            border-radius: 0 4px 4px 0;
        }

        .sidebar .nav-link:hover {
            color: var(--primary-soft);
            font-weight: 600;
            background: var(--glass-blue);
            transform: translateX(8px);
            padding-left: 1.5rem;
            letter-spacing: 0.4px;
        }

        .sidebar .nav-link:hover::before {
            height: 100%;
        }

        .sidebar .nav-link.active {
            background: var(--luxury-gradient);
            color: white;
            font-weight: 600;
            box-shadow: 0 8px 24px rgba(108, 155, 207, 0.3);
            transform: translateX(8px);
            padding-left: 1.5rem;
        }

        .sidebar .nav-link.active::before {
            height: 100%;
            width: 100%;
            border-radius: 16px;
        }

        .sidebar .nav-link i {
            width: 28px;
            margin-right: 14px;
            font-size: 1.15rem;
        }

        /* Elegant Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2.5rem;
            min-height: calc(100vh - 80px);
            position: relative;
            z-index: 1;
            max-width: 100%;
            overflow-x: hidden;
        }

        /* Premium Card Design — Typography Enhanced */
        .card {
            background: var(--card-bg);
            border: 1px solid rgba(108, 155, 207, 0.12);
            border-radius: 24px;
            margin-bottom: 2rem;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            position: relative;
            box-shadow: 0 8px 32px rgba(108, 155, 207, 0.08);
        }

        .card::before {
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

        .card:hover::before {
            transform: scaleX(1);
        }

        .card:hover {
            transform: translateY(-12px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(108, 155, 207, 0.25);
        }

        /* ✨ Clash Grotesk for Card Headers */
        .card-header {
            background: transparent !important;
            border-bottom: 1px solid rgba(108, 155, 207, 0.1);
            font-weight: 600;
            padding: 1.8rem;
            font-family: 'Clash Grotesk', 'Playfair Display', serif;
            font-size: 1.15rem;
            letter-spacing: -0.3px;
            color: var(--text-elegant) !important;
        }

        .card-header.bg-primary {
            background: var(--luxury-gradient) !important;
            border: none;
            color: white !important;
        }

        .card-header.bg-warning {
            background: var(--gold-gradient) !important;
            border: none;
            color: white !important;
        }

        .card-header.bg-success {
            background: var(--mint-gradient) !important;
            border: none;
            color: #2d6a4f !important;
        }

        .card-header.bg-info {
            background: linear-gradient(135deg, #89CFF0 0%, #B8E0F6 100%) !important;
            border: none;
            color: #1e4d6a !important;
        }

        .card-body {
            padding: 1.8rem;
        }

        /* Stats Value — Geist Mono Gold Accent */
        .stats-value {
            font-family: 'Geist Mono', monospace;
            font-weight: 700;
            font-size: clamp(1.8rem, 6vw, 2.8rem);
            letter-spacing: -1px;
            color: var(--primary-soft);
        }

        .stats-label {
            font-family: 'Satoshi', sans-serif;
            font-weight: 400;
            font-size: 0.95rem;
            color: var(--text-secondary);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* Elegant Table — Typography Enhanced */
        .table thead th {
            border-bottom: 2px solid rgba(108, 155, 207, 0.2);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.78rem;
            color: var(--text-elegant);
            letter-spacing: 1.8px;
            padding: 1.2rem;
            background: var(--glass-blue);
            font-family: 'Satoshi', 'Playfair Display', serif;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 1.2rem;
            vertical-align: middle;
            color: var(--text-secondary);
            font-family: 'Satoshi', sans-serif;
        }

        /* IDs & Timestamps — Geist Mono */
        .table-id, .table-timestamp {
            font-family: 'Geist Mono', monospace;
            font-size: 0.88rem;
            color: var(--text-primary);
        }

        /* Premium Buttons — Satoshi for Crisp Text */
        .btn {
            font-family: 'Satoshi', sans-serif;
            font-weight: 600;
            padding: 0.85rem 1.8rem;
            font-size: 0.95rem;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            border-radius: 14px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: none !important;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 400px;
            height: 400px;
        }

        .btn:hover {
            transform: translateY(-4px);
        }

        .btn-primary {
            background: var(--luxury-gradient);
            color: white;
            box-shadow: 0 10px 30px rgba(108, 155, 207, 0.3);
        }

        .btn-warning {
            background: var(--gold-gradient);
            color: var(--text-gold);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
        }

        /* Sophisticated Alerts — Typography Enhanced */
        .alert {
            border: none;
            border-left: 4px solid;
            border-radius: 18px;
            padding: 1.5rem;
            animation: slideInRight 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
            font-family: 'Satoshi', sans-serif;
        }

        .alert-title {
            font-family: 'Clash Grotesk', serif;
            font-weight: 600;
            font-size: 1.15rem;
            letter-spacing: -0.4px;
            margin-bottom: 0.4rem;
        }

        .alert-success { border-left-color: #52b788; background: linear-gradient(135deg, #d8f3dc 0%, #b7e4c7 50%); color: #2d6a4f; }
        .alert-danger { border-left-color: #e63946; background: linear-gradient(135deg, #ffe5e9 0%, #ffccd5 50%); color: #8b1e3f; }
        .alert-warning { border-left-color: #D4AF37; background: linear-gradient(135deg, #fff8e1 0%, #ffe9a8 50%); color: var(--text-gold); }
        .alert-info { border-left-color: #6C9BCF; background: linear-gradient(135deg, #e8f2ff 0%, #d0e7ff 50%); color: #1e4d6a; }

        /* Premium Form Elements */
        .form-control, .form-select {
            background: white;
            border: 2px solid rgba(108, 155, 207, 0.15);
            border-radius: 14px;
            color: var(--text-primary);
            padding: 0.85rem 1.2rem;
            transition: all 0.3s ease;
            font-family: 'Satoshi', sans-serif;
            font-size: 0.95rem;
        }

        .form-label {
            color: var(--text-elegant);
            font-weight: 600;
            margin-bottom: 0.6rem;
            font-family: 'Satoshi', sans-serif;
            font-size: 0.95rem;
            letter-spacing: 0.3px;
        }

        /* Elegant Footer */
        footer {
            background: var(--glass-white) !important;
            backdrop-filter: blur(30px);
            border-top: 1px solid rgba(108, 155, 207, 0.15);
            color: var(--text-secondary);
            padding: 2rem 0;
            margin-top: 4rem;
            box-shadow: 0 -6px 30px rgba(108, 155, 207, 0.08);
            position: relative;
            z-index: 10;
            width: 100%;
        }

        /* 🌟 Text Shimmer for Headings (Subtle Luxury) */
        .text-shimmer {
            position: relative;
            display: inline-block;
        }

        .text-shimmer::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.8s ease;
        }

        .text-shimmer:hover::after {
            left: 100%;
        }

        /* Responsive */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1040;
            }

            .sidebar.show {
                transform: translateX(0);
                box-shadow: 8px 0 40px rgba(108, 155, 207, 0.2);
            }

            .main-content {
                margin-left: 0;
                padding: 1.5rem;
            }

            body {
                padding-top: 70px;
            }
        }

        /* ✨ Auto-Apply Shimmer to Key Headings */
        .display-1, .display-2, .display-3, .display-4,
        .card-header, .stats-value, .alert-title, .navbar-brand {
            animation: none;
        }

        /* Init shimmer on load */
        .text-shimmer {
            opacity: 1;
        }
    </style>
</head>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-flash alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <strong>Berhasil!</strong> <?= htmlspecialchars($_SESSION['success_message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-flash alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i>
    <strong>Error!</strong> <?= htmlspecialchars($_SESSION['error_message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['error_message']); ?>
<?php endif; ?>
<body>



<script>
document.addEventListener('DOMContentLoaded', () => {
    // Apply shimmer to branding & key headings
    const shimmerElements = document.querySelectorAll(
        '.navbar-brand, .card-header, .stats-value, .alert-title, h1, h2, h3'
    );
    shimmerElements.forEach(el => {
        el.classList.add('text-shimmer');
    });

    // Sidebar & Theme logic (if needed later)
});
</script>

