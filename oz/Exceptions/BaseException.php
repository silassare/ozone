<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Exceptions;

	use OZONE\OZ\OZone;
	use OZONE\OZ\Core\ResponseHolder;
	use OZONE\OZ\Core\RequestHandler;
	use OZONE\OZ\WebRoute\WebRoute;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class BaseException
	 *
	 * @package OZONE\OZ\Exceptions
	 */
	abstract class BaseException extends \Exception
	{

		const BAD_REQUEST        = 400;
		const FORBIDDEN          = 403;
		const NOT_FOUND          = 404;
		const METHOD_NOT_ALLOWED = 405;
		const INTERNAL_ERROR     = 500;
		const UNKNOWN_ERROR      = 520;

		// ozone custom error codes
		const UNVERIFIED_USER     = 1;
		const UNAUTHORIZED_ACTION = 2;
		const INVALID_FORM        = 3;
		const INVALID_FIELD       = 4;
		const RUNTIME             = 5;

		private static $ERROR_HEADER_MAP = [
			1 => 'HTTP/1.1 403 Forbidden',
			2 => 'HTTP/1.1 403 Forbidden',
			3 => 'HTTP/1.1 400 Bad Request',
			4 => 'HTTP/1.1 400 Bad Request',
			5 => 'HTTP/1.1 500 Internal Server Error',

			400 => 'HTTP/1.1 400 Bad Request',
			403 => 'HTTP/1.1 403 Forbidden',
			404 => 'HTTP/1.1 404 Not Found',
			405 => 'HTTP/1.1 405 Method Not Allowed',
			500 => 'HTTP/1.1 500 Internal Server Error',
			// default error same as the CloudFlare's Unknown Error
			520 => 'HTTP/1.1 520 Unknown Error'
		];

		/**
		 * @var bool
		 */
		protected static $just_die = false;

		/**
		 * @var array
		 */
		protected $data;

		/**
		 * @var \OZONE\OZ\Core\ResponseHolder
		 */
		private $response_holder;

		/**
		 * BaseException constructor.
		 *
		 * @param string     $message the exception message
		 * @param int        $code    the exception code
		 * @param array|null $data    additional error data
		 */
		public function __construct($message, $code, array $data = null)
		{
			parent::__construct($message, $code);

			$this->data = $data;

			$this->response_holder = new ResponseHolder(get_class($this));
		}

		/**
		 * Gets exception data
		 *
		 * @return array
		 */
		public function getData()
		{
			return $this->data;
		}

		/**
		 * Gets a http header string that corresponds to this exception
		 *
		 * @return string
		 */
		public function getHeaderString()
		{
			$code = $this->getCode();

			if (isset(self::$ERROR_HEADER_MAP[$code])) {
				return self::$ERROR_HEADER_MAP[$code];
			}

			return self::$ERROR_HEADER_MAP[BaseException::UNKNOWN_ERROR];
		}

		/**
		 * show exception according to accept header name or request type
		 */
		protected function informClient()
		{
			// We must avoid any other error, if there is one after this,
			// then we are in a critical situation: "Error handling error"

			// clear the output buffer, "Dirty linen should be washed as a family."
			// loop until we get the top buffer level
			while (ob_get_level()) @ob_end_clean();

			if (self::$just_die === true) {
				$err_msg = 'OZone: An "Error handling error" occurs. If you are an admin, please review the log file and correct it!';

				if (OZ_OZONE_IS_CLI) die(PHP_EOL . $err_msg . PHP_EOL);

				if (!headers_sent()) header(self::$ERROR_HEADER_MAP[BaseException::INTERNAL_ERROR]);

				$err_html = <<<ERROR_PAGE
<!DOCTYPE html>
<html>
	<head>
		<title>Error!</title>
		<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
		<style>
			body{
				margin: 0;
				font-family: Tahoma, Verdana, Arial, sans-serif;
			}
			div.oz-error{
				margin: 50px auto;
				width: 80%;
				padding: 10px 20px;
				border-left: 3px solid #ff0000;
				background-color: whitesmoke;
			}
		</style>
	</head>
	<body>
		<div class="oz-error">
			<p>$err_msg</p>
		</div>
	</body>
</html>
ERROR_PAGE;
				die($err_html);
			}

			// Any other error or exception should not be tolerated
			self::$just_die = true;

			if (OZ_OZONE_IS_CLI) {
				die(PHP_EOL . $this->getMessage() . PHP_EOL);
			} elseif (isset($_SERVER['HTTP_ACCEPT']) AND is_int(strpos($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
				$this->showJson();
			} elseif (!defined('OZ_OZONE_IS_WWW') AND RequestHandler::isPost()) {
				$this->showJson();
			} else {
				WebRoute::showCustomErrorPage($this);
			}
		}

		/**
		 * show exception as json file
		 */
		protected function showJson()
		{
			$this->response_holder->setError($this->getMessage())
								  ->setData($this->getData());

			OZone::sayJson($this->response_holder);
			exit;
		}

		/**
		 * ozone exception to string formatter
		 *
		 * @return string
		 */
		public function __toString()
		{
			$e_data = json_encode($this->getData());
			$e_msg  = <<<STRING
\tFile    : {$this->getFile()}
\tLine    : {$this->getLine()}
\tCode    : {$this->getCode()}
\tMessage : {$this->getMessage()}
\tData    : $e_data
\tTrace   : {$this->getTraceAsString()}
STRING;

			return $e_msg;
		}

		/**
		 * custom procedure for each ozone exception type
		 *
		 * @return void;
		 */
		abstract public function procedure();
	}