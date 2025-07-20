#!/usr/bin/env php
<?php

// === Проверка параметра test_id ===
if (!isset($argv[1]) || !is_numeric($argv[1])) {
    exit("❌ Укажите test_id первым параметром. Пример:\nphp export.php 2311\n");
}
$testId = (int)$argv[1];

// === Параметры подключения ===
$host = 'localhost';
$dbname = 'shravanam';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Подключение успешно\n";
} catch (PDOException $e) {
    die("❌ Ошибка подключения: " . $e->getMessage());
}

function clean($str) {
    $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
    return trim($str);
}

$sql = "
    SELECT q.quest_id, q.test_id, q.question, q.question_type, q.check_mode, q.help, 
           q.true_answer, q.require_all_true, q.status, q.sort AS qsort, q.image, q.points AS qpoints,
           o.option_id, o.quest_id AS o_quest_id, o.title, o.value, o.sort AS osort, o.valid, o.points AS opoints, o.cover
    FROM shravanam.dxg_training_questions q
    LEFT JOIN shravanam.dxg_training_test_options o ON q.quest_id = o.quest_id
    WHERE q.test_id = :test_id
    ORDER BY q.sort, o.sort
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':test_id' => $testId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$questions = [];
foreach ($rows as $row) {
    $qid = $row['quest_id'];
    if (!isset($questions[$qid])) {
        $questions[$qid] = [
            'quest_id' => $qid,
            'test_id' => $row['test_id'],
            'question' => $row['question'],
            'question_type' => (int)$row['question_type'],
            'check_mode' => $row['check_mode'],
            'help' => $row['help'],
            'true_answer' => $row['true_answer'],
            'require_all_true' => $row['require_all_true'],
            'status' => $row['status'],
            'sort' => $row['qsort'],
            'image' => $row['image'],
            'points' => $row['qpoints'],
            'options' => []
        ];
    }
    if ($row['option_id']) {
        $questions[$qid]['options'][] = [
            'option_id' => $row['option_id'],
            'title' => $row['title'],
            'value' => $row['value'],
            'sort' => $row['osort'],
            'valid' => $row['valid'],
            'points' => $row['opoints'],
            'cover' => $row['cover']
        ];
    }
}

$doc = new DOMDocument('1.0', 'UTF-8');
$doc->formatOutput = true;

$quiz = $doc->createElement('quiz');
$doc->appendChild($quiz);

$addedCount = 0;

