<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	//CHECK IF ALL PHP MODULES AND IMPORTANT DEPENDENCIES ARE INSTALLED
	$STEP_MISSING_MODULES = 0;
	//GET DATABASE INFOS: DB NAME AND TABLE PREFIX...
	$STEP_DB_INFOS = 1;
	//ASK FOR DB SERVER AND DBUSER INFOS
	$STEP_DB_SERVER_INFOS = 2;
	//ALL NECESSARY TASK
	$STEP_FINAL = 3;

	$current_step = 0;

	//ALTER TABLE `$TABLE` AUTO_INCREMENT=$VALUE;
	switch ( $current_step ) {
		case $STEP_MISSING_MODULES:

			break;
		case $STEP_DB_INFOS:

			break;
		case $STEP_DB_SERVER_INFOS:

			break;
		case $STEP_FINAL:

			break;
	}
