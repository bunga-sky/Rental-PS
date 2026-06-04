<?php
require_once '../config.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Hapus user
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM users WHERE id = ? AND role = 'customer'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if($stmt->execute()) {
        $success = "User berhasil dihapus!";
    } else {
        $error = "Gagal menghapus user!";
    }
}

include '../includes/header.php';
?>

<div class="container">
    <h1 class="card-title">Kelola Users</h1>
    
    <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Total Wins</th>
                    <th>Total Matches</th>
                    <th>Join Date</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $query = "SELECT * FROM users WHERE role = 'customer' ORDER BY created_at DESC";
                $result = $conn->query($query);
                
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                    echo "<td>" . $row['total_wins'] . "</td>";
                    echo "<td>" . $row['total_matches'] . "</td>";
                    echo "<td>" . date('d/m/Y', strtotime($row['created_at'])) . "</td>";
                    echo "<td>
                            <a href='?delete=" . $row['id'] . "' class='btn btn-danger btn-small' 
                               onclick='return confirm(\"Yakin hapus user?\")'>Hapus</a>
                          </td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>