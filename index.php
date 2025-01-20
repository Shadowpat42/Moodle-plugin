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
$PAGE->set_url(new moodle_url('/local/statistics/index.php', ['courseid' => $courseid]));

// Устанавливаем заголовок страницы
$PAGE->set_title("Статистика курса");
$PAGE->set_heading("Статистика для курса");

// 1. Активность пользователей (онлайн за последние 5 минут)
$online_users = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname
    FROM {user} u
    JOIN {logstore_standard_log} log ON log.userid = u.id
    WHERE log.courseid = :courseid
      AND log.timecreated > :time_threshold
    GROUP BY u.id
", [
    'courseid' => $courseid,
    'time_threshold' => time() - 300 // последние 5 минут
]);
$online_count = count($online_users);

// Оставляем только первых двух пользователей
$online_users = array_slice($online_users, 0, 2, true);

// 2. Средний прогресс пользователей
$total_activities = $DB->get_field_sql("
    SELECT COUNT(cm.id)
    FROM {course_modules} cm
    WHERE cm.course = :courseid
      AND cm.completion > 0
", ['courseid' => $courseid]);

$average_progress = 0;
if ($total_activities > 0) {
    $total_progress = $DB->get_field_sql("
        SELECT AVG(progress) AS average_progress
        FROM (
            SELECT (COUNT(cmc.id) / :total_activities)*100 AS progress
            FROM {user} u
            LEFT JOIN {course_modules_completion} cmc ON cmc.userid = u.id
            LEFT JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
            WHERE cm.course = :courseid
              AND cm.completion > 0
            GROUP BY u.id
        ) subquery
    ", [
        'total_activities' => $total_activities,
        'courseid' => $courseid
    ]);
    $average_progress = round($total_progress, 2);
}

