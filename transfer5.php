#!/usr/bin/env php
<?php

define('CLI_SCRIPT', true);

require_once('/var/www/html/moodle/config.php');
require_once($CFG->libdir.'/completionlib.php');

global $DB;

// Целевые типы активностей
$targetmodnames = ['quiz', 'assign', 'coursecertificate'];

// Получаем ID этих модулей
$modinfo = $DB->get_records_select('modules', "name IN ('" . implode("','", $targetmodnames) . "')");
if (empty($modinfo)) {
    die("❌ Не найдены типы модулей quiz, assign, tool_certificate.\n");
}
$moduleids = array_map(fn($m) => $m->id, $modinfo);

// Получаем все курсы (без системного courseid=1)
$courses = $DB->get_records_select('course', 'id <> 1');

foreach ($courses as $course) {
    if($course->id != $argv[1]){
//	continue;
    }

    echo "\n🔹 Обработка курса: {$course->id} - {$course->fullname}\n";

    // Включаем отслеживание завершения курса
    $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);

    $placeholders = implode(',', array_fill(0, count($moduleids), '?'));
    $params = array_merge([$course->id], $moduleids);

    // Получаем активности нужных типов с включённым completion
    $modules = $DB->get_records_sql("
        SELECT cm.id, m.name AS modname
        FROM {course_modules} cm
        JOIN {modules} m ON cm.module = m.id
        WHERE cm.course = ?
          AND cm.completion > 0
          AND cm.module IN ($placeholders)
    ", $params);

    if (empty($modules)) {
        echo "❗ Нет подходящих активностей с включённым завершением в курсе {$course->id}\n";
        continue;
    }

    $added = 0;
    $skipped = 0;

    foreach ($modules as $mod) {
        $exists = $DB->record_exists('course_completion_criteria', [
            'course' => $course->id,
            'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
            'moduleinstance' => $mod->id
        ]);

        if ($exists) {
            echo "⏭️ Критерий уже есть по '{$mod->modname}' (cmid: {$mod->id}), пропускаем\n";
            $skipped++;
            continue;
        }

        $criteria = (object)[
            'course' => $course->id,
            'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
            'moduleinstance' => $mod->id,
            'module' => $mod->modname,
        ];
        $DB->insert_record('course_completion_criteria', $criteria);
        echo "✅ Добавлен критерий по '{$mod->modname}' (cmid: {$mod->id})\n";
        $added++;
    }

    echo "🎯 Курс {$course->id}: добавлено $added, пропущено $skipped критериев.\n";
}

echo "\n🏁 Обработка всех курсов завершена.\n";
