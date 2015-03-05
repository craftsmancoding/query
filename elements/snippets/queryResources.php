<?php
/**
 * @name queryResources
 * @description Like Query, but only for searching on modResource using TV optimizations. Filter arguments include modResource column names and any TV names.
 *
 *
 *  Sortby only works for regular columns.
 *
 * There are 3 types of queries that could be triggers:
 *  1. Filters applied to modx_site_content columns
 *  2. Filters applied to TV (i.e. virtual columns)
 *  3. Filters applied to both built-in and TV columns
 *
 * The process goes like this:
 *
 * Use filters to get all matching page ids from modx_site_content.
 * Iterate over each TV filter get all matching page ids from modx_site_tmplvar_contentvalues
 * Find the intersect of the arrays of page ids
 * Load data from modx_site_content (limiting by select columns where applicable)
 * Load data from modx_site_tmplvar_contentvalues (limiting by select columns where applicable)
 * Normalize the result set so that each row has the same keys in its array.
 * Format and return the result.
 *
 * USAGE
 *
 *  E.g. search by TVs:
 *  [[queryResources? &city=`city:get` &state=`state:get` &_view=`json`]]
 *
 *  Search by both TVs and regular columns (interface should be the same)
 *  [[queryResources? &published=`1` &city=`city:get` &state=`state:get` &_view=`json`]]
 *
 * No Results - rely on MODX output filters.
 *
 *  [[queryResults:empty=`No results found`]]
 *
 *
 * Copyright 2015 by Everett Griffiths <everett@craftsmancoding.com>

 *
 * Control Parameters
 * ------------------
 * All "control" parameters begin with an underscore. They affect the functionality or formatting of the output.

 *  @param string _tpl chunk or formatting-string to format each record in the collection
 *  @param string  _tplOuter chunk or formatting-string to wrap the result set. Requires the [[+content]] placeholder.
 *  @param string _view oldschool php file to format the output, see the views folder.
 *      Some samples are provided, e.g. 'table', 'json'. If _tpl
 *      and _tplOuter are provided, the _view parameter is ignored.  Default: table.php
 *  @param integer _limit limits the number of results returned, also sets the results shown per page.
 *  @param integer _offset offsets the first record returned, e.g. for pagination.
 *  @param string _sortby column to sort by
 *  @param string _sortdir sort direction. Usually ASC or DESC, but may also contain complex sorting rules.
 *  @param string _style one of Pagination's styles (see https://github.com/craftsmancoding/pagination)
 *  @param string _select controls which columns to select for a getIterator. Default: *
 *  @param string _config sets a pagination formatting pallette, e.g. "default".
 *      Corresponding file must exist inside the config directory, e.g. "default.config.php"
 *  @param integer _log_level overrides the MODX log_level system setting. Defaults to System Setting.
 *  @param boolean _debug triggers debugging information to be set.
 *  @param string _rename JSON hash used to rename any output attributes, for easier use in Ajax requests. E.g. if your Ajax script required the results include attributes named "city" and "desc" but your pages stored this info in the pagetitle and description fields, you can rename the values by setting `{"pagetitle":"city","description":"desc"}  i.e. OLD Value:New Value
 *
 * Filter Parameters
 * ----------------
 * Any parameter that does not begin with an underscore is considered a filter parameter.
 * A filter parameter should be a regular column from the modx_site_content table, otherwise
 * it is considered a TV name (i.e. a virtual column)
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
 * Inspired by MODX's Output Filters (see http://goo.gl/bSzfwi), the Query & queryResources Snippets support
 * dynamic inputs via "value modifiers" that mimic the syntax used by MODX for its output
 * filters (aka "output modifiers).  This is useful for building search forms or enabling pagination.
 * For example, you can change the &id argument dynamically by setting a URL parameter, then you
 * can adjust your Query snippet call to read the $_GET['pageid'] variable:
 *
 *      [[!queryResources? &id=`pageid:get`]]
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
 * @package query
 */
