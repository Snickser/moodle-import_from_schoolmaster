#!/usr/bin/env php
<?php
// Этот скрипт должен выполняться из командной строки.
define('CLI_SCRIPT', true);

require('/var/www/html/moodle/config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/format/lib.php');

global $DB;

echo "Начало обработки курсов...\n";

// Получаем все курсы, кроме сайта
$courses = $DB->get_records_select('course', 'id != 1');

foreach ($courses as $course) {
    if($course->id != $argv[1]){
//        continue;
    }

    echo "Обработка курса: {$course->fullname} (ID: {$course->id})\n";

    $modinfo = get_fast_modinfo($course);
    $sections = $modinfo->get_section_info_all();
    $cms = $modinfo->get_cms(); // все модули курса

    foreach ($sections as $section) {


        if ($section->section == 0) continue; // пропускаем вводный раздел

        $sectionHasRestrictedModules = false;
        $combinedAvailability = [];

        foreach ($cms as $cm) {


            if ($cm->sectionnum != $section->section) {
                continue; // модуль не из этой секции
            }

            if (!empty($cm->availability)) {
                $sectionHasRestrictedModules = true;

                // Декодируем JSON ограничений
                $availability = json_decode($cm->availability, true);
                if ($availability) {
                    $combinedAvailability[] = $availability;
                }
            }
        }


        if ($sectionHasRestrictedModules) {
            echo " - Секция {$section->section} имеет модули с ограничениями доступа.\n";

    	    $sectionAvailability = json_decode($section->availability, true);
	    $sectionHasRealRestriction = !empty($sectionAvailability['c']);

            if (!$sectionHasRealRestriction) {
                // У секции нет ограничений – добавим
                if (count($combinedAvailability) === 1) {
                    $newAvailability = $combinedAvailability[0];
                } else {
                    $newAvailability = [
    'op' => '&',
    'showc' => [true],
    'c' => [
        [
            'type' => 'completion',
            'cm' => '-1',
            'e' => 1,
        ]
    ]
                    ];
                }

                $DB->set_field('course_sections', 'availability', json_encode($newAvailability), ['id' => $section->id]);
                rebuild_course_cache($course->id, true);
                echo "   → Ограничение добавлено на секцию {$section->section}\n";
            } else {
                echo "   → Секция уже имеет ограничение доступа. Пропущено.\n";
            }
        }
    }
}

echo "Обработка завершена.\n";
