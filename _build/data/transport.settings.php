<?php
/**
 * systemSettings transport file for Query extra
 *
 * Copyright 2013 by Everett Griffiths <everett@craftsmancoding.com>
 * Created on 05-12-2013
 *
 * @package query
 * @subpackage build
 */

if (! function_exists('stripPhpTags')) {
    function stripPhpTags($filename) {
        $o = file_get_contents($filename);
        $o = str_replace('<' . '?' . 'php', '', $o);
        $o = str_replace('?>', '', $o);
        $o = trim($o);
        return $o;
    }
}
/* @var $modx modX */
/* @var $sources array */
/* @var xPDOObject[] $systemSettings */


$systemSettings = array();

$systemSettings[1] = $modx->newObject('modSystemSetting');
$systemSettings[1]->fromArray(array(
    'key' => 'query_system_setting1',
    'name' => 'query Setting One',
    'description' => 'Description for setting one',
    'namespace' => 'query',
    'xtype' => 'textfield',
    'value' => 'value1',
    'area' => 'area1',
), '', true, true);
$systemSettings[2] = $modx->newObject('modSystemSetting');
$systemSettings[2]->fromArray(array(
    'key' => 'query_system_setting2',
    'name' => 'query Setting Two',
    'description' => 'Description for setting two',
    'namespace' => 'query',
    'xtype' => 'combo-boolean',
    'value' => true,
    'area' => 'area2',
), '', true, true);
return $systemSettings;
