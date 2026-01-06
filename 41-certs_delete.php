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
force_current_language('uk'); 

$count = 0;

for ($id = 383; $id <= 4085; $id++){ 
    $count++;

    if ($issue = $DB->get_record('tool_certificate_issues', ['id' => $id])) {
    
//    echo print_r($issue,true);
    
	$template = \tool_certificate\template::instance($issue->templateid);
//	$template->revoke_issue($issue->id);
	
	echo "$count {$issue->id}\n";
	
	
    }

}
