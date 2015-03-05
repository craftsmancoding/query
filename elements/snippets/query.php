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
 * Copyright 2014 by Everett Griffiths <everett@craftsmancoding.com>
 * Created on 05-12-2013
 * 
 * Control Parameters
 * ------------------
 * All "control" parameters begin with an underscore. They affect the functionality or formatting of the output.
 *
 *  @param string _classname classname of the object collection you are querying. [default=modResource]
 *  @param string _pkg colon-separated string defining the arguments for addPackage() - package_name, model_path, and optionally table_prefix e.g. `tiles:[[++core_path]]components/tiles/model/:tiles_` or if only the package name is supplied, the path is assumed to be "[[++core_path]]components/$package_name/model/"
 *  @param string _tpl chunk or formatting-string to format each record in the collection
 *  @param string _tplOuter chunk or formatting-string to wrap the result set. Requires the [[+content]] placeholder.
 *  @param string _view oldschool php file to format the output, see the views folder.
 *      Some samples are provided, e.g. 'table', 'json'. If _tpl
 *      and _tplOuter are provided, the _view parameter is ignored.  Default: table.php
 *  @param integer _limit limits the number of results returned, also sets the results shown per page.
 *  @param integer _offset offsets the first record returned, e.g. for pagination.
 *  @param string _sortby column to sort by
 *  @param string _sortdir sort direction. Usually ASC or DESC, but may also contain complex sorting rules.
 *  @param string _sql used to issue a raw SQL query.
 *  @param string _style one of Pagination's styles (see https://github.com/craftsmancoding/pagination)
 *  @param string _graph triggers a getCollectionGraph.
 *  @param string _select controls which columns to select for a getCollection. Ignored when _graph is set. Default: *
 *  @param string _config sets a pagination formatting pallette, e.g. "default". Corresponding file must exist inside the config directory, e.g. "default.config.php"
 *  @param integer _log_level overrides the MODX log_level system setting. Defaults to System Setting.
 *  @param boolean _debug triggers debugging information to be set.
 *
 * Filter Parameters
 * ----------------
 * All other parameters act as query filters and they depend on the collection being queried.
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
 * Input Value Modifiers
 * ---------------
 * Inspired by MODX's Output Filters (see http://goo.gl/bSzfwi), the Query Snippet supports 
 * dynamic inputs via its own "value modifiers" that mimic the syntax used by MODX for its output 
 * filters (aka "output modifiers).  This is useful for building search forms or enabling pagination.  
 * For example, you can change the &_sortby argument dynamically by setting a URL parameter, then you 
 * can adjust your Query snippet call to read the "sortby" $_GET variable:
 *
 *      [[!Query? &_sortby=`sortby:get`]]
 *
 * There are 3 value modifiers included:
 *
 *  get : causes the named value to read from the $_GET array.  $options = default value.
 *  post : causes the named value to read from the $_POST array. $options = default value. 
 *  decode : runs json_decode on the input. Useful if you need to pass an array as an argument.
 *
 * You can also supply your own Snippet names to be used as value modifiers instead of relying on the included get, post
 * and decode. Your custom value modifiers should accept the following inputs
 *  $input : the value sent to the snippet.  E.g. in &_sortby=`xyz:customvaluemodifier`, the $input is "xyz"
 *  $options : any extra option. E.g. &_sortby=`xyz:customvaluemodifier=123`, the $options is "123". These may be quoted any way you prefer.
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
 * $modx->getAggregates() and getComposites() or you can access the $obj->_aggregates and $obj->_composites directly
 * $graph = $xpdo->getGraph('Classname', 1)
 * print_r($modx->classMap) -- lets you trace out all avail. objects
 * @package query
 */

// Caching needs to encapsulate changes in GET and POST since input filters mean that the $scriptProperties may not change
$cache_opts = array(xPDO::OPT_CACHE_KEY => 'query');
$lifetime = 0;
$fingerprint = md5('query'.serialize(array($scriptProperties,$_POST,$_GET)));
if ($results = $modx->cacheManager->get($fingerprint, $cache_opts))
{
    $modx->setPlaceholder('page_count',$results['page_count']);
    $modx->setPlaceholder('results',$results['results']);
    $modx->setPlaceholder('pagination_links',$results['pagination_links']);

    return $results['results'];
}

$core_path = $modx->getOption('query.core_path','',MODX_CORE_PATH.'components/query/');
require_once $core_path .'vendor/autoload.php';
// TODO: Restricted properties (cannot use the get: and post: convenience methods)
// Process the raw $scriptProperties into filters and control_params.
// FYI: We need to translate some stuff here (e.g. '<=' becomes 'LTE') due to limitations in the Snippet Syntax.
// See http://rtfm.modx.com/xpdo/2.x/class-reference/xpdoquery/xpdoquery.where
$control_params = array();
//$scriptProperties; // not a reference!
$filters = array();
// We track which placeholders we set
$placeholder_keys = array();
$page_count = 1; // default is one page

foreach ($scriptProperties as $k => $v) {

    // Dynamically modify values via our "input filters"
    $filter = null;
    $raw_k = $k;
    // $v might be something like `year:get=2012`
    preg_match("/^(.*):((\w+)(=['`\"]?([^'`\"]*)['`\"]?)?)$/i", $v, $matches);
    if ($matches) {
        $filter = (isset($matches[3]))? $matches[3] : '';
        $x = (isset($matches[1]))? $matches[1] : ''; // whatever's to the left of the filter, e.g. 'year'
        $y = (isset($matches[4]))? $matches[4] : ''; // any option, e.g. ="2012"

        // Input Modifiers
        // Don't use getOption here because it will read db config data if there is no $_POST data!
        if (strtolower($filter) == 'get') {
            $v = (isset($_GET[$x]))? $_GET[$x]: ltrim($y,'=');
        }
        elseif (strtolower($filter) == 'post') {
            $v = (isset($_POST[$x]))? $_POST[$x]: ltrim($y,'=');
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

    // Placeholders are used for debugging, raw SQL, and ???
    $placeholder_keys[] = 'query.'.$raw_k;
    $modx->toPlaceholder($raw_k,htmlspecialchars($v),'query');

    // Modify the keys (i.e. translate the syntax to xPDO)
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
$style = $modx->getOption('_style', $control_params, 'default');
$graph = $modx->getOption('_graph', $control_params);
$select = $modx->getOption('_select', $control_params,'*');
$log_level = (int) $modx->getOption('_log_level', $control_params,$modx->getOption('log_level'));
$config = basename($modx->getOption('_config', $control_params,'default'),'.config.php');
$debug = (int) $modx->getOption('_debug', $control_params);

$old_log_level = $modx->setLogLevel($log_level);

// Load up any custom packages
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
$record_count = 0;
// Run raw sql?
if ($sql) {
    // include SQL_CALC_FOUND_ROWS in your query
    if ($limit) {
        $sql .= ' LIMIT '.$limit;
        if ($offset) {
            $sql .= ' OFFSET '.$offset;    
        }
    }
    // Quote any placeholders in case they are used in the query
    $ph = array();
    foreach ($placeholder_keys as $k) {
        $ph[$k] = $modx->quote($modx->getPlaceholder($k));
        $sql = str_replace('[[+'.$k.']]', $ph[$k], $sql);
    }
    if ($debug) {
        return '<div><h2><code>Query</code> Snippet Debug</h2><h3>Raw SQL</h3><textarea rows="10" cols="60">'.$sql.'</textarea>
            <h3>Placeholders</h3>
            <textarea rows="10" cols="60">'.print_r($ph,true).'</textarea>
            <h3>POST</h3>
            <textarea rows="10" cols="60">'.print_r($_POST,true).'</textarea>
        </div>';
    }

    $result = $modx->query($sql);
    $data = $result->fetchAll(PDO::FETCH_ASSOC);

    $result2 = $modx->query('SELECT FOUND_ROWS() as total_pages');
    $data2 = $result2->fetch(PDO::FETCH_ASSOC);
    //return '<pre>'.print_r($data2,true).'</pre>';
    $record_count = $data2['total_pages'];
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
    $record_count = $modx->getCount($classname,$criteria);
    $criteria->limit($limit, $offset); 
    if ($sortby) {
        $criteria->sortby($sortby,$sortdir);
    }

    if ($graph) {
        $results = $modx->getCollectionGraph($classname,$graph,$criteria);

        //$criteria->bindGraph($graph);
        //return print_r($results,true);
    }
    else {
        $results = $modx->getCollection($classname,$criteria);
    }
    // TODO: More info displayed here
    if ($debug) {
        $criteria->prepare();
        return '<div><h2><code>Query</code> Snippet Debug</h2><h3>Raw SQL</h3><textarea rows="10" cols="60">'.$criteria->toSQL().'</textarea>
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
                $clean_k = substr($k,4); // remove the tmp.
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
$pagination_links = '';

// Pagination
if ($limit && $record_count > $limit) {
    
    //Pagination\Pager::style($style);
    $pagination_links = Pagination\Pager::links($record_count, $offset, $limit)
        ->setBaseUrl($modx->makeUrl($modx->resource->get('id'),'','','abs'))
        ->style($style);
    $page_count = ceil(record_count / $limit);
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
}

// Set placeholders
$modx->setPlaceholder('page_count',$page_count);
$modx->setPlaceholder('results',$out);
$modx->setPlaceholder('pagination_links',$pagination_links);
$modx->setLogLevel($old_log_level);

$results = array(
    'page_count' => $page_count,
    'results' => $out,
    'pagination_links' => $pagination_links,
);

// Cache the lookup
$modx->cacheManager->set($fingerprint, $results, $lifetime, $cache_opts);

return $out;