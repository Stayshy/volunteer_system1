document.addEventListener('DOMContentLoaded', function() {
    const loginBtn = document.getElementById('login-btn');
    const registerBtn = document.getElementById('register-btn');
    const loginError = document.getElementById('login-error');
    const loginSuccess = document.getElementById('login-success');
    const registerError = document.getElementById('register-error');
    const registerSuccess = document.getElementById('register-success');
    const roleSelect = document.getElementById('register-role');
    const phoneGroup = document.getElementById('register-phone-group');
    const organizationGroup = document.getElementById('register-organization-group');

    // Очистка уведомлений
    function clearNotifications() {
        loginError.classList.add('d-none');
        loginSuccess.classList.add('d-none');
        registerError.classList.add('d-none');
        registerSuccess.classList.add('d-none');
    }

    // Переключение полей в зависимости от роли
    roleSelect.addEventListener('change', function() {
        if (this.value === 'volunteer') {
            phoneGroup.classList.remove('d-none');
            organizationGroup.classList.add('d-none');
        } else {
            phoneGroup.classList.add('d-none');
            organizationGroup.classList.remove('d-none');
        }
    });

    // Регистрация
    registerBtn.addEventListener('click', function(e) {
        e.preventDefault();
        clearNotifications();

        const name = document.getElementById('register-name').value.trim();
        const email = document.getElementById('register-email').value.trim();
        const password = document.getElementById('register-password').value.trim();
        const role = document.getElementById('register-role').value;
        const phone = document.getElementById('register-phone').value.trim();
        const organization = document.getElementById('register-organization').value.trim();

        // Валидация на клиенте
        if (!name || !email || !password) {
            registerError.textContent = 'Заполните все обязательные поля';
            registerError.classList.remove('d-none');
            return;
        }

        const data = { name, email, password, role };
        if (role === 'volunteer' && phone) data.phone = phone;
        if (role === 'organizer' && organization) data.organization = organization;

        fetch('/volunteer_system/api/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ошибка: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    registerError.textContent = data.error;
                    registerError.classList.remove('d-none');
                } else {
                    registerSuccess.textContent = 'Регистрация успешна! Теперь вы можете войти.';
                    registerSuccess.classList.remove('d-none');
                    // Очистка формы
                    document.getElementById('register-name').value = '';
                    document.getElementById('register-email').value = '';
                    document.getElementById('register-password').value = '';
                    document.getElementById('register-phone').value = '';
                    document.getElementById('register-organization').value = '';
                }
            })
            .catch(error => {
                registerError.textContent = 'Ошибка при регистрации: ' + error.message;
                registerError.classList.remove('d-none');
                console.error('Ошибка:', error);
            });
    });

    // Вход
    loginBtn.addEventListener('click', function(e) {
        e.preventDefault();
        clearNotifications();

        const email = document.getElementById('login-email').value.trim();
        const password = document.getElementById('login-password').value.trim();

        if (!email || !password) {
            loginError.textContent = 'Заполните email и пароль';
            loginError.classList.remove('d-none');
            return;
        }

        fetch('/volunteer_system/api/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ошибка: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    loginError.textContent = data.error;
                    loginError.classList.remove('d-none');
                } else {
                    loginSuccess.textContent = `Добро пожаловать, ${data.name}!`;
                    loginSuccess.classList.remove('d-none');
                    // Перенаправление
                    setTimeout(() => {
                        if (data.role === 'volunteer') {
                            window.location.href = '/volunteer_system/volunteer.html';
                        } else {
                            window.location.href = '/volunteer_system/organizer.html';
                        }
                    }, 1000);
                }
            })
            .catch(error => {
                loginError.textContent = 'Ошибка при входе: ' + error.message;
                loginError.classList.remove('d-none');
                console.error('Ошибка:', error);
            });
    });
});