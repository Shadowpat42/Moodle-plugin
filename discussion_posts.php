<?php
require_once('../../config.php');

// Параметры курса
$courseid = required_param('courseid', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT); // строка поиска
$sort   = optional_param('sort', 'posts_desc', PARAM_ALPHANUMEXT); // тип сортировки

// Проверки Moodle
require_login($courseid);
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/statistics/discussion_posts.php', ['courseid' => $courseid]));
$PAGE->set_title("Сообщения пользователей");
$PAGE->set_heading("Сообщения пользователей для курса");

// WHERE и параметры
$where_search = '';
$params = ['courseid' => $courseid];
if (!empty($search)) {
    $where_search = " AND (u.firstname LIKE :s1 OR u.lastname LIKE :s2)";
    $params['s1'] = '%' . $search . '%';
    $params['s2'] = '%' . $search . '%';
}

// ORDER BY
$order_by = '';
switch ($sort) {
    case 'posts_asc':
        $order_by = "ORDER BY posts_count ASC";
        break;
    case 'posts_desc':
        $order_by = "ORDER BY posts_count DESC";
        break;
    case 'name_asc':
        $order_by = "ORDER BY u.firstname ASC, u.lastname ASC";
        break;
    case 'name_desc':
        $order_by = "ORDER BY u.firstname DESC, u.lastname DESC";
        break;
    default:
        $order_by = "ORDER BY posts_count DESC";
        break;
}

// SQL запрос
$sql = "
    SELECT u.id, u.firstname, u.lastname, COUNT(fp.id) AS posts_count
    FROM {user} u
    JOIN {forum_posts} fp ON fp.userid = u.id
    JOIN {forum_discussions} fd ON fd.id = fp.discussion
    WHERE fd.course = :courseid
          $where_search
    GROUP BY u.id, u.firstname, u.lastname
    $order_by
";

$discussion_posts = $DB->get_records_sql($sql, $params);

// Вывод страницы
echo $OUTPUT->header();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <title>Сообщения пользователей</title>
</head>
<body>
<section class="bar">
    <p class="label-stats">Статистика</p><br><br>
</section>

<section class="info discussion-info"
         style="border: 10px solid #F5A7A7; padding: 30px; border-radius: 20px; max-width: 1050px; margin: auto;">
    <h2 style="border-bottom: 5px solid #F5A7A7; padding-bottom: 15px;">Сообщения пользователей</h2>

    <!-- Форма поиска и сортировки -->
    <form method="get" action="discussion_posts.php" class="row g-3 mb-4">
        <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">

        <!-- Поле поиска -->
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text">Поиск</span>
                <input type="text" name="search" class="form-control" placeholder="Имя или фамилия"
                       value="<?php echo s($search); ?>">
            </div>
        </div>

        <!-- Сортировка -->
        <div class="col-md-4">
            <select name="sort" class="form-select">
                <option value="posts_desc" <?php echo ($sort == 'posts_desc') ? 'selected' : ''; ?>>Сообщений (убывание)</option>
                <option value="posts_asc" <?php echo ($sort == 'posts_asc') ? 'selected' : ''; ?>>Сообщений (возрастание)</option>
                <option value="name_asc" <?php echo ($sort == 'name_asc') ? 'selected' : ''; ?>>Имя (А-Я)</option>
                <option value="name_desc" <?php echo ($sort == 'name_desc') ? 'selected' : ''; ?>>Имя (Я-А)</option>
            </select>
        </div>

        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">Применить</button>
        </div>
    </form>

    <!-- Таблица -->
    <div class="data-container">
        <?php if (empty($discussion_posts)): ?>
            <p>Нет данных о сообщениях пользователей.</p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Сообщений</th>
                </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($discussion_posts as $user): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo s($user->firstname); ?></td>
                        <td><?php echo s($user->lastname); ?></td>
                        <td><?php echo $user->posts_count; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
echo $OUTPUT->footer();
?>
