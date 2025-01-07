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
$PAGE->set_url(new moodle_url('/local/statistics/index.php', array('courseid' => $courseid)));

// Устанавливаем заголовок страницы
$PAGE->set_title("Статистика курса");
$PAGE->set_heading("Статистика для курса");

// **1. Активность пользователей (онлайн за последние 5 минут)**
$online_users = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname
    FROM {user} u
    JOIN {logstore_standard_log} log ON log.userid = u.id
    WHERE log.courseid = :courseid AND log.timecreated > :time_threshold
    GROUP BY u.id
", [
    'courseid' => $courseid,
    'time_threshold' => time() - 300 // последние 5 минут
]);

$online_count = count($online_users);

// **2. Средний прогресс пользователей**
$total_activities = $DB->get_field_sql("
    SELECT COUNT(cm.id)
    FROM {course_modules} cm
    WHERE cm.course = :courseid AND cm.completion > 0
", ['courseid' => $courseid]);

$average_progress = 0;

if ($total_activities > 0) {
    $total_progress = $DB->get_field_sql("
        SELECT AVG(progress) AS average_progress
        FROM (
            SELECT (COUNT(cmc.id) / :total_activities) * 100 AS progress
            FROM {user} u
            LEFT JOIN {course_modules_completion} cmc ON cmc.userid = u.id
            LEFT JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
            WHERE cm.course = :courseid AND cm.completion > 0
            GROUP BY u.id
        ) subquery
    ", [
        'total_activities' => $total_activities,
        'courseid' => $courseid
    ]);

    $average_progress = round($total_progress, 2); // Округляем до 2 знаков
}

// **3. Количество посещений**
$visits = $DB->get_records_sql("
    SELECT u.firstname, u.lastname, COUNT(log.id) AS visits
    FROM {user} u
    JOIN {logstore_standard_log} log ON log.userid = u.id
    WHERE log.courseid = :courseid AND log.action = 'viewed'
    GROUP BY u.id
    ORDER BY visits DESC
", ['courseid' => $courseid]);

$visitors_html = '';
foreach ($visits as $visit) {
    $visitors_html .= "<div>{$visit->firstname} {$visit->lastname} - {$visit->visits} посещений</div>";
}

// **4. Время, проведенное на платформе**
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
        $time_html .= "<div>{$time->firstname} {$time->lastname} - {$hours} часов {$minutes} минут</div>";
    } else {
        $time_html .= "<div>{$time->firstname} {$time->lastname} - Нет данных</div>";
    }
}

// **5. Обсуждения тем (общее количество сообщений)**
$total_posts = $DB->get_field_sql("
    SELECT COUNT(p.id)
    FROM {forum_posts} p
    JOIN {forum_discussions} d ON d.id = p.discussion
    WHERE d.course = :courseid
", ['courseid' => $courseid]);

// Выводим шапку страницы
echo $OUTPUT->header();

?>

    <!DOCTYPE html>
    <html lang="ru">
    <meta charset="utf-8">
    <head>
        <link rel="stylesheet" href="css/styles.css">
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
                    <?= $average_progress; ?>%
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

        <!-- Количество посещений -->
        <div class="stats-panel visitors" style="background: #FEFFD0;">
            <p class="panel-label">Количество посещений</p>
            <div class="panel-content"><?php echo $visitors_html; ?></div>
            <div class="panel-footer" style="background: #EEED83;">
                <a href="visits_users.php?courseid=<?php echo $courseid; ?>">
                    <button style="background: white; border: none; width: 105px; height: 31px; border-radius: 10px; font-size: 14px; cursor: pointer; margin-right: 15px;">
                        Открыть
                    </button>
                </a>
            </div>
        </div>

        <!-- Время, проведенное на платформе -->
        <div class="stats-panel time" style="background: #ECD0EF;">
            <p class="panel-label">Время, проведенное на платформе</p>
            <div class="panel-content"><?php echo $time_html; ?></div>
            <div class="panel-footer" style="background: #EDA3EA;">
                <a href="time_spent.php?courseid=<?php echo $courseid; ?>">
                    <button style="background: white; border: none; width: 105px; height: 31px; border-radius: 10px; font-size: 14px; cursor: pointer; margin-right: 15px;">
                        Открыть
                    </button>
                </a>
            </div>
        </div>

        <!-- Обсуждение тем -->
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

    </section>
    </body>
    </html>


<?php
// Выводим подвал страницы
echo $OUTPUT->footer();
