#!/usr/bin/env php
<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
// CLI-режим

define('CLI_SCRIPT', true);

require('/var/www/html/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/page/lib.php');
require_once($CFG->dirroot . '/mod/assign/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/classes/grade_calculator.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/mod/resource/lib.php');
require_once($CFG->libdir . '/accesslib.php');

use core_course\external\course_module_create;
use mod_quiz\quiz_calculator;
use mod_quiz\quiz_settings;

// Установка пользователя и сессии
\core\session\manager::init_empty_session();
\core\session\manager::set_user(get_admin());

$CFG->debug = DEBUG_ALL;
$CFG->debugdisplay = 1;

// Подключение к вашей внешней БД (вместо moodle)
$externaldb = new mysqli('localhost', 'root', '', 'shravanam');
if ($externaldb->connect_error) {
    die('❌ Ошибка подключения к БД: ' . $externaldb->connect_error);
}

if ($argv[1] === 'full') {
    $where = '';
    echo "Start FULL import !!\n";
    sleep(10);
} else if($argv[1]) {
    $where = "and t.name='{$argv[1]}'";
} else {
    die("❌ Нет параметров. Укажиет full or \"course name\"\n");
}

// Выполняем запрос
$sql = "
 SELECT s.name sname, b.name bname, l.lesson_id lid, l.name lname, t.name cname, l.status, l.create_date
 FROM dxg_training_lessons l
 join dxg_training t on t.training_id=l.training_id
 left join dxg_training_sections s on s.section_id=l.section_id
 left join dxg_training_blocks b on b.block_id=l.block_id
 where t.status=1 {$where}
 order by t.sort, l.sort
";
$result = $externaldb->query($sql);

if (!$result) {
    die("❌ Ошибка запроса: " . $externaldb->error);
}

$addedsections = 0;
$onetime = false;

while ($row = $result->fetch_assoc()) {
    $coursename = $row['cname'];
    $blockname = $row['bname'];
    $sectionname = $row['sname'];
    $lessonname = $row['lname'];
    $lessonid = $row['lid'];
    $lessonstatus = $row['status'];
// $params = json_decode($row['params']);
// $lessontype = $row['type'];
// $createdate = $row['l.create_date'];


// Stage1: создаём секцию 

    // Create section name.
    $fullname = $sectionname ?? null;
    if ($blockname) {
        if ($fullname) {
            $fullname .= ' / ';
        }
        $fullname .= $blockname;
    }
    if (!$fullname && $lessonname) {
        $fullname = $lessonname;
    }

    // Ищем курс по названию
    $course = $DB->get_record('course', ['fullname' => $coursename], '*', IGNORE_MULTIPLE);
    if (!$course) {
        // echo "Курс не найден: $coursename\n";
        continue;
    }

    // Проверка: существует ли секция с таким именем
    $modinfo = get_fast_modinfo($course);
    $sections = $modinfo->get_section_info_all();

    $sectionexists = false;
    foreach ($sections as $existingsection) {
        if ($existingsection->name === $fullname) {
//            cli_writeln("Секция с именем '{$fullname}' уже существует (section ID: {$existingsection->id}). Пропускаем создание.");
//  echo serialize($existingsection);
            $sectionexists = $existingsection->sectionnum;
            break;
            ;
        }
    }
    if (!$sectionexists) {
        try {
            // Создаём новую секцию — просто указываем курс или его ID
            $section = course_create_section($course->id);

	    $sectionexists = $section->section;
	    $onetime = true;
            
	    // Обновляем имя и описание секции
            $sectiondata = (object)[
            'id' => $section->section,
            'name' => $fullname,
            'summary' => '',
            'summaryformat' => FORMAT_HTML,
            'visible' => $lessonstatus,
            ];

            course_update_section($course, $section, $sectiondata);

            echo "Добавлена секция '{$fullname}' в курс '{$coursename}' (ID курса: {$course->id})\n";
            $addedsections++;
        } catch (Exception $e) {
            echo "❌ Ошибка при добавлении секции в курс '{$coursename}': " . $e->getMessage() . "\n";
        }
    }

// Stage2: читаем все уроки в курсе

    if ($lessonid) {
        $sql = "select * from dxg_training_lesson_elements where lesson_id=$lessonid order by sort";
        $lessons = $externaldb->query($sql);

        if (!$lessons) {
            die("❌ Ошибка запроса: " . $externaldb->error);
        }

        // Создаем объект для добавления модуля страницы в курс
        $moduleinfo = new stdClass();
        $moduleinfo->course = $course->id;
        $moduleinfo->modulename = 'page';
        $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => $moduleinfo->modulename]);
        $moduleinfo->section = $section->section ?? $existingsection->sectionnum;
        $moduleinfo->visible = $lessonstatus;
        $moduleinfo->name = $lessonname;
        $moduleinfo->displayoptions = [
    	    'printintro' => 0, // показывать описание
    	    'printlastmodified' => 0, // не показывать "последнее изменение"
        ];