// Caching needs to encapsulate changes in GET and POST since input filters mean that the $scriptProperties may not change
$cache_opts = array(xPDO::OPT_CACHE_KEY => 'query');
$lifetime = 0;
$fingerprint = md5('queryResources'.serialize(array($scriptProperties,$_POST,$_GET)));
if ($results = $modx->cacheManager->get($fingerprint, $cache_opts))
{
    $modx->log(xPDO::LOG_LEVEL_INFO,'[queryResources] returning results from cache.');
    $modx->setPlaceholder('page_count',$results['page_count']);
    $modx->setPlaceholder('results',$results['results']);
    $modx->setPlaceholder('pagination_links',$results['pagination_links']);

    return $results['results'];
}

$core_path = $modx->getOption('query.core_path','',MODX_CORE_PATH.'components/query/');
require_once $core_path .'vendor/autoload.php';

// Read TVs from Cache
if (!$tvlookup_by_name = $modx->cacheManager->get('tvlookup_by_name', $cache_opts))
{
    $query = $modx->newQuery('modTemplateVar');
    $query->select(array('id','name'));
    $tvs = $modx->getIterator('modTemplateVar', $query);
    $tvlookup_by_name = array();
    $tvlookup_by_id = array();
    foreach ($tvs as $t)
    {
        $tvlookup_by_name[$t->get('name')] = $t->get('id');
        $tvlookup_by_id[$t->get('id')] = $t->get('name');
    }

    $modx->cacheManager->set('tvlookup_by_name', $tvlookup_by_name, $lifetime, $cache_opts);
    $modx->cacheManager->set('tvlookup_by_id', $tvlookup_by_id, $lifetime, $cache_opts);
}

$tvlookup_by_name = $modx->cacheManager->get('tvlookup_by_name', $cache_opts);
$tvlookup_by_id = $modx->cacheManager->get('tvlookup_by_id', $cache_opts);



$page_cols = array_keys($modx->getFields('modResource'));


// TODO: Restricted properties (cannot use the get: and post: convenience methods)
// Process the raw $scriptProperties into filters and control_params.
// FYI: We need to translate some stuff here (e.g. '<=' becomes 'LTE') due to limitations in the Snippet Syntax.
// See http://rtfm.modx.com/xpdo/2.x/class-reference/xpdoquery/xpdoquery.where
$control_params = array();
//$scriptProperties; // not a reference!
$filters = array();
$tvfilters = array();
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

    // Does the column belong to the site_content table? Or does it represent a TV?
    if (strpos($k, ':') !== false)
    {
        $tmp = explode(':', $k);
        if (count($tmp) == 2)
        {
            $column_name = $tmp[0];
        }
        else
        {
            $column_name = $tmp[1];
        }
    }
    else
    {
        $column_name = $k;
    }

    if (in_array($column_name, $page_cols))
    {
        $filters[$k] = $v;
    }
    else
    {
        $k = str_replace($column_name,'value',$k);
        $tvfilters[] = array(
            'tvname' => $column_name,
            'filter' => $k,
            'value' => $v
        );
    }
}

// Read the control arguments
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
$select = $modx->getOption('_select', $control_params,'*');
$log_level = (int) $modx->getOption('_log_level', $control_params,$modx->getOption('log_level'));
$config = basename($modx->getOption('_config', $control_params,'default'),'.config.php');
$debug = (int) $modx->getOption('_debug', $control_params);
$rename = $modx->getOption('_rename', $control_params);

if ($rename && !is_array($rename))
{
    $rename = json_decode($rename,true);
    if(!is_array($rename))
    {
        $modx->log(xPDO::LOG_LEVEL_DEBUG,'[queryResources] _map input must be a valid JSON hash');
    }
}


$old_log_level = $modx->setLogLevel($log_level);

// Get page ids from primary query (if filters are present)
$intersects = array();
if ($filters)
{
    $criteria = $modx->newQuery('modResource');
    $criteria->select('id');
    $criteria->where($filters);

    if ($results = $modx->getIterator('modResource',$criteria))
    {
        $this_set = array();
        foreach ($results as $r)
        {
            $this_set[] = $r->get('id');
        }
        $intersects[] = $this_set;
    }
}

