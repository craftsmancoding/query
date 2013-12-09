<?php
/**
 * RepoMan Universal Build Script 
 * 
 * Executable via the command line or via a web request.
 *
 * @author everett@craftsmancoding.com
 */

/**
 * Used to get parameters out of a (PHP) docblock so you can easily document your 
 * code.
 *
 * @param string $string the unparsed contents of a file
 * @param string $dox_start string designating the start of a comment (dox) block
 * @param string $dox_start string designating the start of a comment (dox) block 
 * @return array on success | false on no doc block found
 */
function get_attributes_from_dox($string,$dox_start='/*',$dox_end='*/') {
    
    $dox_start = preg_quote($dox_start,'#');
    $dox_end = preg_quote($dox_end,'#');


    // Any tags to skip in the doc block, e.g. @param, that may have significance for PHPDoc and 
    // for general documentation, but which are not intended for RepoMan and do not describe
    // object attributes. Omit "@" from the attribute names.
    // See http://en.wikipedia.org/wiki/PHPDoc
    $skip_tags = array('param','return','abstract','access','author','copyright','deprecated',
        'deprec','example','exception','global','ignore','internal','link','magic',
        'package','see','since','staticvar','subpackage','throws','todo','var','version'
    );

    preg_match("#$dox_start(.*)$dox_end#msU", $string, $matches);

    if (!isset($matches[1])) {
            return false; // No doc block found!
    }
    
    // Get the docblock                
    $dox = $matches[1];
    
    // Loop over each line in the comment block
    $a = array(); // attributes
    foreach(preg_split('/((\r?\n)|(\r\n?))/', $dox) as $line){
        preg_match('/^\s*\**\s*@(\w+)(.*)$/',$line,$m);
        if (isset($m[1]) && isset($m[2]) && !in_array($m[1], $skip_tags)) {
                $a[$m[1]] = trim($m[2]);
        }
    }
    
    return $a;
}

// Start the stopwatch...
$mtime = microtime();
$mtime = explode(' ', $mtime);
$mtime = $mtime[1] + $mtime[0];
$tstart = $mtime;
// Prevent global PHP settings from interrupting
set_time_limit(0);
 
if (!file_exists(dirname(__FILE__).'/build.config.php')) {
    print "This build script expects package details to be defined inside of a build.config.php file.\n";
    print "Please create a valid build.config.php file inside of ".dirname(__FILE__)."\n";
    die();
}
print "Loading config...<br/>";
include_once(dirname(__FILE__).'/build.config.php');
print "Building package ".PKG_NAME."<br/>";

// As long as this script is built placed inside a MODX docroot, this will sniff out
// a valid MODX_CORE_PATH.  This will effectively force the MODX_CONFIG_KEY too.
// The config key controls which config file will be loaded. 
// Syntax: {$config_key}.inc.php
// 99.9% of the time this will be "config", but it's useful when dealing with
// dev/prod pushes to have a config.inc.php and a prod.inc.php, stg.inc.php etc.
if (!defined('MODX_CORE_PATH') && !defined('MODX_CONFIG_KEY')) {
    $max = 10;
    $i = 0;
    $dir = dirname(__FILE__);
    while(true) {
        if (file_exists($dir.'/config.core.php')) {
            include $dir.'/config.core.php';
            break;
        }
        $i++;
        $dir = dirname($dir);
        if ($i >= $max) {
            print "Could not find a valid config.core.php file.\n";
            print "Make sure your repo is inside a MODX webroot and try again.\n";
            die();
        }
	}
}

print "Loading {$dir}/config.core.php<br/>";

if (!defined('MODX_CORE_PATH') || !defined('MODX_CONFIG_KEY')) {
    print "Somehow the loaded config.core.php did not define both MODX_CORE_PATH and MODX_CONFIG_KEY constants.\n";
    die();    
}

if (!file_exists(MODX_CORE_PATH.'model/modx/modx.class.php')) {
    print "modx.class.php not found at ".MODX_CORE_PATH."\n";
    die();
}
require_once(MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php');

// fire up MODX
require_once MODX_CORE_PATH.'model/modx/modx.class.php';
$modx = new modx();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO'); 
print '<pre>'; 
flush();

$modx->loadClass('transport.modPackageBuilder', '', false, true);
$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAME, PKG_VERSION, PKG_RELEASE);
$builder->registerNamespace(PKG_NAME_LOWER, false, true, '{core_path}components/' . PKG_NAME_LOWER.'/');

