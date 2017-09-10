<?php

	define('DEBUG', true);

	class ErrorOrWarningException extends Exception
	{
		protected $_Context = null;

		public function getContext()
		{
			return $this->_Context;
		}

		public function setContext($value)
		{
			$this->_Context = $value;
		}

		public function __construct($code, $message, $file, $line, $context)
		{
			parent::__construct($message, $code);

			$this->file = $file;
			$this->line = $line;
			$this->setContext($context);
		}
	}

	/**
	 * Inspire to write perfect code. everything is an exception, even minor warnings.
	 **/
	function error_to_exception($code, $message, $file, $line, $context)
	{
		throw new ErrorOrWarningException($code, $message, $file, $line, $context);
	}

	set_error_handler('error_to_exception');

	function global_exception_handler($ex)
	{
		ob_start();
		dump_exception($ex);
		$dump = ob_get_clean();
		// send email of dump to administrator?...

		// if we are in debug mode we are allowed to dump exceptions to the browser.
		if (defined('DEBUG') && DEBUG == true) {
			echo $dump;
		} else // if we are in production we give our visitor a nice message without all the details.
		{
			echo file_get_contents('static/errors/fatalexception.html');
		}
		exit;
	}

	function dump_exception(Exception $ex)
	{
		$file = $ex->getFile();
		$line = $ex->getLine();

		if (file_exists($file)) {
			$lines = file($file);
		}

		?>
        <html>
    <head>
        <title><?= $ex->getMessage(); ?></title>
        <style type="text/css">
            body {
                width: 800px;
                margin: auto;
            }

            ul.code {
                border: inset 1px;
            }

            ul.code li {
                white-space: pre;
                list-style-type: none;
                font-family: monospace;
            }

            ul.code li.line {
                color: red;
            }

            table.trace {
                width: 100%;
                border-collapse: collapse;
                border: solid 1px black;
            }

            table.thead tr {
                background: rgb(240, 240, 240);
            }

            table.trace tr.odd {
                background: white;
            }

            table.trace tr.even {
                background: rgb(250, 250, 250);
            }

            table.trace td {
                padding: 2px 4px 2px 4px;
            }
        </style>
    </head>
    <body>
    <h1>Uncaught <?= get_class($ex); ?></h1>
    <h2><?= $ex->getMessage(); ?></h2>
    <p>
        An uncaught <?= get_class($ex); ?> was thrown on line <?= $line; ?> of file <?= basename($file); ?> that
        prevented further execution of this request.
    </p>
    <h2>Where it happened:</h2>
	<? if (isset($lines)) : ?>
        <code><?= $file; ?></code>
        <ul class="code">
			<? for ($i = $line - 3; $i < $line + 3; $i++) : ?>
				<? if ($i > 0 && $i < count($lines)) : ?>
					<? if ($i == $line - 1) : ?>
                        <li class="line"><?= str_replace("\n", "", $lines[$i]); ?></li>
					<? else : ?>
                        <li><?= str_replace("\n", "", $lines[$i]); ?></li>
					<? endif; ?>
				<? endif; ?>
			<? endfor; ?>
        </ul>
	<? endif; ?>

	<? if (is_array($ex->getTrace())) : ?>
        <h2>Stack trace:</h2>
        <table class="trace">
            <thead>
            <tr>
                <td>File</td>
                <td>Line</td>
                <td>Class</td>
                <td>Function</td>
                <td>Arguments</td>
            </tr>
            </thead>
            <tbody>
			<? foreach ($ex->getTrace() as $i => $trace) : ?>
                <tr class="<?= $i % 2 == 0 ? 'even' : 'odd'; ?>">
                    <td><?= isset($trace['file']) ? basename($trace['file']) : ''; ?></td>
                    <td><?= isset($trace['line']) ? $trace['line'] : ''; ?></td>
                    <td><?= isset($trace['class']) ? $trace['class'] : ''; ?></td>
                    <td><?= isset($trace['function']) ? $trace['function'] : ''; ?></td>
                    <td>
						<? if (isset($trace['args'])) : ?>
							<? foreach ($trace['args'] as $i => $arg) : ?>
                                <span title="<?= var_export($arg, true); ?>"><?= gettype($arg); ?></span>
								<?= $i < count($trace['args']) - 1 ? ',' : ''; ?>
							<? endforeach; ?>
						<? else : ?>
                            NULL
						<? endif; ?>
                    </td>
                </tr>
			<? endforeach; ?>
            </tbody>
        </table>
	<? else : ?>
        <pre><?= $ex->getTraceAsString(); ?></pre>
	<? endif; ?>
    </body>
        </html><? // back in php
	}

	set_exception_handler('global_exception_handler');

	class X
	{
		function __construct()
		{
			trigger_error('Whoops!', E_USER_NOTICE);
		}
	}

	$x = new X();

	throw new Exception('Execution will never get here');

?>