if ($tvfilters)
{
    foreach($tvfilters as $tf)
    {
        if (empty($tf['value'])) continue;
        
        $criteria = $modx->newQuery('modTemplateVarResource');
        $criteria->select('contentid');
        $this_filter = array(
            'tmplvarid' => $tvlookup_by_name[$tf['tvname']],
            $tf['filter'] => $tf['value']
        );

        $criteria->where($this_filter);
        if ($results = $modx->getIterator('modTemplateVarResource',$criteria))
        {
            $this_set = array();
            foreach ($results as $r)
            {
                $this_set[] = $r->get('contentid');
            }
            $intersects[] = $this_set;
        }
    }
}


if (count($intersects) > 1)
{
    $intersects = call_user_func_array('array_intersect', $intersects);
}
else
{
    $intersects = array_shift($intersects);
}
// Page ids here!
$intersects = array_values($intersects);
$record_count = count($intersects);
if ($debug) {
    return '<div><h2><code>queryResources</code> Snippet Debug</h2><h3>Primary Filters</h3><textarea rows="10" cols="60">'.print_r($filters,true).'</textarea>
        <h3>TV Filters</h3>
        <textarea rows="10" cols="60">'.print_r($tvfilters,true).'</textarea>
        <h3>Matching Page IDs</h3>
        <textarea rows="10" cols="60">'.print_r($intersects,true).'</textarea>
    </div>';
}

$real_cols = array(); // real columns in modx_site_content
$virtual_cols = array(); // virtual columns are TVs
if ($select != '*') {
    $cols = explode(',',$select);
    $cols = array_map('trim', $cols);
    $virtual_cols = array_diff($cols, $page_cols);
    $real_cols = array_intersect($page_cols, $cols);
    if (!in_array('id', $real_cols))
    {
        $real_cols[] = 'id'; // make sure we have the pk
    }
}



$data = array();
$tvdata = array();


/*
 * Load up TVs ONLY when needed (i.e. if virtual columns were specified)
 * Format should be:
 * array(
 *  [page_id] => array(
 *      [tv-name] => tv-value
 *      [other-tv-name] => other value
 *   ),
 *   // ...etc...
 * )
 */
if ($virtual_cols || $select == '*') {
    $criteria = $modx->newQuery('modTemplateVarResource');

// Get 'em all
    if (empty($virtual_cols) && $select == '*') {
        $this_filter = array(
            'contentid:IN' => $intersects,
        );
    } // Only get the ones specified
    else {
        $virtual_col_ids = array();
        foreach ($virtual_cols as $vc) {
            $virtual_col_ids[] = $tvlookup_by_name[$vc];
        }
        $this_filter = array(
            'contentid:IN' => $intersects,
            'tmplvarid:IN' => $virtual_col_ids
        );
    }

    $criteria->where($this_filter);

    if ($results = $modx->getIterator('modTemplateVarResource', $criteria)) {
        //return $criteria->toSQL();
        foreach ($results as $r) {
            $tvdata[$r->get('contentid')][$tvlookup_by_id[$r->get('tmplvarid')]] = $r->get('value');
        }
    }
}
// Load up the base pages
$criteria = $modx->newQuery('modResource');
$criteria->select($real_cols); // Only the built-in columns here
$criteria->where(array('id:IN'=>$intersects));

$criteria->limit($limit, $offset);
if ($sortby)
{
    $criteria->sortby($sortby,$sortdir);
}


// Load up pages
if($results = $modx->getIterator('modResource',$criteria))
{
    foreach ($results as $r) {
        $this_row = $r->toArray('',false,true);
        if (isset($tvdata[ $r->get('id') ]))
        {
            $this_row = array_merge($this_row, $tvdata[ $r->get('id') ]);
        }
        // Map...
        if ($rename)
        {
            foreach ($rename as $old => $new)
            {
                if (isset($this_row[$old]))
                {
                   $this_row[$new] = $this_row[$old];
                   unset($this_row[$old]);
                }
            }
        }
        $data[] = $this_row;
    }
}
if (empty($data)) {
    $modx->log(xPDO::LOG_LEVEL_DEBUG,'[queryResources] No output.');
    return '';
}

$out = '';
$pagination_links = '';

// Pagination
if ($limit && $record_count > $limit) {

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
elseif($tpl)
{

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