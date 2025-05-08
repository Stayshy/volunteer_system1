document.addEventListener('DOMContentLoaded', function() {
    const { jsPDF } = window.jspdf;

    // Load profile
    fetch('/volunteer_system/api/profile')
        .then(response => response.json())
        .then(data => {
            document.getElementById('name').textContent = data.name;
            document.getElementById('email').textContent = data.email;
            document.getElementById('phone').textContent = data.phone || 'Не указан';
            document.getElementById('avatar').src = data.avatar || 'assets/images/default.jpg';
            document.getElementById('editName').value = data.name;
            document.getElementById('editEmail').value = data.email;
            document.getElementById('editPhone').value = data.phone || '';
            document.getElementById('editAvatar').value = data.avatar || '';
        });

    // Edit profile
    document.getElementById('editProfileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            name: document.getElementById('editName').value,
            email: document.getElementById('editEmail').value,
            phone: document.getElementById('editPhone').value,
            avatar: document.getElementById('editAvatar').value
        };
        fetch('/volunteer_system/api/profile', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(response => response.json()).then(data => {
            if (data.success) {
                location.reload();
            }
        });
    });

    // Load calendar
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        events: '/volunteer_system/api/events',
        eventClick: function(info) {
            if (confirm('Зарегистрироваться на "' + info.event.title + '"?')) {
                fetch('/volunteer_system/api/registrations', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_id: info.event.id })
                }).then(response => response.json()).then(data => {
                    alert(data.success ? 'Регистрация успешна!' : data.error);
                });
            }
        }
    });
    calendar.render();

    // Load volunteer book
    fetch('/volunteer_system/api/volunteer_book')
        .then(response => response.json())
        .then(data => {
            const book = document.getElementById('volunteerBook');
            data.forEach(entry => {
                book.innerHTML += `<div class="page"><h3>${entry.title}</h3><p>Дата: ${entry.event_date}</p><p>Часы: ${entry.hours}</p><p>Организатор: ${entry.organizer}</p></div>`;
            });
        });

    // Download volunteer book as PDF
    document.getElementById('downloadBook').addEventListener('click', function() {
        const doc = new jsPDF();
        doc.text('Волонтерская книжка', 10, 10);
        fetch('/volunteer_system/api/volunteer_book')
            .then(response => response.json())
            .then(data => {
                let y = 20;
                data.forEach(entry => {
                    doc.text(`${entry.title} | ${entry.event_date} | ${entry.hours} часов | ${entry.organizer}`, 10, y);
                    y += 10;
                });
                doc.save('volunteer_book.pdf');
            });
    });

    // Load achievements
    fetch('/volunteer_system/api/achievements')
        .then(response => response.json())
        .then(data => {
            const achievements = document.getElementById('achievements');
            data.forEach(ach => {
                achievements.innerHTML += `<div class="achievement"><h4>${ach.title}</h4><p>${ach.description}</p><small>${ach.awarded_at}</small></div>`;
            });
        });

    // Load news
    fetch('/volunteer_system/api/news')
        .then(response => response.json())
        .then(data => {
            const news = document.getElementById('news');
            data.forEach(item => {
                news.innerHTML += `<div class="news-item"><h4>${item.title}</h4><p>${item.content}</p><small>${item.created_at}</small></div>`;
            });
        });
});