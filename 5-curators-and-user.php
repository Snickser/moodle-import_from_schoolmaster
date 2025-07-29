#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/moodle/config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/group/lib.php');

global $DB, $CFG;


# Stage: 1 кураторы

// === Получаем роль ===
$role = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);

if (($handle = fopen('curators.csv', 'r')) !== false) {
    // Чтение заголовков
    $headers = fgetcsv($handle, 0, ',');
    if ($headers === false) {
        die("❌ Не удалось прочитать заголовки.\n");
    }

while (($row = fgetcsv($handle, 0, ',')) !== false) {

$email = $row[0];
$coursename = $row[1];

// === Получаем курс ===
if(!$course = $DB->get_record('course', ['fullname' => $coursename], '*')){
    continue;
}

// === Получаем пользователя ===
if(!$user = $DB->get_record('user', ['email' => $email], '*')){
    continue;
}


// === Получаем метод самозачисления или ручного зачисления ===
$enrol = $DB->get_record('enrol', [
    'enrol' => 'manual',
    'courseid' => $course->id
], '*', MUST_EXIST);

// === Получаем плагин ===
$enrol_manual = enrol_get_plugin('manual');

// === Зачисляем пользователя с нужной ролью ===
//$enrol_manual->enrol_user($enrol, $user->id, $role->id);

// === Готово ===
//echo "✅ Куратор {$user->email} добавлен в курс {$course->id}: {$course->fullname}\n";

}
}


# Stage: 2 группы со студентами

// === Получаем роль ===
$role = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

if (($handle = fopen('groups_users.csv', 'r')) !== false) {
    // Чтение заголовков
    $headers = fgetcsv($handle, 0, ',');
    if ($headers === false) {
        die("❌ Не удалось прочитать заголовки.\n");
    }

 while (($row = fgetcsv($handle, 0, ',')) !== false) {

$coursename = $row[0];
$groupname = $row[2];
$username = $row[5];

// === Получаем курс ===
if(!$course = $DB->get_record('course', ['fullname' => $coursename])){
    continue;
}

// === Получаем пользователя ===
if(!$user = $DB->get_record('user', ['email' => $username])){
    continue;
}

// === Проверка: существует ли группа ===
$group = $DB->get_record('groups', ['courseid' => $course->id, 'name' => $groupname]);
if (!$group) {
    $groupkey = random_string(6);
    
    // === Создание группы ===
    $group = new stdClass();
    $group->courseid = $course->id;
    $group->name = $groupname;
    $group->description = $row[3];
    $group->enrolmentkey = $groupkey;
    $group->visibility = 2;
    $groupid = groups_create_group($group);
    echo "✅ Группа '{$groupname}' создана с паролем: $groupkey\n";
} else {
    // === Обновление пароля, если группа уже существует ===
//    $group->enrolmentkey = $groupkey;
//    $group->visibility = 2;
//    $DB->update_record('groups', $group);
//    $groupid = $group->id;
//    echo " Группа '{$groupname}' уже существует.\n";
}

// === Получаем метод самозачисления или ручного зачисления ===
$enrol = $DB->get_record('enrol', [
    'enrol' => 'self',
    'courseid' => $course->id
], '*', MUST_EXIST);

// === Получаем плагин ===
$enrol_self = enrol_get_plugin('self');

// === Зачисляем пользователя с нужной ролью ===
//$enrol_self->enrol_user($enrol, $user->id, $role->id);

// === Добавляем пользователя в группу ===
if ($user) {
    if (!groups_is_member($group->id, $user->id)) {
//        groups_add_member($group->id, $user->id);
        echo "✅ Пользователь {$username} добавлен в группу '{$group->name}' курса '$coursename'\n";
    } else {
        echo "Пользователь {$username} уже в группе\n";
    }
}

//echo "✅ Студент {$user->email} добавлен в курс '{$course->id}' в группу \n";

}
}





# Stage: 3 все остальные студенты в курсах

// === Получаем роль ===
$role = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

if (($handle = fopen('course_users.csv', 'r')) !== false) {
    // Чтение заголовков
    $headers = fgetcsv($handle, 0, ',');
    if ($headers === false) {
        die("❌ Не удалось прочитать заголовки.\n");
    }

while (($row = fgetcsv($handle, 0, ',')) !== false) {

$email = $row[0];
$coursename = $row[1];

// === Получаем курс ===
if(!$course = $DB->get_record('course', ['fullname' => $coursename], '*')){
    continue;
}

// === Получаем пользователя ===
if(!$user = $DB->get_record('user', ['email' => $email], '*')){
    continue;
}


// === Получаем метод самозачисления или ручного зачисления ===
$enrol = $DB->get_record('enrol', [
    'enrol' => 'self',
    'courseid' => $course->id
], '*', MUST_EXIST);

// === Получаем плагин ===
$enrol_manual = enrol_get_plugin('self');

// === Зачисляем пользователя с нужной ролью ===
$enrol_manual->enrol_user($enrol, $user->id, $role->id);

echo "✅ Студент {$user->email} добавлен в курс '{$course->id}'\n";

}
}