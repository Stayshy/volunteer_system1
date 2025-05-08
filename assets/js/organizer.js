document.addEventListener('DOMContentLoaded', function() {
    const { jsPDF } = window.jspdf;

    // Load profile
    fetch('/volunteer_system/api/profile')
        .then(response => response.json())
        .then(data => {
            document.getElementById('name').textContent = data.name;
            document.getElementById('email').textContent = data.email;
            document.getElementById('organization').textContent = data.organization || 'Не указана';
            document.getElementById('editName').value = data.name;
            document.getElementById('editEmail').value = data.email;
            document.getElementById('editOrg').value = data.organization || '';
        });

    // Edit profile
    document.getElementById('editProfileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            name: document.getElementById('editName').value,
            email: document.getElementById('editEmail').value,
            organization: document.getElementById('editOrg').value
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

    // Load events
    fetch('/volunteer_system/api/events')
        .then(response => response.json())
        .then(data => {
            const events = document.getElementById('events');
            data.forEach(event => {
                events.innerHTML += `
                    <div class="event">
                        <h4>${event.title}</h4>
                        <p>${event.description || 'Нет описания'}</p>
                        <p>Дата: ${event.event_date}</p>
                        <p>Статус: ${event.status}</p>
                        <button class="btn btn-primary notify-btn" data-id="${event.id}">Уведомить участников</button>
                        <button class="btn btn-warning edit-btn" data-id="${event.id}" data-bs-toggle="modal" data-bs-target="#createEventModal">Редактировать</button>
                        <button class="btn btn-danger delete-btn" data-id="${event.id}">Удалить</button>
                    </div>`;
            });
            document.querySelectorAll('.notify-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    fetch('/volunteer_system/api/notifications', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ event_id: this.dataset.id })
                    }).then(response => response.json()).then(data => {
                        alert(data.success ? 'Уведомления отправлены!' : 'Ошибка');
                    });
                });
            });
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (confirm('Удалить мероприятие?')) {
                        fetch(`/volunteer_system/api/events/${this.dataset.id}`, {
                            method: 'DELETE'
                        }).then(response => response.json()).then(data => {
                            if (data.success) {
                                location.reload();
                            }
                        });
                    }
                });
            });
        });

    // Create event
    document.getElementById('createEventForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            title: document.getElementById('eventTitle').value,
            description: document.getElementById('eventDesc').value,
            event_date: document.getElementById('eventDate').value,
            max_participants: document.getElementById('eventMax').value,
            hours: document.getElementById('eventHours').value,
            status: document.getElementById('eventStatus').value
        };
        fetch('/volunteer_system/api/events', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(response => response.json()).then(data => {
            if (data.id) {
                location.reload();
            }
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

    // Load reports
    fetch('/volunteer_system/api/reports')
        .then(response => response.json())
        .then(data => {
            const reports = document.getElementById('reports');
            data.forEach(report => {
                reports.innerHTML += `
                    <div class="report">
                        <h4>${report.title}</h4>
                        <p>Участников: ${report.participants}</p>
                        <p>Часов: ${report.total_hours || 0}</p>
                        <p>Средний рейтинг: ${report.avg_rating ? report.avg_rating.toFixed(1) : 'Нет'}</p>
                    </div>`;
            });
        });

    // Download report as PDF
    document.getElementById('downloadReport').addEventListener('click', function() {
        const doc = new jsPDF();
        doc.text('Отчет по мероприятиям', 10, 10);
        fetch('/volunteer_system/api/reports')
            .then(response => response.json())
            .then(data => {
                let y = 20;
                data.forEach(report => {
                    doc.text(`${report.title} | Участников: ${report.participants} | Часов: ${report.total_hours || 0}`, 10, y);
                    y += 10;
                });
                doc.save('report.pdf');
            });
    });
});