//------------------------------------------------------------------------------
//! Categories
//------------------------------------------------------------------------------
$cat_attributes = array(
    xPDOTransport::PRESERVE_KEYS => true,
    xPDOTransport::UPDATE_OBJECT => false,
    xPDOTransport::UNIQUE_KEY => array('category'),
	xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array (
        'Snippets' => array(
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ),
        'Chunks' => array (
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ),
        'Plugins' => array (
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
			xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array (
		        'PluginEvents' => array(
		            xPDOTransport::PRESERVE_KEYS => true,
		            xPDOTransport::UPDATE_OBJECT => false,
		            xPDOTransport::UNIQUE_KEY => array('pluginid','event'),
		        ),
    		),
        ),
    )    
);
    
$Category = $modx->newObject('modCategory');
$Category->set('category', PKG_NAME);


//------------------------------------------------------------------------------
//! Snippets
//------------------------------------------------------------------------------
$dir = dirname(dirname(__FILE__)).'/core/components/'.PKG_NAME_LOWER.'/elements/snippets/';
$objects = array();
if (file_exists($dir) && is_dir($dir)) {
    print 'Packaging snippets from '.$dir."\n";
    $files = glob($dir.'*.php');
    foreach($files as $f) {
        $Obj = $modx->newObject('modSnippet');
        $content = file_get_contents($f);
        $attributes = get_attributes_from_dox($content);
        $Obj->fromArray($attributes);
        $Obj->setContent($content);
        $name = $Obj->get('name');
        if (empty($name)) {
            $name = basename($f,'.php');
            $name = basename($name,'.snippet');
            $Obj->set('name',$name);
        }
        $objects[] = $Obj;   
    }
    $Category->addMany($objects);    
}
else {
    print "No Snippets found in {$dir}\n";
}


//------------------------------------------------------------------------------
//! Chunks
//------------------------------------------------------------------------------
$dir = dirname(dirname(__FILE__)).'/core/components/'.PKG_NAME_LOWER.'/elements/chunks/';
$objects = array();
if (file_exists($dir) && is_dir($dir)) {
    print 'Packaging chunks from '.$dir."\n";
    $files = glob($dir.'*.*');
    foreach($files as $f) {
        $Obj = $modx->newObject('modChunk');
        $content = file_get_contents($f);
        $attributes = get_attributes_from_dox($content,'<!--','-->');
        $Obj->fromArray($attributes);
        $Obj->setContent($content);
        $name = $Obj->get('name');
        if (empty($name)) {
            $name = basename($f,'.php');
            $name = basename($name,'.chunk');
            $Obj->set('name',$name);
        }
        $objects[] = $Obj;   
    }
    
    $Category->addMany($objects);    
}
else {
    print "No Chunks found in {$dir}\n";
}

//------------------------------------------------------------------------------
//! Plugins
//------------------------------------------------------------------------------
$dir = dirname(dirname(__FILE__)).'/core/components/'.PKG_NAME_LOWER.'/elements/plugins/';
$objects = array();
if (file_exists($dir) && is_dir($dir)) {
    print 'Packaging plugins from '.$dir."\n";
    $files = glob($dir.'*.php');
    foreach($files as $f) {
        $events = array();
        $Obj = $modx->newObject('modPlugin');
        $content = file_get_contents($f);
        $attributes = get_attributes_from_dox($content);
        // if Events...
        if (isset($attributes['events'])) {
            $event_names = explode(',',$attributes['events']);
            foreach ($event_names as $e) {
                $Event = $modx->newObject('modPluginEvent');
                $Event->set('event',trim($e));
                $events[] = $Event;
            }
        }
        $Obj->fromArray($attributes);
        $Obj->setContent($content);
        $name = $Obj->get('name');
        if (empty($name)) {
            $name = basename($f,'.php');
            $name = basename($name,'.plugin');
            $Obj->set('name',$name);
        }
        $Obj->addMany($events);
        $objects[] = $Obj;   
    }
    $Category->addMany($objects);    
}
else {
    print "No Plugins found in {$dir}\n";
}

