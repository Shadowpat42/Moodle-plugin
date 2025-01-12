<?php
require_once('../../config.php');

// 1. Параметры
$courseid = required_param('courseid', PARAM_INT);

// Moodle check
require_login($courseid);
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/statistics/time_spent.php', ['courseid'=>$courseid]));
$PAGE->set_title("Время, проведенное на платформе");
$PAGE->set_heading("Время, проведенное на платформе");

// 2. Упрощенный запрос без CROSS APPLY, используя три разных плейсхолдера
$sql = "
    SELECT 
        u.id,
        u.firstname,
        u.lastname,
        SUM(
            CASE
                WHEN (n.timecreated - log.timecreated) <= 600 THEN (n.timecreated - log.timecreated)
                ELSE 600
            END
        ) AS time_spent
    FROM (
        SELECT
            l1.userid,
            l1.timecreated,
            (
                SELECT MIN(l2.timecreated)
                  FROM {logstore_standard_log} l2
                 WHERE l2.userid   = l1.userid
                   AND l2.courseid = :cid2
                   AND l2.timecreated > l1.timecreated
            ) AS next_timecreated
        FROM {logstore_standard_log} l1
        WHERE l1.courseid = :cid1
    ) AS log
    JOIN {user} u ON u.id = log.userid
    LEFT JOIN {logstore_standard_log} n
           ON n.userid     = log.userid
          AND n.timecreated = log.next_timecreated
          AND n.courseid   = :cid3
    GROUP BY
      u.id, u.firstname, u.lastname
    ORDER BY time_spent DESC
";

// Параметры — три штуки (одно и то же значение, но разные ключи)
$params = [
    'cid1' => $courseid,
    'cid2' => $courseid,
    'cid3' => $courseid
];

$timespent = $DB->get_records_sql($sql, $params);

// --- Вывод ---
echo $OUTPUT->header();
?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="utf-8">
        <!-- Ваши стили -->
        <link rel="stylesheet" href="css/styles.css">
        <!-- Если нужен Bootstrap -->
        <link rel="stylesheet"
              href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <title>Время на платформе</title>
    </head>
    <body>
    <section class="bar">
        <p class="label-stats">Статистика</p>
    </section>

    <!-- Фиолетовая рамка, как в старом коде -->
    <section class="info time-info"
             style="border: 10px solid #EDA3EA; padding: 30px; border-radius: 20px;">

        <!-- Меняем цвет фона заголовка на бежевый + прозрачность -->
        <h2 style="background: rgba(245, 245, 220, 0.5);
               padding: 10px;
               border-bottom: 5px solid #EDA3EA;">
            Время, проведенное на платформе
        </h2>

        <div class="data-container" style="margin-top: 20px;">
            <?php if (empty($timespent)): ?>
                <p>Нет данных о времени, проведённом пользователями на платформе.</p>
            <?php else: ?>
                <ol style="list-style-type: decimal; padding-left: 20px;">
                    <?php
                    foreach ($timespent as $user) {
                        $seconds = (int)$user->time_spent;
                        if ($seconds > 0) {
                            $hours   = floor($seconds / 3600);
                            $minutes = floor(($seconds % 3600) / 60);
                            $display = $hours . " ч " . $minutes . " мин";
                        } else {
                            $display = "Нет данных";
                        }
                        echo "<li>".s($user->firstname)." ".s($user->lastname).
                            " – ".$display."</li>";
                    }
                    ?>
                </ol>
            <?php endif; ?>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
<?php
echo $OUTPUT->footer();
