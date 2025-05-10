<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'volunteer_system');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к базе данных: ' . $conn->connect_error]);
    exit;
}

$raw_input = file_get_contents('php://input');
file_put_contents('../debug.log', "Registrations request: $raw_input\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $volunteer_id = $_GET['volunteer_id'] ?? '';
    $calendar = $_GET['calendar'] ?? 0;

    if (empty($volunteer_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID волонтёра']);
        exit;
    }

    $stmt = $conn->prepare('
             SELECT e.id, e.title, e.description, e.event_date, COALESCE(r.hours, e.hours) as hours
             FROM registrations r
             JOIN events e ON r.event_id = e.id
             WHERE r.volunteer_id = ?
         ');
    $stmt->bind_param('i', $volunteer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $registrations = $result->fetch_all(MYSQLI_ASSOC);

    if ($calendar) {
        $events = array_map(function($reg) {
            return [
                'id' => $reg['id'],
                'title' => $reg['title'],
                'start' => $reg['event_date'],
                'description' => $reg['description']
            ];
        }, $registrations);
        echo json_encode($events);
    } else {
        echo json_encode($registrations);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode($raw_input, true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат данных']);
        exit;
    }

    $volunteer_id = $data['volunteer_id'] ?? '';
    $event_id = $data['event_id'] ?? '';

    if (empty($volunteer_id) || empty($event_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID волонтёра и мероприятия']);
        exit;
    }

    $stmt = $conn->prepare('SELECT COUNT(*) as count, max_participants FROM registrations r JOIN events e ON r.event_id = e.id WHERE r.event_id = ?');
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['count'] >= $result['max_participants']) {
        http_response_code(400);
        echo json_encode(['error' => 'Мероприятие заполнено']);
        exit;
    }

    $stmt = $conn->prepare('INSERT INTO registrations (volunteer_id, event_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $volunteer_id, $event_id);
    $stmt->execute();
    http_response_code(201);
    echo json_encode(['success' => true]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не разрешен']);
}

$conn->close();
?>