// 3. Количество посещений (берём всех, потом оставим 2)
$visits = $DB->get_records_sql("
    SELECT u.firstname, u.lastname, COUNT(log.id) AS visits
    FROM {user} u
    JOIN {logstore_standard_log} log ON log.userid = u.id
    WHERE log.courseid = :courseid
      AND log.action = 'viewed'
    GROUP BY u.id
    ORDER BY visits DESC
", ['courseid' => $courseid]);

// Оставляем только первых 2 пользователей
$visits = array_slice($visits, 0, 2, true);

// Генерируем HTML для этих двух
$visitors_html = '';
foreach ($visits as $visit) {
    $visitors_html .= "<div>{$visit->firstname} {$visit->lastname} - {$visit->visits} посещений</div>";
}

// 4. Время, проведённое на платформе
// ========== Замена на улучшенный вариант из time_spent.php ==========
$timespent_sql = "
    SELECT
        u.id,
        u.firstname,
        u.lastname,
        SUM(
            CASE
                WHEN (log_next.timecreated - log.timecreated) > 0
                     AND (log_next.timecreated - log.timecreated) <= 600
                THEN (log_next.timecreated - log.timecreated)
                ELSE 0
            END
        ) AS time_spent
    FROM {user} u
    JOIN {logstore_standard_log} log
         ON log.userid = u.id
         AND log.courseid = :courseid
    LEFT JOIN {logstore_standard_log} log_next
         ON log_next.userid = log.userid
         AND log_next.courseid = log.courseid
         AND log_next.timecreated = (
             SELECT MIN(li.timecreated)
             FROM {logstore_standard_log} li
             WHERE li.userid = log.userid
               AND li.courseid = log.courseid
               AND li.timecreated > log.timecreated
         )
    GROUP BY u.id, u.firstname, u.lastname
    ORDER BY time_spent DESC
";

$timespent_data = $DB->get_records_sql($timespent_sql, ['courseid' => $courseid]);

// Оставляем только двух пользователей с наибольшим временем
$timespent_data = array_slice($timespent_data, 0, 2, true);

$time_html = '';
foreach ($timespent_data as $time) {
    $hours = floor($time->time_spent / 3600);
    $minutes = floor(($time->time_spent % 3600) / 60);
    if ($time->time_spent) {
        $time_html .= "<div>{$time->firstname} {$time->lastname} - {$hours} ч. {$minutes} мин.</div>";
    } else {
        $time_html .= "<div>{$time->firstname} {$time->lastname} - Нет данных</div>";
    }
}
// ========== Конец замены ==========

// 5. Общее количество сообщений
$total_posts = $DB->get_field_sql("
    SELECT COUNT(p.id)
    FROM {forum_posts} p
    JOIN {forum_discussions} d ON d.id = p.discussion
    WHERE d.course = :courseid
", ['courseid' => $courseid]);

# список пользователей





// Выводим шапку страницы
echo $OUTPUT->header();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="css/styles.css">
    <title>Статистика для курса</title>
</head>
<body>
<section class="bar">
    <p class="label-stats">Статистика</p>
</section>
<section class="stats">

    <!-- Активность пользователей -->
    <div class="stats-panel activity" style="background: #E2FEFE;">
        <p class="panel-label">Активность пользователей</p>
        <div class="panel-content">
            <div>Сейчас онлайн:</div>
            <div style="font-size: 32px; font-weight: 300; text-align: center; margin-top: 5px;">
                <?php echo $online_count; ?>
                <img src="img/Vector.svg" width="29px" height="23px">
            </div>
        </div>
        <div class="panel-footer" style="background: #B9FAFA;">
            <a href="active_users.php?courseid=<?php echo $courseid; ?>">
                <button style="background: white; border: none; width: 105px; height: 31px; border-radius: 10px; font-size: 14px; cursor: pointer; margin-right: 15px;">
                    Открыть
                </button>
            </a>
        </div>
    </div>

    <!-- Средний прогресс пользователей -->
    <div class="stats-panel discussion" style="background: #FCE5CD;">
        <p class="panel-label">Прогресс пользователей</p>
        <div class="panel-content">
            <div>Средний % прохождения курса:</div>
            <div style="font-size: 32px; font-weight: 300; text-align: center; margin-top: 10px;">
                <?php echo $average_progress; ?>%
            </div>
        </div>
        <div class="panel-footer" style="background: #FDC896;">
            <a href="progress_users.php?courseid=<?php echo $courseid; ?>">
                <button style="background: white; border: none; width: 105px; height: 31px; border-radius: 10px; font-size: 14px; cursor: pointer; margin-right: 15px;">
                    Открыть
                </button>
            </a>
        </div>
    </div>

    <!-- Количество посещений (2 пользователя) -->
    <div class="stats-panel visitors" style="background: #FEFFD0;">
        <p class="panel-label">Количество посещений</p>
        <div class="panel-content">
            <?php echo $visitors_html; ?>
        </div>
        <div class="panel-footer" style="background: #EEED83;">
            <a href="visits_users.php?courseid=<?php echo $courseid; ?>">
                <button style="background: white; border: none; width: 105px; height: 31px; border-radius: 10px; font-size: 14px; cursor: pointer; margin-right: 15px;">
                    Открыть
                </button>
            </a>
        </div>
    </div>

    <!-- Время, проведенное на платформе (2 пользователя, улучшенный алгоритм) -->
    <div class="stats-panel time" style="background: #ECD0EF;">
        <p class="panel-label">Время, проведенное на платформе</p>
        <div class="panel-content">
            <?php echo $time_html; ?>
        </div>
        <div class="panel-footer" style="background: #EDA3EA;">
            <a href="time_spent.php?courseid=<?php echo $courseid; ?>">
                <button style="background: white; border: none; width: 105px; height: 31px; border-radius: 10px; font-size: 14px; cursor: pointer; margin-right: 15px;">
                    Открыть
                </button>
            </a>
        </div>
    </div>

    <!-- Общее количество сообщений -->
    <div class="stats-panel discussion" style="background: #F4D4D4;">
        <p class="panel-label">Сообщения пользователей</p>
        <div class="panel-content">
            <div>Общее количество сообщений:</div>
            <div style="font-size: 32px; font-weight: 300; text-align: center; margin-top: 10px;">
                <?php echo $total_posts; ?>
                <img src='img/message.svg' alt='Сообщения' style='margin-left: 10px; width="25px" height="20px";'>
            </div>
        </div>
        <div class="panel-footer" style="background: #F5A7A7;">
            <a href="discussion_posts.php?courseid=<?php echo $courseid; ?>">
                <button style="background: white; border: none; width: 105px; height: 31px; border-radius: 10px; font-size: 14px; cursor: pointer; margin-right: 15px;">
                    Открыть
                </button>
            </a>
        </div>
    </div>

    <!-- Индивидуальная статистика -->
    <div class="stats-panel individual-statistics" style="background: #F1F1F1;">
        <p class="panel-label">Пользователи</p>
        <div class="panel-content">
            <div>Просмотреть статистику по отдельным пользователям курса</div>
        </div>
        <div class="panel-footer" style="background: #E4E4E4;">
            <a href="user_statistics.php?courseid=<?php echo $courseid; ?>">
                <button style="background: white; border: none; width: 105px; height: 31px; border-radius: 10px; font-size: 14px; cursor: pointer; margin-right: 15px;">
                    Открыть
                </button>
            </a>
        </div>
    </div>



</section>
</body>
</html>

<?php
// Выводим подвал страницы
echo $OUTPUT->footer();
?>
