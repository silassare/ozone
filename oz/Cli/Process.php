<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Cli;

use OZONE\OZ\FS\FilesManager;
use RuntimeException;

final class Process
{
	const IN = 0;

	const OUT = 1;

	const ERR = 2;

	private $env;

	private $command;

	private $pipes;

	private $files;

	private $cwd;

	private $process;

	private $status;

	private $descriptors = [
		self::IN  => ['pipe', 'r'], // stdin
		self::OUT => ['pipe', 'w'], // stdout
		self::ERR => ['pipe', 'w'], // stderr
	];

	private $read_cb = [];

	private $tmp_stdout;

	private $tmp_stderr;

	/**
	 * @var \OZONE\OZ\FS\FilesManager
	 */
	private $fm;

	private $unblocked = false;

	/**
	 * @param mixed $cmd
	 * @param mixed $cwd
	 *
	 * @var false|resource
	 */

	/**
	 * Process constructor.
	 *
	 * @param string     $cmd the command to run
	 * @param string     $cwd the current working directory
	 * @param null|array $env the environment variables for the command
	 */
	public function __construct($cmd, $cwd = '.', array $env = null)
	{
		if (!empty($descriptors)) {
			$this->descriptors = $descriptors;
		}

		$this->env = ProcessUtils::getDefaultEnv();

		if (!empty($env)) {
			$this->env += $env;
		}

		if (empty($cwd)) {
			$cwd = '.';
		}

		$this->command = $cmd;
		$this->fm      = new FilesManager(\getcwd());
		$this->cwd     = $this->fm->resolve($cwd);
	}

	/**
	 * Process destructor.
	 */
	public function __destruct()
	{
		unset($this->fm);
		$this->close();
	}

	/**
	 * Open a process to run the command.
	 *
	 * @return $this
	 */
	public function open()
	{
		$this->assertNoOpenedProcess(__METHOD__);

		$command = $this->command;
		$options = [];

		if (ProcessUtils::isWindows()) {
			$options['bypass_shell'] = true;
			$command                 = \str_replace('\n', ' ', $this->command);
			$command                 = \sprintf('cmd /V:ON /E:ON /D /C (%s)', $command);
		}

		$this->process = \proc_open($command, $this->descriptors, $this->pipes, $this->cwd, $this->env, $options);

		return $this;
	}

	/**
	 * Close the process.
	 *
	 * This wait for process termination.
	 *
	 * @return int
	 */
	public function close()
	{
		$ret = 0;

		if ($this->pipes) {
			foreach ($this->pipes as $index => $pipe) {
				if (\is_resource($pipe)) {
					\fclose($pipe);
				}
				unset($this->pipes[$index]);
			}
		}

		if (\is_resource($this->process)) {
			$ret = \proc_close($this->process);
		}

		$this->process     = null;
		$this->files       = [];
		$this->read_cb     = [];
		$this->status      = null;
		$this->pipes       = null;
		$this->descriptors = [];

		if (\is_resource($this->tmp_stderr)) {
			\ftruncate($this->tmp_stderr, 0);
			\fclose($this->tmp_stderr);
			$this->tmp_stderr = null;
		}

		if (\is_resource($this->tmp_stdout)) {
			\ftruncate($this->tmp_stdout, 0);
			\fclose($this->tmp_stdout);
			$this->tmp_stdout = null;
		}

		return $ret;
	}

	/**
	 * Sets stdin file path.
	 *
	 * @param string $file_path
	 *
	 * @return $this
	 */
	public function setStdin($file_path)
	{
		$this->assertNoOpenedProcess(__METHOD__);

		$file_path = $this->fm->resolve($file_path);

		$this->fm->assert($file_path, ['file' => true, 'read' => true, 'write' => true]);

		$this->descriptors[self::IN] = ['file', $file_path, 'r'];// the command need to read
		$this->files[self::IN]       = $file_path;

		return $this;
	}

