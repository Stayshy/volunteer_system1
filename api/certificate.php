<?php
// Подключаем TCPDF
file_put_contents('../debug.log', "Шаг 0: Попытка подключения TCPDF\n", FILE_APPEND);
if (!file_exists('tcpdf/tcpdf.php')) {
    file_put_contents('../debug.log', "Ошибка: Файл tcpdf/tcpdf.php не найден\n", FILE_APPEND);
    http_response_code(500);
    echo "Ошибка: Файл tcpdf/tcpdf.php не найден";
    exit;
}
require_once('tcpdf/tcpdf.php');
file_put_contents('../debug.log', "Шаг 0.5: TCPDF успешно подключён\n", FILE_APPEND);

header('Content-Type: application/pdf');
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
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $volunteer_id = $_GET['volunteer_id'] ?? '';
    $event_id = $_GET['event_id'] ?? '';

    file_put_contents('../debug.log', "Certificate request: volunteer_id=$volunteer_id, event_id=$event_id\n", FILE_APPEND);

    if (empty($volunteer_id) || empty($event_id)) {
        file_put_contents('../debug.log', "Ошибка: Укажите ID волонтёра и мероприятия\n", FILE_APPEND);
        http_response_code(400);
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

    // Создаём PDF с помощью TCPDF
    file_put_contents('../debug.log', "Шаг 4: Генерация PDF с помощью TCPDF\n", FILE_APPEND);
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    file_put_contents('../debug.log', "Шаг 4.1: Объект TCPDF создан\n", FILE_APPEND);

    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Volunteer System');
    $pdf->SetTitle('Certificate');
    $pdf->SetSubject('Certificate of Participation');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->setFontSubsetting(true);
    file_put_contents('../debug.log', "Шаг 4.2: Настройки PDF установлены\n", FILE_APPEND);

    // Устанавливаем шрифт (helvetica)
    $pdf->SetFont('helvetica', '', 14);
    file_put_contents('../debug.log', "Шаг 4.3: Шрифт установлен\n", FILE_APPEND);

    // Добавляем страницу
    $pdf->AddPage();
    file_put_contents('../debug.log', "Шаг 4.4: Страница добавлена\n", FILE_APPEND);

    // Добавляем текст (грамота)
    $pdf->SetFont('helvetica', 'B', 36);
    $pdf->Cell(0, 20, 'Certificate', 0, 1, 'C');
    $pdf->Ln(10);
    file_put_contents('../debug.log', "Шаг 4.5: Заголовок добавлен\n", FILE_APPEND);

    $pdf->SetFont('helvetica', '', 20);
    $pdf->Cell(0, 10, 'is awarded to', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 30);
    $pdf->Cell(0, 15, $volunteer_name, 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 20);
    $pdf->Cell(0, 10, 'for participation in the event', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 30);
    $pdf->Cell(0, 15, $event_title, 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 20);
    $pdf->Cell(0, 10, "organized by $organizer_name", 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->Cell(0, 10, "held on $event_date", 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->Cell(0, 10, "Hours: $hours", 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('helvetica', 'I', 20);
    $pdf->Cell(0, 10, 'Thank you for your contribution!', 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, "Issued on: $issue_date", 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Volunteer System', 0, 1, 'C');
    file_put_contents('../debug.log', "Шаг 4.6: Текст добавлен\n", FILE_APPEND);

    file_put_contents('../debug.log', "Шаг 5: PDF сгенерирован, отправка\n", FILE_APPEND);

    // Вывод PDF
    $pdf->Output('certificate.pdf', 'I');

    file_put_contents('../debug.log', "Шаг 6: PDF отправлен\n", FILE_APPEND);
}

$conn->close();
?>