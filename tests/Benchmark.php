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

namespace OZONE\OZ\Tests;

use LogicException;

/**
 * Class Benchmark.
 */
class Benchmark
{
	private array $keys = [
		'ref'               => 'Reference',
		'iteration_count'   => 'Iteration Count',
		'duplicate'         => 'Duplicate',
		'iteration_min'     => 'Min. Time/Iteration',
		'iteration_max'     => 'Max. Time/Iteration',
		'iteration_average' => 'Avg. Time/Iteration',
		'iteration_sum'     => 'Real Time',
		'benchmark'         => 'Benchmark Total Time',
	];

	private bool $check_duplicate;

	private array $results;

	private ?int $max_iteration = null;
	private ?int $max_duration  = null;

	/**
	 * Benchmark constructor.
	 *
	 * @param bool $check_duplicate
	 */
	public function __construct(bool $check_duplicate = true)
	{
		$this->check_duplicate = $check_duplicate;
	}

	/**
	 * @param array    $callable_list
	 * @param int      $max_duration
	 * @param null|int $max_iteration
	 *
	 * @return $this
	 */
	public function runBenchmark(array $callable_list, int $max_duration, ?int $max_iteration = null): self
	{
		$results             = [];
		$this->max_duration  = $max_duration;
		$this->max_iteration = $max_iteration;

		foreach ($callable_list as $name => $func) {
			if (\is_int($name)) {
				$name = \is_string($func) ? $func : $name;
			}

			$results[$name] = $this->run($name, $func);
		}

		$this->results = $results;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getResults(): array
	{
		return $this->results;
	}

	/**
	 * @return $this
	 */
	public function orderByMostFaster(): static
	{
		$this->assertRun();
		$results = $this->results;

		\usort($results, static function ($a, $b) {
			$a_w = $a['iteration_average'];
			$b_w = $b['iteration_average'];

			return $a_w <=> $b_w;
		});

		$this->results = $results;

		return $this;
	}

	/**
	 * @return $this
	 */
	public function orderByBestEntropy(): static
	{
		$this->assertRun();
		$results = $this->results;

		\usort($results, static function ($a, $b) {
			$a_w = \max($a['duplicate'], 1) / $a['iteration_count'];
			$b_w = \max($b['duplicate'], 1) / $b['iteration_count'];

			return $a_w <=> $b_w;
		});

		$this->results = $results;

		return $this;
	}

	public function prettyPrint(): self
	{
		$this->assertRun();

		$stats = $this->results;
		$key0  = \array_key_first($stats);

		if (null === $key0) {
			echo 'Empty result.';

			return $this;
		}

		$first   = $stats[$key0];
		$headers = [];

		foreach ($first as $key => $value) {
			$headers[] = $this->keys[$key];
		}

		$output = \implode(',', $headers) . \PHP_EOL;

		foreach ($stats as $name => $item) {
			$output .= \implode(',', $item) . \PHP_EOL;
		}

		self::printCSV($output);

		return $this;
	}

	public static function printCSV(string $csv): void
	{
		$out_file = \tempnam('/tmp', 'test');
		\file_put_contents($out_file, $csv);

		echo '===========================================' . \PHP_EOL;
		echo \shell_exec("column -ts, {$out_file}");

		\unlink($out_file);
	}

	/**
	 * @param string   $ref
	 * @param callable $fn
	 *
	 * @return array
	 */
	private function run(string $ref, callable $fn): array
	{
		$bm_start        = \microtime(true);
		$it_duration_sum = 0;
		$it_duration_min = 0;
		$it_duration_max = 0;
		$ret_list        = [];
		$dup_count       = 0;
		$it_count        = 0;

		while (true) {
			if (isset($this->max_duration) && (\microtime(true) - $bm_start) >= $this->max_duration) {
				break;
			}
			if (isset($this->max_iteration) && $it_count >= $this->max_iteration) {
				break;
			}

			$it_start = \microtime(true);
			$ret      = $fn();
			$it_end   = \microtime(true);

			$dur             = $it_end - $it_start;
			$it_duration_sum += $dur;

			if ($this->check_duplicate) {
				if (isset($ret_list[$ret])) {
					++$dup_count;
				} else {
					$ret_list[$ret] = 1;
				}
			}

			if (0 === $it_count) {
				$it_duration_min = $it_duration_max = $dur;
			}

			if ($it_duration_max < $dur) {
				$it_duration_max = $dur;
			}

			if ($it_duration_min > $dur) {
				$it_duration_min = $dur;
			}

			++$it_count;
		}

		$bm_end = \microtime(true);

		return [
			'ref'               => $ref,
			'duplicate'         => $this->check_duplicate ? $dup_count : 0,
			'iteration_count'   => $it_count,
			'iteration_min'     => $it_duration_min,
			'iteration_max'     => $it_duration_max,
			'iteration_average' => $it_duration_sum / $it_count,
			'iteration_sum'     => $it_duration_sum,
			'benchmark'         => ($bm_end - $bm_start),
		];
	}

	private function assertRun(): void
	{
		if (!isset($this->results)) {
			throw new LogicException('You should run a benchmark first.');
		}
	}
}
