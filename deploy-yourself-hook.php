<?php

/**
 * Router for deploy-yourself, outside of Kirby
 *
 * This is intentionally very low-tech
 *
 * Copy this file into your root Kirby installation
 */
define('KIRBY_ROOT_CONFIG_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'config');
define('DEPLOY_YOURSELF_PLUGIN_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'deploy-yourself'  . DIRECTORY_SEPARATOR );

// intentionally not using an autoloader.
require_once(DEPLOY_YOURSELF_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'DeployYourself.php');

$dy = new Leobard\DeployYourself\DeployYourself(
  kirby_root_config_path: KIRBY_ROOT_CONFIG_PATH,
  get_parameters: $_GET
);
$dy->hook();
