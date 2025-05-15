const http = require('http');
const Static = require('node-static');
const httpProxy = require('http-proxy');

// Создаём прокси-сервер для перенаправления API-запросов
const proxy = httpProxy.createProxyServer({});

// Настраиваем сервер для статических файлов
const fileServer = new Static.Server('./', {
    cache: 3600,
    headers: {
        'Content-Type': 'text/html'
    }
});

// Создаём HTTP-сервер
const server = http.createServer((req, res) => {
    console.log(`Request: ${req.url}`);

    // Перенаправляем API-запросы к Denwer
    if (req.url.startsWith('/volunteer_system/api')) {
        proxy.web(req, res, {
            target: 'http://localhost', // Denwer на localhost
            changeOrigin: true
        });
    } else {
        // Упрощаем маршруты
        let filePath = req.url;
        if (filePath === '/' || filePath === '/volunteer_system/') {
            filePath = '/volunteer_system/index.html';
        } else if (filePath === '/index.html') {
            filePath = '/volunteer_system/index.html';
        } else if (!filePath.startsWith('/volunteer_system/')) {
            filePath = `/volunteer_system${filePath}`;
        }

        // Устанавливаем новый URL для обработки
        req.url = filePath;

        // Отдаём статические файлы
        fileServer.serve(req, res, (err) => {
            if (err) {
                res.writeHead(404, { 'Content-Type': 'text/html' });
                res.end('<h1>404: Страница не найдена</h1>');
            }
        });
    }
});

// Обработка ошибок прокси
proxy.on('error', (err, req, res) => {
    res.writeHead(500, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ error: 'Proxy error', details: err.message }));
});

// Запускаем сервер на порту 3000
server.listen(3000, 'localhost', () => {
    console.log('Сервер запущен на http://localhost:3000/');
});