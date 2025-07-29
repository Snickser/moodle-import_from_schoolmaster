#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);
require('/var/www/html/moodle/config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/admin/tool/certificate/lib.php');

use tool_certificate\template;
use tool_certificate\page;
use core\output\template_renderer;

// Установка пользователя и сессии
\core\session\manager::init_empty_session();
\core\session\manager::set_user(get_admin());
force_current_language('uk'); 

$filename = 'data-1753437909638.csv'; // Укажи имя или путь к CSV-файлу

$data = new stdClass();

// Установим контекст (системный или категории)
$context = context_system::instance();

// Проверяем наличие файла
if (!file_exists($filename)) {
    die("Файл '$filename' не найден.\n");
}

if (($handle = fopen($filename, 'r')) !== false) {
    // Чтение заголовков
    $headers = fgetcsv($handle, 0, ',');

    if ($headers === false) {
        die("Не удалось прочитать заголовки.\n");
    }

    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        foreach ($headers as $i => $header) {
            $data->{$header} = $row[$i] ?? null;
        }


$rowid = str_pad($data->id, 3, '0', STR_PAD_LEFT);

if (!$instance = $DB->get_record('tool_certificate_templates', [
    'name' => $rowid.'-'.$data->title])) {
    echo "Шаблон ID: {$data->id} {$data->title} не найден\n";
    continue;
}


$date = strtotime($data->issued_date);
$date = userdate($date, '%d %B %Y');

$template = \tool_certificate\template::instance(154);

$coursid = 20;
$userid = 3;

$object = [
    'courseid' => $coursid,
    'coursefullname' => '_name_',
    'coursecompletiondate' => $date,
    'coursegrade' => $data->final_score,
];


$template->issue_certificate($userid, false, $object, 'mod_coursecertificate', $coursid);


echo "✅ Сертификат создан\n";


die;

    }


}