<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Cli;

	use OZONE\OZ\FS\FilesManager;

	class Process
	{
		private $descriptor = [
			["pipe", "r"], // stdin
			["pipe", "w"], // stdout
			["pipe", "w"] // stderr
		];

		private $env    = [];
		private $command;
		private $pipes;
		private $cwd;
		private $process;
		private $output = '';
		private $error  = '';

		public function __construct($cmd, $cwd = '.')
		{
			$this->command = $cmd;
			$fm            = new FilesManager(getcwd());
			$this->cwd     = $fm->resolve($cwd);
		}

		public function run()
		{
			$this->process = proc_open($this->command, $this->descriptor, $this->pipes, $this->cwd, $this->env);

			return $this;
		}

		/**
		 * Writes to stdin.
		 *
		 * @param string $input
		 *
		 * @return bool|int returns the number of bytes written, or FALSE if an error occurs.
		 */
		public function write($input)
		{
			if (is_resource($this->pipes[0])) {
				fwrite($this->pipes[0], (string)$input);
			}

			return false;
		}

		public function getOutput()
		{
			if (is_resource($this->pipes[1])) {
				return stream_get_contents($this->pipes[1]);
			}

			return $this->output;
		}

		public function getError()
		{
			if (is_resource($this->pipes[2])) {
				return stream_get_contents($this->pipes[2]);
			}

			return $this->error;
		}

		public function close()
		{
			if ($this->pipes) {
				$this->output = $this->getOutput();
				$this->error  = $this->getError();

				foreach ($this->pipes as $index => $pipe) {
					fclose($pipe);
					unset($this->pipes[$index]);
				}

				$this->pipes = null;
			}

			if (is_resource($this->process)) {
				return proc_close($this->process);
			}

			return 0;
		}

		public function __destruct()
		{
			$this->close();
		}
	}