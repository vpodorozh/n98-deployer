<?php
/**
 * @copyright Copyright (c) 2017 netz98 GmbH (http://www.netz98.de)
 *
 * @see LICENSE
 */

namespace N98\Deployer\Config;

use N98\Deployer\Service\GetReleasesNameService;

/**
 * ReleaseConfig
 */
class ReleaseConfig
{
    /**
     * Register Config Proxies that are executed when config is fetched the first time
     */
    public static function register()
    {
        \Deployer\set('release_path_app', function () { return ReleaseConfig::getReleasePathAppDir(); });
        \Deployer\set('shared_path_app', function () { return ReleaseConfig::getSharedPathAppDir(); });

        \Deployer\set('release_name', function () { return GetReleasesNameService::execute(); });
    }

    /**
     * @return string
     */
    public static function getReleasePathAppDir()
    {
        return self::buildAppPath('{{release_path}}');
    }

    /**
     * @return string
     */
    public static function getSharedPathAppDir()
    {
        return self::buildAppPath('{{deploy_path}}/shared');
    }

    /**
     * @return string
     */
    public static function getAppDir()
    {
        return \Deployer\get('app_dir');
    }

    /**
     * @param string $appPath
     *
     * @return string
     */
    protected static function buildAppPath($appPath)
    {
        $appdir = self::getAppDir();
        if (!empty($appdir)) {
            $appPath .= "/$appdir";
        }

        return $appPath;
    }
}
