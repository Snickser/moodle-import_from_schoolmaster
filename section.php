#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);

require('/var/www/html/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');

// CLI options
list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'courseid' => null,
        'name' => null,
        'summary' => '',
        'visible' => 1,
    ],
    [
        'h' => 'help',
        'c' => 'courseid',
        'n' => 'name',
        's' => 'summary',
        'v' => 'visible',
    ]
);

if ($options['help'] || empty($options['courseid']) || empty($options['name'])) {
    $help = "Создание секции в курсе Moodle 5.0

Параметры:
  -c, --courseid   ID курса (обязательный)
  -n, --name       Название секции (обязательный)
  -s, --summary    Описание секции
  -v, --visible    1 (видно) или 0 (скрыто)
  -h, --help       Показать помощь

Пример:
  php create_section.php -c=2 -n='Тема 2' -s='Описание темы 2' -v=1
";
    echo $help;
    exit(0);
}

$courseid = (int)$options['courseid'];
$sectionname = $options['name'];
$summary = $options['summary'];
$visible = (int)$options['visible'];

// Получаем курс
$course = get_course($courseid);
if (!$course) {
    cli_error("Курс с ID $courseid не найден.");
}

// Создаём новую секцию — просто указываем курс или его ID
$section = course_create_section($courseid);

// Обновляем имя и описание секции
$sectiondata = (object)[
    'id' => $section,
    'name' => $sectionname,
    'summary' => $summary,
    'summaryformat' => FORMAT_HTML,
    'visible' => $visible,
];

course_update_section($course, $section, $sectiondata);

cli_writeln("Секция успешно создана (ID: $section->id) в курсе ID: $courseid");
