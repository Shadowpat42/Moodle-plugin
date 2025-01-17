<?php
require_once('../../config.php');

// Параметры курса
$courseid = required_param('courseid', PARAM_INT);

// Параметры поиска/сортировки
$search = optional_param('search', '', PARAM_TEXT);
$sort = optional_param('sort', 'progress_desc', PARAM_ALPHANUMEXT);
$grade_filter = optional_param('grade_filter', '', PARAM_INT);

// Проверки Moodle
require_login($courseid);
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/statistics/progress_users.php', ['courseid' => $courseid]));
$PAGE->set_title("Прогресс пользователей");
$PAGE->set_heading("Прогресс пользователей");

// WHERE для поиска
$where_search = '';
$params = ['courseid' => $courseid];
$total_activities = $DB->get_field_sql("
    SELECT COUNT(cm.id)
    FROM {course_modules} cm
    WHERE cm.course = :courseid AND cm.completion > 0
", ['courseid' => $courseid]);

if (!$total_activities) {
    $total_activities = 1;
}

if (!empty($search)) {
    $where_search = " AND (u.firstname LIKE :s1 OR u.lastname LIKE :s2)";
    $params['s1'] = '%' . $search . '%';
    $params['s2'] = '%' . $search . '%';
}

// Фильтр по оценке
$grade_filter_sql = '';
if (!empty($grade_filter)) {
    switch ($grade_filter) {
        case '5':
            $grade_filter_sql = "HAVING progress BETWEEN 80 AND 100";
            break;
        case '4':
            $grade_filter_sql = "HAVING progress BETWEEN 60 AND 79";
            break;
        case '3':
            $grade_filter_sql = "HAVING progress BETWEEN 40 AND 59";
            break;
        case '2':
            $grade_filter_sql = "HAVING progress < 40";
            break;
    }
}

// ORDER BY
$order_by = '';
switch ($sort) {
    case 'progress_asc':
        $order_by = "ORDER BY progress ASC";
        break;
    case 'name_asc':
        $order_by = "ORDER BY u.firstname ASC, u.lastname ASC";
        break;
    case 'name_desc':
        $order_by = "ORDER BY u.firstname DESC, u.lastname DESC";
        break;
    default:
        $order_by = "ORDER BY progress DESC";
        break;
}

// SQL для прогресса пользователей
$sql = "
    SELECT
        u.id,
        u.firstname,
        u.lastname,
        (COUNT(cmc.id) / :total_activities) * 100 AS progress
    FROM {user} u
    LEFT JOIN {course_modules_completion} cmc ON cmc.userid = u.id
    LEFT JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
    WHERE cm.course = :courseid AND cm.completion > 0
    $where_search
    GROUP BY u.id, u.firstname, u.lastname
    $order_by
";

// Добавляем фильтрацию по прогрессу
if (!empty($grade_filter)) {
    $sql .= " $grade_filter_sql";
}

$params['total_activities'] = $total_activities;
$progress_users = $DB->get_records_sql($sql, $params);

// Подготовка данных для графика
$chart_labels = [];
$chart_data = [];
foreach ($progress_users as $user) {
    $chart_labels[] = "{$user->firstname} {$user->lastname}";
    $chart_data[] = round($user->progress, 2);
}

// Вывод
echo $OUTPUT->header();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <title>Прогресс пользователей</title>
    <style>
        .table { border-radius: 10px; overflow: hidden; }
        .progress { height: 20px; border-radius: 10px; }
        .progress-bar { text-align: center; line-height: 20px; border-radius: 10px; }
        .form-select, .input-group-text, .input-group input { border-radius: 5px; }
    </style>
</head>
<body>
<section class="info progress-info" style="border: 10px solid #FDC896; padding: 30px; max-width: 1050px; margin: auto;">
    <h2 style="border-bottom: 5px solid #FDC896;">Прогресс пользователей</h2>

    <form method="get" action="progress_users.php" class="row g-3 mb-4">
        <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text">Поиск</span>
                <input type="text" name="search" class="form-control" placeholder="Имя или фамилия" value="<?php echo s($search); ?>">
            </div>
        </div>
        <div class="col-md-4">
            <select name="grade_filter" class="form-select">
                <option value="">Выберите оценку</option>
                <option value="5" <?php if ($grade_filter == '5') echo 'selected'; ?>>Оценка 5</option>
                <option value="4" <?php if ($grade_filter == '4') echo 'selected'; ?>>Оценка 4</option>
                <option value="3" <?php if ($grade_filter == '3') echo 'selected'; ?>>Оценка 3</option>
                <option value="2" <?php if ($grade_filter == '2') echo 'selected'; ?>>Оценка 2</option>
            </select>
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
        <?php if (empty($progress_users)): ?>
            <p>Нет данных о прогрессе пользователей.</p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Прогресс</th>
                </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($progress_users as $user): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo s($user->firstname); ?></td>
                        <td><?php echo s($user->lastname); ?></td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo round($user->progress, 2); ?>%;" aria-valuenow="<?php echo round($user->progress, 2); ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round($user->progress, 2); ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <h3 style="text-align: center; margin-top: 20px;">График прогресса пользователей</h3>
    <canvas id="progressChart"></canvas>
</section>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('progressChart').getContext('2d');
    const progressChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Прогресс (%)',
                data: <?php echo json_encode($chart_data); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, max: 100 } }
        }
    });
</script>
</body>
</html>
<?php echo $OUTPUT->footer(); ?>
