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

namespace OZONE\OZ\Cli;

use OZONE\OZ\Cli\Utils\Utils;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\FS\FilesManager;

/**
 * Class Process.
 */
final class Process
{
	public const IN = 0;

	public const OUT = 1;

	public const ERR = 2;

	private array $env;

	private string $command;

	private ?array $pipes = null;

	private array $files;

	private string $cwd;

	/**
	 * @var bool|resource
	 */
	private $process;

	private false|array|null $status = null;

	private array $descriptors = [
		self::IN  => ['pipe', 'r'], // stdin
		self::OUT => ['pipe', 'w'], // stdout
		self::ERR => ['pipe', 'w'], // stderr
	];

	private array $read_cb = [];

	private mixed $tmp_stdout = null;

	private mixed $tmp_stderr = null;

	/**
	 * @var \OZONE\OZ\FS\FilesManager
	 */
	private FilesManager $fm;

	private bool $unblocked = false;

	/**
	 * @param mixed $cmd
	 * @param mixed $cwd
	 */

	/**
	 * Process constructor.
	 *
	 * @param string     $cmd the command to run
	 * @param string     $cwd the current working directory
	 * @param null|array $env the environment variables for the command
	 */
	public function __construct(string $cmd, string $cwd = '.', array $env = null)
	{
		$this->env = \array_merge(Utils::getDefaultEnv(), !empty($env) ? $env : []);

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
	public function open(): self
	{
		$this->assertNoOpenedProcess(__METHOD__);

		$options = [];
		$command = Utils::getPlatform()
			->format($this->command);

		if (Utils::isDOS()) {
			$options['bypass_shell'] = true;
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
	public function close(): int
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
	public function setStdin(string $file_path): self
	{
		$this->assertNoOpenedProcess(__METHOD__);

		$file_path = $this->fm->resolve($file_path);

		$this->fm->filter()
			->isFile()
			->isReadable()
			->isWritable()
			->assert($file_path);

		$this->descriptors[self::IN] = ['file', $file_path, 'r']; // the command need to read
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
	public function setStdout(string $file_path): self
	{
		$this->assertNoOpenedProcess(__METHOD__);

		$file_path = $this->fm->resolve($file_path);

		$this->fm->filter()
			->isFile()
			->isReadable()
			->isWritable()
			->assert($file_path);

		$this->descriptors[self::OUT] = ['file', $file_path, 'w']; // the command need to write
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
	public function setStderr(string $file_path): self
	{
		$this->assertNoOpenedProcess(__METHOD__);

		$file_path = $this->fm->resolve($file_path);

		$this->fm->filter()
			->isFile()
			->isReadable()
			->isWritable()
			->assert($file_path);

		$this->descriptors[self::ERR] = ['file', $file_path, 'w']; // the command need to write
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
	public function writeStdin(string $input): bool|int
	{
		$this->assertOpenedProcess(__METHOD__);

		if (isset($this->pipes[self::IN]) && \is_resource($this->pipes[0])) {
			return \fwrite($this->pipes[self::IN], $input);
		}

		if (isset($this->files[self::IN])) {
			return \file_put_contents($this->files[self::IN], $input, \FILE_APPEND);
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
	public function watch(callable $stdout_cb, callable $stderr_cb): self
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
	public function readStdout(): false|string
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
	public function readStderr(): false|string
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
	public function kill(): bool
	{
		if ($this->isRunning()) {
			$pid = $this->getPid();

			$ok = Utils::getPlatform()
				->kill($pid);

			$this->close();

			return $ok;
		}

		return true;
	}

	/**
	 * Returns the process pid.
	 *
	 * @return mixed
	 */
	public function getPid(): mixed
	{
		$this->assertOpenedProcess(__METHOD__);

		return $this->getStatus()['pid'];
	}

	/**
	 * Checks if the process is running.
	 *
	 * @return bool
	 */
	public function isRunning(): bool
	{
		if ($this->process) {
			$status = $this->getStatus(true);

			return (bool) $status['running'];
		}

		return false;
	}

	/**
	 * Returns process status.
	 *
	 * @param bool $refresh
	 *
	 * @return null|array|false
	 */
	public function getStatus(bool $refresh = false): false|array|null
	{
		$this->assertOpenedProcess(__METHOD__);

		if ($refresh || !$this->status) {
			/** @var array|false $result */
			$result = \proc_get_status($this->process);

			if (!$result) {
				throw new RuntimeException('Unable to get running process status.');
			}

			$this->status = $result;

			if (Utils::sigChildEnabled()) {
				$this->status['signaled'] = true;
				$this->status['exitcode'] = -1;
				$this->status['termsig']  = -1;
			}
		}

		return $this->status;
	}

	/**
	 * Read helper.
	 */
	private function readHelper(): void
	{
		$this->assertOpenedProcess(__METHOD__);

		$this->unblock();
		$tv_usec = 200000; // 0.2 sec

		$tmp_path         = 'php://temp/maxmemory:' . (1024 * 1024);
		$this->tmp_stdout = \fopen($tmp_path, 'wb+');
		$this->tmp_stderr = \fopen($tmp_path, 'wb+');
		$read             = [];
		$sources          = [];
		$files_handles    = [];

		if ($this->files) {
			foreach ($this->files as $key => $path) {
				if (self::ERR === $key || $key = self::OUT) {
					$files_handles[$key] = $read[$key] = $sources[$key] = \fopen($path, 'rb');
				}
			}
		}

		while ($this->isRunning()) {
			if ($this->pipes) {
				foreach ($this->pipes as $key => $pipe) {
					if (self::ERR === $key || $key = self::OUT) {
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
								\fwrite(self::ERR === $type ? $this->tmp_stderr : $this->tmp_stdout, $data);

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
	 *
	 * @return string
	 */
	private function readRemainder($handle): string
	{
		$all = '';

		do {
			$data = \fread($handle, 1024);
			$all .= $data;
		} while (isset($data[0], $data[1024 - 1]));

		return $all;
	}

	/**
	 * Unblock mode to prevent deadlock in certain circumstance.
	 */
	private function unblock(): void
	{
		if ($this->pipes && !$this->unblocked) {
			$this->unblocked = true;

			foreach ($this->pipes as $pipe) {
				\stream_set_blocking($pipe, false);
			}
		}
	}

	/**
	 * Asserts that the process is opened.
	 *
	 * @param string $method
	 */
	private function assertOpenedProcess(string $method): void
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
	private function assertNoOpenedProcess(string $method): void
	{
		if ($this->process) {
			throw new RuntimeException(\sprintf('You should not call %s after process is opened.', $method));
		}
	}
}
