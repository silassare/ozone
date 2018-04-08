<?php

	namespace OZONE\OZ\WebRoute\Views;

	use OZONE\OZ\WebRoute\WebPageBase;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class ErrorView extends WebPageBase
	{
		private $compileData = [];

		/**
		 * {@inheritdoc}
		 */
		public function __construct(array $request = [])
		{
			$this->compileData = $request;
		}

		/**
		 * {@inheritdoc}
		 */
		public function getCompileData()
		{
			return $this->compileData;
		}

		/**
		 * {@inheritdoc}
		 */
		public function getTemplate()
		{
			return 'error.otpl';
		}
	}