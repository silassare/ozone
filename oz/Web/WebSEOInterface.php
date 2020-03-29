<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Web;

\defined('OZ_SELF_SECURITY_CHECK') || die;

interface WebSEOInterface
{
	/**
	 * @return string
	 */
	public function getSEOPageTitle();

	/**
	 * @return string
	 */
	public function getSEOPageName();

	/**
	 * @return string
	 */
	public function getSEOPageURL();

	/**
	 * @return string
	 */
	public function getSEOPageDescription();

	/**
	 * @return string
	 */
	public function getSEOPageAuthor();

	/**
	 * @return string
	 */
	public function getSEOPageImage();

	/**
	 * @return string[]
	 */
	public function getSEOPageKeywords();

	/**
	 * @return string
	 */
	public function getSEOOGSiteName();

	/**
	 * @return string
	 */
	public function getSEOOGType();

	/**
	 * @return string
	 */
	public function getSEOTwitterCard();

	/**
	 * @return array
	 */
	public function getSEOInjectData();
}
