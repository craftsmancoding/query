<?php
/**
 * propertySets transport file for Query extra
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
/* @var xPDOObject[] $propertySets */


$propertySets = array();

$propertySets[1] = $modx->newObject('modPropertySet');
$propertySets[1]->fromArray(array(
    'id' => '1',
    'name' => 'modResourceQueryParameters',
    'description' => 'Default arguments for Query Snippet when querying modResource collections.',
), '', true, true);
return $propertySets;