if ($sectionexists == 1 && $onetime){
    $onetime = false;
        $moduleinfo->completion = COMPLETION_TRACKING_MANUAL; // Ручное завершение
//        $moduleinfo->completion = COMPLETION_TRACKING_AUTOMATIC; // Автоматическое завершение
//        $moduleinfo->completionview = 1; // Завершить при просмотре
}

        // Содержимое страницы
        $moduleinfo->intro = '';  // обычно пусто для страницы
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->contentformat = FORMAT_HTML;

        $moduleinfo->content = '';

        foreach ($lessons as $lsn) {
            $params = json_decode($lsn['params']);

        // echo serialize($params);
	    $addfile = false;

    	    // Контент самой страницы
            switch ($lsn['type']) {
                case 4:
            	    if ($params->type == 1) {
            		$addfile = true;
            	    } else {
                	$linkup = $params->link_up ? "col-12" : "col-md-4";
        		$moduleinfo->content .= '<div class="' . $linkup . ' d-flex">
    <a href="' . $params->link . '" class="btn btn-primary py-4 text-white text-center d-flex flex-column justify-content-center flex-grow-1">
      ' . $params->name . '
    </a>
  </div>';
		    }
                    break;
                case 3:
                    $moduleinfo->content .= '<div class="col-12 flex-column">
      ' . $params->text . '
  </div>';
                    break;
                case 1:
                    $moduleinfo->content .= '
    <div class="col-12 d-flex">
    <h5>' . $params->name . '</h5>
    </div>
    <div class="col-12 d-flex">
    <a href="' . $params->url . '" class="btn btn-primary py-4 text-white text-center d-flex flex-column justify-content-center flex-grow-1">
      ' . $params->name . '
    </a>
  </div>';
                    break;
            }
        }

        if ($moduleinfo->content) {
            $moduleinfo->content = '<div style="max-width: 100%; overflow-x: hidden;"><div class="row g-3">' . $moduleinfo->content . '</div></div><p>';

            try {
                $cm = add_moduleinfo($moduleinfo, $course);

        	// Получаем запись page.
                $page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);

	        // Меняем displayoptions.
                $options = unserialize($page->displayoptions) ?: [];
                $options['printlastmodified'] = 0;  // Отключаем "Последнее изменение".
                $page->displayoptions = serialize($options);
                $DB->update_record('page', $page);

                echo "Страница '{$lessonname}' успешно создана. ID: {$cm->instance}\n";
	        $addedmodules++;
            } catch (Exception $e) {
                echo "❌ Ошибка при создании страницы: " . $e->getMessage() . "\n";
            }
        }
        
        if ($addfile) {
try {
    $context = context_course::instance($course->id);
    
    // Создаем область для черновика
    $draftitemid = file_get_unused_draft_itemid();
    $fs = get_file_storage();
    
    // Создаем файл в области черновика
    $fileinfo = [
        'contextid' => context_user::instance(get_admin()->id)->id,
        'component' => 'user',
        'filearea' => 'draft',
        'itemid' => $draftitemid,
        'filepath' => '/',
        'filename' => $params->attach,
    ];
    
    $baseurl = trim(file_get_contents('config.txt')); // читаем и убираем пробелы/переводы строки
    $filecontent = download_file_content($baseurl . '/training/lesson/attach/' . $lsn['id']);
    
    if (!$filecontent) {
	echo "❌ Ошибка при скачивании файла {$lsn['id']}\n";
    }
        
    $fs->create_file_from_string($fileinfo, $filecontent);
    
    // Подготовка данных модуля
    $moduleinfo = new stdClass();
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $section->section ?? $existingsection->sectionnum;;
    $moduleinfo->name = $params->title ?? 'Файл - '.$params->attach;
    $moduleinfo->intro = '';
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->visible = $lessonstatus;
    $moduleinfo->display = 0;
    $moduleinfo->showdescription = 0;
    $moduleinfo->showsize = 1;
    $moduleinfo->showtype = 1;
    $moduleinfo->files = $draftitemid; // Используем область черновика
    
    // Добавляем модуль
    $moduleinfo->modulename = 'resource';
    $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'resource']);
    $moduleinfo->instance = 0;
    $moduleinfo->add = 'resource';
    
    $moduleinfo = add_moduleinfo($moduleinfo, $course);
    
    echo "Ресурс '{$moduleinfo->name}' успешно создан. ID: " . $moduleinfo->coursemodule . "\n";
    
    // Дополнительная проверка - убедимся, что файл прикрепился
    $modcontext = context_module::instance($moduleinfo->coursemodule);
    $files = $fs->get_area_files($modcontext->id, 'mod_resource', 'content', 0, 'id', false);
    if (empty($files)) {
        cli_error("Элемент создан, но файлы не прикрепились!");
    } else {
        echo "Файл успешно прикреплен: " . reset($files)->get_filename() . "\n";
    }
    $addedmodules++;
    
} catch (Exception $e) {
    cli_error("❌ Ошибка: " . $e->getMessage());
}            
            
            
        }