	/**
	 * Sets stdout file path.
	 *
	 * @param string $file_path
	 *
	 * @return $this
	 */
	public function setStdout($file_path)
	{
		$this->assertNoOpenedProcess(__METHOD__);

		$file_path = $this->fm->resolve($file_path);

		$this->fm->assert($file_path, ['file' => true, 'read' => true, 'write' => true]);

		$this->descriptors[self::OUT] = ['file', $file_path, 'w'];// the command need to write
		$this->files[self::OUT]       = $file_path;

		return $this;
	}

	/**
	 * Sets stderr file path.
	 *
	 * @param string $file_path
	 *
	 * @return $this
	 */
	public function setStderr($file_path)
	{
		$this->assertNoOpenedProcess(__METHOD__);

		$file_path = $this->fm->resolve($file_path);

		$this->fm->assert($file_path, ['file' => true, 'read' => true, 'write' => true]);

		$this->descriptors[self::ERR] = ['file', $file_path, 'w'];// the command need to write
		$this->files[self::ERR]       = $file_path;

		return $this;
	}

	/**
	 * Writes to stdin.
	 *
	 * @param string $input
	 *
	 * @return bool|int returns the number of bytes written, or FALSE if an error occurs
	 */
	public function writeStdin($input)
	{
		$this->assertOpenedProcess(__METHOD__);

		if (isset($this->pipes[self::IN]) && \is_resource($this->pipes[0])) {
			return \fwrite($this->pipes[self::IN], (string) $input);
		}

		if (isset($this->files[self::IN])) {
			return \file_put_contents($this->files[self::IN], (string) $input, \FILE_APPEND);
		}

		return false;
	}

	/**
	 * Watch stdout & stderr.
	 *
	 * @param callable $stdout_cb
	 * @param callable $stderr_cb
	 *
	 * @return $this
	 */
	public function watch(callable $stdout_cb, callable $stderr_cb)
	{
		$this->assertOpenedProcess(__METHOD__);

		$this->read_cb[self::OUT] = $stdout_cb;
		$this->read_cb[self::ERR] = $stderr_cb;

		$this->readHelper();

		return $this;
	}

	/**
	 * Reads stdout.
	 *
	 * @return false|string
	 */
	public function readStdout()
	{
		if (isset($this->tmp_stdout) && \is_resource($this->tmp_stdout)) {
			return \stream_get_contents($this->tmp_stdout, -1, 0);
		}

		if (isset($this->pipes[self::OUT]) && \is_resource($this->pipes[self::OUT])) {
			return \stream_get_contents($this->pipes[self::OUT], -1, 0);
		}

		if (isset($this->files[self::OUT])) {
			return \file_get_contents($this->files[self::OUT]);
		}

		return '';
	}

	/**
	 * Reads stderr.
	 *
	 * @return false|string
	 */
	public function readStderr()
	{
		if (isset($this->tmp_stderr) && \is_resource($this->tmp_stderr)) {
			return \stream_get_contents($this->tmp_stderr, -1, 0);
		}

		if (isset($this->pipes[self::ERR]) && \is_resource($this->pipes[self::ERR])) {
			return \stream_get_contents($this->pipes[self::ERR], -1, 0);
		}

		if (isset($this->files[self::ERR])) {
			return \file_get_contents($this->files[self::ERR]);
		}

		return '';
	}

	/**
	 * Kill the process.
	 *
	 * @return bool
	 */
	public function kill()
	{
		if ($this->isRunning()) {
			$pid = $this->getPid();

			if (ProcessUtils::isWindows()) {
				$cmd = 'taskkill /T /F /PID ' . $pid;
			} else {
				$cmd = 'kill -9 ' . $pid;
			}

			$p = new self($cmd);

			$p->open();
			$out = $p->readStderr();
			$p->close();
			$this->close();

			return empty($out);
		}

		return true;
	}

	/**
	 * Returns the process pid.
	 *
	 * @return mixed
	 */
	public function getPid()
	{
		$this->assertOpenedProcess(__METHOD__);

		return $this->getStatus()['pid'];
	}

