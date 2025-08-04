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

$CFG->debug = DEBUG_ALL;
$CFG->debugdisplay = 1;

if (!isset($argv[1])) {
    exit("❌ Нужно указать ID или 'full'\n");
}


$filename = 'data-1753298014083.csv'; // Укажи имя или путь к CSV-файлу
$data = [];

// Проверяем наличие файла
if (!file_exists($filename)) {
    die("❌ Файл '$filename' не найден.\n");
}

if (($handle = fopen($filename, 'r')) !== false) {
    // Чтение заголовков
    $headers = fgetcsv($handle, 0, ',');

    if ($headers === false) {
        die("❌ Не удалось прочитать заголовки.\n");
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
    die("❌ Не удалось открыть файл '$filename'.\n");
}


$filename = 'data-1753385691293.csv'; // Укажи имя или путь к CSV-файлу
$text = [];

// Проверяем наличие файла
if (!file_exists($filename)) {
    die("❌ Файл '$filename' не найден.\n");
}

if (($handle = fopen($filename, 'r')) !== false) {
    // Чтение заголовков
    $headers = fgetcsv($handle, 0, ',');

    if ($headers === false) {
        die("❌ Не удалось прочитать заголовки.\n");
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
    die("❌ Не удалось открыть файл '$filename'.\n");
}


// Установим контекст (системный или категории)
$context = context_system::instance();


$bgf = [];
//new stdClass;    

foreach ($data as $id => $items) {
    $page = null; 

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

    $use = false;
    foreach ($data[$id] as $n){
        if($n->sname == 'Печатка КДАСК'){
            $use = true;
            break;
        }
    }
/*
    if(!$use){
        $items[] = $data[150][1];
        $items[] = $data[150][3];
        $items[1]->certname = $items[0]->certname;
        $items[2]->certname = $items[0]->certname;
    }
*/

# Stage 1: создание основы и графических элементов
    $rownum = 0;
    while($rownum < count($items)) {
	$row = $items[$rownum];
	$rownum++;

//echo "{$row->sx} х {$row->sy} $sscale $row->sangle\n";
	
//print_r($row);

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
                echo "❌ Файл '$bgfilepath' не найден. Используем пустой шаблон + элементы.\n";

                // добавить печать
		$olddata = $row->certname;
		if (str_contains($row->certname,'Новий Маяпур') && $id != 133) {
            	    $bgfilepath = 'bgdefault1.png';
		    $row = $data[133][1];
		    // 1
            	    $newdata = $data[133][0];
                    $newdata->certname = $olddata;
	            $newdata->id = $id;
    		    $items[] = $newdata;
    		    // 2
            	    $newdata = $data[133][2];
    	    	    $newdata->certname = $olddata;
            	    $newdata->id = $id;
    		    $items[] = $newdata;
		} else {
            	    $bgfilepath = 'bgdefault2.png';
                    if($id != 150){
            	    $row = $data[150][1];
            	    $newdata = $data[150][3];
            	    $newdata->certname = $olddata;
            	    $newdata->id = $id;
            	    $items[] = $newdata;
}
            	}
            	$row->certname = $olddata;
        	$row->id = $id;
                $bgf[$id] = (object)[ 'default_file' => true ];
	    }

            // Проверяем существование.
            $template = \tool_certificate\template::find_by_name($rowid.'-'.$row->certname);
            if($template){
        	// Удалить
                echo "✅ Delete existing certificate ID: {$row->id}\n";
        	$template->delete();
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
            
	} //end one time


//print_r($row);


//        echo "  Имя элемента: {$row->sname}\n";
        echo "  Файл: {$row->sfile}\n";

        //$signature = '../pg/uploads/signature/file/'.'27681a43-aeca-4af9-b7bf-1cbfb0433171.png';
        
        $signature = '../pg/uploads/signature/file/'.$row->sfile;
        
        // Проверяем наличие файла
        if (!file_exists($signature)) {
            echo "❌ Файл сигнатуры '$signature' не найден.\n";
        
        //$signature = '../pg/uploads/signature/file/be7bb783-735e-4757-acbe-c587b456769c.png';
        
        //$row->sx = 500;
        //$row->sy = 120;
        //$row->sscale = 0.22;
        //$row->sname = "Підпис Абгінандана дас";
        //$row->sangle = 0;
        
            continue;
        }
        
        if($row->sname === 'Печатка КДАСК'){
            $row->sscale = 0.25;
            $oldsequence = $sequence;
            $sequence = 300;
        }
        
        if($row->sname == 'Печатка Вайшнавський навчальний центр "Новий Маяпур"'){
    	    $pp = 18;
    	} else {
    	    $pp = 0;
    	}
        
        $size = getimagesize($signature);
        $dpi = 0.352778;
        $sscale = $row->sscale*$dpi;

//echo "{$row->sx} х {$row->sy} $sscale $row->sangle\n";
        
        $posx = floor(($row->sx - ($row->sangle != 0 ? 15 : 0)) / 2.83465) + 14;
        $posy = ceil((595.28 - $row->sy - $pp + ($row->sangle != 0 ? 5 : 0)) / 2.83465) - 14;

//echo "$posx х $posy $sscale $row->sangle $size[0] х $size[1]\n";
        
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
    
    	if($row->sname == 'Печатка КДАСК'){
    	    $sequence = $oldsequence;
    	}
    	$signature = null; //обнулить.
    
        $sequence++;
    } // end loop
        
    $sequence = 1;
    
    if(!isset($text[$id]) && $bgf[$id]->default_file){
	// Создаём все заглушки если совсем нет данных и шаблон пустой
        echo "❌ Нет текстовых данных. Добавляем текстовые данные по умолчанию.\n";

//print_r($row);
        
	if (str_contains($row->certname,'Новий Маяпур')) {



	    $text[$id] = $text[133];
	    $text[$id][9]->template = "успішно закінчив курс «".$row->certname."»";
    	    unset($text[$id][10]);
    	    $text[$id][] = (object)[ 'template' => '{{qrcode}}', 'width' => 20, 'ex' => 37, 'ey' => 168 ];



	} else {
    	    $text[$id] = $text[150];
        
//        unset($text[$id][5]);
//        unset($text[$id][8]);

        unset($text[$id][9]);
        unset($text[$id][11]);
        unset($text[$id][12]);
        unset($text[$id][13]);
    
        $text[$id][10]->template = $row->certname;
        $text[$id][] = (object)[ 'template' => '{{qrcode}}', 'width' => 20, 'ex' => 46, 'ey' => 156 ];
	}
        
//print_r($text[$id]);
        
    } else {
        // создаём только отсутстсвующую
        $use = false;
    
    //print_r($bgf);
    //echo $bgf[$id]->default_file;
    
        foreach ($text[$id] as $n){
    	    if($n->template == 'Київської духовної академії свідомості Крішни'){
        	$use = true;
        	break;
    	    }
        }
        if(!$use && $bgf[$id]->default_file){
            echo "✅ Добавляем текстовые данные по умолчанию.\n";
    	    if (str_contains($row->certname,'Новий Маяпур')) {
    		$text[$id][] = (object)[ 'template' => '{{qrcode}}', 'width' => 20, 'ex' => 37, 'ey' => 168 ];
	    } else {
    	    $text[$id][] = $text[150][5];
	    $text[$id][] = $text[150][6];
    	    $text[$id][] = $text[150][7];
    	    $text[$id][] = $text[150][8];
    		$text[$id][] = (object)[ 'template' => '{{qrcode}}', 'width' => 20, 'ex' => 46, 'ey' => 156 ];
    	    }
        }
    }
    
# Stage 2: создание текстовых элементов
    
    foreach ($text[$id] as $row) {
//        echo "  Имя элемента: {$row->template}\n";
//	  echo "  Файл: {$row->font}\n";
        
        if(isset($row->default_file)){
            continue;
        }
        
        $posx = floor($row->ex / 2.83465) + 14;
        $posy = ceil((595.28 - $row->ey) / 2.83465) - 14;
        
        switch($row->font) {
            case "ArnoPro-Regular":
        	$font = "arnopro";
        //	$font = "freeserif";
                break;
            case "ArnoPro-Bold":
        	$font = "arnoprob";
        //	$font = "freeserifb";
                break;
            case "Constantia Regular":
        	$font = "constantia";
//        	$font = "freeserif";
                break;
            case "Constantia Bold":
        	$font = "constantiab";
//        	$font = "freeserifb";
                break;
            case "Constantia Italic":
        	$font = "constantiai";
//        	$font = "freeserifi";
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
    	    // Date
    	    if ($matches[1] === 'issued_date') {
    	        if (str_contains($row->template,'Новий Маяпур')) {
    	    	    $str = 'Україна, «Новий Маяпур»,';
    		    $elementrecord = ['pageid' => $page->get_id(), 'name' => 'Україна, «Новий Маяпур»', 'element' => 'text',
        		'data' => $str, 'font' => $font, 'fontsize' => $row->font_size, 'colour' => $row->color,
        		'posx' => $posx, 'posy' => $posy, 'width' => 0,
        		'sequence' => $sequence, 'refpoint' => 2, ];
    		    $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
    		    $element->save();
    		    $refpoint = 0;
    		    $posx+=1;
    	        }

        	$elementrecord = ['pageid' => $page->get_id(), 'name' => 'Дата курса', 'element' => 'program', 'font' => $font, 'width' => 0,
            	'fontsize' => $row->font_size, 'data' => json_encode(['display' => 'coursecompletiondate']),
        	'colour' => $row->color, 'posx' => $posx, 'posy' => $posy, 'sequence' => $sequence, 'refpoint' => $refpoint, ];
		$element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
    		$element->save();
    		continue;
	    }
	
    	    // Serial
    	    if ($matches[1] === 'serial') {
                $elementrecord = ['pageid' => $page->get_id(), 'name' => '№', 'element' => 'text',
            	'data' => '№', 'font' => 'freeserif', 'fontsize' => $row->font_size, 'colour' => $row->color,
                    'posx' => 123, 'posy' => $posy, 'width' => 0,
                    'sequence' => $sequence, 'refpoint' => $refpoint, ];
                $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
                $element->save();
                $elementrecord = ['pageid' => $page->get_id(), 'name' => 'Serial', 'element' => 'code',
            	    'font' => 'freeserif', 'width' => 60,
                    'fontsize' => $row->font_size, 'data' => json_encode([ 'display' => 1 ]),
                    'colour' => $row->color, 'posx' => $posx+5, 'posy' => $posy, 'sequence' => $sequence, 'refpoint' => $refpoint, ];
                $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
                $element->save();
        	    continue;
        	}
    	
            // Username
            if ($matches[1] === '^diploma_name_present?') {
                $elementrecord = ['pageid' => $page->get_id(), 'name' => 'Username', 'element' => 'userfield',
            	'data' => 'fullname', 'font' => $font, 'fontsize' => $row->font_size, 'colour' => $row->color,
                    'posx' => $posx, 'posy' => $posy-1, 'width' => 0,
                    'sequence' => $sequence, 'refpoint' => $refpoint, ];
                $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
                $element->save();
        	    continue;
        	}
    	
            // QR code
            if ($matches[1] === 'qrcode') {
                $elementrecord = ['pageid' => $page->get_id(), 'name' => 'QRcode', 'element' => 'code',
        	    'data' => json_encode([ 'display' => 4 ]), 'font' => $font, 'fontsize' => $row->font_size, 'colour' => $row->color,
                    'posx' => $row->ex, 'posy' => $row->ey, 'width' => $row->width,
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
        $options = ['male?' => true];
        $row->template = $mustache->render($row->template, $options);
                    
        $str = str_replace(', Новий Маяпур', '', $row->template);
                    
        if(strlen($row->template)>50){
//    	    $row->font_size -= 2;
        }
        $elementrecord = ['pageid' => $page->get_id(), 'name' => $row->template, 'element' => 'text',
            'data' => $str, 'font' => $font, 'fontsize' => $row->font_size, 'colour' => $row->color,
            'posx' => $posx, 'posy' => $posy, 'width' => 0,
            'sequence' => $sequence, 'refpoint' => $refpoint, ];
        $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
        $element->save();
        $sequence++;
    } // end loop
 
//break;

} // end loop



