<?php
/**
 * =====================================================
 * LOGOUT.PHP - Logout Handler
 * Menghapus session dan redirect ke login
 * =====================================================
 */

require_once 'config.php';

// Update logout time di database
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE user_sessions 
            SET logout_at = NOW() 
            WHERE user_id = ? AND logout_at IS NULL
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Ignore error
    }
}

// Hapus semua session
session_unset();
session_destroy();

// Redirect ke login dengan pesan
redirect('login.php?logout=success');
?>