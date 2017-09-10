<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\AppManager;

	use OZONE\OZ\Exceptions\OZoneBaseException;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class App
	{
		private $defaultAppName = 'My';
		private $appName;
		private $appCleanName;
		private $appPrefix;
		private $appClassName;
		private $appNamespace;

		public function __construct($appName)
		{
			if (!is_string($appName) OR !strlen($appName)) {
				throw new \Exception('app name should be a valid string');
			}

			$this->appName = $appName;
			// kepp only allowed chars
			$name = preg_replace("#[^a-zA-Z0-9_]+#", '', $this->appName);
			// remove numerics from start
			$name = strtolower(preg_replace("#^[0-9]+#", '', $name));
			$len  = strlen($name);

			if ($len < 2) {
				$name = $this->defaultAppName;
			}

			$this->appCleanName = $name;
			$this->appPrefix    = strtoupper(substr($name, 0, 2));
			$this->appClassName = $this->appPrefix . strtolower(substr($name, 2)) . 'App';
			$this->appNamespace = 'OZONE\\' . strtoupper($this->appCleanName);
		}

		public function getCompileData()
		{
			return [
				'app_name'       => $this->appName,
				'app_clean_name' => $this->appCleanName,
				'app_prefix'     => $this->appPrefix,
				'app_class_name' => $this->appClassName,
				'app_namespace'  => $this->appNamespace
			];
		}
	}