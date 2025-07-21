#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);

require_once('/var/www/html/moodle/config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

global $DB;

// === Проверка параметра test_id ===
if (!isset($argv[1]) || !is_numeric($argv[1])) {
    exit("❌ Укажите quiz_id первым параметром. Пример:\nphp import.php 194\n");
}

$quizid = (int)$argv[1];

$xmlfile = __DIR__ . '/quiz_questions.xml';

if (!file_exists($xmlfile)) {
    cli_writeln("❌ Файл не найден: $xmlfile");
    die;
}

function ensure_question_category_for_module($context, $courseid): stdClass {
    global $DB;

//    $coursecontext = context_course::instance($courseid);

//    $rootcategory = $DB->get_record_sql("SELECT * FROM {question_categories} WHERE contextid = ? AND parent = 0 ORDER BY id ASC LIMIT 1", [$coursecontext->id]);

    $rootcategory = $DB->get_record_sql("SELECT * FROM {question_categories} WHERE contextid = ? AND parent = 0 ORDER BY id ASC LIMIT 1", [$context->id]);

    if (!$rootcategory) {
        throw new Exception('Корневая категория курса не найдена');
    }

    // Проверяем существование категории в контексте модуля с родителем rootcategory->id
    $existing = $DB->get_record_sql("SELECT * FROM {question_categories} WHERE contextid = ? AND parent = ? ORDER BY id ASC LIMIT 1", [$context->id, $rootcategory->id]);

    if ($existing) {
        return $existing;
    }

    $record = new stdClass();
    $record->name = 'Імпортовані для тесту';
    $record->info = 'Категорія питань';
    $record->contextid = $context->id;
    $record->parent = $rootcategory->id;
    $record->sortorder = 999;
    $record->stamp = make_unique_id_code();

    $record->id = $DB->insert_record('question_categories', $record);
    return $record;
}


// Получаем курс и модуль
$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quizid, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

$category = ensure_question_category_for_module($context, $course->id);

// Импорт XML вопросов
$format = new qformat_xml();
$format->setCategory($category);               // объект категории
$format->setContexts([$context]);              // массив контекстов
$format->setCourse($course);                   // объект курса
$format->setFilename($xmlfile);                // путь к XML-файлу
$format->setRealfilename(basename($xmlfile));  // имя файла

// Импорт
if (!$format->importpreprocess()) {
    cli_writeln("Ошибка importpreprocess()");
}

try {
    $format->importprocess(true);
    echo "\n➕ Вопросы в банк ID:{$category->id} импортированы.\n";
} catch (Throwable $e) {
    cli_writeln("❌ Ошибка при разборе вопросов: " . $e->getMessage());
    die;
}

if (!$format->importpostprocess()) {
    cli_writeln("❌ Ошибка importpostprocess()");
    die;
}

// === ДОБАВЛЯЕМ ВОПРОСЫ В ТЕСТ ===
$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$slot = 1 + (int)$DB->get_field_sql("SELECT MAX(slot) FROM {quiz_slots} WHERE quizid = ?", [$quiz->id]);
// Получаем все questionid из этой категории
$sql = "
    SELECT q.id, q.name
    FROM {question} q
    JOIN {question_versions} qv ON qv.questionid = q.id
    JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
    WHERE qbe.questioncategoryid = :catid AND q.parent = 0
    ORDER BY q.id ASC
";
$params = ['catid' => $category->id];
$questions = $DB->get_records_sql($sql, $params);

foreach ($questions as $q) {
    quiz_add_quiz_question($q->id, $quiz);
    $qname = $DB->get_field('question', 'name', ['id' => $q->id]);
    echo "📌 Добавлен вопрос '{$qname}' в слот $slot\n";
    $slot++;
}

echo "✅ Импорт завершён.\n";
exit(0);