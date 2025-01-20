<?php
require_once('../../config.php');

// Параметры курса
$courseid = required_param('courseid', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$sort   = optional_param('sort', 'time_desc', PARAM_ALPHANUMEXT);

// Проверки Moodle
require_login($courseid);
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/statistics/time_spent.php', ['courseid' => $courseid]));
$PAGE->set_title("Время, проведенное на платформе");
$PAGE->set_heading("Время, проведенное на платформе");

// WHERE для поиска
$where_search = '';
$params = ['courseid' => $courseid];
if (!empty($search)) {
    $where_search = " AND (u.firstname LIKE :s1 OR u.lastname LIKE :s2)";
    $params['s1'] = '%'.$search.'%';
    $params['s2'] = '%'.$search.'%';
}

// ORDER BY
$order_by = '';
switch ($sort) {
    case 'time_asc':
        $order_by = "ORDER BY time_spent ASC";
        break;
    case 'time_desc':
        $order_by = "ORDER BY time_spent DESC";
        break;
    case 'name_asc':
        $order_by = "ORDER BY u.firstname ASC, u.lastname ASC";
        break;
    case 'name_desc':
        $order_by = "ORDER BY u.firstname DESC, u.lastname DESC";
        break;
    default:
        $order_by = "ORDER BY time_spent DESC";
        break;
}

// Алгоритм 10-минутных сессий
$sql = "
    SELECT
        u.id,
        u.firstname,
        u.lastname,
        SUM(
            CASE
                WHEN (log_next.timecreated - log.timecreated) > 0
                     AND (log_next.timecreated - log.timecreated) <= 600
                THEN (log_next.timecreated - log.timecreated)
                ELSE 0
            END
        ) AS time_spent
    FROM {user} u
    JOIN {logstore_standard_log} log
         ON log.userid = u.id
         AND log.courseid = :courseid
    LEFT JOIN {logstore_standard_log} log_next
         ON log_next.userid = log.userid
         AND log_next.courseid = log.courseid
         AND log_next.timecreated = (
             SELECT MIN(li.timecreated)
               FROM {logstore_standard_log} li
              WHERE li.userid = log.userid
                AND li.courseid = log.courseid
                AND li.timecreated > log.timecreated
         )
    WHERE 1=1
    $where_search
    GROUP BY u.id, u.firstname, u.lastname
    $order_by
";

$time_spent_users = $DB->get_records_sql($sql, $params);

// Подготавливаем данные для графика
$chart_labels = [];
$chart_data = [];
foreach ($time_spent_users as $user) {
    $chart_labels[] = "{$user->firstname} {$user->lastname}";
    $chart_data[] = round((int)$user->time_spent / 3600, 2); // Время в часах
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
    <title>Время, проведенное на платформе</title>
</head>
<body>
<section class="bar">
    <p class="label-stats" style="margin-bottom: 20px">Статистика</p>
</section>

<section class="info time-info"
         style="border: 10px solid #EDA3EA; padding: 30px; border-radius: 20px; max-width: 1050px; margin-top: 60px;">
    <h2 style="border-bottom: 5px solid #EDA3EA; padding-bottom: 15px;">
        Время, проведенное на платформе
    </h2>

    <!-- Форма поиска и сортировки -->
    <form method="get" action="time_spent.php" class="row g-3 mb-4">
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
                <option value="time_desc" <?php if ($sort=='time_desc') echo 'selected'; ?>>Время (убывание)</option>
                <option value="time_asc"  <?php if ($sort=='time_asc')  echo 'selected'; ?>>Время (возрастание)</option>
                <option value="name_asc"  <?php if ($sort=='name_asc')  echo 'selected'; ?>>Имя (А-Я)</option>
                <option value="name_desc" <?php if ($sort=='name_desc') echo 'selected'; ?>>Имя (Я-А)</option>
            </select>
        </div>

        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">Применить</button>
        </div>
    </form>

    <!-- Таблица результатов -->
    <div class="data-container">
        <?php if (empty($time_spent_users)): ?>
            <p>Нет данных о времени, проведенном на платформе.</p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Время (часы)</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $i = 1;
                foreach ($time_spent_users as $user) {
                    $hours = round((int)$user->time_spent / 3600, 2);
                    ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo s($user->firstname); ?></td>
                        <td><?php echo s($user->lastname); ?></td>
                        <td><?php echo "{$hours} ч"; ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- График -->
    <h3 style="text-align: center; margin-top: 20px;">Время на платформе (часы)</h3>
    <div style="position: relative; height: 400px; width: 100%; margin: auto;">
        <canvas id="timeSpentChart"></canvas>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const timeSpentCtx = document.getElementById('timeSpentChart').getContext('2d');
    const timeSpentChart = new Chart(timeSpentCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Время на платформе (часы)',
                data: <?php echo json_encode($chart_data); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)', // Красный
                    'rgba(54, 162, 235, 0.6)', // Голубой
                    'rgba(255, 206, 86, 0.6)', // Желтый
                    'rgba(75, 192, 192, 0.6)', // Зеленый
                    'rgba(153, 102, 255, 0.6)', // Фиолетовый
                    'rgba(255, 159, 64, 0.6)'  // Оранжевый
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1.5,
                borderRadius: 8 // Закругленные края столбцов
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false // Скрытие легенды
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    callbacks: {
                        label: function(tooltipItem) {
                            return `${tooltipItem.raw} часов`; // Подсказка с указанием часов
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Пользователи',
                        color: '#333',
                        font: {
                            size: 14,
                            weight: 'bold'
                        }
                    },
                    grid: {
                        color: 'rgba(200, 200, 200, 0.3)', // Лёгкая сетка
                        borderColor: 'rgba(150, 150, 150, 0.5)'
                    },
                    ticks: {
                        color: '#333',
                        autoSkip: false,
                        maxRotation: 45,
                        minRotation: 0
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Часы',
                        color: '#333',
                        font: {
                            size: 14,
                            weight: 'bold'
                        }
                    },
                    grid: {
                        color: 'rgba(200, 200, 200, 0.3)', // Лёгкая сетка
                        borderColor: 'rgba(150, 150, 150, 0.5)'
                    },
                    ticks: {
                        color: '#333'
                    }
                }
            }
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
echo $OUTPUT->footer();
?>
