<?php

require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT); // строка поиска
$sort   = optional_param('sort', 'name_asc', PARAM_ALPHANUMEXT); // тип сортировки

// Проверки Moodle
require_login($courseid);
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/statistics/user_statistics.php', ['courseid' => $courseid]));
$PAGE->set_title("Индивидуальная статистика");
$PAGE->set_heading("Статистика пользователей для курса");

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
    case 'name_asc':
        $order_by = "ORDER BY u.firstname ASC, u.lastname ASC";
        break;
    case 'name_desc':
        $order_by = "ORDER BY u.firstname DESC, u.lastname DESC";
        break;
    default:
        $order_by = "ORDER BY u.firstname ASC, u.lastname ASC";
        break;
}

// Новый SQL запрос для получения списка пользователей
$sql = "
    SELECT u.id, u.firstname, u.lastname
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {context} c ON c.id = ra.contextid
    WHERE c.contextlevel = :contextlevel
    AND c.instanceid = :courseid
    $where_search
    $order_by
";

$users = $DB->get_records_sql($sql, ['contextlevel' => CONTEXT_COURSE, 'courseid' => $courseid] + $params);

echo $OUTPUT->header();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <title>Индивидуальная статистика пользователей</title>
</head>
<body>
<section class="bar">
    <p class="label-stats">Статистика</p>
</section>

<section class="info user-list-info"
         style="border: 10px solid #B3E5FC; padding: 30px; border-radius: 20px; max-width: 1050px; margin-top: 60px;">
    <h2 style="border-bottom: 5px solid #B3E5FC; padding-bottom: 15px;">Список пользователей</h2>

    <form method="get" action="user_statistics.php" class="row g-3 mb-4">
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
            </select>
        </div>

        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">Применить</button>
        </div>
    </form>

    <!-- Таблица -->
    <div class="data-container">
        <?php if (empty($users)): ?>
            <p>Нет данных о пользователях.</p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Имя</th>
                    <th>Фамилия</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo s($user->firstname); ?></td>
                        <td><?php echo s($user->lastname); ?></td>
                        <td>
                            <a href="user_details.php?courseid=<?php echo $courseid; ?>&userid=<?php echo $user->id; ?>">Открыть</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

</body>
</html>

<?php
echo $OUTPUT->footer();
?>
