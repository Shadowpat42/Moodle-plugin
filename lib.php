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
