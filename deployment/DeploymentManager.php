<?php

/*
 * Copyright (c) 2009 - 2011, Frosted Design
 * deployment module introduced by Johannes Becker <johannes@gibbonweb.net>
 * All rights reserved.
 */

namespace hydrogen\deployment;

use hydrogen\deploymeng\exceptions\DeploymentFailedException;
use hydrogen\config\Config;

/**
 * The DeploymentManager class can be used to deploy a database setup with a
 * Hydrogen app.
 * 
 * If the deployment status of the app is up to date, the DeploymentManager will
 * return immediately.
 */
class DeploymentManager {

    protected static $historyPath = NULL;

    public static function autoDeploy() {
        if ("false" == Config::getVal("deployment", "auto_deploy"))
            return;
        if (DeploymentManager::needsDeployment()) {
            if (DeploymentManager::$historyPath !== NULL) {
                include(DeploymentManager::$historyPath);
            }
            for (   $i = DeploymentManager::instanceRevision();
                    $i < DeploymentManager::appRevision(); ++$i ) {
                switch ($deploymentHistory[$i]["type"]) {
                    case "database":
                        break;
                    case "system":
                        break;
                    default:
                        break;
                }
            }
        }
    }

    public static function instanceRevision() {
        return Config::getVal("deployment", "instance_revision");
    }

    public static function appRevision() {
        return Config::getVal("deployment", "app_revision");
    }

    public static function needsDeployment() {
        return (DeploymentManager::appRevision()
                > DeploymentManager::instanceRevision());
    }

    public static function useHistory($path) {
        DeploymentManager::$historyPath = $path;
    }

    /**
     * This class should never be instantiated.
     */
    private function __construct() {
        
    }

}

?>
