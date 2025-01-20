<?php
defined('MOODLE_INTERNAL') || die();

function local_statistics_extend_navigation_course($parentnode, $course, $context) {
    if (has_capability('local/statistics:view', $context)) {
        $url = new moodle_url('/local/statistics/index.php', ['courseid' => $course->id]);
        $parentnode->add(
            'Статистика курса', // Название ссылки
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'local_statistics'
        );
    }
}

function local_statistics_upgrade($oldversion) {
    global $DB;

    // Проверка версии плагина
    if ($oldversion < 2025012000) { // Укажите новую версию плагина
        // Получаем контекст системы
        $context = context_system::instance();

        // Разрешение для роли "teacher"
        $role = $DB->get_record('role', ['shortname' => 'teacher']);
        if ($role) {
            assign_capability('local/statistics:view', CAP_ALLOW, $role->id, $context->id);
        }

        // Разрешение для роли "editingteacher"
        $role = $DB->get_record('role', ['shortname' => 'editingteacher']);
        if ($role) {
            assign_capability('local/statistics:view', CAP_ALLOW, $role->id, $context->id);
        }

        // Установить точку сохранения версии плагина
        upgrade_plugin_savepoint(true, 2023110436, 'local', 'statistics');
    }

    return true;
}

function local_statistics_install() {
    global $DB;

    // Получаем контекст системы (если права должны быть на уровне системы)
    $context = context_system::instance();

    // Получаем роль "teacher" (учитель)
    $role = $DB->get_record('role', ['shortname' => 'teacher']);
    if ($role) {
        // Назначаем разрешение для роли "teacher"
        assign_capability('local/statistics:view', CAP_ALLOW, $role->id, $context->id);
    }

    // Получаем роль "editingteacher" (редактирующий учитель)
    $role = $DB->get_record('role', ['shortname' => 'editingteacher']);
    if ($role) {
        // Назначаем разрешение для роли "editingteacher"
        assign_capability('local/statistics:view', CAP_ALLOW, $role->id, $context->id);
    }
}


