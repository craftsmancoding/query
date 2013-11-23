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
 $xpdo->getAggregates() and getComposites() or you can access the $obj->_aggregates and $obj->_composites directly
 $graph = $xpdo->getGraph('Classname', 1)
 * @package query
 **/
//return '<textarea rows="40" cols="80">'.print_r($scriptProperties,true).'</textarea>';

//return '<textarea rows="40" cols="80">'.$graph = $modx->getGraph('modUser').'</textarea>';
// return print_r($modx->classMap,true);
$core_path = $modx->getOption('query.core_path','',MODX_CORE_PATH);

// Process the raw $scriptProperties into filters and control_params.
// We need to translate stuff here due to limitations in the Snippet Syntax.
// See http://rtfm.modx.com/xpdo/2.x/class-reference/xpdoquery/xpdoquery.where
$control_params = array(); $scriptProperties; // not a reference!
$filters = array();
$modified_operators = array(':E',':NE',':GT',':LT',':GTE',':LTE',':NOT_LIKE',':NOT_IN');
$standard_operators = array(':=',':!=',':>',':<',':>=',':<=',':NOT LIKE',':NOT IN');
foreach ($scriptProperties as $k => $v) {
    // All control_params begin with an underscore
    if ($k[0] == '_') {
        if (strtolower(substr($v,0,4)) == 'get:') {
            $v = $modx->getOption(substr($v,4), $_GET);
        }
        elseif (strtolower(substr($v,0,5)) == 'post:') {
            $v = $modx->getOption(substr($v,5), $_POST);
        }
        $control_params[$k] = $v;
        unset($scriptProperties[$k]);
        continue;
    }

    $raw_k = $k;
    if ($pos = strpos($k,':')) {
        $raw_k = substr($k,0,$pos);
    }
    
    // Optionally read out of the $_GET or $_POST arrays
    if (strtolower(substr($v,0,4)) == 'get:') {
        $v = $modx->getOption(substr($v,4), $_GET);
        $modx->setPlaceholder('query.'.$raw_k,htmlspecialchars($v));

    }
    elseif (strtolower(substr($v,0,5)) == 'post:') {
        $v = $modx->getOption(substr($v,5), $_POST);
        $modx->setPlaceholder('query.'.$raw_k,htmlspecialchars($v));
    }

    $k = str_replace($modified_operators, $standard_operators, $k);
    if (strtolower($v) == 'null') {
        $v = null;
    }
    // Manually set an operator
    if (isset($scriptProperties['_op_'.$k])) {
        $k = $k.':'.ltrim($scriptProperties['_op_'.$k],':');
    }
    unset($scriptProperties['_op_'.$k]);
    $filters[$k] = $v;
    
}

//return '<textarea rows="40" cols="80">'.print_r($filters,true).'</textarea>';

$object = $modx->getOption('_object', $control_params,'modResource');
$pkg = $modx->getOption('_pkg', $control_params);
// $xpdo->addPackage('moxycart',$adjusted_core_path.'components/moxycart/model/','moxy_')
$tpl = $modx->getOption('_tpl', $control_params);
$tplOuter = $modx->getOption('_tplOuter', $control_params);
$limit = (int) $modx->getOption('_limit', $control_params);
$sortby = $modx->getOption('_sortby', $control_params);
$sortdir = $modx->getOption('_sortdir', $control_params,'ASC'); 
$page = (int) $modx->getOption('_page', $control_params);
$offset = (int) $modx->getOption('_offset', $control_params);
$sql = $modx->getOption('_sql', $control_params);
$graph = $modx->getOption('_graph', $control_params);
$select = $modx->getOption('_select', $control_params,'*');
$log_level = (int) $modx->getOption('_log_level', $control_params,$modx->getOption('log_level'));
$config = basename($modx->getOption('_config', $control_params,'default'),'.config.php');
$debug = (int) $modx->getOption('_debug', $control_params);
$json = (int) $modx->getOption('_json', $control_params);

$old_log_level = $modx->setLogLevel($log_level);


//return '<textarea rows="40" cols="80">'.print_r($scriptProperties,true).'</textarea>';
//return $object;
$data = array();
$total_pages = 0;
if ($sql) {
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
    // Graphing potentially needs *all* fields to function, so limiting it is not rec'd
    if (!$graph) {
        $criteria->select($select);
    }
    $criteria->where($filters);
    $total_pages = $modx->getCount($object,$criteria);
    $criteria->limit($limit, $offset); 
    if ($sortby) {
        $criteria->sortby($sortby,$sortdir);
    }

    if ($graph) {
        $results = $modx->getCollectionGraph($object,$graph,$criteria);
    }
    else {
        $results = $modx->getCollection($object,$criteria);
    }
    // TODO: More info displayed here
    if ($debug) {
        $criteria->prepare();
        // $x = $modx->getComposites($object);        
        return '<div><h3>Raw SQL</h3><textarea rows="20" cols="60">'.$criteria->toSQL().'</textarea></div>';
    }

    foreach ($results as $r) {
        // Cheap trick to flatten the hierarchy using toPlaceholders
        if ($graph) {
            $keys = $modx->toPlaceholders($r->toArray('',false,true,$graph),'tmp'); // without period
        }
        else {
            $keys = $modx->toPlaceholders($r->toArray(),'tmp'); // without period
        }
        $this_row = array();
        // Cols are set only when $graph && $select
        if ($cols) {
            foreach ($cols as $k) {
                // $k seems to come out clean when $select is used
                $this_row[$k] = $modx->getPlaceholder('tmp'.$k); // with period
            }
        }
        else {
            foreach ($keys['keys'] as $k) {
                $clean_k = substr($k,4);
                $this_row[$clean_k] = $modx->getPlaceholder($k); 
            }
        }
        $data[] = $this_row;
    }

}

// Useful if this is to supply an Ajax request
if ($json) {
    $data = array(
        'results' => $data,
        'total' => $total_pages,
    );
    return json_encode($data);
}

$out = '';

// Pagination
if ($total_pages > $limit) {
    require_once $core_path.'components/query/lib/pagination.class.php';
    $P = new Pagination();
    $P->set_base_url($modx->makeUrl($modx->resource->get('id'),'','','abs'));
    $P->set_offset($offset); 
    $P->set_results_per_page($limit);
    $tpls = require $core_path.'components/query/lib/'.$config.'.config.php';
//    return '<textarea>'.print_r($tpls,true).'</textarea>';
    $P->set_tpls($tpls);
    $pagination_links = $P->paginate($total_pages);
}

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
/*
. '<div>
    <h3>POST</h3>
    <textarea rows="10" cols="60">'.print_r($_POST,true).'</textarea>
    <h3>Filters</h3>
    <textarea rows="10" cols="60">'.print_r($filters,true).'</textarea>

    <h3>Raw SQL</h3><textarea rows="20" cols="60">'.$criteria->toSQL().'</textarea>
    </div>';
*/