$vehicle = $builder->createVehicle($Category, $cat_attributes);

    //------------------------------------------------------------------------------
    //! Files
    //------------------------------------------------------------------------------
    // Assets
    $dir = dirname(dirname(__FILE__)).'/assets/components/'.PKG_NAME_LOWER;
    if (file_exists($dir) && is_dir($dir)) {
        print "Adding Asset files from $dir<br/>";
        $vehicle->resolve('file', array(
            'source' => $dir,
            'target' => "return MODX_ASSETS_PATH . 'components/';",
        ));
        //$builder->putVehicle($vehicle);
    }
    else {
        print "No asset files found.";
    }
    
    // Core
    $dir = dirname(dirname(__FILE__)).'/core/components/'.PKG_NAME_LOWER;
    if (file_exists($dir) && is_dir($dir)) {
        print "Adding Core files $dir<br/>";
        $vehicle->resolve('file', array(
            'source' => $dir,
            'target' => "return MODX_CORE_PATH . 'components/';",
        ));
        //$builder->putVehicle($vehicle);
    }
    else {
        print "No core files found.";
    }

    //------------------------------------------------------------------------------
    //! Resolvers
    //------------------------------------------------------------------------------
    $dir = dirname(__FILE__).'/resolvers/';
    $objects = array();
    if (file_exists($dir) && is_dir($dir)) {
        print 'Packaging Resolvers from '.$dir."\n";
        $files = glob($dir.'*.php');
        foreach($files as $f) {
            print 'Resolver '.$f."\n";
            $vehicle->resolve('php', array('source' => $f));
            //$builder->putVehicle($vehicle);
        }
    }
    else {
        print "No Resolvers found in {$dir}\n";
    }

$builder->putVehicle($vehicle);



//------------------------------------------------------------------------------
//! System Settings
//------------------------------------------------------------------------------
$attributes = array(
	xPDOTransport::UNIQUE_KEY => 'key',
	xPDOTransport::PRESERVE_KEYS => true,
	xPDOTransport::UPDATE_OBJECT => false,	
);
$filenames = array(
    dirname(__FILE__).'/data/transport.settings.php',
    dirname(__FILE__).'/data/settings.php'
);
foreach ($filenames as $file) {
    if (file_exists($file)) {
        $settings = include($file);
        if (is_array($settings)) {
            foreach($settings as $s) {
                $Setting = $modx->newObject('modSystemSetting');
                $Setting->fromArray($s,'',true,true);
                $vehicle = $builder->createVehicle($Setting, $attributes);
                $builder->putVehicle($vehicle);            
            }
        }
    }
}


//------------------------------------------------------------------------------
//! Actions and Menus (CMP)
//------------------------------------------------------------------------------
$menu_attributes = array(
   xPDOTransport::PRESERVE_KEYS => true,
   xPDOTransport::UPDATE_OBJECT => true,
   xPDOTransport::UNIQUE_KEY => 'text',
   xPDOTransport::RELATED_OBJECTS => true,
   xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array(
       'Action' => array(
           xPDOTransport::PRESERVE_KEYS => false,
           xPDOTransport::UPDATE_OBJECT => true,
           xPDOTransport::UNIQUE_KEY => array(
               'namespace',
               'controller'
           ),
       ),
   ),
);
if (file_exists(dirname(__FILE__).'/data/transport.menus.php')) {
    $menus = include(dirname(__FILE__).'/data/transport.menus.php');
    foreach ($menus as $menu) {
        $vehicle = $builder->createVehicle($menu, $menu_attributes);
        $builder->putVehicle($vehicle);
    }
}


//------------------------------------------------------------------------------
//! DOCS
//------------------------------------------------------------------------------
$dir = dirname(dirname(__FILE__)).'/core/components/'.PKG_NAME_LOWER.'/docs/';
if (file_exists($dir) && is_dir($dir)) {
    $docs = array(
        'readme'=>'No readme defined.',
        'changelog'=>'No changelog defined.',
        'license'=>'No license defined.'
    );
    $files = glob($dir.'*.{html,txt}',GLOB_BRACE);
    foreach($files as $f) {
        $stub = basename($f,'.txt');
        $stub = basename($stub,'.html');
        $docs[$stub] = file_get_contents($f);
        print "Adding doc $stub for $f";
    }

    if (!empty($docs)) {
        $builder->setPackageAttributes($docs);
    }
}
else {
    print "No docs found in $dir";
}



// Zip up the package
$builder->pack();

// tiles-1.0-pl.transport.zip
print '<br/>Package complete. Check your '.MODX_CORE_PATH . 'packages/ directory for the newly created package.';
print '</pre>';
$zip = PKG_NAME_LOWER.'-'.PKG_VERSION.'-'.PKG_RELEASE.'.transport.zip';
print MODX_CORE_PATH.'packages/'.$zip;
//print '<a href="'.MODX_CORE_PATH.'packages/'.$zip.'">Download '.$zip.'</a>';
/*EOF*/