<?php

require_once('../../config.php');

// Получаем параметры из запроса
$courseid = required_param('courseid', PARAM_INT);

// Проверяем, что пользователь авторизован и имеет доступ к курсу
require_login($courseid);

// Устанавливаем контекст страницы для курса
$context = context_course::instance($courseid);
$PAGE->set_context($context);

// Устанавливаем URL для страницы
$PAGE->set_url(new moodle_url('/local/statistics/time_spent.php', array('courseid' => $courseid)));

// Устанавливаем заголовок страницы
$PAGE->set_title("Время, проведенное на платформе");
$PAGE->set_heading("Время, проведенное на платформе для курса");

// Проверка поддержки оконных функций (MariaDB 10.2+)
$version = $DB->get_field_sql("SELECT VERSION()");
if (version_compare($version, '10.2', '<')) {
    echo $OUTPUT->header();
    echo "<div class='alert alert-danger'>Ваш сервер MariaDB не поддерживает необходимые функции для выполнения этого запроса.</div>";
    echo $OUTPUT->footer();
    exit;
}

// Получаем список пользователей, зарегистрированных в курсе
$users = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname
    FROM {user} u
    JOIN {user_enrolments} ue ON ue.userid = u.id
    JOIN {enrol} e ON e.id = ue.enrolid
    WHERE e.courseid = :courseid
", ['courseid' => $courseid]);

if (empty($users)) {
    // Нет пользователей в курсе
    echo $OUTPUT->header();
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="utf-8">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="css/styles.css">
        <title>Время, проведенное на платформе</title>
    </head>
    <body>
    <section class="bar">
        <p class="label-stats">Статистика</p>
    </section>

    <section class="info time-info container my-4">
        <h2 class="border-bottom pb-2">Время, проведенное на платформе</h2>
        <p>Нет пользователей в курсе.</p>
    </section>

    <!-- Подключение Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    echo $OUTPUT->footer();
    exit;
}

// Оптимизированный запрос без использования CROSS APPLY
// Получаем время, проведенное пользователями на платформе
$timespent = $DB->get_records_sql("
    SELECT 
        u.id, 
        u.firstname, 
        u.lastname, 
        SUM(
            CASE 
                WHEN TIMESTAMPDIFF(SECOND, l1.timecreated, l2.timecreated) <= 600 
                THEN TIMESTAMPDIFF(SECOND, l1.timecreated, l2.timecreated) 
                ELSE 600 
            END
        ) AS time_spent
    FROM {user} u
    JOIN {user_enrolments} ue ON ue.userid = u.id
    JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
    JOIN {logstore_standard_log} l1 ON l1.userid = u.id AND l1.courseid = :courseid
    LEFT JOIN {logstore_standard_log} l2 ON l2.userid = u.id AND l2.courseid = :courseid AND l2.timecreated = (
        SELECT MIN(l3.timecreated)
        FROM {logstore_standard_log} l3
        WHERE l3.userid = l1.userid AND l3.courseid = l1.courseid AND l3.timecreated > l1.timecreated
    )
    GROUP BY u.id, u.firstname, u.lastname
    ORDER BY time_spent DESC
", ['courseid' => $courseid]);

$time_html = '';
foreach ($timespent as $time) {
    if ($time->time_spent) {
        $hours = floor($time->time_spent / 3600);
        $minutes = floor(($time->time_spent % 3600) / 60);
        $time_html .= "<tr>
                            <td>" . htmlspecialchars($time->firstname, ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . htmlspecialchars($time->lastname, ENT_QUOTES, 'UTF-8') . "</td>
                            <td>{$hours} часов {$minutes} минут</td>
                       </tr>";
    } else {
        $time_html .= "<tr>
                            <td>" . htmlspecialchars($time->firstname, ENT_QUOTES, 'UTF-8') . "</td>
                            <td>" . htmlspecialchars($time->lastname, ENT_QUOTES, 'UTF-8') . "</td>
                            <td>Нет данных</td>
                       </tr>";
    }
}

// Выводим шапку страницы
echo $OUTPUT->header();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <title>Время, проведенное на платформе</title>
</head>
<body>
<section class="bar">
    <p class="label-stats">Статистика</p>
</section>

<section class="info time-info container my-4">
    <h2 class="border-bottom pb-2">Время, проведенное на платформе</h2>

    <!-- Таблица времени -->
    <div class="table-responsive">
        <?php if (empty($timespent)) : ?>
            <p>Нет данных о времени, проведенном пользователями на платформе.</p>
        <?php else : ?>
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                <tr>
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Время (часы:минуты)</th>
                </tr>
                </thead>
                <tbody>
                <?php echo $time_html; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<!-- Подключение Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Выводим подвал страницы
echo $OUTPUT->footer();
?>
