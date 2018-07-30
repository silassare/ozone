<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\WebRoute;

	use OZONE\OZ\Core\BaseView;
	use OZONE\OZ\Core\URIHelper;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	abstract class WebSEO extends BaseView
	{
		/**
		 * @return string
		 */
		public function getSEOPageTitle()
		{
			return "";
		}

		/**
		 * @return string
		 */
		public function getSEOPageName()
		{
			return "";
		}

		/**
		 * @return string
		 */
		public function getSEOPageURL()
		{
			return URIHelper::getRequestURL(true);
		}

		/**
		 * @return string
		 */
		public function getSEOPageDescription()
		{
			return "";
		}

		/**
		 * @return string
		 */
		public function getSEOPageAuthor()
		{
			return "";
		}

		/**
		 * @return string
		 */
		public function getSEOPageImage()
		{
			return "";
		}

		/**
		 * @return string[]
		 */
		public function getSEOPageKeywords()
		{
			return [];
		}

		/**
		 * @return string
		 */
		public function getSEOOGSiteName()
		{
			return "";
		}

		/**
		 * @return string
		 */
		public function getSEOOGType()
		{
			return "website";
		}

		/**
		 * @return string
		 */
		public function getSEOTwitterCard()
		{
			return "summary_large_image";
		}

		/**
		 * @return array
		 */
		public function getSEOInjectData()
		{
			return [
				"name"        => $this->getSEOPageName(),
				"title"       => $this->getSEOPageTitle(),
				"description" => $this->getSEOPageDescription(),
				"keywords"    => $this->getSEOPageKeywords(),
				"url"         => $this->getSEOPageURL(),
				"ogType"      => $this->getSEOOGType(),
				"ogSiteName"  => $this->getSEOOGSiteName(),
				"image"       => $this->getSEOPageImage(),
				"author"      => $this->getSEOPageAuthor(),
				"twitterCard" => $this->getSEOTwitterCard()
			];
		}
	}