<?php
/**
 * @name Query
 * @description A generic utility/interface used for querying any MODX database collection.
 *
 * USAGE
 *
 *  [[Query? &_classname=`modResource` &template=`3`]]
 *
 * No Results - rely on MODX output filters.
 *
 *  [[Query:empty=`No results found`? &_classname=`modUser` &_tpl=`SingleUser`]]
 *
 *
 * Copyright 2013 by Everett Griffiths <everett@craftsmancoding.com>
 * Created on 05-12-2013
 * 
 * Control Parameters
 * ------------------
 * All "control" parameters begin with an underscore. They affect the functionality or formatting of the output.
 *
 *  _classname (string) classname of the object collection you are querying. Default: modResource
 *  _pkg (string) colon-separated string defining the arguments for addPackage() -- 
 *      package_name, model_path, and optionally table_prefix  
 *      e.g. `tiles:[[++core_path]]components/tiles/model/:tiles_` or 
 *      If only the package name is supplied, the path is assumed to be "[[++core_path]]components/$package_name/model/"
 *  _tpl (string) chunk to format each record in the collection
 *  _tplOuter (string) chunk to wrap the result set. Requires the [[+content]] placeholder.
 *  _view (string) oldschool php file to format the output, see the views folder.  
 *      Some samples are provided, e.g. 'table', 'json'. If _tpl
 *      and _tplOuter are provided, the _view parameter is ignored.  Default: table.php
 *  _limit (integer) limits the number of results returned, also sets the results shown per page. 
 *  _offset (integer) offsets the first record returned, e.g. for pagination.
 *  _sortby (string) column to sort by
 *  _sortdir (string) sort direction. Usually ASC or DESC, but may also contain complex sorting rules.
 *  _sql (string) used to issue a raw SQL query.
 *  _graph (string) triggers a getCollectionGraph.
 *  _select (string) controls which columns to select for a getCollection. Ignored when _graph is set. Default: *
 *  _config (string) sets a pagination formatting pallette, e.g. "default". 
 *      Corresponding file must exist inside the config directory, e.g. "default.config.php"
 *  _log_level (integer) overrides the MODX log_level system setting. Defaults to System Setting.
 *  _debug (integer) triggers debugging information to be set.
 *
 * Filter Paramters
 * ----------------
 * All other parameters act as filters and they depend on the collection being queried. 
 * Any parameter that does not begin with an underscore is considered a filter parameter.
 *
 *
 * Input Operators
 * ---------------
 *  Apply these to the end of any parameter name with a colon, e.g. &firstname:LIKE=`Sue`
 *  E          =   equals
 *  NE         !=  not equal
 *  GT         >   greater than
 *  LT         <   less than
 *  GTE        >=  greater than or equal to
 *  LTE        <=  less than or equal to
 *  LIKE       LIKE -- Query will automatically quote the input value as '%value%'
 *  NOT_LIKE   NOT LIKE
 *  NOT_IN     NOT IN
 *  IN         IN
 *  STARTS_WITH behaves like "LIKE", but quotes the value as 'value%'
 *  ENDS_WITH behaves like "LIKE", but quotes the value as '%value' 
 *
 * 
 * Value Modifiers
 * ---------------
 * Inspired by MODX's Output Filters (see http://goo.gl/bSzfwi), the Query Snippet supports 
 * dynamic inputs via its own "value modifiers" that mimic the syntax used by MODX for its output 
 * filters (aka "output modifiers).  This is useful for building search forms or enabling pagination.  
 * For example, you can change the &_sortby argument dynamically by setting a URL parameter, then you 
 * can adjust your Query snippet call to read the "sortby" $_GET variable:
 *
 *      [[!Query? &_sortby=`sortby:get`]]
 *
 * There are 2 value modifiers included:
 *
 *  get : causes the named value to read from the $_GET array.  $options = default value.
 *  post : causes the named value to read from the $_POST array. $options = default value. 
 *  decode : runs json_decode on the input. Useful if you need to pass an array as an argument.
 *
 * You can also supply your own Snippet names to be used as value modifiers. They should accept the following inputs:
 *  $input : the value sent to the snippet.  E.g. in &_sortby=`xyz:get`, the $input is "xyz"
 *  $options : any extra option. E.g. &_sortby=`xyz:get=123`, the $options is "123". These may be quoted any way you prefer.
 *
 * WARNING: use value modifiers with extreme caution! Query does not perform any data sanitization, so these
 * could be exploited via SQL injection if you exposed a value that should not be exposed (like &_sql).
 * 
 * 
 *
 * Variables
 * ---------
 * @var $modx modX
 * @var $scriptProperties array
 *
 $modx->getAggregates() and getComposites() or you can access the $obj->_aggregates and $obj->_composites directly
 $graph = $xpdo->getGraph('Classname', 1)
 print_r($modx->classMap) -- lets you trace out all avail. objects
 * @package query
 */
