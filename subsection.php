#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);

require('/var/www/html/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php'); // Для add_moduleinfo

// CLI options
list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'courseid' => null,
        'sectionnum' => null,
        'name' => null,
        'visible' => 1,
    ],
    [
        'h' => 'help',
        'c' => 'courseid',
        's' => 'sectionnum',
        'n' => 'name',
        'v' => 'visible',
    ]
);

if ($options['help'] || empty($options['courseid']) || empty($options['sectionnum']) || empty($options['name'])) {
    $help = "Создание подсекции (mod_subsection) в курсе Moodle

Параметры:
  -c, --courseid     ID курса (обязательный)
  -s, --sectionnum   Номер секции (0, 1, 2...) (обязательный)
  -n, --name         Название подсекции (обязательный)
  -v, --visible      1 = видно, 0 = скрыто (по умолчанию 1)
  -h, --help         Показать помощь

Пример:
  php create_subsection.php -c=5 -s=2 -n='Подраздел 2.1' -v=1
";
    echo $help;
    exit(0);
}

$courseid = (int)$options['courseid'];
$sectionnum = (int)$options['sectionnum'];
$name = $options['name'];
$visible = (int)$options['visible'];

// Получаем курс
$course = get_course($courseid);
if (!$course) {
    cli_error("Курс с ID {$courseid} не найден.");
}

// Получаем modinfo
$modinfo = get_fast_modinfo($course);
$section = $modinfo->get_section_info($sectionnum);

if (!$section) {
    cli_error("Секция номер {$sectionnum} в курсе не найдена.");
}

// Подготовка данных для создания активности mod_subsection
$moduleinfo = new stdClass();
$moduleinfo->modulename = 'subsection';
$moduleinfo->course = $course->id;
$moduleinfo->section = $sectionnum;
$moduleinfo->visible = $visible;
$moduleinfo->name = $name;
$moduleinfo->intro = '';
$moduleinfo->introformat = FORMAT_HTML;
$moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'subsection'], MUST_EXIST);

// Создание подсекции
$moduleinfo = add_moduleinfo($moduleinfo, $course);

cli_writeln("Подсекция '{$name}' успешно создана в секции {$sectionnum} курса ID {$courseid}");
