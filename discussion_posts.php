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

// Подготовка данных для графика
$chart_labels = [];
$chart_data = [];
foreach ($discussion_posts as $user) {
    $chart_labels[] = "{$user->firstname} {$user->lastname}";
    $chart_data[] = $user->posts_count;
}

// Вывод страницы
echo $OUTPUT->header();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Сообщения пользователей</title>
    <style>
        .label-stats {
            width: 193px;
            height: 55px;
            background: #EEECEC;
            margin: 0 28px 51px auto;
            text-align: center;
            padding-top: 10px;
            font-size: 20px;
            font-weight: 300;
        }

        .bar {
            width: 1050px;
            height: 137px;
            background: #F5F5F5;
            margin: auto;
            margin-top: 113px;
            padding-top: 52px;
        }
    </style>
</head>
<body>
<section class="bar">
    <p class="label-stats" style="font-weight: 400">Статистика</p>
</section>

<section class="info discussion-info"
         style="border: 10px solid #F5A7A7; padding: 30px; border-radius: 20px; max-width: 1050px; margin-top: 60px; margin-left: 160px;">
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

    <!-- График -->
    <div style="max-width: 800px; margin: auto; padding-top: 20px;">
        <h3>Сообщения пользователей</h3>
        <canvas id="discussionChart"></canvas>
    </div>
</section>

<script>
    const ctx = document.getElementById('discussionChart').getContext('2d');
    const discussionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Количество сообщений',
                data: <?php echo json_encode($chart_data); ?>,
                borderColor: 'rgba(255, 99, 132, 1)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
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
                    title: { display: true, text: 'Пользователи' },
                },
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Сообщения' },
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