// Stage3: создаём домашку (assign)

        $sql = "select * from dxg_training_task where lesson_id=$lessonid limit 1";
        $rs = $externaldb->query($sql);

        if (!$rs) {
            die("❌ Ошибка запроса: " . $externaldb->error);
        }

        $task = $rs->fetch_assoc();

        if ($task['task_type'] == 1 || $task['task_type'] == 2) {
            // Создание объекта данных для задания
            $moduleinfo = new stdClass();
            $moduleinfo->course = $course->id;
            $moduleinfo->module = 'assign';
            $moduleinfo->modulename = 'assign';
            $moduleinfo->section = $section->section ?? $existingsection->sectionnum;
            $moduleinfo->visible = $lessonstatus;
            $moduleinfo->name = 'Домашнє завдання - ' . $lessonname;
            $moduleinfo->introeditor = [
                'text' => $task['text'],
	        'format' => FORMAT_HTML,
	    ];
            $moduleinfo->introformat = FORMAT_HTML;
            $moduleinfo->duedate = 0;
            $moduleinfo->allowsubmissionsfromdate = 0;
            $moduleinfo->assignsubmission_onlinetext_enabled = 1;
            $moduleinfo->assignsubmission_onlinetext_wordlimit = 0; // без лимита
	    $moduleinfo->assignsubmission_onlinetext_wordlimit_enabled = 0;

	    if($task['show_upload_file']) {
        	$moduleinfo->assignsubmission_file_enabled = 1;
		$moduleinfo->assignsubmission_file_maxfiles = 1;
		$moduleinfo->assignsubmission_file_maxfiles_enabled = 1;
	    } else {
        	$moduleinfo->assignsubmission_file_enabled = 0;
	    }

            // Обязательные поля assign
            $moduleinfo->submissiondrafts = 1;
            $moduleinfo->requiresubmissionstatement = 0;
            $moduleinfo->alwaysshowdescription = 1;
            $moduleinfo->teamsubmission = 0;
            $moduleinfo->requireallteammemberssubmit = 0;
            $moduleinfo->blindmarking = 0;
            $moduleinfo->markingworkflow = 0;
            $moduleinfo->markingallocation = 0;
            $moduleinfo->cutoffdate = 0;
            $moduleinfo->gradingduedate = 0;
            $moduleinfo->assignfeedback_comments_enabled = 1;
            $moduleinfo->assignfeedback_editpdf_enabled = 1;
            $moduleinfo->attemptreopenmethod = 'untilpass';
            $moduleinfo->maxattempts = -1;
            $moduleinfo->sendnotifications = 1;
            $moduleinfo->sendlatenotifications = 1;
            $moduleinfo->hidegrader = 1;
	    
            $moduleinfo->groupmode = 0; // 0 — нет, 1 — отдельные группы, 2 — видимые группы
            $moduleinfo->grade = 10;
	    $moduleinfo->gradepass = 10;
	    $moduleinfo->completion = COMPLETION_TRACKING_AUTOMATIC; // Автоматическое отслеживание
//	    $moduleinfo->completion = COMPLETION_TRACKING_MANUAL;
	    
            if($task['check_type'] > 0) {
	        $moduleinfo->completionusegrade = 1; // Учитывать оценку для выполнения
        	$moduleinfo->completionpass = 1;        // Требуется проход (0 - не обязательно)
    	        $moduleinfo->completionpassgrade = 1;   // Проходной балл
    	    } else {
    		$moduleinfo->completionsubmit = 1;  // Требуется отправка работы для выполнения
//		$moduleinfo->completionview = 1; // Завершить при просмотре
    	    }
    	    
    	    try {

                // Создание модуля
	        $moduleinfo = (array)$moduleinfo;
    	        $moduleinfo['add'] = 'assign';
    		$moduleinfo['type'] = 'assign';

        	$moduleinfo = create_module((object)$moduleinfo);
    	    
    	        cli_writeln("Задание '{$lessonname}' успешно создано в курсе с ID {$course->id}.");
	        $addedmodules++;

		if ($task['stop_lesson']) {
// Ограничение доступа
$availability = [
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
// Устанавливаем ограничение
$av = get_coursemodule_from_id(null, $moduleinfo->coursemodule, 0, false, MUST_EXIST);
$av->availability = json_encode($availability);
$DB->update_record('course_modules', $av);
rebuild_course_cache($course->id, true);
}

    	    } catch (Exception $e) {
    	        cli_writeln("❌ Ошибка создания задания '{$lessonname}' в курсе с ID {$course->id}.");
    	    }

        }

// Stage4: создаём тест (quiz)

        $sql = "SELECT * FROM dxg_training_questions where test_id=$lessonid limit 1";
        $qs = $externaldb->query($sql);
        if (!$qs) {
            die("❌ Ошибка запроса: " . $externaldb->error);
        }

        if ($qs->num_rows && $task['task_type'] > 1) {
            $qz = $qs->fetch_assoc();
//            echo serialize($qz) . "\n";

    	    $sql = "SELECT * FROM dxg_training_test where test_id=$lessonid limit 1";
	    $qtest = $externaldb->query($sql);
    	    if (!$qtest) {
        	die("❌ Ошибка запроса: " . $externaldb->error);
    	    }
            $qparm = $qtest->fetch_assoc();

//        echo serialize($qparm) . "\n";

            // === Добавляем модуль в курс ===
            $moduleinfo = new stdClass();
            $moduleinfo->modulename = 'quiz';
            $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);
            $moduleinfo->section = $section->section ?? $existingsection->sectionnum;
            $moduleinfo->visible = $lessonstatus;
            $moduleinfo->visibleoncoursepage = 1;
            $moduleinfo->course = $course->id;
            $moduleinfo->name = 'Тест - ' . $lessonname;
            $moduleinfo->introeditor = [
	        'text' => $task['text'],
    	        'format' => FORMAT_HTML,
        	'itemid' => 0, // можно оставить 0, если не используешь файловый менеджер
            ];
            $moduleinfo->timeopen = 0;
            $moduleinfo->timeclose = 0;
            $moduleinfo->timelimit = 0;
            $moduleinfo->grade = 1;
            $moduleinfo->gradepass = $qparm['finish'];
            $moduleinfo->attempts = $moduleinfo->attempts = $qparm['test_try'] > 10 ? 0 : $qparm['test_try'];
            $moduleinfo->completion = 1;

            $moduleinfo->overduehandling = 'autoabandon';
            $moduleinfo->grademethod = 1;
            $moduleinfo->sumgrades = 1; // будет пересчитано
            $moduleinfo->preferredbehaviour = 'deferredfeedback';
	    $moduleinfo->shuffleanswers = $qparm['is_random_questions'];
            $moduleinfo->questionsperpage = 1;
            $moduleinfo->quizpassword = '';
            $moduleinfo->browsersecurity = '-';
            $moduleinfo->attemptonlast = 1;
            $moduleinfo->showuserpicture = 1;
	    
            $moduleinfo->completion = COMPLETION_TRACKING_AUTOMATIC; // Автоматическое отслеживание
            if ($qparm['finish']) {
        	$moduleinfo->completionpassgrade = 1;
    	    }
            $moduleinfo->completionpass = 1;
    	    $moduleinfo->completionusegrade = 1; // Учитывать оценку для выполнения

            $moduleinfo->groupmode = 0; // 0 — нет, 1 — отдельные группы, 2 — видимые группы

            try {
                // === Добавляем с помощью course module API ===
	        $module = create_module($moduleinfo);

                // Шаг 2. Обновление настроек просмотра вручную
	        $quizid = $module->instance;
    		$updatequiz = new stdClass();
    	        $updatequiz->id = $quizid;
        	$updatequiz->reviewattempt = 69888;
        	$updatequiz->reviewmaxmarks = 69888;
        	$updatequiz->reviewmarks = 4352;
        	$updatequiz->reviewspecificfeedback = 4352;
        	$updatequiz->reviewgeneralfeedback = 4352;
        	$updatequiz->reviewoverallfeedback = 4352;
        	$DB->update_record('quiz', $updatequiz);
        	
        	// Перемешать вопросы.
        	if ($qparm['is_random_questions']) {
            	    $updatequiz = new stdClass();
        	    $updatequiz->id = $quizid;
        	    $updatequiz->shufflequestions = 1; 
        	    $DB->update_record('quiz_sections', $updatequiz);
        	}
        	
        	// Шаг 3. Создание корневой категории
		$cm = get_coursemodule_from_instance('quiz', $quizid, $course->id, false, MUST_EXIST);
		$context = context_module::instance($cm->id); // ✅ контекст модуля
		question_get_default_category($context->id, true);
        	question_get_top_category($context->id, true);

		if ($task['stop_lesson']) {
// Ограничение доступа
$availability = [
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
// Устанавливаем ограничение
$cm->availability = json_encode($availability);
$DB->update_record('course_modules', $cm);
rebuild_course_cache($course->id, true);
}

	        cli_writeln("Тест '{$lessonname}' ID {$quizid} успешно создан в курсе ID {$course->id}.");
	        $addedmodules++;
            } catch (Exception $e) {
	        cli_writeln("❌ Ошибка создания теста '{$lessonname}' в курсе ID {$course->id}.");
            }

// Stage5: выгружаем вопросы теста в xml

	    cli_writeln("Старт выгрузки вопросов ID {$lessonid}");
	    $output = shell_exec("/usr/bin/php test_xml_export.php {$lessonid}");
	    echo $output;
	    
// Stage6: импортируем вопросы теста из xml
	    
	    cli_writeln("Старт импорта вопросов ID {$module->instance}");
	    $output = shell_exec("/usr/bin/php test_xml_import.php {$module->instance}");
	    echo $output;
	    
//$quizzes = $DB->get_records('quiz');
//foreach ($quizzes as $quiz) {
//$quizid = $quiz->id;
	    
	    // обновить grade и sumgrades
            $quizobj = quiz_settings::create($quizid);
	    $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();
            $updatequiz = new stdClass();
            $updatequiz->id = $quizid;
            $updatequiz->grade = $DB->get_field('quiz', 'sumgrades', ['id' => $quizid]);
	    $DB->update_record('quiz', $updatequiz);

//}
    

        }
    }
}

echo "\nИтого добавлено секций: $addedsections модулей: $addedmodules\n";

// Закрыть соединение
$externaldb->close();
