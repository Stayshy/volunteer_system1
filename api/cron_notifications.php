<?php
$conn = new mysqli('localhost', 'root', '', 'volunteer_system');
if ($conn->connect_error) {
    file_put_contents('cron_errors.log', "Ошибка подключения к базе данных: " . $conn->connect_error . "\n", FILE_APPEND);
    exit;
}

// Получаем мероприятия, которые начинаются завтра
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$stmt = $conn->prepare('
    SELECT e.id, e.title, e.event_date, e.organizer_id
    FROM events e
    WHERE DATE(e.event_date) = ?
    AND e.status = "active"
');
$stmt->bind_param('s', $tomorrow);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($events as $event) {
    $event_id = $event['id'];
    $message = "Напоминание: мероприятие \"{$event['title']}\" состоится {$event['event_date']}.";

    // Уведомление для организатора
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, user_role, event_id, message) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isis', $event['organizer_id'], 'organizer', $event_id, $message);
    $stmt->execute();

    // Уведомления для волонтёров
    $stmt = $conn->prepare('
        SELECT v.id, v.email
        FROM registrations r
        JOIN volunteers v ON r.volunteer_id = v.id
        WHERE r.event_id = ?
    ');
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $volunteers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($volunteers as $volunteer) {
        $stmt = $conn->prepare('INSERT INTO notifications (user_id, user_role, event_id, message) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('isis', $volunteer['id'], 'volunteer', $event_id, $message);
        $stmt->execute();

        file_put_contents('emails.log', "To: {$volunteer['email']}\nSubject: Напоминание о мероприятии\n$message\n\n", FILE_APPEND);
    }
}

$conn->close();
?>