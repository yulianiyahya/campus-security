<?php 
require_once 'config.php';

$error = '';
$success = '';

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nim_nip = clean_input($_POST['nim_nip']);
    $password_baru = $_POST['password_baru'];
    $konfirmasi = $_POST['konfirmasi_password'];

    if (empty($nim_nip) || empty($password_baru) || empty($konfirmasi)) {
        $error = "Semua field harus diisi!";
    } elseif ($password_baru !== $konfirmasi) {
        $error = "Konfirmasi password tidak cocok!";
    } else {
        // Cek apakah user ada
        $stmt = $pdo->prepare("SELECT id FROM users WHERE nim_nip = ?");
        $stmt->execute([$nim_nip]);
        $user = $stmt->fetch();

        if ($user) {
            // Update password baru
            $hash = password_hash($password_baru, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE nim_nip = ?");
            $stmt->execute([$hash, $nim_nip]);

            $success = "Password berhasil diperbarui! Silakan login kembali.";
        } else {
            $error = "NIM/NIP tidak ditemukan!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lupa Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="col-md-4 mx-auto">
        <div class="card p-4 shadow-sm">
            <h3 class="text-center mb-3">Lupa Password</h3>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <label class="mb-1">Masukkan NIM/NIP Anda:</label>
                <input type="text" name="nim_nip" class="form-control mb-3" required>

                <label class="mb-1">Password Baru:</label>
                <input type="password" name="password_baru" class="form-control mb-3" required>

                <label class="mb-1">Konfirmasi Password Baru:</label>
                <input type="password" name="konfirmasi_password" class="form-control mb-3" required>

                <button type="submit" class="btn btn-primary w-100">Reset Password</button>

                <a href="login.php" class="btn btn-secondary w-100 mt-3">Kembali ke Login</a>
            </form>
        </div>
    </div>
</div>

</body>
</html>
