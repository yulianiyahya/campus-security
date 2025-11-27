<?php
require_once '../config.php';

// Check if user is logged in and is security officer
if (!is_logged_in() || $_SESSION['role'] !== 'security') {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get security officer ID
$sql = "SELECT id FROM security_officers WHERE user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$officer = $stmt->fetch();
$officer_id = $officer['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
    $new_status = clean_input($_POST['status']);
    $resolution_notes = isset($_POST['resolution_notes']) ? clean_input($_POST['resolution_notes']) : '';
    
    // Validate status
    $valid_statuses = ['in_progress', 'resolved', 'rejected'];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['error_message'] = "Status tidak valid!";
        redirect("handle_report.php?id=$report_id");
    }
    
    // Check if report belongs to this officer
    $sql = "SELECT * FROM reports WHERE id = ? AND assigned_officer_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$report_id, $officer_id]);
    $report = $stmt->fetch();
    
    if (!$report) {
        $_SESSION['error_message'] = "Laporan tidak ditemukan atau bukan tugas Anda!";
        redirect('my_assignments.php');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update report status
        if ($new_status === 'resolved') {
            $sql = "UPDATE reports 
                    SET status = ?, resolution_notes = ?, resolved_at = NOW(), updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_status, $resolution_notes, $report_id]);
            
            $action_desc = "Laporan diselesaikan";
        } elseif ($new_status === 'rejected') {
            $sql = "UPDATE reports 
                    SET status = ?, resolution_notes = ?, updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_status, $resolution_notes, $report_id]);
            
            $action_desc = "Laporan ditolak dengan alasan: " . $resolution_notes;
        } else {
            $sql = "UPDATE reports 
                    SET status = ?, updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_status, $report_id]);
            
            $action_desc = "Status diubah menjadi: " . $new_status;
        }
        
        // Log action
        $sql = "INSERT INTO report_actions (report_id, officer_id, action_type, action_description, notes, action_date) 
                VALUES (?, ?, 'status_update', ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$report_id, $officer_id, $action_desc, $resolution_notes]);
        
        // Send notification to reporter
        $sql = "INSERT INTO notifications (user_id, title, message, type, reference_id, created_at) 
                VALUES (?, ?, ?, 'report_update', ?, NOW())";
        
        $notification_title = "Update Status Laporan";
        $notification_message = "Laporan Anda ({$report['report_number']}) telah diupdate menjadi status: " . 
                               ($new_status === 'resolved' ? 'Selesai' : 
                               ($new_status === 'rejected' ? 'Ditolak' : 'Sedang Ditangani'));
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$report['reporter_id'], $notification_title, $notification_message, $report_id]);
        
        $pdo->commit();
        
        // Handle different response types
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['success' => true, 'message' => 'Status berhasil diupdate!']);
            exit;
        } else {
            $_SESSION['success_message'] = "Status laporan berhasil diupdate!";
            redirect("handle_report.php?id=$report_id");
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        } else {
            $_SESSION['error_message'] = "Gagal mengupdate status: " . $e->getMessage();
            redirect("handle_report.php?id=$report_id");
        }
    }
} else {
    redirect('my_assignments.php');
}
