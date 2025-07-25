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


if (!isset($argv[1])) {
    exit("Нужно указать ID или 'full'\n");
}


$filename = 'data-1753298014083.csv'; // Укажи имя или путь к CSV-файлу
$data = [];

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

    // Чтение всех строк
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        $obj = new stdClass();

        foreach ($headers as $i => $header) {
            $obj->{$header} = $row[$i] ?? null;
        }

        $key = $row[0]; // столбец — ключ (id)

        if (!isset($data[$key])) {
            $data[$key] = [];
        }

        $data[$key][] = $obj;
    }

    fclose($handle);
} else {
    die("Не удалось открыть файл '$filename'.\n");
}


$filename = 'data-1753385691293.csv'; // Укажи имя или путь к CSV-файлу
$text = [];

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

    // Чтение всех строк
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        $obj = new stdClass();

        foreach ($headers as $i => $header) {
            $obj->{$header} = $row[$i] ?? null;
        }

        $key = $row[0]; // столбец — ключ (id)

        if (!isset($text[$key])) {
            $text[$key] = [];
        }

        $text[$key][] = $obj;
    }

    fclose($handle);
} else {
    die("Не удалось открыть файл '$filename'.\n");
}


// Установим контекст (системный или категории)
$context = context_system::instance();
    
foreach ($data as $id => $items) {

if ($argv[1] != 'full' and (int)$argv[1] != $id) {
    continue;
}

    echo "ID: $id\n";
    $onetime = true;
    $sequence = 100;

/*
if (in_array($id, range(151, 151))){
    continue;
}
*/

# Stage 1: создание основы и графических элементов

    foreach ($items as $row) {

$rowid = str_pad($row->id, 3, '0', STR_PAD_LEFT);

if ($onetime) {
    $onetime = false;

    // $bgfilepath = '../pg/pdf_to_png_templates/'.'757f333d-49b9-47c6-bb49-8add9d6c6453.png';

    $bgname = preg_replace('/\.pdf$/i', '.png', $row->bgfile);
    $bgfilepath = '../pg/pdf_to_png_templates/'.$bgname;

//    $bgfilepath = '../pg/pdf_to_png_templates/'.'151-example.png';

    echo " BG: {$bgname}\n";

// Проверяем наличие файла
if (!file_exists($bgfilepath)) {
    echo "Файл '$bgfilepath' не найден. Используем пустой шаблон.\n";
    $bgfilepath = '../pg/pdf_to_png_templates/'.'0e9b589d-d70d-4db4-ba0b-c88595a40513.png';
}

    $template = \tool_certificate\template::find_by_name($rowid.'-'.$row->certname);

    // Проверяем существование.
    if($template){
	// Удалить
        echo "Delete existing certificate ID: {$row->id}\n";
	$template->delete();
    }

if(!$text[$id]){
    continue;
}

	// Создаём новый шаблон.
        $templateobj = new stdClass();
	$templateobj->name = $rowid . '-' . $row->certname;
	$templateobj->contextid = $context->id;
	$templateobj->shared = 1;
	$template = \tool_certificate\template::create($templateobj);

	// Create page.
        $page = $template->new_page();
        $pagerecord = [];
        $page->save((object)($pagerecord ?: []));

        // Create background.
        $elementrecord = ['pageid' => $page->get_id(), 'name' => 'Background', 'element' => 'image',
            'data' => json_encode(['width' => 0, 'height' => 0, 'isbackground' => true]), 'sequence' => 0, ];
        $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
        $element->save();

	$fs = get_file_storage();
	$filerecord = [
            'contextid' => $context->id,
            'component' => 'tool_certificate',
            'filearea'  => 'element',
            'itemid'    => $element->get('id'),
            'filepath'  => '/',
            'filename'  => basename($bgfilepath),
        ];
        $fs->create_file_from_pathname($filerecord, $bgfilepath);
        
        
}

//        echo "  Имя элемента: {$row->sname}\n";
//        echo "  Файл: {$row->sfile}\n";

//$signature = '../pg/uploads/signature/file/'.'27681a43-aeca-4af9-b7bf-1cbfb0433171.png';

$signature = '../pg/uploads/signature/file/'.$row->sfile;

// Проверяем наличие файла
if (!file_exists($signature)) {
    echo "Файл '$signature' не найден.\n";
    continue;
}

$size = getimagesize($signature);
$dpi = 0.352778;
$sscale = $row->sscale*$dpi;

$posx = floor(($row->sx - ($row->sangle != 0 ? 15 : 0)) / 2.83465) + 14;
$posy = ceil((595.28 - $row->sy + ($row->sangle != 0 ? 5 : 0)) / 2.83465) - 14;

	// Signature.
        $elementrecord = ['pageid' => $page->get_id(), 'name' => $row->sname, 'element' => 'image',
    	    'posx' => $posx, 'posy' => $posy, 'width' => 0,
            'data' => json_encode(['width' => floor($size[0]*$sscale), 'height' => floor($size[1]*$sscale),
            'isbackground' => false]), 'sequence' => $sequence, ];
        $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
        $element->save();

	$fs = get_file_storage();
	$filerecord = [
            'contextid' => $context->id,
            'component' => 'tool_certificate',
            'filearea'  => 'element',
            'itemid'    => $element->get('id'),
            'filepath'  => '/',
            'filename'  => basename($signature),
        ];
        $fs->create_file_from_pathname($filerecord, $signature);
        $sequence++;
    }

$sequence = 1;

# Stage 2: создание текстовых элементов

    foreach ($text[$id] as $row) {
//        echo "  Имя элемента: {$row->template}\n";
//        echo "  Файл: {$row->font}\n";

if(!$row->ex || !$row->ey){
//    continue;
}

$posx = floor($row->ex / 2.83465) + 14;
$posy = ceil((595.28 - $row->ey) / 2.83465) - 14;

switch($row->font) {
    case "ArnoPro-Regular":
	$font = "arnopro";
        break;
    case "ArnoPro-Bold":
	$font = "arnoprob";
        break;
    case "Constantia Regular":
	$font = "constantia";
        break;
    case "Constantia Bold":
	$font = "constantiab";
        break;
    case "Constantia Italic":
	$font = "constantiai";
        break;
}

switch($row->align) {
    case 0:
	$refpoint = 1; // center
        break;
    case 1:
	$refpoint = 0; // left
        break;
    case 2:
	$refpoint = 2; // right
        break;
    default:
	$refpoint = 0;
}
    // Center fix
    if ($row->ex == 0) {
	$posx = 148;
    }

    if (preg_match('/\{\{(.*?)\}\}/', $row->template, $matches)) {

	// Create date
	if ($matches[1] === 'issued_date') {
    	$elementrecord = ['pageid' => $page->get_id(), 'name' => 'Date', 'element' => 'date', 'font' => $font, 'width' => 0,
            'fontsize' => $row->font_size, 'data' => json_encode(['dateitem' => -1, 'dateformat' => 'strftimedate']),
            'colour' => $row->color, 'posx' => $posx, 'posy' => $posy, 'sequence' => $sequence, 'refpoint' => $refpoint, ];
        $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
        $element->save();
	continue;
	}
	
	// Create serial
	if ($matches[1] === 'serial') {
        $elementrecord = ['pageid' => $page->get_id(), 'name' => '№', 'element' => 'text',
    	    'data' => '№', 'font' => $font, 'fontsize' => $row->font_size, 'colour' => $row->color,
            'posx' => 123, 'posy' => $posy, 'width' => 0,
            'sequence' => $sequence, 'refpoint' => $refpoint, ];
        $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
        $element->save();

        $elementrecord = ['pageid' => $page->get_id(), 'name' => 'Serial', 'element' => 'code', 'font' => $font, 'width' => 60,
            'fontsize' => $row->font_size, 'data' => json_encode([ 'display' => 1 ]),
            'colour' => $row->color, 'posx' => $posx+5, 'posy' => $posy, 'sequence' => $sequence, 'refpoint' => $refpoint, ];
        $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
        $element->save();
	continue;
	}
	
        // Create username
        if ($matches[1] === '^diploma_name_present?') {
        $elementrecord = ['pageid' => $page->get_id(), 'name' => 'Username', 'element' => 'userfield',
    	    'data' => 'fullname', 'font' => $font, 'fontsize' => $row->font_size, 'colour' => $row->color,
            'posx' => $posx, 'posy' => $posy-1, 'width' => 0,
            'sequence' => $sequence, 'refpoint' => $refpoint, ];
        $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
        $element->save();
	continue;
        }

//echo " {$matches[1]} \n";
 
    }

$mustache = new Mustache_Engine([
    'escape' => 's', // Использует стандартную функцию s() из Moodle для защиты HTML
]);
$data = ['male?' => 1];
$row->template = $mustache->render($row->template, $data) . PHP_EOL;
    
        $elementrecord = ['pageid' => $page->get_id(), 'name' => $row->template, 'element' => 'text',
    	    'data' => $row->template, 'font' => $font, 'fontsize' => $row->font_size, 'colour' => $row->color,
            'posx' => $posx, 'posy' => $posy, 'width' => 0,
            'sequence' => $sequence, 'refpoint' => $refpoint, ];
        $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
        $element->save();
        $sequence++;
    }

 
 
//break;


}


cli_writeln("✅ Created template {$templateobj->name}");

