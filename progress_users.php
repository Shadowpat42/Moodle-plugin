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
$PAGE->set_url(new moodle_url('/local/statistics/progress_users.php', array('courseid' => $courseid)));

// Устанавливаем заголовок страницы
$PAGE->set_title("Прогресс пользователей");
$PAGE->set_heading("Прогресс пользователей для курса");

// Получаем общее количество активностей в курсе (модули с параметром completion > 0)
$total_activities = $DB->get_field_sql("
    SELECT COUNT(cm.id)
    FROM {course_modules} cm
    WHERE cm.course = :courseid AND cm.completion > 0
", ['courseid' => $courseid]);

// Получаем прогресс пользователей
$users_progress = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname, 
           (COUNT(cmc.id) / :total_activities) * 100 AS progress
    FROM {user} u
    LEFT JOIN {course_modules_completion} cmc ON cmc.userid = u.id
    LEFT JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
    WHERE cm.course = :courseid AND cm.completion > 0
    GROUP BY u.id
", [
    'courseid' => $courseid,
    'total_activities' => $total_activities > 0 ? $total_activities : 1 // Чтобы избежать деления на ноль
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

<section class="info progress-info" style="border: 10px solid #FDC896; padding: 30px; border-radius: 20px;">
    <h2 style="border-bottom: 5px solid #FDC896; padding-bottom: 15px;">Прогресс прохождения курса</h2>
    <div class="data-container">
        <?php if (empty($users_progress)) : ?>
            <p>Нет данных о прогрессе пользователей.</p>
        <?php else : ?>
            <ol class="labels" style="padding-left: 20px; list-style-type: decimal;">
                <?php foreach ($users_progress as $user_progress) : ?>
                    <li>
                        <?php
                        // Проверяем, если прогресс пустой, ставим 0%
                        $progress = $user_progress->progress ?? 0;
                        // Округляем прогресс до 2 знаков
                        echo $user_progress->firstname . ' ' . $user_progress->lastname . ' - ' . round($progress, 2) . '%';
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
