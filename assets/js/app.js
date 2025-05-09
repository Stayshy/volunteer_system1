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

    function clearNotifications() {
        loginError.classList.add('d-none');
        loginSuccess.classList.add('d-none');
        registerError.classList.add('d-none');
        registerSuccess.classList.add('d-none');
    }

    roleSelect.addEventListener('change', function() {
        if (this.value === 'volunteer') {
            phoneGroup.classList.remove('d-none');
            organizationGroup.classList.add('d-none');
        } else {
            phoneGroup.classList.add('d-none');
            organizationGroup.classList.remove('d-none');
        }
    });

    registerBtn.addEventListener('click', function(e) {
        e.preventDefault();
        clearNotifications();

        const name = document.getElementById('register-name').value.trim();
        const email = document.getElementById('register-email').value.trim();
        const password = document.getElementById('register-password').value.trim();
        const role = document.getElementById('register-role').value;
        const phone = document.getElementById('register-phone').value.trim();
        const organization = document.getElementById('register-organization').value.trim();

        if (!name || !email || !password) {
            registerError.textContent = 'Заполните все обязательные поля';
            registerError.classList.remove('d-none');
            return;
        }

        const data = { name, email, password, role };
        if (role === 'volunteer' && phone) data.phone = phone;
        if (role === 'organizer' && organization) data.organization = organization;

        console.log('Регистрация:', JSON.stringify(data));

        fetch('/volunteer_system/api/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        })
            .then(response => {
                console.log('Ответ (регистрация):', {
                    status: response.status,
                    statusText: response.statusText,
                    url: response.url,
                    headers: [...response.headers.entries()]
                });
                return response.text().then(text => ({ ok: response.ok, status: response.status, text }));
            })
            .then(({ ok, status, text }) => {
                console.log('Тело ответа (регистрация):', text);
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error(`Ошибка парсинга JSON: ${e.message}, Текст: ${text}`);
                }
                if (!ok) {
                    throw new Error(`HTTP ${status}: ${data.error || 'Неизвестная ошибка'}`);
                }
                if (data.error) {
                    registerError.textContent = data.error;
                    registerError.classList.remove('d-none');
                } else {
                    registerSuccess.textContent = 'Регистрация успешна! Теперь вы можете войти.';
                    registerSuccess.classList.remove('d-none');
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

        const data = { email, password };
        console.log('Вход:', JSON.stringify(data));

        fetch('/volunteer_system/api/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        })
            .then(response => {
                console.log('Ответ (вход):', {
                    status: response.status,
                    statusText: response.statusText,
                    url: response.url,
                    headers: [...response.headers.entries()],
                    requestData: JSON.stringify(data)
                });
                return response.text().then(text => ({ ok: response.ok, status: response.status, text }));
            })
            .then(({ ok, status, text }) => {
                console.log('Тело ответа (вход):', text);
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error(`Ошибка парсинга JSON: ${e.message}, Текст: ${text}`);
                }
                if (!ok) {
                    throw new Error(`HTTP ${status}: ${data.error || 'Неизвестная ошибка'}`);
                }
                if (data.error) {
                    loginError.textContent = data.error;
                    loginError.classList.remove('d-none');
                } else {
                    localStorage.setItem('user', JSON.stringify({
                        id: data.id,
                        name: data.name,
                        email: data.email,
                        phone: data.phone,
                        avatar: data.avatar,
                        role: data.role
                    }));
                    loginSuccess.textContent = `Добро пожаловать, ${data.name}!`;
                    loginSuccess.classList.remove('d-none');
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