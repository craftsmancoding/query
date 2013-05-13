<?php
/**
 * snippets transport file for Query extra
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
/* @var xPDOObject[] $snippets */


$snippets = array();

$snippets[1] = $modx->newObject('modSnippet');
$snippets[1]->fromArray(array(
    'id' => '1',
    'description' => 'A generic utility used for querying any database collection.',
    'name' => 'Query',
), '', true, true);
$snippets[1]->setContent(file_get_contents($sources['source_core'] . '/elements/snippets/query.snippet.php'));

return $snippets;
