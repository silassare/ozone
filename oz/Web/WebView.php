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

namespace OZONE\OZ\Web;

use Closure;
use OZONE\OZ\Core\Service;
use OZONE\OZ\FS\TemplatesUtils;
use OZONE\OZ\Http\Body;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Router\Router;
use OZONE\OZ\Web\Traits\WebSEOTrait;

/**
 * Class WebView.
 */
class WebView extends Service
{
	use WebSEOTrait;

	protected string $template = '';

	protected array $compile_data = [];

	/**
	 * WebView constructor.
	 */
	public function __destruct()
	{
		unset($this->compile_data);
	}

	/**
	 * @param array $data
	 *
	 * @return \OZONE\OZ\Web\WebView
	 */
	public function inject(array $data): self
	{
		foreach ($data as $key => $value) {
			$this->compile_data[$key] = $value;
		}

		return $this;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return \OZONE\OZ\Web\WebView
	 */
	public function injectKey(string $key, mixed $value): self
	{
		$this->compile_data[$key] = $value;

		return $this;
	}

	/**
	 * Gets the view template compile data, called just before the view is rendered.
	 *
	 * @return array
	 */
	public function getCompileData(): array
	{
		return $this->compile_data;
	}

	/**
	 * Gets the view template to render.
	 *
	 * @return string
	 */
	public function getTemplate(): string
	{
		return $this->template;
	}

	/**
	 * Sets the view template to render.
	 *
	 * @param string $template
	 *
	 * @return \OZONE\OZ\Web\WebView
	 */
	public function setTemplate(string $template): self
	{
		$this->template = $template;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSEOPageURL(): string
	{
		return (string) $this->getContext()
			->getRequest()
			->getUri();
	}

	/**
	 * Render the view and return response.
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function respond(): Response
	{
		return $this->renderTo($this->getContext()
			->getResponse());
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
	}

	/**
	 * Render the view.
	 *
	 * @return string
	 */
	protected function render(): string
	{
		$context         = $this->getContext();
		$wi              = new WebInject($context);
		$data            = $this->getCompileData();
		$data['context'] = $context;
		$data['oz']      = $wi;
		$data['i18n']    = Closure::fromCallable([$wi, 'i18n']);
		$data['seo']     = $this->getSEOInjectData();

		return TemplatesUtils::compile($this->getTemplate(), $data);
	}

	/**
	 * Render the view to a given response object.
	 *
	 * @param \OZONE\OZ\Http\Response $response
	 * @param string                  $mode     one of: append, prepend, overwrite
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	protected function renderTo(Response $response, string $mode = 'append'): Response
	{
		$output = $this->render();

		if ('prepend' === $mode) {
			$output .= $response->getBody();
			$body   = Body::fromString($output);

			return $response->withBody($body);
		}

		if ('overwrite' === $mode) {
			$body = Body::fromString($output);

			return $response->withBody($body);
		}

		// append
		return $response->write($output);
	}
}
