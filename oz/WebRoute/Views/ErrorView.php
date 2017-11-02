<?php

	namespace OZONE\OZ\WebRoute\Views;

	use OZONE\OZ\Core\BaseView;

	final class ErrorView extends BaseView
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