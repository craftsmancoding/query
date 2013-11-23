<?php
/**
 * Query snippet for Query extra
 *
 * Copyright 2013 by Everett Griffiths <everett@craftsmancoding.com>
 * Created on 05-12-2013
 *
 * Query is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * Query is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Query; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package query
 */

/**
 * Description
 * -----------
 * A generic utility used for querying any database collection.
 *
 * Input Operators
 *  Apply these to the end of any parameter name with a colon, e.g. &firstname:LIKE=`Sue`
 *  E          =   equals
 *  NE         !=  not equal
 *  GT         >   greater than
 *  LT         <   less than
 *  GTE        >=  greater than or equal to
 *  LTE        <=  less than or equal to
 *  NOT_LIKE   NOT LIKE
 *  NOT_IN     NOT IN
 *  IN         IN
 *
 * Variables
 * ---------
 * @var $modx modX
 * @var $scriptProperties array
 *
 * @package query
 **/
//return '<textarea rows="40" cols="80">'.print_r($scriptProperties,true).'</textarea>';

$object = $modx->getOption('_object', $scriptProperties,'modResource');
$pkg = $modx->getOption('_pkg', $scriptProperties);
// $xpdo->addPackage('moxycart',$adjusted_core_path.'components/moxycart/model/','moxy_')
$tpl = $modx->getOption('_tpl', $scriptProperties);
$tplOuter = $modx->getOption('_tplOuter', $scriptProperties);
$limit = (int) $modx->getOption('_limit', $scriptProperties);
$sortby = $modx->getOption('_sortby', $scriptProperties);
$sortdir = $modx->getOption('_sortdir', $scriptProperties,'ASC'); 
$page = (int) $modx->getOption('_page', $scriptProperties);
$offset = (int) $modx->getOption('_offset', $scriptProperties);
$sql = $modx->getOption('_sql', $scriptProperties);
$graph = $modx->getOption('_graph', $scriptProperties);
$select = $modx->getOption('_select', $scriptProperties,'*');
$log_level = (int) $modx->getOption('_log_level', $scriptProperties,$modx->getOption('log_level'));
$debug = (int) $modx->getOption('_debug', $scriptProperties);
$json = (int) $modx->getOption('_json', $scriptProperties);

$old_log_level = $modx->setLogLevel($log_level);
$core_path = $modx->getOption('query.core_path','',MODX_CORE_PATH);

unset($scriptProperties['_object']);
unset($scriptProperties['_pkg']);
unset($scriptProperties['_tpl']);
unset($scriptProperties['_tplOuter']);
unset($scriptProperties['_limit']);
unset($scriptProperties['_page']);
unset($scriptProperties['_offset']);
unset($scriptProperties['_sql']);
unset($scriptProperties['_graph']);
unset($scriptProperties['_select']);
unset($scriptProperties['_debug']);
unset($scriptProperties['_log_level']);
unset($scriptProperties['_json']);



// Translate a few bits back due to conflicts in Snippet Syntax
// See http://rtfm.modx.com/xpdo/2.x/class-reference/xpdoquery/xpdoquery.where
$modified_operators = array(':E',':NE',':GT',':LT',':GTE',':LTE',':NOT_LIKE',':NOT_IN');
$standard_operators = array(':=',':!=',':>',':<',':>=',':<=',':NOT LIKE',':NOT IN');
foreach ($scriptProperties as $k => $v) {
    $k = str_replace($modified_operators, $standard_operators, $k);
    if (strtolower($v) == 'null') {
        $v = null;
    }
    // Manually set an operator
    if (isset($scriptProperties['_op_'.$k])) {
        $k = $k.':'.ltrim($scriptProperties['_op_'.$k],':');
    }
    unset($scriptProperties['_op_'.$k]);
    $scriptProperties[$k] = $v;
}

//return '<textarea rows="40" cols="80">'.print_r($scriptProperties,true).'</textarea>';
//return $object;
$data = array();
$total_pages = 0;
if ($sql) {
//    return 'asdfa;';
    $result = $modx->query($sql);
    $data = $result->fetchAll(PDO::FETCH_ASSOC);
}
else {
    $cols = array();
    if ($select != '*') {
        $cols = explode(',',$select);
        $cols = array_map('trim', $cols);
    }

    $criteria = $modx->newQuery($object);
//    $criteria->select($modx->getSelectColumns($object,'','',array('id','username')));
    $criteria->where($scriptProperties);
    $total_pages = $modx->getCount($object,$criteria);
    //return $total_pages; 
    $criteria->limit($limit, $offset); 
    if ($sortby) {
        $criteria->sortby($sortby,$sortdir);
    }
    //return 'asdf';
    $results = $modx->getCollection($object,$criteria);
    
    // TODO: More info
    if ($debug) {
        return '<div><h3>Raw SQL</h3><textarea rows="20" cols="60">'.$criteria->toSQL().'</textarea></div>';
    }

    foreach ($results as $r) {
        if ($select == '*') {
            $this_row = $r->toArray();   
        }
        else {
            foreach($cols as $c) {
                $this_row[$c] = $r->get($c);
            }
        }
        $data[] = $this_row;
    }

}
//$out = '<pre>'.print_r($data,true).'</pre>';




if ($json) {
    $data = array(
        'results' => $data,
        'total' => $total_pages,
    );
    return json_encode($data);
}

$out = '';

// Default formatting
if (!$tpl && !$tplOuter) {
    ob_start();
    include $core_path.'components/query/views/table.php';
    $out = ob_get_contents();
    ob_end_clean();
}
elseif($tpl) {
    foreach($data as $d) {
        $out .= $modx->getChunk($tpl,$d);
    }
}
if ($tplOuter) {
    $out = $modx->getChunk($tplOuter, array('content'=>$out));
}
$modx->setLogLevel($old_log_level);

return $out;