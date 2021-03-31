<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Web;

use OZONE\OZ\FS\TemplatesUtils;
use OZONE\OZ\Http\Body;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\RouteProviderInterface;

abstract class WebViewBase implements RouteProviderInterface, WebSEOInterface
{
	/**
	 * @var \OZONE\OZ\Router\RouteInfo
	 */
	protected $r;

	/**
	 * @var \OZONE\OZ\Web\WebInject
	 */
	protected $oz;

	/**
	 * WebViewBase constructor.
	 *
	 * @param \OZONE\OZ\Router\RouteInfo $r
	 */
	public function __construct(RouteInfo $r)
	{
		$this->r  = $r;
		$this->oz = new WebInject($r->getContext());
	}

	/**
	 * WebViewBase constructor.
	 */
	public function __destruct()
	{
		unset($this->r, $this->oz);
	}

	/**
	 * Returns the context.
	 *
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext()
	{
		return $this->r->getContext();
	}

	/**
	 * Gets the view template compile data, called just before the view is rendered
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

	/*
	 * WebSEOInterface
	 */

	/**
	 * @inheritdoc
	 */
	public function getSEOPageTitle()
	{
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function getSEOPageName()
	{
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function getSEOPageURL()
	{
		return (string) $this->r->getContext()
							   ->getRequest()
							   ->getUri();
	}

	/**
	 * @inheritdoc
	 */
	public function getSEOPageDescription()
	{
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function getSEOPageAuthor()
	{
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function getSEOPageImage()
	{
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function getSEOPageKeywords()
	{
		return [];
	}

	/**
	 * @inheritdoc
	 */
	public function getSEOOGSiteName()
	{
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function getSEOOGType()
	{
		return 'website';
	}

	/**
	 * @inheritdoc
	 */
	public function getSEOTwitterCard()
	{
		return 'summary_large_image';
	}

	/**
	 * @inheritdoc
	 */
	public function getSEOInjectData()
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

	/**
	 * Render the view.
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 *
	 * @return string
	 */
	protected function render()
	{
		$data = \array_replace($this->getDefaultCompileData(), $this->getCompileData());

		return TemplatesUtils::compute($this->getTemplate(), $data);
	}

	/**
	 * Render the view to a given response object.
	 *
	 * @param \OZONE\OZ\Http\Response $response
	 * @param string                  $mode
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	protected function renderTo(Response $response, $mode = 'append')
	{
		$output = $this->render();

		if ($mode === 'prepend') {
			$output .= $response->getBody();
			$body   = Body::fromString($output);

			return $response->withBody($body);
		}

		if ($mode === 'overwrite') {
			$body = Body::fromString($output);

			return $response->withBody($body);
		}

		// append
		return $response->write($output);
	}

	/**
	 * Render the view and return response.
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	protected function respond()
	{
		return $this->renderTo($this->getContext()
									->getResponse());
	}

	/**
	 * Returns default data to inject in template.
	 *
	 * @return array
	 */
	private function getDefaultCompileData()
	{
		$data['context'] = $this->r->getContext();
		$data['oz']      = $this->oz;
		$data['i18n']    = $this->wrap([$this->oz, 'i18n']);
		$data['seo']     = $this->getSEOInjectData();

		return $data;
	}

	/**
	 * Wraps the given callable in a closure.
	 *
	 * @param callable $callable
	 *
	 * @return \Closure
	 */
	private function wrap(callable $callable)
	{
		return function () use ($callable) {
			$args = \func_get_args();

			return \call_user_func_array($callable, $args);
		};
	}
}
