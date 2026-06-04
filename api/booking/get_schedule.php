<?php
// ============================================================
// API: Ambil Jadwal PS (untuk kalender / slot picker)
// GET /api/booking/get_schedule.php?ps_id=1&date=2026-03-10
// ============================================================

require_once __DIR__ . '/../../api/middleware.php';
requireMethod('GET');

$psId = isset($_GET['ps_id']) ? (int) $_GET['ps_id'] : 0;
$date = isset($_GET['date'])  ? trim($_GET['date'])  : date('Y-m-d');

if (!$psId) {
    jsonResponse(['status' => 'error', 'message' => 'ps_id wajib diisi'], 422);
}

// Ambil semua slot yang sudah dibooking
$stmt = $conn->prepare("
    SELECT
        ps_sched.start_time,
        ps_sched.end_time
    FROM playstation_schedules ps_sched
    JOIN bookings b ON ps_sched.booking_id = b.id
    WHERE ps_sched.ps_id = ?
      AND ps_sched.schedule_date = ?
      AND ps_sched.is_active = 1
      AND b.payment_status IN ('pending', 'paid')
    ORDER BY ps_sched.start_time
");
$stmt->bind_param('is', $psId, $date);
$stmt->execute();
$bookedSlots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Generate semua slot 1 jam dalam jam operasional
$openHour  = (int) explode(':', OPEN_HOUR)[0];
$closeHour = (int) explode(':', CLOSE_HOUR)[0];
$allSlots  = [];

for ($hour = $openHour; $hour < $closeHour; $hour++) {
    $slotStart = sprintf('%02d:00', $hour);
    $slotEnd   = sprintf('%02d:00', $hour + 1);

    $isBooked = false;
    foreach ($bookedSlots as $booked) {
        $bookedStart = substr($booked['start_time'], 0, 5);
        $bookedEnd   = substr($booked['end_time'], 0, 5);
        // Overlap check
        if ($slotStart < $bookedEnd && $slotEnd > $bookedStart) {
            $isBooked = true;
            break;
        }
    }

    $allSlots[] = [
        'start'     => $slotStart,
        'end'       => $slotEnd,
        'available' => !$isBooked,
    ];
}

jsonResponse([
    'status'       => 'success',
    'date'         => $date,
    'ps_id'        => $psId,
    'open_hour'    => OPEN_HOUR,
    'close_hour'   => CLOSE_HOUR,
    'booked_slots' => $bookedSlots,
    'all_slots'    => $allSlots,
]);
