<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Hooks;

	use http\Exception\InvalidArgumentException;
	use OZONE\OZ\Core\Context;
	use OZONE\OZ\Hooks\Interfaces\HookInterface;
	use OZONE\OZ\Http\Uri;
	use OZONE\OZ\Router\RouteInfo;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class HooksManager
	{
		/**
		 * @var HookInterface[][]
		 */
		private $hooks = [
			HookInterface::RUN_FIRST   => [],
			HookInterface::RUN_DEFAULT => [],
			HookInterface::RUN_LAST    => []
		];

		/**
		 * Register hooks.
		 *
		 * @param HookInterface $hook
		 * @param int           $priority
		 *
		 * @return $this
		 */
		public function register(HookInterface $hook, $priority = HookInterface::RUN_DEFAULT)
		{
			if (
				$priority === HookInterface::RUN_DEFAULT
				OR $priority === HookInterface::RUN_FIRST
				OR $priority === HookInterface::RUN_LAST
			) {
				$this->hooks[$priority][] = $hook;
			} else {
				throw new InvalidArgumentException(sprintf(
						'Invalid priority %s set for hook %s. Allowed value are %s::RUN_* constants.',
						$priority, get_class($hook), HookInterface::class)
				);
			}

			return $this;
		}

		/**
		 * @param callable $cb
		 *
		 * @return $this
		 */
		private function loop(callable $cb)
		{
			$first   = $this->hooks[HookInterface::RUN_FIRST];
			$default = $this->hooks[HookInterface::RUN_DEFAULT];
			$last    = $this->hooks[HookInterface::RUN_LAST];

			foreach ($first as $hook) {
				call_user_func($cb, $hook);
			}

			foreach ($default as $hook) {
				call_user_func($cb, $hook);
			}

			$last = array_reverse($last);

			foreach ($last as $hook) {
				call_user_func($cb, $hook);
			}

			return $this;
		}

		/**
		 * @param \OZONE\OZ\Core\Context $context
		 *
		 * @return \OZONE\OZ\Hooks\HooksManager
		 */
		public function onInit(Context $context)
		{
			$hc = new HookContext($context);

			return $this->loop(function (HookInterface $h) use ($hc) {
				$h->onInit($hc);
			});
		}

		/**
		 * @param \OZONE\OZ\Core\Context $context
		 *
		 * @return \OZONE\OZ\Http\Request
		 */
		public function onRequest(Context $context)
		{
			$hc = new HookContext($context);

			$this->loop(function (HookInterface $h) use ($hc) {
				$h->onRequest($hc);
			});

			return $hc->getRequest();
		}

		/**
		 * @param \OZONE\OZ\Router\RouteInfo $r
		 * @param \OZONE\OZ\Core\Context     $context
		 *
		 * @return \OZONE\OZ\Http\Response
		 */
		public function onFound(RouteInfo $r, Context $context)
		{
			$hc = new HookContext($context);

			$this->loop(function (HookInterface $h) use ($r, $hc) {
				$h->onFound($r, $hc);
			});

			return $hc->getResponse();
		}

		/**
		 * @param \OZONE\OZ\Core\Context $context
		 *
		 * @return \OZONE\OZ\Http\Response
		 */
		public function onNotFound(Context $context)
		{
			$hc = new HookContext($context);

			$this->loop(function (HookInterface $h) use ($hc) {
				$h->onNotFound($hc);
			});

			return $hc->getResponse();
		}

		/**
		 * @param \OZONE\OZ\Core\Context $context
		 *
		 * @return \OZONE\OZ\Http\Response
		 */
		public function onMethodNotAllowed(Context $context)
		{
			$hc = new HookContext($context);

			$this->loop(function (HookInterface $h) use ($hc) {
				$h->onMethodNotAllowed($hc);
			});

			return $hc->getResponse();
		}

		/**
		 * @param \OZONE\OZ\Core\Context $context
		 *
		 * @return \OZONE\OZ\Http\Response
		 */
		public function onResponse(Context $context)
		{
			$hc = new HookContext($context);

			$this->loop(function (HookInterface $h) use ($hc) {
				$h->onResponse($hc);
			});

			return $hc->getResponse();
		}

		/**
		 * @param \OZONE\OZ\Core\Context $context
		 *
		 * @return \OZONE\OZ\Http\Response
		 */
		public function onFinish(Context $context)
		{
			$hc = new HookContext($context);

			$this->loop(function (HookInterface $h) use ($hc) {
				$h->onFinish($hc);
			});

			return $hc->getResponse();
		}

		/**
		 * @param \OZONE\OZ\Http\Uri     $target
		 * @param \OZONE\OZ\Core\Context $context
		 *
		 * @return \OZONE\OZ\Http\Response
		 */
		public function onRedirect(Uri $target, Context $context)
		{
			$hc = new HookContext($context);

			$this->loop(function (HookInterface $h) use ($target, $hc) {
				$h->onRedirect($target, $hc);
			});

			return $hc->getResponse();
		}

	}