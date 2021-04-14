<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use OZONE\OZ\Cli\Cli;

//= For backward compatibility only
//= @deprecated 2.0.0
\define('OZ_SELF_SECURITY_CHECK', 1);

//=	Don't forget to use DS instead of \ or / and
//= always add the last DS to your directories path
\define('DS', \DIRECTORY_SEPARATOR);

//= Project directory
\define('OZ_PROJECT_DIR', \getcwd() . DS);

//= OZone app directory
\define('OZ_APP_DIR', OZ_PROJECT_DIR . 'api' . DS . 'app' . DS);

//= Logs directory
\define('OZ_LOG_DIR', OZ_PROJECT_DIR);

//= Load composer autoload
require_once OZ_PROJECT_DIR . DS . 'vendor' . DS . 'autoload.php';

if (!\defined('OZ_OZONE_IS_CLI') || !OZ_OZONE_IS_CLI) {
	print 'This is the command line tool for OZone Framework.';
	exit(1);
}

//= Run the cli
Cli::run($argv);
