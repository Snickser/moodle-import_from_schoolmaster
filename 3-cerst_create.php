#!/usr/bin/env php
<?php
define('CLI_SCRIPT', true);
require('/var/www/html/moodle/config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/admin/tool/certificate/lib.php');

use tool_certificate\template;
use tool_certificate\page;

// Установка пользователя и сессии
\core\session\manager::init_empty_session();
\core\session\manager::set_user(get_admin());

$name = 'Prefix 111';
$bgfilepath = '../pg/pdf_to_png_templates/757f333d-49b9-47c6-bb49-8add9d6c6453.png';
$signature = '../pg/uploads/signature/file/27681a43-aeca-4af9-b7bf-1cbfb0433171.png';

$size = getimagesize($signature);
$sscale = 0.18;


// Установим контекст (системный или категории)
$context = context_system::instance();

//for ($i = 1; $i <= $count; $i++) {

    // Создаём шаблон
    $templateobj = new stdClass();
    $templateobj->name = $name;
    $templateobj->contextid = $context->id;
    $templateobj->shared = 1;

    $template = \tool_certificate\template::create($templateobj);

	// Create page.
        $page = $template->new_page();
        $pagerecord = [];
        $page->save((object)($pagerecord ?: []));

        // Create background.
        $str = get_string('demotmplbackground', 'tool_certificate');
        $elementrecord = ['pageid' => $page->get_id(), 'name' => $str, 'element' => 'image',
            'data' => json_encode(['width' => 0, 'height' => 0, 'isbackground' => true]), 'sequence' => 1, ];
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

        // Username.
        $str = get_string('demotmplusername', 'tool_certificate');
        $elementrecord = ['pageid' => $page->get_id(), 'name' => $str, 'element' => 'userfield', 'data' => 'fullname',
            'font' => 'freesansb', 'fontsize' => 28, 'colour' => '#000', 'posx' => 125, 'posy' => 116, 'sequence' => 3,
            'refpoint' => 0, ];
        $element = new \tool_certificate\persistent\element(0, (object)$elementrecord);
        $element->save();

	// Signature.
        $str = get_string('demotmplsignature', 'tool_certificate');
        $elementrecord = ['pageid' => $page->get_id(), 'name' => $str, 'element' => 'image', 'posx' => 60, 'posy' => 115,
            'data' => json_encode(['width' => $size[0]*$sscale, 'height' => $size[1]*$sscale, 'isbackground' => false]), 'sequence' => 9, ];
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


    cli_writeln("Created template #$templateid with background: {$templateobj->name}");

//}
