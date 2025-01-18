<?php
require_once('../../config.php');

// Получаем параметры из запроса
$userid = required_param('userid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT); // Опционально, если нужно указать курс

// Проверяем, что пользователь авторизован
require_login();

// Получаем данные пользователя
$user = $DB->get_record('user', ['id' => $userid]);

// Получаем профильную фотографию пользователя (если она есть)
$profile_picture_url = moodle_url::make_pluginfile_url(
    $user->contextid,
    'user',
    'icon',
    0,
    '/',
    $user->id . '.jpg'
)->out();

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
                    <div class="rounded-top text-white d-flex flex-row" style="background-color: #000; height:200px;">
                        <div class="ms-4 mt-5 d-flex flex-column" style="width: 150px;">
                            <!-- Профильная фотография -->
                            <img src="<?php echo $profile_picture_url; ?>" alt="Avatar" class="img-fluid img-thumbnail mt-4 mb-2" style="width: 150px; z-index: 1">
                            <button type="button" class="btn btn-outline-dark text-body" style="z-index: 1;">
                                Редактировать профиль
                            </button>
                        </div>
                        <div class="ms-3" style="margin-top: 130px;">
                            <!-- Имя пользователя -->
                            <h5><?php echo fullname($user); ?></h5>
                            <p><?php echo $user->city ? $user->city : 'Не указано'; ?></p>
                        </div>
                    </div>

                    <div class="p-4 text-black bg-body-tertiary">
                        <div class="d-flex justify-content-end text-center py-1 text-body">
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
?>
