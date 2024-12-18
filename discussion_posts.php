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
$PAGE->set_url(new moodle_url('/local/statistics/discussion_posts.php', array('courseid' => $courseid)));

// Устанавливаем заголовок страницы
$PAGE->set_title("Сообщения пользователей");
$PAGE->set_heading("Сообщения пользователей для курса");

// Запрос на получение всех пользователей курса
$users = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname
    FROM {user} u
    WHERE u.id IN (
        SELECT DISTINCT p.userid
        FROM {forum_posts} p
        JOIN {forum_discussions} d ON d.id = p.discussion
        WHERE d.course = :courseid
    )
    ORDER BY u.firstname, u.lastname
", array('courseid' => $courseid));

// Запрос на получение количества сообщений для каждого пользователя
$posts_count = $DB->get_records_sql("
    SELECT p.userid, COUNT(p.id) AS posts_count
    FROM {forum_posts} p
    JOIN {forum_discussions} d ON d.id = p.discussion
    WHERE d.course = :courseid
    GROUP BY p.userid
", array('courseid' => $courseid));

// Формируем список пользователей и их количества сообщений
$user_posts_data = [];
foreach ($users as $user) {
    // Проверим, есть ли данные о количестве сообщений для пользователя
    $post_count = isset($posts_count[$user->id]) ? $posts_count[$user->id]->posts_count : 0;
    $user_posts_data[$user->id] = $post_count;
}

// Заголовок страницы
echo $OUTPUT->header();

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="styles.css">
    <title>Сообщения пользователей</title>
</head>
<body>
<section class="bar">
    <p class="label-stats">Статистика</p>
</section>

<section class="info">
    <p class="info-label">Сообщения пользователей:</p>
    <ul class="user-list" style="list-style-type: disc; padding-left: 10px;">
        <?php
        foreach ($user_posts_data as $user_id => $post_count) {
            // Выводим имя пользователя и количество сообщений
            $user = $users[$user_id];
            echo "<li>{$user->firstname} {$user->lastname} - {$post_count} сообщений</li>";
        }
        ?>
    </ul>
</section>

</body>
</html>

<?php
// Выводим подвал страницы
echo $OUTPUT->footer();
?>
