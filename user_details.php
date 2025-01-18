<?php
require_once('../../config.php');

// Получаем параметры из запроса
$userid = required_param('userid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT); // Опционально, если нужно указать курс

// Проверяем, что пользователь авторизован
require_login();

// Получаем данные пользователя
$user = $DB->get_record('user', ['id' => $userid]);

// Устанавливаем контекст страницы
$PAGE->set_context(context_user::instance($userid));

// Устанавливаем заголовок страницы
$PAGE->set_title("Профиль пользователя: " . fullname($user));
$PAGE->set_heading("Профиль пользователя");

// Получаем дополнительную информацию о пользователе
$total_posts = $DB->get_field_sql("SELECT COUNT(p.id) FROM {forum_posts} p WHERE p.userid = :userid", ['userid' => $userid]);

// Получаем статистику активности (например, количество посещений)
$activity_count = $DB->get_field_sql("
    SELECT COUNT(log.id) 
    FROM {logstore_standard_log} log 
    WHERE log.userid = :userid AND log.courseid = :courseid 
", ['userid' => $userid, 'courseid' => $courseid]);

// Выводим шапку страницы
echo $OUTPUT->header();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <title>Профиль пользователя</title>
</head>
<body>

<section class="h-100 gradient-custom-2">
    <div class="container py-5 h-100">
        <div class="row d-flex justify-content-center">
            <div class="col col-lg-9 col-xl-8">
                <div class="card">
                    <div class="rounded-top text-white d-flex flex-column align-items-center" style="background-color: #007bff; height: 200px;">
                        <div class="mt-5">
                            <!-- Место для персонализированного текста -->
                            <h5 class="text-white"><?php echo fullname($user); ?></h5>
                            <p class="text-white"><?php echo $user->city ? $user->city : 'Не указано'; ?></p>
                            <p class="text-white"><?php echo $user->email ? $user->email : 'Не указан'; ?></p>
                        </div>
                    </div>

                    <div class="p-4 text-black bg-body-tertiary">
                        <div class="d-flex justify-content-between text-center py-1 text-body">
                            <div>
                                <p class="mb-1 h5"><?php echo $total_posts; ?></p>
                                <p class="small text-muted mb-0">Сообщений</p>
                            </div>
                            <div class="px-3">
                                <p class="mb-1 h5"><?php echo $activity_count; ?></p>
                                <p class="small text-muted mb-0">Посещений курса</p>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4 text-black">
                        <div class="mb-5 text-body">
                            <p class="lead fw-normal mb-1">О пользователе</p>
                            <div class="p-4 bg-body-tertiary">
                                <p class="font-italic mb-1">Роль: <?php echo $user->role; ?></p>
                                <p class="font-italic mb-1">Город: <?php echo $user->city ? $user->city : 'Не указан'; ?></p>
                                <p class="font-italic mb-0">Описание: <?php echo $user->description ? $user->description : 'Нет описания'; ?></p>
                            </div>
                        </div>

                        <!-- Статистика по курсу -->
                        <div class="mb-5 text-body">
                            <p class="lead fw-normal mb-1">Статистика по курсу</p>
                            <div class="p-4 bg-body-tertiary">
                                <p class="font-italic mb-1">Завершено курсов: <?php echo get_courses_count($userid); ?></p>
                                <p class="font-italic mb-1">Прогресс на текущем курсе: <?php echo get_user_course_progress($userid, $courseid); ?>%</p>
                            </div>
                        </div>

                        <!-- Статистика активности (время проведённое в курсе) -->
                        <div class="mb-5 text-body">
                            <p class="lead fw-normal mb-1">Активность на платформе</p>
                            <div class="p-4 bg-body-tertiary">
                                <p class="font-italic mb-1">Время на платформе: <?php echo get_time_spent_on_platform($userid, $courseid); ?></p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

</body>
</html>

<?php
// Выводим подвал страницы
echo $OUTPUT->footer();

// Функции для получения статистики
function get_courses_count($userid) {
    global $DB;
    $count = $DB->count_records('user_enrolments', ['userid' => $userid]);
    return $count;
}

function get_user_course_progress($userid, $courseid) {
    global $DB;
    // Получаем прогресс (например, завершённые модули)
    $completed = $DB->count_records('course_modules_completion', ['userid' => $userid, 'course' => $courseid, 'completionstate' => 1]);
    $total_modules = $DB->count_records('course_modules', ['course' => $courseid]);
    $progress = $total_modules ? round(($completed / $total_modules) * 100, 2) : 0;
    return $progress;
}

function get_time_spent_on_platform($userid, $courseid) {
    global $DB;
    // Получаем суммарное время, которое пользователь провел на платформе
    $time = $DB->get_field_sql("
        SELECT SUM(log_next.timecreated - log.timecreated)
        FROM {logstore_standard_log} log
        JOIN {logstore_standard_log} log_next ON log_next.timecreated > log.timecreated
        WHERE log.userid = :userid AND log.courseid = :courseid
    ", ['userid' => $userid, 'courseid' => $courseid]);

    // Конвертируем время в часы и минуты
    $hours = floor($time / 3600);
    $minutes = floor(($time % 3600) / 60);
    return "{$hours} ч. {$minutes} мин.";
}
?>
