<?php
require_once('../../config.php');

// [1] Параметр курса
$courseid = required_param('courseid', PARAM_INT);

// [2] Проверяем права Moodle
require_login($courseid);
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/statistics/progress_users.php', [
    'courseid' => $courseid
]));
$PAGE->set_title("Прогресс пользователей");
$PAGE->set_heading("Прогресс пользователей для курса");

// [3] Параметры поиска и сортировки
$search = optional_param('search', '', PARAM_TEXT);       // Поисковая строка
$sort   = optional_param('sort', 'progress_desc', PARAM_ALPHA); // Тип сортировки

// [4] Узнаём общее число активностей
$total_activities = $DB->get_field_sql("
    SELECT COUNT(cm.id)
      FROM {course_modules} cm
     WHERE cm.course = :c
       AND cm.completion > 0
", ['c' => $courseid]);

// [5] Во избежание деления на 0
if (!$total_activities) {
    $total_activities = 1;
}

// [6] Готовим WHERE для поиска и массив $params
$where_search = '';
$params = [
    'c'     => $courseid,
    'tacts' => $total_activities
];

if (!empty($search)) {
    $where_search = " AND (u.firstname LIKE :s1 OR u.lastname LIKE :s2) ";
    $params['s1'] = '%' . $search . '%';
    $params['s2'] = '%' . $search . '%';
}

// [7] Определяем ORDER BY
$order_by = '';
switch ($sort) {
    case 'progress_asc':
        $order_by = "ORDER BY progress ASC";
        break;
    case 'name_asc':
        $order_by = "ORDER BY firstname ASC, lastname ASC";
        break;
    case 'name_desc':
        $order_by = "ORDER BY firstname DESC, lastname DESC";
        break;
    default:
        // progress_desc
        $order_by = "ORDER BY progress DESC";
        break;
}

// В блоке [8], где формируем финальный запрос, делаем подзапрос:
$sql = "
SELECT sub.*
FROM (
    SELECT
       u.id AS userid,
       MIN(u.firstname) AS firstname,
       MIN(u.lastname)  AS lastname,
       (COUNT(cmc.id) / :tacts) * 100 AS progress
    FROM {user} u
    LEFT JOIN {course_modules_completion} cmc ON cmc.userid = u.id
    LEFT JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
    WHERE cm.course = :c
      AND cm.completion > 0
      $where_search
    GROUP BY u.id
) sub
$order_by
";

// [9] Получаем записи
error_log("==== DEBUG progress_users.php ====");
error_log("SQL:\n$sql");
error_log("PARAMS: " . print_r($params, true));
error_log("Sort parameter: " . $sort);

$users_progress = $DB->get_records_sql($sql, $params);

// --- Выводим страницу ---
echo $OUTPUT->header();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <title>Прогресс пользователей</title>
</head>
<body>
<section class="bar">
    <p class="label-stats">Статистика</p>
</section>
<section class="info progress-info" style="border: 10px solid #FDC896; padding: 30px; border-radius: 20px;">
    <h2 style="border-bottom: 5px solid #FDC896; padding-bottom: 15px;">Прогресс прохождения курса</h2>
    <form method="get" action="progress_users.php" class="row g-3 mb-4">
        <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text">Поиск</span>
                <input type="text" name="search" class="form-control" placeholder="Имя или фамилия" value="<?php echo s($search); ?>">
            </div>
        </div>
        <div class="col-md-4">
            <select name="sort" class="form-select">
                <option value="progress_desc" <?php if ($sort == 'progress_desc') echo 'selected'; ?>>Прогресс (убывание)</option>
                <option value="progress_asc" <?php if ($sort == 'progress_asc') echo 'selected'; ?>>Прогресс (возрастание)</option>
                <option value="name_asc" <?php if ($sort == 'name_asc') echo 'selected'; ?>>Имя (А-Я)</option>
                <option value="name_desc" <?php if ($sort == 'name_desc') echo 'selected'; ?>>Имя (Я-А)</option>
            </select>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">Применить</button>
        </div>
    </form>
    <div class="data-container">
        <?php if (empty($users_progress)): ?>
            <p>Нет данных о прогрессе пользователей.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Прогресс (%)</th>
                </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($users_progress as $record): ?>
                    <tr>
                        <th><?php echo $i++; ?></th>
                        <td><?php echo s($record->firstname); ?></td>
                        <td><?php echo s($record->lastname); ?></td>
                        <td><?php echo round($record->progress, 2); ?>%</td>
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
