<?php
require_once('../../config.php');

// Параметры курса
$courseid = required_param('courseid', PARAM_INT);

// Параметры поиска/сортировки
$search = optional_param('search', '', PARAM_TEXT);
$sort   = optional_param('sort', 'progress_desc', PARAM_ALPHANUMEXT);

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
    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <title>Прогресс пользователей</title>
</head>
<body>
<section class="bar">
    <p class="label-stats" style="margin-bottom: 20px">Статистика</p>
</section>

<section class="info progress-info"
         style="border: 10px solid #FDC896; padding: 30px; border-radius: 20px; max-width: 1050px; margin: auto;">
    <h2 style="border-bottom: 5px solid #FDC896; padding-bottom: 15px;">
        Прогресс пользователей
    </h2>

    <!-- Форма поиска и сортировки -->
    <form method="get" action="progress_users.php" class="row g-3 mb-4">
        <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">

        <!-- Поиск -->
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text">Поиск</span>
                <input type="text" name="search" class="form-control"
                       placeholder="Имя или фамилия"
                       value="<?php echo s($search); ?>">
            </div>
        </div>

        <!-- Сортировка -->
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

    <!-- Таблица результатов -->
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
                    <th>Прогресс (%)</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $i = 1;
                foreach ($progress_users as $user) {
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo s($user->firstname); ?></td>
                        <td><?php echo s($user->lastname); ?></td>
                        <td><?php echo round($user->progress, 2); ?>%</td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- График -->
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
            plugins: {
                legend: {
                    position: 'top',
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
</script>
</body>
</html>
<?php
echo $OUTPUT->footer();
?>
