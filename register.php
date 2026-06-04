<?php
require_once 'config.php';
session_start();

if(isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    // Cek username/email sudah ada
    $check_query = "SELECT id FROM users WHERE username = '$username' OR email = '$email'";
    $check_result = $conn->query($check_query);
    
    if($check_result->num_rows > 0) {
        $error = "Username atau email sudah terdaftar!";
    } else {
        $insert_query = "INSERT INTO users (username, email, password, phone) 
                        VALUES ('$username', '$email', '$password', '$phone')";
        
        if($conn->query($insert_query)) {
            $success = "Registrasi berhasil! Silakan login.";
        } else {
            $error = "Registrasi gagal: " . $conn->error;
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card" style="max-width: 500px; margin: 0 auto;">
        <h1 class="card-title">Registrasi</h1>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>No. Telepon</label>
                <input type="text" name="phone" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Daftar</button>
        </form>
        
        <p style="text-align: center; margin-top: 1rem;">
            Sudah punya akun? <a href="login.php">Login</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>