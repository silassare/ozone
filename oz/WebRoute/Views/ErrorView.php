<?php

	namespace OZONE\OZ\WebRoute\Views;

	use OZONE\OZ\Core\OZoneView;

	final class ErrorView extends OZoneView
	{

		private $compileData = [];

		/**
		 * {@inheritdoc}
		 */
		public function __construct($request = [])
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