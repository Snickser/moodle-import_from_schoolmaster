#!/usr/bin/env php
<?php
// CLI-скрипт: удаляет и добавляет блоки в курсы с completion > 0
define('CLI_SCRIPT', true);

require('/var/www/html/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');

cli_heading('Принудительное добавление блоков в курсы с контролем прохождения');

global $DB;

// Блоки и порядок
$blocks_to_add = [
    'grade_me' => 0,
    'calendar_upcoming' => 1,
    'completion_progress' => 2,
    'studentstracker' => 3,
    'completionstatus' => 4,
    'sharing_cart' => 5,
];

// Получаем список установленных блоков
$pluginman = core_plugin_manager::instance();
$installed_blocks = $pluginman->get_installed_plugins('block');

// Курсы (кроме главной)
$courses = $DB->get_records_select('course', 'id != ? AND visible = 1', [SITEID]);

foreach ($courses as $course) {
    $context = context_course::instance($course->id);

    // Есть ли модули с включённым completion?
    $sql = "SELECT 1 FROM {course_modules} WHERE course = ? AND completion > 0";
    if (!$DB->record_exists_sql($sql, [$course->id])) {
        mtrace("Курс '{$course->fullname}' (ID: {$course->id}) — нет модулей с completion.");
        continue;
    }

    mtrace("Курс '{$course->fullname}' (ID: {$course->id}) — найдено completion.");

    foreach ($blocks_to_add as $blockname => $blockweight) {
        if (!array_key_exists($blockname, $installed_blocks)) {
            mtrace("  ⚠️ Блок '$blockname' не установлен.");
            continue;
        }

        // Удаляем старые блоки
        $instances = $DB->get_records('block_instances', [
            'blockname' => $blockname,
            'parentcontextid' => $context->id
        ]);

        foreach ($instances as $instance) {
            blocks_delete_instance($instance);
            mtrace("  🗑️ Удалён старый блок '$blockname' (ID: {$instance->id}).");
        }

        // Добавляем вручную в block_instances
        $block = new stdClass();
        $block->blockname         = $blockname;
        $block->parentcontextid   = $context->id;
        $block->showinsubcontexts = 0;
        $block->pagetypepattern   = 'course-view-*';
        $block->subpagepattern    = '';
        $block->defaultregion     = 'side-post'; // или 'side-pre'
        $block->defaultweight     = $blockweight;
        $block->configdata        = base64_encode(serialize([]));
        $block->timecreated       = time();
        $block->timemodified      = time();

        $instanceid = $DB->insert_record('block_instances', $block);

        // Добавляем вручную позицию в block_positions
        $position = new stdClass();
        $position->blockinstanceid = $instanceid;
        $position->contextid       = $context->id;
        $position->pagetype        = 'course-view-*';
        $position->subpage         = '';
        $position->visible         = 1;
        $position->region          = 'side-post';
        $position->weight          = $blockweight;

        $DB->insert_record('block_positions', $position);

        mtrace("  ✅ Добавлен блок '$blockname' (ID: $instanceid, вес: $blockweight).");
    }

    // Обновляем кеш курса
    rebuild_course_cache($course->id, true);
}

mtrace("🎉 Готово.");
