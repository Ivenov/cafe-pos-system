<?php
session_start();

// Перевірка, чи авторизований користувач
if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: dashboard/dashboard.php');
        exit();
    } elseif ($_SESSION['user']['role'] === 'seller') {
        header('Location: core/pos.php');
        exit();
    }
}

// Підключення до бази даних
require_once 'core/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $storedPassword = $user['password'];
            $isPasswordVerified = false;

            // Перевірка старого формату MD5
            if ($storedPassword === md5($password)) {
                $isPasswordVerified = true;

                // Оновлення пароля до bcrypt
                $newPasswordHash = password_hash($password, PASSWORD_BCRYPT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                $updateStmt->execute(['password' => $newPasswordHash, 'id' => $user['id']]);
            }

            // Перевірка формату bcrypt
            if (password_verify($password, $storedPassword)) {
                $isPasswordVerified = true;
            }

            if ($isPasswordVerified) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ];

                // Для продавця перевіряємо або створюємо активну зміну
                if ($user['role'] === 'seller') {
                    try {
                        $shiftCheckStmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = :user_id AND closed_at IS NULL");
                        $shiftCheckStmt->execute(['user_id' => $user['id']]);
                        $activeShift = $shiftCheckStmt->fetch(PDO::FETCH_ASSOC);

                        if (!$activeShift) {
                            $newShiftStmt = $pdo->prepare("
                                INSERT INTO shifts (user_id, opened_at) 
                                VALUES (:user_id, NOW())
                            ");
                            $newShiftStmt->execute(['user_id' => $user['id']]);
                            $_SESSION['shift_id'] = $pdo->lastInsertId();
                        } else {
                            $_SESSION['shift_id'] = $activeShift['id'];
                        }
                    } catch (Exception $e) {
                        error_log("[LOGIN ERROR] Помилка при роботі зі змінами: " . $e->getMessage(), 3, __DIR__ . '/core/logs/error_log.txt');
                        $error = 'Помилка при створенні або перевірці зміни. Зверніться до адміністратора.';
                    }
                }

                // Перенаправлення в залежності від ролі
                header('Location: ' . ($_SESSION['user']['role'] === 'admin' ? 'dashboard/dashboard.php' : 'core/pos.php'));
                exit();
            } else {
                $error = 'Невірне ім\'я користувача або пароль';
            }
        } else {
            $error = 'Невірне ім\'я користувача або пароль';
        }
    } catch (Exception $e) {
        error_log("[LOGIN ERROR] " . $e->getMessage(), 3, __DIR__ . '/core/logs/error_log.txt');
        $error = 'Помилка авторизації. Зверніться до адміністратора.';
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вхід в систему</title>
    <link rel="stylesheet" href="acsees/indexstyle.css">
    <style>
    /* Загальний стиль для сторінки */
    body {
        margin: 0;
        padding: 0;
        font-family: 'Courier New', Courier, monospace;
        height: 100vh;
        display: flex;
        justify-content: center; /* Центруємо вміст по горизонталі */
        align-items: center; /* Центруємо вміст по вертикалі */
        background: black; /* Фон матриці */
        color:#083655;
        overflow: hidden;
    }

    /* Завантаження */
    #loading-screen {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        background-color: rgba(0, 0, 0, 0.9);
        z-index: 10;
    }

    /* Прогрес-бар */
    .loading-bar {
        width: 80%;
        max-width: 400px;
        height: 10px;
        background-color: #333;
        border-radius: 5px;
        overflow: hidden;
        margin-top: 20px;
        position: relative;
    }

    .progress {
        display: block;
        height: 100%;
        width: 0;
        background-color: #083655;
        border-radius: 5px;
        transition: width 0.5s linear;
    }

    /* Текст завантаження */
    #loading-screen p {
        margin: 20px 0 0;
        font-size: 18px;
        text-align: center;
        color: #083655;
    }

    /* Центрування форми */
    main {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
        height: 100%;
        position: relative;
        z-index: 1;
    }

    /* Стилі форми */
    form.login-form {
        background: rgba(0, 0, 0, 0.8); /* Прозорий фон для форми */
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 0 15px #083655;
        width: 400px;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        z-index: 1; /* Поверх фону */
    }

    /* Заголовки форми */
    form h1, form h2 {
        text-align: center;
        font-size: 2rem;
        color: #083655;
    }

    /* Поля вводу */
    form input {
        padding: 0.7rem;
        font-size: 1rem;
        border: 1px solid #083655;
        border-radius: 5px;
        background: black;
        color: #083655;
        box-sizing: border-box;
        width: 100%;
    }

    /* Кнопка відправки */
    form button {
        padding: 0.7rem;
        font-size: 1rem;
        background-color: #083655;
        color: black;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    form button:hover {
        background-color: #083655;
    }

    /* Медіа-запит для мобільних пристроїв */
    @media (max-width: 768px) {
        form.login-form {
            width: 90%;
            padding: 1.5rem;
        }

        .loading-bar {
            width: 90%;
        }
    }
</style>

</head>
<body>
<div id="loading-screen">
    <div class="loading-bar">
        <span class="progress"></span>
    </div>
    <p>Починаємо</p>
</div>

<canvas id="matrix"></canvas>
        <form method="POST" class="login-form">
            <h2>Авторизація</h2>
            <?php if (!empty($error)) : ?>
                <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <label for="username">Ім'я користувача</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Пароль</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Увійти</button>
        </form>
    </main>
    
    <script>
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('matrix');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const fontSize = 16;
        const columns = Math.floor(canvas.width / fontSize);
        const drops = Array(columns).fill(1);

        function drawMatrix() {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.fillStyle = '#00ff00';
            ctx.font = `${fontSize}px Courier`;

            drops.forEach((y, i) => {
                const text = String.fromCharCode(0x30A0 + Math.random() * 96);
                const x = i * fontSize;
                ctx.fillText(text, x, y * fontSize);

                if (y * fontSize > canvas.height && Math.random() > 0.975) {
                    drops[i] = 0;
                }
                drops[i]++;
            });
        }

        setInterval(drawMatrix, 50);

        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            drops.length = Math.floor(canvas.width / fontSize);
            drops.fill(1);
        });
    }
});

document.addEventListener("DOMContentLoaded", function () {
    const loadingScreen = document.getElementById("loading-screen");
    const progressBar = document.querySelector(".progress");
    const messages = ["Підготовка", "Очищення кешу", "Завантаження", "Збереження"];
    let currentMessageIndex = 0;

    // Змінюємо повідомлення кожні 1.5 секунди
    const interval = setInterval(() => {
        loadingScreen.querySelector("p").textContent = messages[currentMessageIndex];
        currentMessageIndex++;

        // Прогрес-бар
        progressBar.style.width = `${(currentMessageIndex / messages.length) * 100}%`;

        if (currentMessageIndex >= messages.length) {
            clearInterval(interval); // Зупиняємо зміну повідомлень
        }
    }, 1500);

    // Прибираємо завантажувальний екран через 6 секунд
    setTimeout(() => {
        loadingScreen.style.display = "none"; // Прибираємо екран
    }, 7000); // Затримка 6 секунд
});
</script>

</body>
</html>
