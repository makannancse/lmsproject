<?php
require_once __DIR__ . '/common.php';
api_headers();

api_json(true, array(
    array('group' => 'Scheduling', 'items' => array(
        array('label' => 'Dashboard', 'href' => 'scheduling-dashboard.html', 'icon' => 'calendar', 'active' => true),
        //array('label' => 'Waitlist', 'href' => '#', 'icon' => 'waiting', 'active' => false),
        array('label' => 'Check-In Queue', 'href' => '#', 'icon' => 'check', 'active' => false),
		array('label' => 'Transportation', 'href' => '#', 'icon' => 'uber', 'active' => false)
    )),
    array('group' => 'Clinical', 'items' => array(
        array('label' => 'Patient List', 'href' => 'active-patients.html', 'icon' => 'users', 'active' => false),
        //array('label' => 'Documentation', 'href' => '#', 'icon' => 'clipboard', 'active' => false),
        //array('label' => 'E-Sign', 'href' => '#', 'icon' => 'esign', 'active' => false),
        //array('label' => 'Tasks', 'href' => '#', 'icon' => 'warning', 'active' => false)
    )),
    array('group' => 'Reports', 'items' => array(
       // array('label' => 'Productivity', 'href' => '#', 'icon' => 'calendar', 'active' => false),
        array('label' => 'Attendance', 'href' => '#', 'icon' => 'users', 'active' => false),
       // array('label' => 'Custom Reports', 'href' => '#', 'icon' => 'reports', 'active' => false)
    ))
));
