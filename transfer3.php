#!/usr/bin/env php
<?php
// Настройки подключения к внешней базе с курсами
$sourceDbHost = 'localhost';
$sourceDbName = 'shravanam';
$sourceDbUser = 'root';
$sourceDbPass = '';

// Настройки подключения к базе Moodle
$moodleDbHost = 'localhost';
$moodleDbName = 'moodle';
$moodleDbUser = 'root';
$moodleDbPass = '';

try {
    // Подключение к внешней базе
    $sourcePdo = new PDO("mysql:host=$sourceDbHost;dbname=$sourceDbName;charset=utf8", $sourceDbUser, $sourceDbPass);
    $sourcePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Подключение к базе Moodle
    $moodlePdo = new PDO("mysql:host=$moodleDbHost;dbname=$moodleDbName;charset=utf8", $moodleDbUser, $moodleDbPass);
    $moodlePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получаем из внешней базы список курсов с их параметрами
    $stmt = $sourcePdo->query("SELECT name, access_type FROM dxg_training"); // замените на реальное имя таблицы и полей

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $courseName = $row['name'];
        $accessType = $row['access_type'];

	$password = null;
        if ($accessType > 0){
	    $accessType = 0;
	    $password = '12345';
	}

        // Находим курс в Moodle по имени fullname
        $courseStmt = $moodlePdo->prepare("SELECT id FROM mdl_course WHERE fullname = :fullname");
        $courseStmt->execute([':fullname' => $courseName]);
        $course = $courseStmt->fetch(PDO::FETCH_ASSOC);

        if ($course) {
            $courseId = $course['id'];

            // Проверяем, есть ли уже enrol_self для этого курса
            $enrolStmt = $moodlePdo->prepare("SELECT id FROM mdl_enrol WHERE courseid = :courseid AND enrol = 'self'");
            $enrolStmt->execute([':courseid' => $courseId]);
            $enrol = $enrolStmt->fetch(PDO::FETCH_ASSOC);


            if ($enrol) {
                // Если есть — обновляем, включаем (status = 0)
                $updateStmt = $moodlePdo->prepare("UPDATE mdl_enrol SET status = :accessType, password = :password WHERE id = :id");
                $updateStmt->execute([':id' => $enrol['id'], ':accessType' => $accessType, ':password' => $password]);
                echo "Саморегистрация $accessType для курса '$courseName' (обновлено).\n";
            } else {
                // Если нет — вставляем новую запись с enrol = 'self'
                $insertStmt = $moodlePdo->prepare("
                    INSERT INTO mdl_enrol (enrol, status, courseid, sortorder)
                    VALUES ('self', :accessType, :courseid, 1)
                ");
                $insertStmt->execute([':courseid' => $courseId, ':accessType' => $accessType]);
                echo "Саморегистрация включена для курса '$courseName' (создано).\n";
            }
        } else {
            echo "Курс '$courseName' не найден в базе Moodle.\n";
        }
    }

} catch (PDOException $e) {
    die("Ошибка подключения или запроса: " . $e->getMessage());
}
