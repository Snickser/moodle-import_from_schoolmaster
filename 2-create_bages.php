<?php
define('CLI_SCRIPT', true);

require_once('/var/www/html/moodle/config.php');
require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/badges/lib.php');

$CFG->debug = DEBUG_ALL;
$CFG->debugdisplay = 1;

// Установка пользователя и сессии
\core\session\manager::init_empty_session();
\core\session\manager::set_user(get_admin());

global $USER, $DB;

$badges = [
    [
        'name' => 'Закінчив курс',
        'description' => 'Успішно пройдено навчання на курсі',
    ],
];

$context = context_system::instance();

foreach ($badges as $b) {

    $badge = new stdClass();
    $badge->name = $b['name'];
    $badge->description = $b['description'];
    $badge->issuername = 'Krishna Academy';
    $badge->issuerurl = 'https://moodle.krishna.ua';
    $badge->issuercontact = '';
    $badge->type = BADGE_TYPE_SITE;
    $badge->status = BADGE_STATUS_INACTIVE;
    $badge->language = 'uk';
    $badge->version = '1.0';
//    $badge->imageauthorname = 'Автор';
//    $badge->imageauthoremail = 'author@example.com';
//    $badge->imageauthorurl = 'https://example.com';
    $badge->imagecaption = '';
    $badge->attachment = 0;
    $badge->timecreated = time();
    $badge->timemodified = time();
    $badge->usercreated = $USER->id;
    $badge->usermodified = $USER->id;

    // 💡 Добавляем обязательное поле message:
    $badge->messagesubject = "Вы получили значок!";
    $badge->message = "Поздравляем! Вы получили значок: {$b['name']}";

    // Вставка значка в базу данных
    $badgeid = $DB->insert_record('badge', $badge);

    // Работа с изображением
    $fs = get_file_storage();
    $file_record = [
        'contextid' => $context->id,
        'component' => 'badges',
        'filearea'  => 'badgeimage',
        'itemid'    => $badgeid,
        'filepath'  => '/',
        'userid'    => $USER->id
    ];

    $fs->delete_area_files($context->id, 'badges', 'badgeimage', $badgeid);

    $file_record['filename'] = 'f1.png';
    $storedfile = $fs->create_file_from_pathname($file_record, 'f1.png');

    $file_record['filename'] = 'f2.png';
    $storedfile = $fs->create_file_from_pathname($file_record, 'f2.png');

    $file_record['filename'] = 'f3.png';
    $storedfile = $fs->create_file_from_pathname($file_record, 'f3.png');

    // Теперь можно создать объект badge (если нужно)
    $badgeobj = new \core_badges\badge($badgeid);

//echo serialize($storedfile);

//$badge2 = badge::create_badge($badge, 1);
//badges_process_badge_image($badge2, 'badge.png');

    echo "✅ Создан значок: {$badgeid} - {$b['name']}\n";
}
