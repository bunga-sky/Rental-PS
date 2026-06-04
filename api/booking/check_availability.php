<?php
// ============================================================
// API: Cek Ketersediaan PlayStation
// GET /api/booking/check_availability.php
//     ?ps_id=1&date=2026-03-10&start_time=10:00&duration=2
// ============================================================

require_once __DIR__ . '/../../api/middleware.php';
requireMethod('GET');

$psId      = isset($_GET['ps_id'])     ? (int) $_GET['ps_id']           : 0;
$date      = isset($_GET['date'])      ? trim($_GET['date'])             : '';
$startTime = isset($_GET['start_time'])? trim($_GET['start_time'])       : '';
$duration  = isset($_GET['duration'])  ? (int) $_GET['duration']         : 0;

// Validasi input
if (!$psId || !$date || !$startTime || !$duration) {
    jsonResponse(['status' => 'error', 'message' => 'Parameter tidak lengkap'], 422);
}

// Validasi tanggal tidak boleh lampau
if ($date < date('Y-m-d')) {
    jsonResponse(['status' => 'error', 'message' => 'Tanggal tidak boleh lampau'], 422);
}

// Validasi durasi
if ($duration < MIN_BOOKING_DURATION || $duration > MAX_BOOKING_DURATION) {
    jsonResponse([
        'status'  => 'error',
        'message' => 'Durasi harus antara ' . MIN_BOOKING_DURATION . '-' . MAX_BOOKING_DURATION . ' jam'
    ], 422);
}

// Hitung end_time
$endTime = date('H:i:s', strtotime($startTime . ' +' . $duration . ' hours'));

// Validasi jam operasional
if ($startTime < OPEN_HOUR || $endTime > CLOSE_HOUR . ':00') {
    jsonResponse([
        'status'  => 'error',
        'message' => 'Jam operasional ' . OPEN_HOUR . ' - ' . CLOSE_HOUR
    ], 422);
}

// Cek apakah PS ada dan available
$stmtPs = $conn->prepare("SELECT id, name, type, status FROM playstation WHERE id = ?");
$stmtPs->bind_param('i', $psId);
$stmtPs->execute();
$ps = $stmtPs->get_result()->fetch_assoc();

if (!$ps) {
    jsonResponse(['status' => 'error', 'message' => 'PlayStation tidak ditemukan'], 404);
}
if ($ps['status'] === 'maintenance') {
    jsonResponse(['status' => 'error', 'message' => 'PlayStation sedang dalam maintenance'], 400);
}

// Cek konflik jadwal pakai playstation_schedules (index-optimized)
// Overlap condition: start_a < end_b AND end_a > start_b
$stmtConflict = $conn->prepare("
    SELECT b.id, b.start_time, b.end_time, u.username
    FROM playstation_schedules ps_sched
    JOIN bookings b ON ps_sched.booking_id = b.id
    JOIN users u ON b.user_id = u.id
    WHERE ps_sched.ps_id = ?
      AND ps_sched.schedule_date = ?
      AND ps_sched.is_active = 1
      AND ps_sched.start_time < ?
      AND ps_sched.end_time   > ?
    ORDER BY ps_sched.start_time
");
$stmtConflict->bind_param('isss', $psId, $date, $endTime, $startTime);
$stmtConflict->execute();
$conflicts = $stmtConflict->get_result()->fetch_all(MYSQLI_ASSOC);

// Ambil semua slot yang sudah dibooking pada tanggal itu
$stmtSlots = $conn->prepare("
    SELECT ps_sched.start_time, ps_sched.end_time
    FROM playstation_schedules ps_sched
    WHERE ps_sched.ps_id = ?
      AND ps_sched.schedule_date = ?
      AND ps_sched.is_active = 1
    ORDER BY ps_sched.start_time
");
$stmtSlots->bind_param('is', $psId, $date);
$stmtSlots->execute();
$bookedSlots = $stmtSlots->get_result()->fetch_all(MYSQLI_ASSOC);

// Hitung harga
$pricePerHour = ($ps['type'] === 'PS4') ? PS4_PRICE_PER_HOUR : PS5_PRICE_PER_HOUR;
$totalPrice   = $pricePerHour * $duration;

$isAvailable = count($conflicts) === 0;

jsonResponse([
    'status'      => 'success',
    'available'   => $isAvailable,
    'message'     => $isAvailable ? 'Jadwal tersedia!' : 'Jadwal sudah dibooking',
    'ps'          => [
        'id'   => $ps['id'],
        'name' => $ps['name'],
        'type' => $ps['type'],
    ],
    'slot' => [
        'date'       => $date,
        'start_time' => $startTime,
        'end_time'   => $endTime,
        'duration'   => $duration,
    ],
    'price' => [
        'per_hour'    => $pricePerHour,
        'total'       => $totalPrice,
        'formatted'   => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
    ],
    'booked_slots' => $bookedSlots, // untuk render kalender di frontend
    'conflicts'    => $isAvailable ? [] : array_map(fn($c) => [
        'start_time' => substr($c['start_time'], 0, 5),
        'end_time'   => substr($c['end_time'], 0, 5),
    ], $conflicts),
]);
