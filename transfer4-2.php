<?php
// CLI-режим
define('CLI_SCRIPT', true);

require('/var/www/html/moodle/config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/course/modlib.php');
require_once($CFG->dirroot.'/mod/page/lib.php');
require_once($CFG->dirroot . '/mod/assign/lib.php');
require_once($CFG->libdir . '/gradelib.php');

use core_course\external\course_module_create;

$CFG->debug = DEBUG_ALL;
$CFG->debugdisplay = 1;

// Подключение к вашей внешней БД (вместо moodle)
$externalDB = new mysqli('localhost', 'root', '', 'shravanam');
if ($externalDB->connect_error) {
    die('Ошибка подключения к БД: ' . $externalDB->connect_error);
}

// Выполняем запрос
$sql = "
 SELECT s.name sname, b.name bname, l.lesson_id lid, l.name lname, t.name cname, l.status, l.create_date
 FROM dxg_training_lessons l
 join dxg_training t on t.training_id=l.training_id
 left join dxg_training_sections s on s.section_id=l.section_id
 left join dxg_training_blocks b on b.block_id=l.block_id
 where t.status=1
 order by cname, sname, bname, l.sort
";
$result = $externalDB->query($sql);

if (!$result) {
    die("Ошибка запроса: " . $externalDB->error);
}

$addedSections = 0;

