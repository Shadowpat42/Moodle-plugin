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

// Запрос на получение времени, проведенного пользователями на платформе
$timespent = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname, 
           SUM(
               CASE
                   WHEN log_next.timecreated - log.timecreated > 0 
                        AND log_next.timecreated - log.timecreated <= 600 -- максимум 10 минут между действиями
                   THEN log_next.timecreated - log.timecreated
                   ELSE 0
               END
           ) AS time_spent
    FROM {user} u
    JOIN {logstore_standard_log} log ON log.userid = u.id
    LEFT JOIN {logstore_standard_log} log_next 
           ON log_next.userid = log.userid 
          AND log_next.timecreated > log.timecreated 
          AND log_next.timecreated = (
              SELECT MIN(log_inner.timecreated) 
              FROM {logstore_standard_log} log_inner 
              WHERE log_inner.userid = log.userid 
                AND log_inner.timecreated > log.timecreated
          )
    WHERE log.courseid = :courseid
    GROUP BY u.id, u.firstname, u.lastname
    ORDER BY time_spent DESC
", ['courseid' => $courseid]);

$time_html = '';
foreach ($timespent as $time) {
    if ($time->time_spent) {
        $hours = floor($time->time_spent / 3600);
        $minutes = floor(($time->time_spent % 3600) / 60);
        $time_html .= "<li>{$time->firstname} {$time->lastname} - {$hours} часов {$minutes} минут</li>";
    } else {
        $time_html .= "<li>{$time->firstname} {$time->lastname} - Нет данных</li>";
    }
}

// Выводим шапку страницы
echo $OUTPUT->header();

?>

<!DOCTYPE html>
<html lang="ru">
<meta charset="utf-8">
<head>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<section class="bar">
    <p class="label-stats">Статистика</p>
</section>

<section class="info">
    <p class="info-label">Время, проведенное на платформе:</p>
    <div class="user-list" style="list-style-type: disc">
        <?php echo $time_html; ?>
    </div>
</section>

</body>
</html>

<?php
// Выводим подвал страницы
echo $OUTPUT->footer();
?>
