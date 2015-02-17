<?php
/**
 * @name QueryCacheClean
 * @description Remove results cached by the Query and queryResource Snippets.
 * @PluginEvents OnBeforeCacheUpdate
 */

$cache_dir = 'query';
switch ($modx->event->name) {

    //------------------------------------------------------------------------------
    //! OnBeforeCacheUpdate
    //  Clear out our custom cache files.
    //------------------------------------------------------------------------------
    case 'OnBeforeCacheUpdate':
        $modx->log(modX::LOG_LEVEL_DEBUG,'[QueryCacheClean plugin] ','OnBeforeCacheUpdate');
        $modx->cacheManager->clean(array(xPDO::OPT_CACHE_KEY => $cache_dir));
        break;
}
/*EOF*/