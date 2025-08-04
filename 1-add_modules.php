#!/usr/bin/env php
<?php

// Скрипт для добавления модулей в нулевую секцию всех курсов
define('CLI_SCRIPT', true);

require('/var/www/html/moodle/config.php');

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/modinfolib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/completionlib.php');

global $DB, $USER;

// Модули, которые хотим добавить. Формат: [modulename => параметры]
$modules_to_add = [
    'attendance' => [
        'name' => 'Занятия с куратором (посещаемость)',
        'grade' => 0,
    ],
];

// Получаем все курсы
$courses = $DB->get_records('course', ['visible' => 1]);

foreach ($courses as $course) {
if($course->id == 1){
    continue;
}
    echo "Обработка курса: {$course->fullname} (ID: {$course->id})\n";

    $modinfo = get_fast_modinfo($course);
//    $section = $modinfo->get_section_info(0);
    $existing_mods = [];

if (!empty($modinfo->sections[0])) {
    foreach ($modinfo->sections[0] as $cmid) {
        $cm = $modinfo->cms[$cmid];
        $existing_mods[$cm->modname] = true;
    }
}

    foreach ($modules_to_add as $modname => $modparams) {
        if (isset($existing_mods[$modname])) {
            echo " - Модуль '$modname' уже существует, пропускаем.\n";
            continue;
        }

        echo " - Добавляем модуль: $modname\n";

        $module = $DB->get_record('modules', ['name' => $modname], '*', MUST_EXIST);
        $moduleid = $module->id;

        $mod = new stdClass();
        $mod->course = $course->id;
        $mod->module = $moduleid;
        $mod->modulename = $modname;
        $mod->section = 0;
        $mod->visible = 1;
        $mod->visibleoncoursepage = 1;
        $mod->groupmode = 0;
        $mod->groupingid = 0;
        $mod->idnumber = '';
        $mod->added = time();
        $mod->completion = 0;
        $mod->completionview = 0;
        $mod->completionexpected = 0;
        $mod->showdescription = 0;

        foreach ($modparams as $key => $value) {
            $mod->$key = $value;
        }

        $modinfo = add_moduleinfo($mod, $course);

	rebuild_course_cache($course->id);

        echo "   -> Модуль '$modname' добавлен с id={$modinfo->coursemodule}\n";
    }


}

echo "Готово!\n";