$core_path = $modx->getOption('query.core_path','',MODX_CORE_PATH.'components/query/');

// Restricted properties (cannot use the get: and post: convenience methods)

// Process the raw $scriptProperties into filters and control_params.
// We need to translate stuff here due to limitations in the Snippet Syntax.
// See http://rtfm.modx.com/xpdo/2.x/class-reference/xpdoquery/xpdoquery.where
$control_params = array(); $scriptProperties; // not a reference!
$filters = array();
foreach ($scriptProperties as $k => $v) {

    // Dynamically modify values via our "input filters"
    $filter = null;
    $raw_k = $k;
    preg_match("/^(.*):((\w+)(=['`\"]?([^'`\"]*)['`\"]?)?)$/i", $v, $matches);
    if ($matches) {
        $filter = (isset($matches[3]))? $matches[3] : '';
        $x = (isset($matches[1]))? $matches[1] : ''; // whatever's to the left of the filter
        $y = (isset($matches[4]))? $matches[4] : ''; // any option, e.g. :get="my-default"
        $raw_k = $x;
        if (strtolower($filter) == 'get') {
            $v = (isset($_GET[$x]))? $_GET[$x]: $y;
        }
        // Don't use getOption here because it will read db config data if there is no $_POST data
        elseif (strtolower($filter) == 'post') {
            $v = (isset($_POST[$x]))? $_POST[$x]: $y;
        }
        elseif (strtolower($filter) == 'decode') {
            $v = json_decode($matches[1]);
        }
        else {
            $v = $modx->runSnippet($filter,array('input'=>$x,'options'=>$y));
        }
    }

    // All control_params begin with an underscore
    if ($k[0] == '_') {
        $control_params[$k] = $v;
        unset($scriptProperties[$k]);
        continue;
    }
    $modx->toPlaceholder($raw_k,htmlspecialchars($v),'query');
    
    // Modify the keys (i.e. translate the syntax)
    if (strtolower(substr($k,-2))==':e') {
        $k = substr($k,0,-2).':=';
    }
    elseif (strtolower(substr($k,-3))==':ne') {
        $k = substr($k,0,-3).':!=';
    }
    elseif (strtolower(substr($k,-3))==':gt') {
        $k = substr($k,0,-3).':>';
    }
    elseif (strtolower(substr($k,-4))==':gte') {
        $k = substr($k,0,-4).':>=';
    }
    elseif (strtolower(substr($k,-3))==':lt') {
        $k = substr($k,0,-3).':<';
    }
    elseif (strtolower(substr($k,-4))==':lte') {
        $k = substr($k,0,-4).':<=';
    }
    elseif (strtolower(substr($k,-5))==':like') {
        $v = '%'.trim($v,'%').'%';
    }
    elseif (strtolower(substr($k,-9))==':not_like') {
        $k = substr($k,0,-9).':NOT LIKE';
        $v = '%'.trim($v,'%').'%';
    }
    elseif (strtolower(substr($k,-3))==':in') {
        $v = (!is_array($v))? explode(',',$v):$v;
        $v = array_map('trim', $v);
    }
    elseif (strtolower(substr($k,-7))==':not_in') {
        $k = substr($k,0,-7).':NOT IN';
        $v = (!is_array($v))? explode(',',$v):$v;
        $v = array_map('trim', $v);
    }
    elseif (strtolower(substr($k,-12))==':starts_with') {
        $k = substr($k,0,-12).':LIKE';
        $v = trim($v,'%').'%';
    }
    elseif (strtolower(substr($k,-10))==':ends_with') {
        $k = substr($k,0,-10).':LIKE';
        $v = '%'.trim($v,'%');
    }
    
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

// Read the control arguments
$classname = $modx->getOption('_classname', $control_params,'modResource');
$pkg = $modx->getOption('_pkg', $control_params);
$tpl = $modx->getOption('_tpl', $control_params);
$tplOuter = $modx->getOption('_tplOuter', $control_params);
$view = $modx->getOption('_view', $control_params,'table');
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

$old_log_level = $modx->setLogLevel($log_level);

if ($pkg) {
    $parts = explode(':',$pkg);
    if (isset($parts[2])) {
        $modx->addPackage($parts[0],$parts[1],$parts[2]);     
    }
    elseif(isset($parts[1])) {
        $modx->addPackage($parts[0],$parts[1]);
    }
    else {
        $modx->addPackage($parts[0],MODX_CORE_PATH.'components/'.$parts[0].'/model/');
    }
}

$data = array();
$total_pages = 0;
if ($sql) {
    // include SQL_CALC_FOUND_ROWS in your query
    if ($limit) {
        $sql .= ' LIMIT '.$limit;
        if ($offset) {
            $sql .= ' OFFSET '.$offset;    
        }
    }

    $result = $modx->query($sql);
    $data = $result->fetchAll(PDO::FETCH_ASSOC);

    $result2 = $modx->query('SELECT FOUND_ROWS() as total_pages');
    $data2 = $result2->fetch(PDO::FETCH_ASSOC);
    $total_pages = $data2['total_pages'];
}
else {    
    $cols = array();
    if ($select != '*') {
        $cols = explode(',',$select);
        $cols = array_map('trim', $cols);
    }

    $criteria = $modx->newQuery($classname);
    // Graphing potentially needs *all* fields to function, so forcefully restricting it via "select" it is not rec'd
    if (!$graph) {
        $criteria->select($select);
    }
    $criteria->where($filters);
    $total_pages = $modx->getCount($classname,$criteria);
    $criteria->limit($limit, $offset); 
    if ($sortby) {
        $criteria->sortby($sortby,$sortdir);
    }

    if ($graph) {
        $results = $modx->getCollectionGraph($classname,$graph,$criteria);
    }
    else {
        $results = $modx->getCollection($classname,$criteria);
    }
    // TODO: More info displayed here
    if ($debug) {
        $criteria->prepare();
        return '<div><h3>Raw SQL</h3><textarea rows="10" cols="60">'.$criteria->toSQL().'</textarea>
            <h3>POST</h3>
            <textarea rows="10" cols="60">'.print_r($_POST,true).'</textarea>
            <h3>Control Parameters</h3>
            <textarea rows="10" cols="60">'.print_r($control_params,true).'</textarea>
            <h3>Filters</h3>
            <textarea rows="10" cols="60">'.print_r($filters,true).'</textarea>
        </div>';
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
                $this_row[$k] = $modx->getPlaceholder('tmp.'.$k); // with period
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

if (empty($data)) {
    $modx->log(xPDO::LOG_LEVEL_DEBUG,'[Query] No output.');
    return '';
}

$out = '';

// Pagination
if ($total_pages > $limit) {
    require_once $core_path.'model/query/pagination.class.php';
    $P = new Pagination();
    $P->set_base_url($modx->makeUrl($modx->resource->get('id'),'','','abs'));
    $P->set_offset($offset); 
    $P->set_results_per_page($limit);
    $tpls = require $core_path.'config/'.$config.'.config.php';
    $P->set_tpls($tpls);
    $pagination_links = $P->paginate($total_pages);
}

// Default formatting (via a PHP view)
if (!$tpl && !$tplOuter) {
    $view_file = $core_path.'views/'.basename($view,'.php').'.php';    
    if (!file_exists($view_file)) {
        $view_file = $view;
        if (!file_exists($view_file)) {
            $modx->log(xPDO::LOG_LEVEL_ERROR,'[Query] The view file '.$view_file.' does not exist.');
            return 'The view file '.htmlspecialchars($view_file).' does not exist.';
        }
    }
    ob_start();
    include $view_file;
    $out = ob_get_contents();
    ob_end_clean();
}
elseif($tpl) {

    $use_tmp_chunk = false;
    if (!$innerChunk = $modx->getObject('modChunk', array('name' => $tpl))) {
        $use_tmp_chunk = true; // No chunk was passed... a formatting string was passed instead.
    }
    
    foreach ($data as $r) {
        if (is_object($r)) $r = $r->toArray('',false,false,true); // Handle xPDO objects
        // Use a temporary Chunk when dealing with raw formatting strings
        if ($use_tmp_chunk) {
            $uniqid = uniqid();
            $innerChunk = $modx->newObject('modChunk', array('name' => "{tmp-inner}-{$uniqid}"));
            $innerChunk->setCacheable(false);    
            $out .= $innerChunk->process($r, $tpl);
        }
        // Use getChunk when a chunk name was passed
        else {
            $out .= $modx->getChunk($tpl, $r);
        }
    }

// Old version
//    foreach($data as $d) {
//        $out .= $modx->getChunk($tpl,$d);
//    }

}
if ($tplOuter) {
    $props = array('content'=>$out);
    // Formatting String
    if (!$outerChunk = $modx->getObject('modChunk', array('name' => $tplOuter))) {  
        $uniqid = uniqid();
        $outerChunk = $modx->newObject('modChunk', array('name' => "{tmp-outer}-{$uniqid}"));
        $outerChunk->setCacheable(false);    
        $out = $outerChunk->process($props, $tplOuter);        
    }
    // Chunk Name
    else {
        $out = $modx->getChunk($tplOuter, $props);
    }

//    $out = $modx->getChunk($tplOuter, array('content'=>$out));
}

// Set placeholders
$modx->setPlaceholder('page_count',$total_pages);
$modx->setPlaceholder('results',$out);

$modx->setLogLevel($old_log_level);

return $out;