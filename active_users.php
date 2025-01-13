<?php
require_once('../../config.php');

// Параметры курса
$courseid = required_param('courseid', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT); // строка поиска
$sort   = optional_param('sort', 'name_asc', PARAM_ALPHANUMEXT); // тип сортировки

// Проверки Moodle
require_login($courseid);
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/statistics/active_users.php', ['courseid' => $courseid]));
$PAGE->set_title("Активные пользователи");
$PAGE->set_heading("Активные пользователи для курса");

// WHERE и параметры
$where_search = '';
$params = ['courseid' => $courseid, 'time_threshold' => time() - 300];
if (!empty($search)) {
    $where_search = " AND (u.firstname LIKE :s1 OR u.lastname LIKE :s2)";
    $params['s1'] = '%' . $search . '%';
    $params['s2'] = '%' . $search . '%';
}

// ORDER BY
$order_by = '';
switch ($sort) {
    case 'name_asc':
        $order_by = "ORDER BY u.firstname ASC, u.lastname ASC";
        break;
    case 'name_desc':
        $order_by = "ORDER BY u.firstname DESC, u.lastname DESC";
        break;
    case 'time_asc':
        $order_by = "ORDER BY log.timecreated ASC";
        break;
    case 'time_desc':
        $order_by = "ORDER BY log.timecreated DESC";
        break;
    default:
        $order_by = "ORDER BY u.firstname ASC, u.lastname ASC";
        break;
}

// SQL запрос
$sql = "
    SELECT u.id, u.firstname, u.lastname, MAX(log.timecreated) AS last_active
    FROM {user} u
    JOIN {logstore_standard_log} log ON log.userid = u.id
    WHERE log.courseid = :courseid AND log.timecreated > :time_threshold
    $where_search
    GROUP BY u.id, u.firstname, u.lastname
    $order_by
";

$active_users = $DB->get_records_sql($sql, $params);

// Данные для графика
$time_intervals = [];
$active_counts = [];
$now = time();
for ($i = 10; $i >= 0; $i--) {
    $start_time = $now - ($i * 1800); // Каждые 30 минут
    $end_time = $now - (($i - 1) * 1800);

    $interval_count = $DB->count_records_sql("
        SELECT COUNT(DISTINCT log.userid)
        FROM {logstore_standard_log} log
        WHERE log.courseid = :courseid
          AND log.timecreated BETWEEN :start_time AND :end_time
    ", [
        'courseid' => $courseid,
        'start_time' => $start_time,
        'end_time' => $end_time,
    ]);

    $time_intervals[] = date('H:i', $start_time);
    $active_counts[] = $interval_count;
}

echo $OUTPUT->header();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Активные пользователи</title>
</head>
<body>
<section class="bar">
    <p class="label-stats">Статистика</p>
</section>

<section class="info progress-info"
         style="border: 10px solid #B9FAFA; padding: 30px; border-radius: 20px; max-width: 1050px; margin: auto;">
    <h2 style="border-bottom: 5px solid #B9FAFA; padding-bottom: 15px;">Активные пользователи</h2>

    <form method="get" action="active_users.php" class="row g-3 mb-4">
        <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">

        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text">Поиск</span>
                <input type="text" name="search" class="form-control" placeholder="Имя или фамилия"
                       value="<?php echo s($search); ?>">
            </div>
        </div>

        <div class="col-md-4">
            <select name="sort" class="form-select">
                <option value="name_asc" <?php echo ($sort == 'name_asc') ? 'selected' : ''; ?>>Имя (А-Я)</option>
                <option value="name_desc" <?php echo ($sort == 'name_desc') ? 'selected' : ''; ?>>Имя (Я-А)</option>
                <option value="time_asc" <?php echo ($sort == 'time_asc') ? 'selected' : ''; ?>>Активность (по возрастанию)</option>
                <option value="time_desc" <?php echo ($sort == 'time_desc') ? 'selected' : ''; ?>>Активность (по убыванию)</option>
            </select>
        </div>

        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">Применить</button>
        </div>
    </form>

    <!-- Таблица -->
    <div class="data-container">
        <?php if (empty($active_users)): ?>
            <p>Нет данных об активных пользователях.</p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Последняя активность</th>
                </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($active_users as $user): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo s($user->firstname); ?></td>
                        <td><?php echo s($user->lastname); ?></td>
                        <td><?php echo date('d.m.Y H:i:s', $user->last_active); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- График -->
    <div style="max-width: 800px; margin: auto; padding-top: 20px;">
        <h3>Активность пользователей за последние 5 часов</h3>
        <canvas id="activeUsersChart"></canvas>
    </div>
</section>

<script>
    const timeLabels = <?php echo json_encode($time_intervals); ?>;
    const activeCounts = <?php echo json_encode($active_counts); ?>;

    const ctx = document.getElementById('activeUsersChart').getContext('2d');
    const activeUsersChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: timeLabels,
            datasets: [{
                label: 'Активные пользователи',
                data: activeCounts,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderWidth: 2,
                tension: 0.4,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: true, position: 'top' },
            },
            scales: {
                x: {
                    title: { display: true, text: 'Время (по интервалам)' },
                },
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Количество пользователей' },
                },
            },
        },
    });
</script>
</body>
</html>

<?php
echo $OUTPUT->footer();
?>

