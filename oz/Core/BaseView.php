<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	use OZONE\OZ\Exceptions\InternalErrorException;
	use OZONE\OZ\FS\TemplatesUtils;

	abstract class BaseView
	{
		/**
		 * the view output
		 */
		private $output = null;

		/**
		 * BaseView constructor.
		 *
		 * @param array $request the request
		 */
		abstract public function __construct(array $request = []);

		/**
		 * Gets the view template compile data, called just before view rendering
		 *
		 * @return array
		 */
		abstract public function getCompileData();

		/**
		 * Gets the view template to render
		 *
		 * @return string
		 */
		abstract public function getTemplate();

		/**
		 * Gets the view name
		 *
		 * @return string
		 */
		public function getViewName()
		{
			return get_class($this);
		}

		/**
		 * Gets the view output
		 *
		 * @return string
		 */
		public function getOutput()
		{
			return $this->output;
		}

		/**
		 * render the view
		 *
		 * @param boolean $force should we force rendering
		 *
		 * @return \OZONE\OZ\Core\BaseView
		 */

		public function render($force = false)
		{
			if (is_null($this->output) OR $force) {
				$this->output = TemplatesUtils::compute($this->getTemplate(), $this->getCompileData());
			}

			return $this;
		}

		/**
		 * serve the view to the browser and exit
		 *
		 * @return void
		 */

		public function serve()
		{
			Assert::assertAuthorizeAction(!is_null($this->output), new InternalErrorException('OZ_VIEW_NOT_RENDERED', [$this->getTemplate()]));
			// set headers
			// send data to browser

			echo $this->getOutput();

			exit;
		}
	}