	/**
	 * Checks if the process is running.
	 *
	 * @return bool
	 */
	public function isRunning()
	{
		if ($this->process) {
			$status = $this->getStatus(true);

			return (bool) ($status['running']);
		}

		return false;
	}

	/**
	 * Returns process status.
	 *
	 * @param bool $refresh
	 *
	 * @return array|false
	 */
	public function getStatus($refresh = false)
	{
		$this->assertOpenedProcess(__METHOD__);

		if ($refresh || !$this->status) {
			$this->status = \proc_get_status($this->process);

			if (ProcessUtils::sigChildEnabled()) {
				$this->status['signaled'] = true;
				$this->status['exitcode'] = -1;
				$this->status['termsig']  = -1;
			}

			if (!$this->status) {
				throw new RuntimeException('Unable to get running process status.');
			}
		}

		return $this->status;
	}

	/**
	 * Read helper.
	 */
	private function readHelper()
	{
		$this->assertOpenedProcess(__METHOD__);

		$this->unblock();
		$tv_usec = 200000;// 0.2 sec

		$tmp_path         = 'php://temp/maxmemory:' . (1024 * 1024);
		$this->tmp_stdout = \fopen($tmp_path, 'w+b');
		$this->tmp_stderr = \fopen($tmp_path, 'w+b');
		$read             = [];
		$sources          = [];
		$files_handles    = [];

		if ($this->files) {
			foreach ($this->files as $key => $path) {
				if ($key === self::ERR || $key = self::OUT) {
					$files_handles[$key] = $read[$key] = $sources[$key] = \fopen($path, 'r');
				}
			}
		}

		while ($this->isRunning()) {
			if ($this->pipes) {
				foreach ($this->pipes as $key => $pipe) {
					if ($key === self::ERR || $key = self::OUT) {
						$read[$key] = $sources[$key] = $pipe;
					}
				}
			}

			if (!empty($read) && \stream_select($read, $write, $except, 0, $tv_usec)) {
				foreach ($read as $h) {
					// we don't rely on $read index because
					// stream_select may change it
					$types = [self::OUT, self::ERR];

					foreach ($types as $type) {
						if (isset($sources[$type]) && $sources[$type] === $h) {
							if ($data = $this->readRemainder($h)) {
								\fwrite($type === self::ERR ? $this->tmp_stderr : $this->tmp_stdout, $data);

								if (isset($this->read_cb[$type])) {
									\call_user_func($this->read_cb[$type], $data);
								}
							}

							break;
						}
					}
				}
			}
		}

		foreach ($files_handles as $handle) {
			\fclose($handle);
		}
	}

	/**
	 * Reads remainder of a stream into a string.
	 *
	 * @param resource $handle
	 * @param int      $chunk_size
	 *
	 * @return string
	 */
	private function readRemainder($handle, $chunk_size = 1024)
	{
		$all = '';

		do {
			$data = \fread($handle, $chunk_size);
			$all .= $data;
		} while (isset($data[0], $data[$chunk_size - 1]));

		return $all;
	}

	/**
	 * Unblock mode to prevent deadlock in certain circumstance.
	 */
	private function unblock()
	{
		if ($this->pipes && !$this->unblocked) {
			$this->unblocked = true;

			foreach ($this->pipes as $k => $pipe) {
				\stream_set_blocking($pipe, 0);
			}
		}
	}

	/**
	 * Asserts that the process is opened.
	 *
	 * @param string $method
	 */
	private function assertOpenedProcess($method)
	{
		if (!$this->process) {
			throw new RuntimeException(\sprintf('Makes sure process is opened before calling %s.', $method));
		}
	}

	/**
	 * Asserts that the process is not opened.
	 *
	 * @param string $method
	 */
	private function assertNoOpenedProcess($method)
	{
		if ($this->process) {
			throw new RuntimeException(\sprintf('You should not call %s after process is opened.', $method));
		}
	}
}
