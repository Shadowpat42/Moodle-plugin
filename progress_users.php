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
$PAGE->set_url(new moodle_url('/local/statistics/progress_users.php', array('courseid' => $courseid)));

// Устанавливаем заголовок страницы
$PAGE->set_title("Прогресс пользователей");
$PAGE->set_heading("Прогресс пользователей для курса");

// Получаем параметры фильтрации и сортировки
$search = optional_param('search', '', PARAM_TEXT);
$sort = optional_param('sort', 'progress_desc', PARAM_ALPHA);

// Получаем общее количество активностей в курсе (модули с параметром completion > 0)
$total_activities = $DB->get_field_sql("
    SELECT COUNT(cm.id)
    FROM {course_modules} cm
    WHERE cm.course = :courseid AND cm.completion > 0
", ['courseid' => $courseid]);

// Формируем SQL-часть для поиска
$where_search = '';
$params = ['courseid' => $courseid, 'total_activities' => ($total_activities > 0 ? $total_activities : 1)];

if (!empty($search)) {
    $where_search .= " AND (u.firstname LIKE :search OR u.lastname LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

// Определяем порядок сортировки
$order = '';
switch ($sort) {
    case 'name_asc':
        $order = "ORDER BY u.firstname ASC, u.lastname ASC";
        break;
    case 'name_desc':
        $order = "ORDER BY u.firstname DESC, u.lastname DESC";
        break;
    case 'progress_asc':
        $order = "ORDER BY progress ASC";
        break;
    case 'progress_desc':
    default:
        $order = "ORDER BY progress DESC";
        break;
}

// Получаем прогресс пользователей с учётом фильтрации и сортировки
$users_progress = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname, 
           (COUNT(cmc.id) / :total_activities) * 100 AS progress
    FROM {user} u
    JOIN {user_enrolments} ue ON ue.userid = u.id
    JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
    LEFT JOIN {course_modules_completion} cmc ON cmc.userid = u.id
    LEFT JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
    WHERE cm.course = :courseid AND cm.completion > 0
    $where_search
    GROUP BY u.id, u.firstname, u.lastname
    $order
", $params);

// Подготовка данных для графика
$chart_labels = [];
$chart_data = [];

foreach ($users_progress as $user_progress) {
    $chart_labels[] = $user_progress->firstname . ' ' . $user_progress->lastname;
    $chart_data[] = round($user_progress->progress, 2);
}

// Выводим шапку страницы
echo $OUTPUT->header();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Прогресс пользователей</title>
</head>
<body>
<section class="bar">
    <p class="label-stats">Статистика</p>
</section>

<section class="info progress-info container my-4">
    <h2 class="border-bottom pb-2">Прогресс прохождения курса</h2>

    <!-- Форма фильтрации и сортировки -->
    <form method="GET" action="progress_users.php" class="row g-3 mb-4">
        <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">

        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text" id="search-addon">🔍</span>
                <input type="text" class="form-control" placeholder="Поиск по имени" aria-label="Search" aria-describedby="search-addon" name="search" value="<?php echo htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>

        <div class="col-md-4">
            <select class="form-select" aria-label="Sort options" name="sort">
                <option value="progress_desc" <?php if ($sort == 'progress_desc') echo 'selected'; ?>>Прогресс (по убыванию)</option>
                <option value="progress_asc" <?php if ($sort == 'progress_asc') echo 'selected'; ?>>Прогресс (по возрастанию)</option>
                <option value="name_asc" <?php if ($sort == 'name_asc') echo 'selected'; ?>>Имя (А-Я)</option>
                <option value="name_desc" <?php if ($sort == 'name_desc') echo 'selected'; ?>>Имя (Я-А)</option>
            </select>
        </div>

        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">Применить</button>
        </div>
    </form>

    <!-- Таблица прогресса -->
    <div class="table-responsive">
        <?php if (empty($users_progress)) : ?>
            <p>Нет данных о прогрессе пользователей.</p>
        <?php else : ?>
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                <tr>
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Прогресс (%)</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users_progress as $user_progress) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user_progress->firstname, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($user_progress->lastname, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo round($user_progress->progress, 2); ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- График прогресса пользователей -->
    <?php if (!empty($users_progress)) : ?>
        <div class="my-4">
            <canvas id="progressChart"></canvas>
        </div>
    <?php endif; ?>
</section>

<!-- Подключение Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if (!empty($users_progress)) : ?>
    <script>
        const ctx = document.getElementById('progressChart').getContext('2d');
        const progressChart = new Chart(ctx, {
            type: 'bar', // Тип графика: bar, line, pie и т.д.
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Прогресс (%)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Прогресс пользователей по курсу'
                    }
                }
            }
        });
    </script>
<?php endif; ?>
</body>
</html>

<?php
// Выводим подвал страницы
echo $OUTPUT->footer();
?>
