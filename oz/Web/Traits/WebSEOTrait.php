<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace OZONE\Core\Web\Traits;

/**
 * Trait WebSEOTrait.
 */
trait WebSEOTrait
{
	/**
	 * @return string
	 */
	public function getSEOPageTitle(): string
	{
		return '';
	}

	/**
	 * @return string
	 */
	public function getSEOPageName(): string
	{
		return '';
	}

	/**
	 * @return string
	 */
	public function getSEOPageURL(): string
	{
		return '';
	}

	/**
	 * @return string
	 */
	public function getSEOPageDescription(): string
	{
		return '';
	}

	/**
	 * @return string
	 */
	public function getSEOPageAuthor(): string
	{
		return '';
	}

	/**
	 * @return string
	 */
	public function getSEOPageImage(): string
	{
		return '';
	}

	/**
	 * @return string[]
	 */
	public function getSEOPageKeywords(): array
	{
		return [];
	}

	/**
	 * @return string
	 */
	public function getSEOOGSiteName(): string
	{
		return '';
	}

	/**
	 * @return string
	 */
	public function getSEOOGType(): string
	{
		return 'website';
	}

	/**
	 * @return string
	 */
	public function getSEOTwitterCard(): string
	{
		return 'summary_large_image';
	}

	/**
	 * @return array
	 */
	public function getSEOInjectData(): array
	{
		return [
			'page_name'        => $this->getSEOPageName(),
			'page_title'       => $this->getSEOPageTitle(),
			'page_description' => $this->getSEOPageDescription(),
			'page_keywords'    => $this->getSEOPageKeywords(),
			'page_image'       => $this->getSEOPageImage(),
			'page_author'      => $this->getSEOPageAuthor(),
			'page_url'         => $this->getSEOPageURL(),
			'og_type'          => $this->getSEOOGType(),
			'og_site_name'     => $this->getSEOOGSiteName(),
			'twitter_card'     => $this->getSEOTwitterCard(),
		];
	}
}
