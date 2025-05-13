<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

include 'db_connect.php';

$tag = isset($_GET['tag']) ? $conn->real_escape_string($_GET['tag']) : '';

if (!$tag) {
    echo json_encode(['error' => 'Укажите тег мероприятия']);
    exit;
}

$sql = "SELECT * FROM events WHERE tags = ? AND status = 'active' AND event_date > NOW() LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $tag);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
if ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

echo json_encode($events);

$stmt->close();
$conn->close();
?>