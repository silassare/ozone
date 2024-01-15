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

namespace OZONE\Core\Web;

use OZONE\Core\App\Service;
use OZONE\Core\FS\Templates;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Router;
use OZONE\Core\Web\Traits\WebSEOTrait;

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
	 * @return $this
	 */
	public function inject(array $data): static
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
	 * @return $this
	 */
	public function injectKey(string $key, mixed $value): static
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
	 * @return $this
	 */
	public function setTemplate(string $template): static
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
	 * @return Response
	 */
	public function respond(): Response
	{
		return $this->renderTo(
			$this->getContext()
				->getResponse()
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void {}

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
		$data['i18n']    = $wi->i18n(...);
		$data['seo']     = $this->getSEOInjectData();

		return Templates::compile($this->getTemplate(), $data);
	}

	/**
	 * Render the view to a given response object.
	 *
	 * @param Response $response
	 * @param string   $mode     one of: append, prepend, overwrite
	 *
	 * @return Response
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
