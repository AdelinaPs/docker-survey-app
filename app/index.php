<?php
$host     = getenv('DB_HOST');
$dbname   = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');

// Жесткая проверка: если пароли не переданы в контейнер, сайт не запустится
if (!$host || !$dbname || !$username || !$password) {
    die("<div style='padding:40px; font-family:sans-serif; text-align:center;'>
            <h2 style='color:#dc2626;'>🔒 Ошибка безопасности</h2>
            <p>Критические данные подключения (DB_PASS) не найдены в окружении.</p>
            <p>Проверьте файлы .env и docker-compose.yaml</p>
         </div>");
}

$error_msg = "";

try {
    // Подключение к PostgreSQL
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // АВТОМАТИЧЕСКОЕ СОЗДАНИЕ ТАБЛИЦЫ (DevOps-подход: инфраструктура как код)
    $pdo->exec("CREATE TABLE IF NOT EXISTS results (
        id SERIAL PRIMARY KEY,
        username TEXT NOT NULL,
        q1 TEXT, q2 TEXT, q3 TEXT, q4 TEXT, q5 TEXT, q6 TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // ОБРАБОТКА ФОРМЫ
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
        $user_input = trim($_POST['username']);
        
        // Валидация ФИО: Минимум два слова (Фамилия Имя)
        if (!preg_match("/^[a-zA-Zа-яА-ЯёЁ\-]+\s+[a-zA-Zа-яА-ЯёЁ\-\s]+$/u", $user_input)) {
            $error_msg = "⚠️ Введите полное ФИО (минимум 2 слова) без цифр.";
        } else {
            $sql = "INSERT INTO results (username, q1, q2, q3, q4, q5, q6) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([
                $user_input, 
                $_POST['q1'] ?? '', 
                $_POST['q2'] ?? '', 
                $_POST['q3'] ?? '', 
                $_POST['q4'] ?? '', 
                $_POST['q5'] ?? '', 
                $_POST['q6'] ?? ''
            ]);
            // Редирект, чтобы форма не отправилась повторно при обновлении страницы
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // ПОЛУЧЕНИЕ ИСТОРИИ (только если подключение успешно)
    $history = $pdo->query("SELECT * FROM results ORDER BY id DESC LIMIT 50")->fetchAll();

} catch (PDOException $e) {
    // Безопасный вывод ошибки: не показываем детали подключения пользователю
    error_log($e->getMessage()); // Логируем реальную ошибку в логи Docker
    die("<div style='padding:40px; font-family:sans-serif; text-align:center;'>
            <h2 style='color:#dc2626;'>🔌 Ошибка базы данных</h2>
            <p>Приложение не может связаться с контейнером базы данных.</p>
         </div>");
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Модернизация Етис | Docker Version</title>
    <style>
        :root {
            --primary: #0062ff;
            --bg: #f3f4f6;
            --card: #ffffff;
            --text: #1f2937;
            --border: #e5e7eb;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 40px 15px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background: var(--card);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }

        h1 { font-size: 26px; color: var(--primary); text-align: center; margin-top: 0; }
        p.desc { text-align: center; color: #6b7280; margin-bottom: 30px; }

        .form-group { margin-bottom: 24px; width: 100%; }
        
        label { display: block; font-weight: 700; margin-bottom: 10px; font-size: 15px; }

        input[type="text"], select, textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(0, 98, 255, 0.1);
        }

        .error-alert {
            background: #fef2f2;
            color: #991b1b;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #fee2e2;
            font-weight: 500;
        }

        .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
        
        .radio-label {
            background: #f9fafb;
            border: 1px solid var(--border);
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 18px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: 0.2s;
        }

        button:hover { background: #0052d4; transform: translateY(-1px); }

        .history-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 5px solid var(--primary);
        }
        
        .history-name { font-weight: 800; font-size: 17px; margin-bottom: 10px; display: block; }
        
        .tag {
            background: #eff6ff;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-right: 5px;
            display: inline-block;
            margin-top: 5px;
        }

        .wish { margin-top: 15px; font-size: 14px; color: #4b5563; border-top: 1px solid #f3f4f6; padding-top: 10px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h1>💎 Улучшение Етис (Docker)</h1>
        <p class="desc">Безопасная версия системы сбора пожеланий</p>

        <?php if ($error_msg): ?>
            <div class="error-alert"><?= $error_msg ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>👤 Ваше ФИО</label>
                <input type="text" name="username" placeholder="Иванов Иван Иванович" required 
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
            </div>

            <div class="form-group">
                <label>1. Как часто вы не можете зайти в Етис?</label>
                <select name="q1" required>
                    <option value="">Выберите ответ...</option>
                    <option value="Часто (ежедневно)">Часто (ежедневно)</option>
                    <option value="Редко (раз в неделю)">Редко (раз в неделю)</option>
                    <option value="Почти нет проблемы">Почти нет проблемы</option>
                </select>
            </div>

            <div class="form-group">
                <label>2. Оцените скорость работы Етиса:</label>
                <div class="options-grid">
                    <label class="radio-label"><input type="radio" name="q2" value="Очень быстро" required> &nbsp; Быстро</label>
                    <label class="radio-label"><input type="radio" name="q2" value="Удовлетворительно"> &nbsp; Средне</label>
                    <label class="radio-label"><input type="radio" name="q2" value="Медленно"> &nbsp; Медленно</label>
                </div>
            </div>

            <div class="form-group">
                <label>3. Что бы вы хотели улучшить?</label>
                <select name="q3" required>
                    <option value="Мобильное приложение">Мобильное приложение</option>
                    <option value="Чат поддержки">Чат поддержки</option>
                    <option value="Чат с преподавателем">Чат с преподавателем</option>
                    <option value="Интерфейс">Интерфейс</option>
                </select>
            </div>

            <div class="form-group">
                <label>4. Удобен ли текущий дизайн?</label>
                <select name="q4" required>
                    <option value="Да, вполне">Да, вполне</option>
                    <option value="Нужен редизайн">Нужен редизайн</option>
                    <option value="Нет, неудобен">Нет, неудобен</option>
                </select>
            </div>

            <div class="form-group">
                <label>5. Полезно ли текущее приложение?</label>
                <select name="q5" required>
                    <option value="Да">Да</option>
                    <option value="Скорее да">Скорее да</option>
                    <option value="Нет">Нет</option>
                </select>
            </div>

            <div class="form-group">
                <label>6. Пожелания (до 200 символов)</label>
                <textarea name="q6" maxlength="200" placeholder="Ваши мысли..."></textarea>
            </div>

            <button type="submit" name="send">Отправить анкету</button>
        </form>
    </div>

    <div style="margin-top: 50px;">
        <h2 style="text-align: center; color: #4b5563;">Архив ответов</h2>
        <?php if (empty($history)): ?>
            <p style="text-align: center; color: #9ca3af;">Здесь пока нет ответов. Будьте первыми!</p>
        <?php else: ?>
            <?php foreach ($history as $row): ?>
                <div class="history-item">
                    <span class="history-name"><?= htmlspecialchars($row['username']) ?></span>
                    <div class="tags">
                        <span class="tag">Вход: <?= htmlspecialchars($row['q1']) ?></span>
                        <span class="tag">Скорость: <?= htmlspecialchars($row['q2']) ?></span>
                        <span class="tag">Улучшить: <?= htmlspecialchars($row['q3']) ?></span>
                        <span class="tag">Дизайн: <?= htmlspecialchars($row['q4']) ?></span>
                    </div>
                    <?php if ($row['q6']): ?>
                        <div class="wish"><strong>💬 Пожелание:</strong> <?= htmlspecialchars($row['q6']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
