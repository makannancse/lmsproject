<?php
require_once __DIR__ . '/common.php';
api_headers();

api_json(true, array(
    array('group' => 'Case Management', 'items' => array(
        array('label' => 'Dashboard', 'href' => 'attorney-dashboard.html', 'icon' => 'calendar', 'active' => true),
        array('label' => 'MVA', 'href' => '#', 'icon' => 'check', 'active' => false),
		array('label' => 'Premise Liability', 'href' => '#', 'icon' => 'uber', 'active' => false),
		array('label' => 'Surgical', 'href' => '#', 'icon' => 'waiting', 'active' => false),
    )),
    array('group' => 'Client List', 'items' => array(
        array('label' => 'Cases', 'href' => '#', 'icon' => 'users', 'active' => false),
        //array('label' => 'Documentation', 'href' => '#', 'icon' => 'clipboard', 'active' => false),
        //array('label' => 'E-Sign', 'href' => '#', 'icon' => 'esign', 'active' => false),
        //array('label' => 'Tasks', 'href' => '#', 'icon' => 'warning', 'active' => false)
    )),
    array('group' => 'Reports', 'items' => array(
		array('label' => 'Productivity', 'href' => '#', 'icon' => 'calendar', 'active' => false),
        array('label' => 'Client Compliacy', 'href' => '#', 'icon' => 'users', 'active' => false),
        array('label' => 'Surgical Cases', 'href' => '#', 'icon' => 'reports', 'active' => false)
    ))
));
