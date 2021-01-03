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

use OZONE\OZ\Core\Context;
use OZONE\OZ\Hooks\Interfaces\MainHookReceiverInterface;
use OZONE\OZ\Http\Uri;
use OZONE\OZ\Router\RouteInfo;
use ReflectionClass;
use ReflectionException;

final class MainHookProvider extends HookProvider
{
	/**
	 * @param \OZONE\OZ\Core\Context $context
	 */
	public function triggerInit(Context $context)
	{
		$hc = new HookContext($context, false);

		$this->loop('onInit', function (MainHookReceiverInterface $h) use ($hc) {
			$h->onInit($hc);
		}, function (callable $cb) use ($context) {
			\call_user_func($cb, $context);
		});
	}

	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function triggerRequest(Context $context)
	{
		$hc = new HookContext($context);

		$this->loop('onRequest', function (MainHookReceiverInterface $h) use ($hc) {
			$h->onRequest($hc);
		}, function (callable $cb) use ($hc) {
			\call_user_func($cb, $hc);
		});

		return $hc->getResponse();
	}

	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function triggerSubRequest(Context $context)
	{
		$hc = new HookContext($context);

		$this->loop('onSubRequest', function (MainHookReceiverInterface $h) use ($hc) {
			$h->onSubRequest($hc);
		}, function (callable $cb) use ($hc) {
			\call_user_func($cb, $hc);
		});

		return $hc->getResponse();
	}

	/**
	 * @param \OZONE\OZ\Core\Context     $context
	 * @param \OZONE\OZ\Router\RouteInfo $route_info
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function triggerRouteFound(Context $context, RouteInfo $route_info)
	{
		$hc = new HookContext($context);

		$this->loop('onRouteFound', function (MainHookReceiverInterface $h) use ($hc, $route_info) {
			$h->onRouteFound($hc, $route_info);
		}, function (callable $cb) use ($hc, $route_info) {
			\call_user_func($cb, $hc, $route_info);
		});

		return $hc->getResponse();
	}

	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function triggerRouteNotFound(Context $context)
	{
		$hc = new HookContext($context);

		$this->loop('onRouteNotFound', function (MainHookReceiverInterface $h) use ($hc) {
			$h->onRouteNotFound($hc);
		}, function (callable $cb) use ($hc) {
			\call_user_func($cb, $hc);
		});

		return $hc->getResponse();
	}

	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function triggerMethodNotAllowed(Context $context)
	{
		$hc = new HookContext($context);

		$this->loop('onMethodNotAllowed', function (MainHookReceiverInterface $h) use ($hc) {
			$h->onMethodNotAllowed($hc);
		}, function (callable $cb) use ($hc) {
			\call_user_func($cb, $hc);
		});

		return $hc->getResponse();
	}

	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function triggerResponse(Context $context)
	{
		$hc = new HookContext($context);

		$this->loop('onResponse', function (MainHookReceiverInterface $h) use ($hc) {
			$h->onResponse($hc);
		}, function (callable $cb) use ($hc) {
			\call_user_func($cb, $hc);
		});

		return $hc->getResponse();
	}

	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function triggerFinish(Context $context)
	{
		$hc = new HookContext($context);

		$this->loop('onFinish', function (MainHookReceiverInterface $h) use ($hc) {
			$h->onFinish($hc);
		}, function (callable $cb) use ($hc) {
			\call_user_func($cb, $hc);
		});

		return $hc->getResponse();
	}

	/**
	 * @param \OZONE\OZ\Http\Uri     $target
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function triggerRedirect(Context $context, Uri $target)
	{
		$hc = new HookContext($context);

		$this->loop('onRedirect', function (MainHookReceiverInterface $h) use ($hc, $target) {
			$h->onRedirect($hc, $target);
		}, function (callable $cb) use ($hc, $target) {
			\call_user_func($cb, $hc, $target);
		});

		return $hc->getResponse();
	}

	/**
	 * @param callable $cb
	 * @param int      $priority
	 *
	 * @return $this
	 */
	public function onInit(callable $cb, $priority = HookProvider::RUN_DEFAULT)
	{
		return $this->addHookReceiverCallable(__FUNCTION__, $cb, $priority);
	}

	/**
	 * @param callable $cb
	 * @param int      $priority
	 *
	 * @return $this
	 */
	public function onRequest(callable $cb, $priority = HookProvider::RUN_DEFAULT)
	{
		return $this->addHookReceiverCallable(__FUNCTION__, $cb, $priority);
	}

	/**
	 * @param callable $cb
	 * @param int      $priority
	 *
	 * @return $this
	 */
	public function onSubRequest(callable $cb, $priority = HookProvider::RUN_DEFAULT)
	{
		return $this->addHookReceiverCallable(__FUNCTION__, $cb, $priority);
	}

	/**
	 * @param callable $cb
	 * @param int      $priority
	 *
	 * @return $this
	 */
	public function onRedirect(callable $cb, $priority = HookProvider::RUN_DEFAULT)
	{
		return $this->addHookReceiverCallable(__FUNCTION__, $cb, $priority);
	}

	/**
	 * @param callable $cb
	 * @param int      $priority
	 *
	 * @return $this
	 */
	public function onRouteFound(callable $cb, $priority = HookProvider::RUN_DEFAULT)
	{
		return $this->addHookReceiverCallable(__FUNCTION__, $cb, $priority);
	}

	/**
	 * @param callable $cb
	 * @param int      $priority
	 *
	 * @return $this
	 */
	public function onRouteNotFound(callable $cb, $priority = HookProvider::RUN_DEFAULT)
	{
		return $this->addHookReceiverCallable(__FUNCTION__, $cb, $priority);
	}

	/**
	 * @param callable $cb
	 * @param int      $priority
	 *
	 * @return $this
	 */
	public function onResponse(callable $cb, $priority = HookProvider::RUN_DEFAULT)
	{
		return $this->addHookReceiverCallable(__FUNCTION__, $cb, $priority);
	}

	/**
	 * @param callable $cb
	 * @param int      $priority
	 *
	 * @return $this
	 */
	public function onMethodNotAllowed(callable $cb, $priority = HookProvider::RUN_DEFAULT)
	{
		return $this->addHookReceiverCallable(__FUNCTION__, $cb, $priority);
	}

	/**
	 * @param callable $cb
	 * @param int      $priority
	 *
	 * @return $this
	 */
	public function onFinish(callable $cb, $priority = HookProvider::RUN_DEFAULT)
	{
		return $this->addHookReceiverCallable(__FUNCTION__, $cb, $priority);
	}

	/**
	 * @inheritDoc
	 */
	public static function getReceiverInstance($receiver_class)
	{
		return new $receiver_class();
	}

	/**
	 * @inheritDoc
	 */
	public static function isCompatibleHookReceiverClass($hook_receiver_class)
	{
		try {
			$rc = new ReflectionClass($hook_receiver_class);

			return $rc->implementsInterface(MainHookReceiverInterface::class);
		} catch (ReflectionException $e) {
		}

		return false;
	}
}
