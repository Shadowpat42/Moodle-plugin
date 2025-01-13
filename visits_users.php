<?php
require_once('../../config.php');

// Параметры курса
$courseid = required_param('courseid', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT); // строка поиска
$sort   = optional_param('sort', 'visits_desc', PARAM_ALPHANUMEXT); // тип сортировки

// Проверки Moodle
require_login($courseid);
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/statistics/visits_users.php', ['courseid' => $courseid]));
$PAGE->set_title("Посещения пользователей");
$PAGE->set_heading("Посещения пользователей для курса");

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
    case 'visits_asc':
        $order_by = "ORDER BY visits ASC";
        break;
    case 'visits_desc':
        $order_by = "ORDER BY visits DESC";
        break;
    case 'name_asc':
        $order_by = "ORDER BY u.firstname ASC, u.lastname ASC";
        break;
    case 'name_desc':
        $order_by = "ORDER BY u.firstname DESC, u.lastname DESC";
        break;
    default:
        $order_by = "ORDER BY visits DESC";
        break;
}

// SQL запрос
$sql = "
    SELECT u.id, u.firstname, u.lastname, COUNT(log.id) AS visits
    FROM {user} u
    JOIN {logstore_standard_log} log ON log.userid = u.id
    WHERE log.courseid = :courseid AND log.action = 'viewed'
    $where_search
    GROUP BY u.id, u.firstname, u.lastname
    $order_by
";

$visits_users = $DB->get_records_sql($sql, $params);

// Вывод страницы
echo $OUTPUT->header();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <title>Посещения пользователей</title>
</head>
<body>
<section class="bar">
    <p class="label-stats">Статистика</p><br><br>
</section>

<section class="info visitors-info"
         style="border: 10px solid #EEED83; padding: 30px; border-radius: 20px; max-width: 1050px; margin: auto;">
    <h2 style="border-bottom: 5px solid #EEED83; padding-bottom: 15px;">Посещения пользователей</h2>

    <!-- Форма поиска и сортировки -->
    <form method="get" action="visits_users.php" class="row g-3 mb-4">
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
                <option value="visits_desc" <?php echo ($sort == 'visits_desc') ? 'selected' : ''; ?>>Посещения (убывание)</option>
                <option value="visits_asc" <?php echo ($sort == 'visits_asc') ? 'selected' : ''; ?>>Посещения (возрастание)</option>
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
        <?php if (empty($visits_users)): ?>
            <p>Нет данных о посещениях пользователей.</p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Количество посещений</th>
                </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($visits_users as $user): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo s($user->firstname); ?></td>
                        <td><?php echo s($user->lastname); ?></td>
                        <td><?php echo $user->visits; ?></td>
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