foreach ($questions as $q) {
    $type = $q['question_type'];

    // Проверяем количество вариантов для multichoice и ordering
    if (($type === 1 || $type === 3) && count($q['options']) < 2) {
        echo "⚠️ Вопрос {$q['quest_id']} пропущен — меньше 2 вариантов\n";
        continue;
    }

    // Создаём узел вопроса
    $questionNode = $doc->createElement('question');

    switch ($type) {
        case 1: // multichoice
            $questionNode->setAttribute('type', 'multichoice');

            $name = $doc->createElement('name');
            $nameText = $doc->createElement('text');
            $nameText->appendChild($doc->createCDATASection("Вопрос {$q['quest_id']}"));
            $name->appendChild($nameText);
            $questionNode->appendChild($name);

            $qtext = $doc->createElement('questiontext');
            $qtext->setAttribute('format', 'html');
            $qtextText = $doc->createElement('text');
            $qtextText->appendChild($doc->createCDATASection(clean($q['question'])));
            $qtext->appendChild($qtextText);
            $questionNode->appendChild($qtext);

            $gf = $doc->createElement('generalfeedback');
            $gf->setAttribute('format', 'html');
            $gfText = $doc->createElement('text');
            $gfText->appendChild($doc->createCDATASection(clean($q['help'])));
            $gf->appendChild($gfText);
            $questionNode->appendChild($gf);

            $questionNode->appendChild($doc->createElement('defaultgrade', 1));
            $questionNode->appendChild($doc->createElement('penalty', '0'));

            $correctCount = 0;
            foreach ($q['options'] as $opt) {
                if ((int)$opt['valid'] === 1) $correctCount++;
            }
            $questionNode->appendChild($doc->createElement('single', $correctCount === 1 ? 'true' : 'false'));
            $questionNode->appendChild($doc->createElement('shuffleanswers', 'true'));
            $questionNode->appendChild($doc->createElement('answernumbering', 'abc'));

            foreach ($q['options'] as $opt) {
                $answer = $doc->createElement('answer');
                $fraction = ((int)$opt['valid'] === 1) ? 100 : 0;
                $answer->setAttribute('fraction', (string)$fraction);
                $answer->setAttribute('format', 'html');

                $aText = $doc->createElement('text');
                $aText->appendChild($doc->createCDATASection(clean($opt['title'])));
                $answer->appendChild($aText);

                $fb = $doc->createElement('feedback');
                $fb->setAttribute('format', 'html');
                $fbText = $doc->createElement('text');
                $fbText->appendChild($doc->createCDATASection(''));
                $fb->appendChild($fbText);
                $answer->appendChild($fb);

                $questionNode->appendChild($answer);
            }
            break;

        case 2: // shortanswer
            $questionNode->setAttribute('type', 'shortanswer');

            $name = $doc->createElement('name');
            $nameText = $doc->createElement('text');
            $nameText->appendChild($doc->createCDATASection("Вопрос {$q['quest_id']}"));
            $name->appendChild($nameText);
            $questionNode->appendChild($name);

            $qtext = $doc->createElement('questiontext');
            $qtext->setAttribute('format', 'html');
            $qtextText = $doc->createElement('text');
            $qtextText->appendChild($doc->createCDATASection(clean($q['question'])));
            $qtext->appendChild($qtextText);
            $questionNode->appendChild($qtext);

            $gf = $doc->createElement('generalfeedback');
            $gf->setAttribute('format', 'html');
            $gfText = $doc->createElement('text');
            $gfText->appendChild($doc->createCDATASection(clean($q['help'])));
            $gf->appendChild($gfText);
            $questionNode->appendChild($gf);

            $questionNode->appendChild($doc->createElement('defaultgrade', 1));
            $questionNode->appendChild($doc->createElement('penalty', '0'));
            $questionNode->appendChild($doc->createElement('usecase', 0));

            foreach ($q['options'] as $opt) {
                $answer = $doc->createElement('answer');
                $fraction = ((int)$opt['valid'] === 1) ? 100 : 0;
                $answer->setAttribute('fraction', (string)$fraction);
                $answer->setAttribute('format', 'moodle_auto_format');

                $aText = $doc->createElement('text');
                $aText->appendChild($doc->createCDATASection(clean($opt['title'])));
                $answer->appendChild($aText);

                $fb = $doc->createElement('feedback');
                $fb->setAttribute('format', 'html');
                $fbText = $doc->createElement('text');
                $fbText->appendChild($doc->createCDATASection(''));
                $fb->appendChild($fbText);
                $answer->appendChild($fb);

                $questionNode->appendChild($answer);
            }
            break;

        case 99: // ordering
            $questionNode->setAttribute('type', 'ordering');

            $name = $doc->createElement('name');
            $nameText = $doc->createElement('text');
            $nameText->appendChild($doc->createCDATASection("Вопрос {$q['quest_id']}"));
            $name->appendChild($nameText);
            $questionNode->appendChild($name);

            $qtext = $doc->createElement('questiontext');
            $qtext->setAttribute('format', 'html');
            $qtextText = $doc->createElement('text');
            $qtextText->appendChild($doc->createCDATASection(clean($q['question'])));
            $qtext->appendChild($qtextText);
            $questionNode->appendChild($qtext);

            $gf = $doc->createElement('generalfeedback');
            $gf->setAttribute('format', 'html');
            $gfText = $doc->createElement('text');
            $gfText->appendChild($doc->createCDATASection(clean($q['help'])));
            $gf->appendChild($gfText);
            $questionNode->appendChild($gf);

            $questionNode->appendChild($doc->createElement('defaultgrade', 1));
            $questionNode->appendChild($doc->createElement('penalty', '0'));
            $questionNode->appendChild($doc->createElement('shuffleanswers', 'true'));

            foreach ($q['options'] as $opt) {
                $item = $doc->createElement('item');
                $itemText = $doc->createElement('text');
                $itemText->appendChild($doc->createCDATASection(clean($opt['title'])));
                $item->appendChild($itemText);
                $questionNode->appendChild($item);
            }
            break;

        default:
            echo "⚠️ Вопрос {$q['quest_id']} имеет неизвестный тип вопроса: {$type}\n";
            continue 2;
    }

    $quiz->appendChild($questionNode);
    $addedCount++;
    echo "➕ Вопрос {$q['quest_id']} (тип {$type}) добавлен\n";
}

$filename = 'quiz_questions.xml';
if ($addedCount) {
    $doc->save($filename);
    echo "✅ XML сохранён: $filename\n";
} else {
    unlink($filename);
    echo "✅ пустой XML удалён\n";
}
echo "Добавлено вопросов: $addedCount\n";
