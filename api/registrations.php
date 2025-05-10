<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'volunteer_system');
if ($conn->connect_error) {
    file_put_contents('../debug.log', "Ошибка подключения к базе данных: " . $conn->connect_error . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к базе данных']);
    exit;
}

$raw_input = file_get_contents('php://input');
file_put_contents('../debug.log', "Registrations request: $raw_input\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $volunteer_id = $_GET['volunteer_id'] ?? '';
    $calendar = isset($_GET['calendar']) && $_GET['calendar'] === '1';

    file_put_contents('../debug.log', "GET params: volunteer_id=$volunteer_id, calendar=$calendar\n", FILE_APPEND);

    if (empty($volunteer_id)) {
        file_put_contents('../debug.log', "Ошибка: Укажите ID волонтёра\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID волонтёра']);
        exit;
    }

    if ($calendar) {
        // Формат для FullCalendar
        $stmt = $conn->prepare('
            SELECT e.id, e.title, e.event_date as start
            FROM registrations r
            JOIN events e ON r.event_id = e.id
            WHERE r.volunteer_id = ?
        ');
        if (!$stmt) {
            file_put_contents('../debug.log', "Ошибка подготовки запроса (calendar): " . $conn->error . "\n", FILE_APPEND);
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка подготовки запроса']);
            exit;
        }
        $stmt->bind_param('i', $volunteer_id);
    } else {
        // Полный список регистраций
        $stmt = $conn->prepare('
            SELECT r.event_id, r.hours, r.registered_at,
                   e.title, e.description, e.event_date, e.status, e.hours as event_hours, e.organizer_id,
                   o.name as organizer_name,
                   rp.rating, rp.comments as comment
            FROM registrations r
            JOIN events e ON r.event_id = e.id
            JOIN organizers o ON e.organizer_id = o.id
            LEFT JOIN reports rp ON r.volunteer_id = rp.volunteer_id AND r.event_id = rp.event_id
            WHERE r.volunteer_id = ?
        ');
        if (!$stmt) {
            file_put_contents('../debug.log', "Ошибка подготовки запроса (registrations): " . $conn->error . "\n", FILE_APPEND);
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка подготовки запроса']);
            exit;
        }
        $stmt->bind_param('i', $volunteer_id);
    }

    if (!$stmt->execute()) {
        file_put_contents('../debug.log', "Ошибка выполнения запроса: " . $stmt->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка выполнения запроса']);
        exit;
    }

    $result = $stmt->get_result();
    $registrations = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($registrations);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode($raw_input, true);
    if (!$data) {
        file_put_contents('../debug.log', "Ошибка: Неверный формат данных для POST\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Неверный формат данных']);
        exit;
    }

    $volunteer_id = $data['volunteer_id'] ?? '';
    $event_id = $data['event_id'] ?? '';

    if (empty($volunteer_id) || empty($event_id)) {
        file_put_contents('../debug.log', "Ошибка: Укажите ID волонтёра и мероприятия\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Укажите ID волонтёра и мероприятия']);
        exit;
    }

    // Проверка, что мероприятие активно
    $stmt = $conn->prepare('SELECT status, max_participants, (SELECT COUNT(*) FROM registrations WHERE event_id = ?) as current_participants FROM events WHERE id = ?');
    if (!$stmt) {
        file_put_contents('../debug.log', "Ошибка подготовки запроса (event check): " . $conn->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки запроса']);
        exit;
    }
    $stmt->bind_param('ii', $event_id, $event_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();

    if (!$event) {
        file_put_contents('../debug.log', "Мероприятие с ID $event_id не найдено\n", FILE_APPEND);
        http_response_code(404);
        echo json_encode(['error' => 'Мероприятие не найдено']);
        exit;
    }

    if ($event['status'] !== 'active') {
        file_put_contents('../debug.log', "Мероприятие с ID $event_id не активно\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Мероприятие не активно']);
        exit;
    }

    if ($event['current_participants'] >= $event['max_participants']) {
        file_put_contents('../debug.log', "Мероприятие с ID $event_id достигло максимума участников\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Мероприятие достигло максимума участников']);
        exit;
    }

    // Проверка, что волонтёр ещё не зарегистрирован
    $stmt = $conn->prepare('SELECT id FROM registrations WHERE volunteer_id = ? AND event_id = ?');
    if (!$stmt) {
        file_put_contents('../debug.log', "Ошибка подготовки запроса (registration check): " . $conn->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки запроса']);
        exit;
    }
    $stmt->bind_param('ii', $volunteer_id, $event_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        file_put_contents('../debug.log', "Волонтёр $volunteer_id уже зарегистрирован на мероприятие $event_id\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Вы уже зарегистрированы на это мероприятие']);
        exit;
    }

    // Регистрация
    $stmt = $conn->prepare('INSERT INTO registrations (volunteer_id, event_id) VALUES (?, ?)');
    if (!$stmt) {
        file_put_contents('../debug.log', "Ошибка подготовки запроса (insert registration): " . $conn->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка подготовки запроса']);
        exit;
    }
    $stmt->bind_param('ii', $volunteer_id, $event_id);

    if (!$stmt->execute()) {
        file_put_contents('../debug.log', "Ошибка выполнения запроса (insert registration): " . $stmt->error . "\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка выполнения запроса']);
        exit;
    }

    if ($stmt->affected_rows > 0) {
        http_response_code(201);
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка при регистрации']);
    }
}

$conn->close();
?>