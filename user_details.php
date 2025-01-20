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

// Получаем общее количество активностей курса
$total_activities = $DB->get_field_sql("
    SELECT COUNT(cm.id)
    FROM {course_modules} cm
    WHERE cm.course = :courseid
      AND cm.completion > 0
", ['courseid' => $courseid]);

// Подсчет прогресса пользователя
$user_progress = 0;
if ($total_activities > 0) {
    $completed_activities = $DB->get_field_sql("
        SELECT COUNT(cmc.id)
        FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        WHERE cmc.userid = :userid
          AND cm.course = :courseid
          AND cm.completion > 0
    ", ['userid' => $userid, 'courseid' => $courseid]);

    $user_progress = round(($completed_activities / $total_activities) * 100, 2);
}

// Получаем дополнительные данные: сообщения и посещения
$total_posts = $DB->get_field_sql("SELECT COUNT(p.id) FROM {forum_posts} p WHERE p.userid = :userid", ['userid' => $userid]);
$activity_count = $DB->get_field_sql("
    SELECT COUNT(log.id) 
    FROM {logstore_standard_log} log 
    WHERE log.userid = :userid AND log.courseid = :courseid 
", ['userid' => $userid, 'courseid' => $courseid]);

// Получение роли пользователя в контексте курса
$context = context_course::instance($courseid); // Контекст курса
$roleid = $DB->get_field('role_assignments', 'roleid', [
    'userid' => $userid,
    'contextid' => $context->id
]);

$role_name = 'Роль не найдена';
if ($roleid) {
    $role_name = $DB->get_field('role', 'name', ['id' => $roleid]);
}

// Дата регистрации
$registration_date = date('d.m.Y', $user->firstaccess);

// Последняя активность
$last_login = date('d.m.Y H:i', $user->lastaccess);

// Количество курсов, в которых участвует пользователь
$course_count = $DB->count_records('user_enrolments', ['userid' => $userid]);

// Средняя оценка пользователя
$avg_grade = $DB->get_field_sql("SELECT AVG(finalgrade) FROM {grade_grades} WHERE userid = :userid AND finalgrade IS NOT NULL", ['userid' => $userid]);
$avg_grade = $avg_grade ? round($avg_grade, 2) : 'Нет оценок';

// Количество завершенных курсов
$completed_courses = $DB->get_field_sql("SELECT COUNT(id) FROM {course_completions} WHERE userid = :userid AND timecompleted IS NOT NULL", ['userid' => $userid]);

// Выводим шапку страницы
echo $OUTPUT->header();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <title>Профиль пользователя</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .progress-circle-container {
            position: absolute;
            right: 20px;
            top: 37%;
            transform: translateY(-50%);
            width: 80px;
            height: 80px;
        }
        .progress-circle {
            width: 100%;
            height: 100%;
        }
        .progress-text {
            position: absolute;
            font-size: 14px;
            font-weight: bold;
            color: #333;
            width: 100%;
            text-align: center;
            top: 50%;
            transform: translateY(-50%);
        }
        .stats-container {
            display: flex;
            gap: 15px;
        }
    </style>
</head>
<body>

<section class="h-100 gradient-custom-2">
    <div class="container py-5 h-100">
        <div class="row d-flex justify-content-center">
            <div class="col col-lg-9 col-xl-8">
                <div class="card">
                    <div class="rounded-top text-white d-flex flex-row position-relative" style="background-color: #000; height:200px;">
                        <div class="ms-3" style="margin-top: 130px;">
                            <h5><?php echo fullname($user); ?></h5>
                            <p><?php echo $user->city ? $user->city : 'Не указано'; ?></p>
                        </div>
                    </div>

                    <div class="p-4 text-black bg-body-tertiary">
                        <div class="d-flex justify-content-between align-items-center text-body">
                            <div class="stats-container">
                                <div>
                                    <p class="mb-1 h5"> <?php echo $total_posts; ?> </p>
                                    <p class="small text-muted mb-0">Сообщений</p>
                                </div>
                                <div>
                                    <p class="mb-1 h5"> <?php echo $activity_count; ?> </p>
                                    <p class="small text-muted mb-0">Посещений курса</p>
                                </div>
                            </div>
                            <div class="progress-circle-container">
                                <canvas id="progressCircle" class="progress-circle"></canvas>
                                <div class="progress-text"> <?php echo $user_progress; ?>% </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4 text-black">
                        <div class="mb-5 text-body">
                            <p class="lead fw-normal mb-1">О пользователе</p>
                            <div class="p-4 bg-body-tertiary">
                                <p class="font-italic mb-1">Роль: <?php echo $role_name; ?></p>
                                <p class="font-italic mb-1">Дата регистрации: <?php echo $registration_date; ?></p>
                                <p class="font-italic mb-1">Последняя активность: <?php echo $last_login; ?></p>
                                <p class="font-italic mb-1">Записан в курсов: <?php echo $course_count; ?></p>
                                <p class="font-italic mb-1">Средняя оценка: <?php echo $avg_grade; ?></p>
                                <p class="font-italic mb-1">Завершенных курсов: <?php echo $completed_courses; ?></p>
                                <p class="font-italic mb-0">Описание: <?php echo $user->description ? $user->description : 'Нет описания'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const ctx = document.getElementById('progressCircle').getContext('2d');
        const userProgress = <?php echo $user_progress; ?>;

        const progressCircle = new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [userProgress, 100 - userProgress],
                    backgroundColor: ['rgba(255, 159, 64, 1)', 'rgba(200, 200, 200, 0.3)'],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '75%',
                responsive: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                }
            }
        });
    });
</script>

</body>
</html>

<?php
echo $OUTPUT->footer();
?>
