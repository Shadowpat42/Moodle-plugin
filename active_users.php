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
$PAGE->set_url(new moodle_url('/local/statistics/active_users.php', array('courseid' => $courseid)));

// Устанавливаем заголовок страницы
$PAGE->set_title("Активные пользователи");
$PAGE->set_heading("Активные пользователи для курса");

// Запрос на получение активных пользователей
$online_users = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname
    FROM {user} u
    JOIN {logstore_standard_log} log ON log.userid = u.id
    WHERE log.courseid = :courseid AND log.timecreated > :time_threshold
    GROUP BY u.id
", [
    'courseid' => $courseid,  // передаем курс в параметр
    'time_threshold' => time() - 300 // последние 5 минут
]);

// Выводим шапку страницы
echo $OUTPUT->header();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<section class="bar">
    <p class="label-stats">Статистика</p>
</section>

<section class="info activity-info" style="border: 10px solid #B9FAFA; padding: 30px; border-radius: 20px;">
    <h2 style="border-bottom: 5px solid #B9FAFA; padding-bottom: 15px;">Активные пользователи</h2>
    <div class="user-list">
        <?php if (empty($online_users)) : ?>
            <p>Нет активных пользователей в данный момент.</p>
        <?php else : ?>
            <ol style="padding-left: 20px; list-style-type: decimal;">
                <?php foreach ($online_users as $user) : ?>
                    <li><?php echo $user->firstname . ' ' . $user->lastname; ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
</body>
</html>

<?php
// Выводим подвал страницы
echo $OUTPUT->footer();
?>
