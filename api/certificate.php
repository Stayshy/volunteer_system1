<?php
header('Content-Type: text/html; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'volunteer_system');
if ($conn->connect_error) {
    file_put_contents('../debug.log', "Ошибка подключения к базе данных: " . $conn->connect_error . "\n", FILE_APPEND);
    http_response_code(500);
    echo "Ошибка подключения к базе данных: " . $conn->connect_error;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $volunteer_id = $_GET['volunteer_id'] ?? '';
    $event_id = $_GET['event_id'] ?? '';

    file_put_contents('../debug.log', "Certificate request: volunteer_id=$volunteer_id, event_id=$event_id\n", FILE_APPEND);

    if (empty($volunteer_id) || empty($event_id)) {
        file_put_contents('../debug.log', "Ошибка: Укажите ID волонтёра и мероприятия\n", FILE_APPEND);
        http_response_code(400);
        echo "Укажите ID волонтёра и мероприятия";
        exit;
    }

    // Получаем данные волонтёра
    file_put_contents('../debug.log', "Шаг 1: Получение данных волонтёра\n", FILE_APPEND);
    $stmt = $conn->prepare('SELECT name FROM volunteers WHERE id = ?');
    if (!$stmt) {
        file_put_contents('../debug.log', "Ошибка подготовки запроса (volunteer): " . $conn->error . "\n", FILE_APPEND);
        http_response_code(500);
        exit;
    }
    $stmt->bind_param('i', $volunteer_id);
    if (!$stmt->execute()) {
        file_put_contents('../debug.log', "Ошибка выполнения запроса (volunteer): " . $stmt->error . "\n", FILE_APPEND);
        http_response_code(500);
        exit;
    }
    $volunteer = $stmt->get_result()->fetch_assoc();
    if (!$volunteer) {
        file_put_contents('../debug.log', "Волонтёр с ID $volunteer_id не найден\n", FILE_APPEND);
        http_response_code(404);
        exit;
    }
    $volunteer_name = $volunteer['name'];
    file_put_contents('../debug.log', "Volunteer name: $volunteer_name\n", FILE_APPEND);

    // Получаем данные мероприятия и организатора
    file_put_contents('../debug.log', "Шаг 2: Получение данных мероприятия\n", FILE_APPEND);
    $stmt = $conn->prepare('
        SELECT e.title, e.event_date, e.status, e.hours, e.completed_at, e.organizer_id, o.name as organizer_name
        FROM events e
        JOIN organizers o ON e.organizer_id = o.id
        WHERE e.id = ?
    ');
    if (!$stmt) {
        file_put_contents('../debug.log', "Ошибка подготовки запроса (event): " . $conn->error . "\n", FILE_APPEND);
        http_response_code(500);
        exit;
    }
    $stmt->bind_param('i', $event_id);
    if (!$stmt->execute()) {
        file_put_contents('../debug.log', "Ошибка выполнения запроса (event): " . $stmt->error . "\n", FILE_APPEND);
        http_response_code(500);
        exit;
    }
    $event = $stmt->get_result()->fetch_assoc();
    if (!$event) {
        file_put_contents('../debug.log', "Мероприятие с ID $event_id не найдено\n", FILE_APPEND);
        http_response_code(404);
        exit;
    }

    if ($event['status'] !== 'completed') {
        file_put_contents('../debug.log', "Мероприятие с ID $event_id не завершено\n", FILE_APPEND);
        http_response_code(400);
        exit;
    }

    $event_title = $event['title'];
    $event_date = date('d.m.Y', strtotime($event['event_date']));
    $event_hours = $event['hours'];
    $organizer_name = $event['organizer_name'];
    $issue_date = isset($event['completed_at']) && $event['completed_at'] ? date('d.m.Y', strtotime($event['completed_at'])) : date('d.m.Y');
    file_put_contents('../debug.log', "Event title: $event_title, Organizer name: $organizer_name, Issue date: $issue_date\n", FILE_APPEND);

    // Получаем данные регистрации
    file_put_contents('../debug.log', "Шаг 3: Получение данных регистрации\n", FILE_APPEND);
    $stmt = $conn->prepare('SELECT hours FROM registrations WHERE volunteer_id = ? AND event_id = ?');
    if (!$stmt) {
        file_put_contents('../debug.log', "Ошибка подготовки запроса (registration): " . $conn->error . "\n", FILE_APPEND);
        http_response_code(500);
        exit;
    }
    $stmt->bind_param('ii', $volunteer_id, $event_id);
    if (!$stmt->execute()) {
        file_put_contents('../debug.log', "Ошибка выполнения запроса (registration): " . $stmt->error . "\n", FILE_APPEND);
        http_response_code(500);
        exit;
    }
    $registration = $stmt->get_result()->fetch_assoc();
    if (!$registration) {
        file_put_contents('../debug.log', "Регистрация волонтёра $volunteer_id на мероприятие $event_id не найдена\n", FILE_APPEND);
        http_response_code(404);
        exit;
    }
    $hours = $registration['hours'];
    file_put_contents('../debug.log', "Hours: $hours\n", FILE_APPEND);

    // Возвращаем данные в формате JSON
    $data = [
        'volunteer_name' => $volunteer_name,
        'event_title' => $event_title,
        'organizer_name' => $organizer_name,
        'event_date' => $event_date,
        'hours' => $hours,
        'issue_date' => $issue_date
    ];
    echo json_encode($data);
}

$conn->close();
?>