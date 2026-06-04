<?php
require_once '../config.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Tambah tournament
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_tournament'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $max_participants = $_POST['max_participants'];
    
    $query = "INSERT INTO tournaments (name, description, start_date, end_date, max_participants) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssi", $name, $description, $start_date, $end_date, $max_participants);
    
    if($stmt->execute()) {
        $success = "Tournament berhasil ditambahkan!";
    } else {
        $error = "Gagal menambahkan tournament!";
    }
}

// Hapus tournament
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $query = "DELETE FROM tournaments WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if($stmt->execute()) {
        $success = "Tournament berhasil dihapus!";
    } else {
        $error = "Gagal menghapus tournament!";
    }
}

include '../includes/header.php';
?>

<div class="container">
    <h1 class="card-title">Kelola Tournament</h1>
    
    <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="grid">
        <!-- Form Tambah Tournament -->
        <div class="card">
            <h2>Tambah Tournament</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Nama Tournament</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="description" class="form-control" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Tanggal Mulai</label>
                    <input type="datetime-local" name="start_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Tanggal Selesai</label>
                    <input type="datetime-local" name="end_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Max Peserta</label>
                    <input type="number" name="max_participants" class="form-control" min="2" value="16" required>
                </div>
                
                <button type="submit" name="add_tournament" class="btn btn-primary">Tambah Tournament</button>
            </form>
        </div>
        
        <!-- Daftar Tournament -->
        <div class="card">
            <h2>Daftar Tournament</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Peserta</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT t.*, 
                             (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as participants
                             FROM tournaments t
                             ORDER BY t.start_date DESC";
                    $result = $conn->query($query);
                    
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                        echo "<td>" . $row['participants'] . "/" . $row['max_participants'] . "</td>";
                        echo "<td>" . date('d/m/Y', strtotime($row['start_date'])) . "</td>";
                        echo "<td><span class='badge badge-" . $row['status'] . "'>" . 
                             ucfirst($row['status']) . "</span></td>";
                        echo "<td>
                                <a href='?delete=" . $row['id'] . "' class='btn btn-danger btn-small'
                                   onclick='return confirm(\"Yakin hapus?\")'>Hapus</a>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
