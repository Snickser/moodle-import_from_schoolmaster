#!/usr/bin/env php
<?php
// CLI-режим
define('CLI_SCRIPT', true);

require_once('/var/www/html/moodle/config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/gdlib.php');
require_once($CFG->dirroot.'/course/lib.php');

// Подключение к внешней базе (или можно использовать $DB, если это таблица Moodle)
$extdb = new mysqli('localhost', 'root', '', 'shravanam');
if ($extdb->connect_error) {
    cli_error("Ошибка подключения к БД: " . $extdb->connect_error);
}

// Запрос к таблице
$sql = "SELECT name coursename,cover FROM dxg_training";
$result = $extdb->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $shortname = trim($row['coursename']);
        $baseurl = trim(file_get_contents('config.txt')); // читаем и убираем пробелы/переводы строки
        $url = $baseurl . '/images/training/' . rawurlencode(trim($row['cover']));

        // Получаем курс по shortname
        $course = $DB->get_record('course', ['fullname' => $shortname]);
        if (!$course) {
            mtrace("❌ Курс с именем '$shortname' не найден");
            continue;
        }

        // Скачиваем изображение
        $image = download_file_content($url);
        if ($image === false) {
            mtrace("❌ Не удалось скачать изображение: $url");
            continue;
        }

        $context = context_course::instance($course->id);
        $fs = get_file_storage();

        // Удаляем старое изображение
        $fs->delete_area_files($context->id, 'course', 'overviewfiles');

        // Создаём новый файл
        $filename = basename(parse_url(urldecode($url), PHP_URL_PATH));
        $fileinfo = [
            'contextid' => $context->id,
            'component' => 'course',
            'filearea'  => 'overviewfiles',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        ];
        $fs->create_file_from_string($fileinfo, $image);

        // Очищаем кеш миниатюры
	$course->timemodified = time();
	$DB->update_record('course', $course);

        mtrace("✅ Установлено изображение для курса '$shortname'");
    }
} else {
    mtrace("Нет записей");
}

$extdb->close();
