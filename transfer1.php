#!/usr/bin/env php
<?php
// Настройки подключения к БД
$host = 'localhost';
$dbname = 'shravanam';
$user = 'root';
$pass = '';

// Подключение к базе данных с использованием PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Получаем данные из source_table
    $stmt = $pdo->query("SELECT name,short_desc,start_date,end_date FROM dxg_training");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sourceId = $row['name'];
        $decodedContent = html_entity_decode($row['short_desc'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sdate = $row['start_date'];
        $edate = $row['end_date'];

        // 2. Обновляем соответствующую запись в target_table
        $updateStmt = $pdo->prepare("
            UPDATE moodle.mdl_course 
            SET summaryformat=1, summary = :content, startdate = :sdate, enddate = :edate 
            WHERE fullname = :id
        ");
        $updateStmt->execute([
            ':content' => $decodedContent,
            ':sdate' => $sdate,
            ':edate' => $edate,
            ':id' => $sourceId
        ]);
    }

    echo "Обновление завершено успешно.";

} catch (PDOException $e) {
    echo "Ошибка подключения или выполнения запроса: " . $e->getMessage();
}
