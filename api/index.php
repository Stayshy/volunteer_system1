<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Подключение к базе данных
$conn = new mysqli('localhost', 'root', '', 'volunteer_system');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к базе данных: ' . $conn->connect_error]);
    exit;
}

// Функция для имитации отправки email
function sendEmail($to, $subject, $message) {
    file_put_contents('../emails.log', "To: $to\nSubject: $subject\n$message\n\n", FILE_APPEND);
}

// Парсинг запроса
$method = $_SERVER['REQUEST_METHOD'];
$path = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$resource = $path[0] ?? '';
$id = $path[1] ?? null;

switch ($resource) {
    case 'register':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                http_response_code(400);
                echo json_encode(['error' => 'Неверный формат данных']);
                exit;
            }

            $name = $data['name'] ?? '';
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            $role = $data['role'] ?? '';
            $phone = $data['phone'] ?? null;
            $organization = $data['organization'] ?? null;

            // Валидация
            if (empty($name) || empty($email) || empty($password) || !in_array($role, ['volunteer', 'BETorganizer'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Заполните все обязательные поля и выберите корректную роль']);
                exit;
            }

            // Проверка уникальности email
            $stmt = $conn->prepare('SELECT id FROM volunteers WHERE email = ? UNION SELECT id FROM organizers WHERE email = ?');
            $stmt->bind_param('ss', $email, $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'Пользователь с таким email уже существует']);
                exit;
            }

            // Регистрация
            try {
                if ($role === 'volunteer') {
                    $stmt = $conn->prepare('INSERT INTO volunteers (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('sssss', $name, $email, $phone, $password, $role);
                } else {
                    $stmt = $conn->prepare('INSERT INTO organizers (name, email, organization, password, role) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bind_param('sssss', $name, $email, $organization, $password, $role);
                }
                $stmt->execute();
                echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Регистрация успешна']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Ошибка при регистрации: ' . $e->getMessage()]);
            }
        }
        break;

    case 'login':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';

            // Проверка волонтеров
            $stmt = $conn->prepare('SELECT id, name, role FROM volunteers WHERE email = ? AND password = ?');
            $stmt->bind_param('ss', $email, $password);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                echo json_encode(['id' => $row['id'], 'name' => $row['name'], 'role' => $row['role']]);
                exit;
            }

            // Проверка организаторов
            $stmt = $conn->prepare('SELECT id, name, role FROM organizers WHERE email = ? AND password = ?');
            $stmt->bind_param('ss', $email, $password);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                echo json_encode(['id' => $row['id'], 'name' => $row['name'], 'role' => $row['role']]);
                exit;
            }

            http_response_code(401);
            echo json_encode(['error' => 'Неверный email или пароль. Зарегистрируйтесь, если у вас нет аккаунта']);
        }
        break;

    case 'volunteers':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare('INSERT INTO volunteers (name, email, phone, password, role) VALUES (?, ?, ?, ?, "volunteer")');
            $stmt->bind_param('ssss', $data['name'], $data['email'], $data['phone'], $data['password']);
            $stmt->execute();
            echo json_encode(['id' => $conn->insert_id]);
        } elseif ($method === 'GET' && $id) {
            $stmt = $conn->prepare('SELECT id, name, email, phone, role FROM volunteers WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_assoc());
        } elseif ($method === 'PUT' && $id) {
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare('UPDATE volunteers SET name = ?, email = ?, phone = ? WHERE id = ?');
            $stmt->bind_param('sssi', $data['name'], $data['email'], $data['phone'], $id);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } elseif ($method === 'DELETE' && $id) {
            $stmt = $conn->prepare('DELETE FROM volunteers WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            echo json_encode(['success' => true]);
        }
        break;

    case 'events':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare('INSERT INTO events (title, description, event_date, max_participants, organizer_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssii', $data['title'], $data['description'], $data['event_date'], $data['max_participants'], $data['organizer_id']);
            $stmt->execute();
            echo json_encode(['id' => $conn->insert_id]);
        } elseif ($method === 'GET' && !$id) {
            $result = $conn->query('SELECT * FROM events');
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        } elseif ($method === 'GET' && $id) {
            $stmt = $conn->prepare('SELECT * FROM events WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_assoc());
        } elseif ($method === 'PUT' && $id) {
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare('UPDATE events SET title = ?, description = ?, event_date = ?, max_participants = ?, organizer_id = ? WHERE id = ?');
            $stmt->bind_param('sssiii', $data['title'], $data['description'], $data['event_date'], $data['max_participants'], $data['organizer_id'], $id);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } elseif ($method === 'DELETE' && $id) {
            $stmt = $conn->prepare('DELETE FROM events WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            echo json_encode(['success' => true]);
        }
        break;

    case 'registrations':
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare('SELECT COUNT(*) as count, max_participants FROM registrations r JOIN events e ON r.event_id = e.id WHERE r.event_id = ?');
            $stmt->bind_param('i', $data['event_id']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result['count'] >= $result['max_participants']) {
                http_response_code(400);
                echo json_encode(['error' => 'Мероприятие заполнено']);
                exit;
            }
            $stmt = $conn->prepare('INSERT INTO registrations (volunteer_id, event_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $data['volunteer_id'], $data['event_id']);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } elseif ($method === 'DELETE' && $id) {
            $event_id = $path[2] ?? null;
            $stmt = $conn->prepare('DELETE FROM registrations WHERE volunteer_id = ? AND event_id = ?');
            $stmt->bind_param('ii', $id, $event_id);
            $stmt->execute();
            echo json_encode(['success' => true]);
        }
        break;

    case 'notifications':
        if ($method === 'POST') {
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            $stmt = $conn->prepare('SELECT v.email, e.title FROM registrations r JOIN volunteers v ON r.volunteer_id = v.id JOIN events e ON r.event_id = e.id WHERE DATE(e.event_date) = ?');
            $stmt->bind_param('s', $tomorrow);
            $stmt->execute();
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($results as $row) {
                sendEmail($row['email'], 'Напоминание о мероприятии', "Завтра состоится мероприятие: {$row['title']}");
            }
            echo json_encode(['success' => true]);
        }
        break;

    case 'statistics':
        if ($method === 'GET' && $id) {
            $stmt = $conn->prepare('SELECT COUNT(r.id) as events_count, AVG(rep.rating) as avg_rating FROM registrations r LEFT JOIN reports rep ON r.volunteer_id = rep.volunteer_id AND r.event_id = rep.event_id WHERE r.volunteer_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_assoc());
        }
        break;

    case 'ratings':
        if ($method === 'GET') {
            $result = $conn->query('SELECT v.name, SUM(r.tasks_completed) as total_tasks, AVG(r.rating) as avg_rating FROM reports r JOIN volunteers v ON r.volunteer_id = v.id GROUP BY v.id ORDER BY total_tasks DESC');
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Ресурс не найден']);
        break;
}

$conn->close();
?>