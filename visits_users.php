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
$PAGE->set_url(new moodle_url('/local/statistics/visits_users.php', array('courseid' => $courseid)));

// Устанавливаем заголовок страницы
$PAGE->set_title("Количество посещений");
$PAGE->set_heading("Количество посещений для курса");

// Получаем данные о посещениях
$visits = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname, COUNT(log.id) AS visits
    FROM {user} u
    JOIN {logstore_standard_log} log ON log.userid = u.id
    WHERE log.courseid = :courseid AND log.action = 'viewed'
    GROUP BY u.id
    ORDER BY visits DESC
", ['courseid' => $courseid]);

// Выводим шапку страницы
echo $OUTPUT->header();

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<section class="bar">
    <p class="label-stats">Статистика</p>
</section>

<section class="info visitors-info" style="border: 10px solid #EEED83; padding: 30px; border-radius: 20px;">
    <h2 style="border-bottom: 5px solid #EEED83; padding-bottom: 15px;">Количество посещений сайта</h2>
    <div class="data-container">
        <?php if (empty($visits)) : ?>
            <p>Нет данных о посещениях пользователей.</p>
        <?php else : ?>
            <ol class="labels" style="padding-left: 20px; list-style-type: decimal;">
                <?php foreach ($visits as $visit) : ?>
                    <li>
                        <?php
                        // Выводим имя пользователя и количество посещений
                        echo $visit->firstname . ' ' . $visit->lastname . ' - ' . $visit->visits . ' посещений';
                        ?>
                    </li>
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
