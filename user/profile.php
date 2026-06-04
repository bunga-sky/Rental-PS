<?php
require_once '../config.php';
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Ambil data user
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Update profile
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        
        $update_query = "UPDATE users SET phone = ?, email = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $phone, $email, $user_id);
        
        if($stmt->execute()) {
            $success = "Profile berhasil diupdate!";
            // Refresh data
            $query = "SELECT * FROM users WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Gagal update profile!";
        }
    }
    
    if(isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if(password_verify($current, $user['password'])) {
            if($new == $confirm) {
                $new_hash = password_hash($new, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("si", $new_hash, $user_id);
                
                if($stmt->execute()) {
                    $success = "Password berhasil diubah!";
                } else {
                    $error = "Gagal mengubah password!";
                }
            } else {
                $error = "Password baru tidak cocok!";
            }
        } else {
            $error = "Password saat ini salah!";
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <h1 class="card-title">Profile Saya</h1>
    
    <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="grid">
        <!-- Info Profile -->
        <div class="card">
            <h2>Informasi Profile</h2>
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="width: 100px; height: 100px; background: #667eea; border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                    <span style="font-size: 3rem; color: white;"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                </div>
                <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                <p>Member since <?php echo date('d F Y', strtotime($user['created_at'])); ?></p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" class="form-control" disabled>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>No. Telepon</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="form-control">
                </div>
                
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
        
        <!-- Statistik -->
        <div class="card">
            <h2>Statistik Duel</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; text-align: center; margin-bottom: 2rem;">
                <div style="background: #f7fafc; padding: 1rem; border-radius: 5px;">
                    <div style="font-size: 2rem; font-weight: 700; color: #48bb78;"><?php echo $user['total_wins']; ?></div>
                    <div>Menang</div>
                </div>
                <div style="background: #f7fafc; padding: 1rem; border-radius: 5px;">
                    <div style="font-size: 2rem; font-weight: 700; color: #f56565;"><?php echo $user['total_matches'] - $user['total_wins']; ?></div>
                    <div>Kalah</div>
                </div>
            </div>
            
            <?php
            $win_rate = $user['total_matches'] > 0 ? 
                round(($user['total_wins'] / $user['total_matches']) * 100) : 0;
            ?>
            <p><strong>Total Matches:</strong> <?php echo $user['total_matches']; ?></p>
            <p><strong>Win Rate:</strong> <?php echo $win_rate; ?>%</p>
            
            <hr style="margin: 2rem 0;">
            
            <h2>Ganti Password</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Password Saat Ini</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Password Baru</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Konfirmasi Password Baru</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" name="change_password" class="btn btn-warning">Ganti Password</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>