while ($row = $result->fetch_assoc()) {

    $coursename = $row['cname'];
    $blockname = $row['bname'];
    $sectionname = $row['sname'];
    $lessonname = $row['lname'];
    $lessonid = $row['lid'];
//    $params = json_decode($row['params']);
//    $lessontype = $row['type'];
//    $createdate = $row['l.create_date'];

// Create section name.
$fullname = $sectionname ?? null;
if($blockname){
    if($fullname){
	$fullname .= ' / ';
    }
    $fullname .= $blockname;
}
if(!$fullname && $lessonname){
    $fullname = $lessonname;
}

    // Ищем курс по названию
    $course = $DB->get_record('course', ['fullname' => $coursename], '*', IGNORE_MULTIPLE);
    if (!$course) {
//        echo "Курс не найден: $coursename\n";
        continue;
    }

// Проверка: существует ли секция с таким именем
$modinfo = get_fast_modinfo($course);
$sections = $modinfo->get_section_info_all();

$sectionexists = false;
foreach ($sections as $existingsection) {
    if ($existingsection->name === $fullname) {
        cli_writeln("Секция с именем '{$fullname}' уже существует (section ID: {$existingsection->id}). Пропускаем создание.");
//        echo serialize($existingsection);
        $sectionexists = true;
        break;;
    }
}
if(!$sectionexists){
    try {
// Создаём новую секцию — просто указываем курс или его ID
$section = course_create_section($course->id);

// Обновляем имя и описание секции
$sectiondata = (object)[
    'id' => $section->section,
    'name' => $fullname,
    'summary' => '',
    'summaryformat' => FORMAT_HTML,
    'visible' => 1,
];

course_update_section($course, $section, $sectiondata);

        echo "Добавлена секция '{$fullname}' в курс '{$coursename}' (ID курса: {$course->id})\n";
        $addedSections++;
    } catch (Exception $e) {
        echo "Ошибка при добавлении секции в курс '{$coursename}': " . $e->getMessage() . "\n";
    }

}

if($lessonid){

$sql="
 select * from dxg_training_lesson_elements
 where lesson_id=$lessonid
 order by sort
";
$lessons = $externalDB->query($sql);

if (!$lessons) {
    die("Ошибка запроса: " . $externalDB->error);
}

// Создаем объект для добавления модуля страницы в курс
$moduleinfo = new stdClass();
$moduleinfo->course = $course->id;
$moduleinfo->modulename = 'page';
$moduleinfo->module = $DB->get_field('modules', 'id', ['name' => $moduleinfo->modulename]);
$moduleinfo->section = $section->section ?? $existingsection->sectionnum;
$moduleinfo->visible = 1;
$moduleinfo->name = $lessonname;
$moduleinfo->displayoptions = [
    'printintro' => 0,            // показывать описание
    'printlastmodified' => 0      // не показывать "последнее изменение"
];
$moduleinfo->completion = COMPLETION_TRACKING_AUTOMATIC; // Автоматическое завершение
$moduleinfo->completionview = 1; // Завершить при просмотре

// Содержимое страницы
$moduleinfo->intro = '';  // обычно пусто для страницы
$moduleinfo->introformat = FORMAT_HTML;
$moduleinfo->contentformat = FORMAT_HTML;

$moduleinfo->content = '';

foreach($lessons as $lsn){
    $params = json_decode($lsn['params']);

//echo serialize($params);

// Контент самой страницы
 switch($lsn['type']){
    case 4:
$linkup = $params->link_up ? "col-12" : "col-md-4";
$moduleinfo->content .= '<div class="'.$linkup.' d-flex">
    <a href="'.$params->link.'" class="btn btn-primary py-4 text-white text-center d-flex flex-column justify-content-center flex-grow-1">
      '.$params->name.'
    </a>
  </div>
';break;
    case 3:
$moduleinfo->content .= '<div class="col-12 flex-column">
      '.$params->text.'
  </div>
';break;
    case 1:
$moduleinfo->content .= '
    <div class="col-12 d-flex">
    <h5>'.$params->name.'</h5>
    </div>
    <div class="col-12 d-flex">
    <a href="'.$params->url.'" class="btn btn-primary py-4 text-white text-center d-flex flex-column justify-content-center flex-grow-1">
      '.$params->name.'
    </a>
  </div>
';break;
 }
}

if($moduleinfo->content){

$moduleinfo->content = '<div style="max-width: 100%; overflow-x: hidden;"><div class="row g-3">'. $moduleinfo->content .
'</div></div><p>';

try {
    $cm = add_moduleinfo($moduleinfo, $course);

// Получаем запись page.
$page = $DB->get_record('page', ['id' => $cm->instance], '*', MUST_EXIST);
// Меняем displayoptions.
$options = unserialize($page->displayoptions) ?: [];
$options['printlastmodified'] = 0;  // Отключаем "Последнее изменение".
$page->displayoptions = serialize($options);
$DB->update_record('page', $page);

    echo "Страница успешно создана. ID: {$cm->instance}\n";
} catch (Exception $e) {
    echo "Ошибка при создании страницы: " . $e->getMessage() . "\n";
}

}


$sql="
 select * from dxg_training_task
 where lesson_id=$lessonid
 limit 1
";
$rs = $externalDB->query($sql);

if (!$rs) {
    die("Ошибка запроса: " . $externalDB->error);
}

$task = $rs->fetch_assoc();

if($task['task_type']>0){

// Создание объекта данных для задания
$moduleinfo = new stdClass();
$moduleinfo->course = $course->id;
$moduleinfo->module = 'assign';
$moduleinfo->modulename = 'assign';
$moduleinfo->section = $section->section ?? $existingsection->sectionnum;
$moduleinfo->visible = 1;
$moduleinfo->name = 'Домашнє завдання - '.$lessonname;
$moduleinfo->introeditor = [
    'text' => $task['text'],
    'format' => FORMAT_HTML,
];
$moduleinfo->introformat = FORMAT_HTML;
$moduleinfo->duedate = 0;
$moduleinfo->allowsubmissionsfromdate = 0;
$moduleinfo->grade = 10;
$moduleinfo->gradepass = 1;
$moduleinfo->assignsubmission_onlinetext_enabled = 1;
$moduleinfo->assignsubmission_file_enabled = 1;

// Обязательные поля assign
$moduleinfo->submissiondrafts = 1;
$moduleinfo->requiresubmissionstatement = 1;
$moduleinfo->sendnotifications = 0;
$moduleinfo->sendlatenotifications = 0;
$moduleinfo->alwaysshowdescription = 1;
$moduleinfo->teamsubmission = 0;
$moduleinfo->requireallteammemberssubmit = 0;
$moduleinfo->blindmarking = 0;
$moduleinfo->attemptreopenmethod = 'none';
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
$moduleinfo->completion = COMPLETION_TRACKING_AUTOMATIC; // Автоматическое отслеживание
$moduleinfo->completionsubmit = 1;  // Требуется отправка работы для выполнения
$moduleinfo->completionpass = 0;        // Требуется проход (0 - не обязательно)

// Создание модуля
$moduleinfo = (array)$moduleinfo;
$moduleinfo['add'] = 'assign';
$moduleinfo['type'] = 'assign';

\core\session\manager::set_user(get_admin());
$moduleinfo = create_module((object)$moduleinfo);
}










}

}

echo "\nИтого добавлено секций: $addedSections\n";

// Закрыть соединение
$externalDB->close();
