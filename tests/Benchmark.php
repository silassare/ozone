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

namespace OZONE\Tests;

use InvalidArgumentException;
use JsonException;
use Kli\KliStyle;
use Kli\Table\Interfaces\KliTableCellFormatterInterface;
use Kli\Table\KliTable;
use Kli\Table\KliTableFormatter;
use Kli\Table\KliTableHeader;
use LogicException;
use PHPUtils\Str;

/**
 * Class Benchmark.
 *
 * Measures execution speed and related statistics for one or more PHP callables.
 * Designed to detect speed regressions, identify bottlenecks, and provide a
 * rich set of stats (avg, min, max, median, p95, std dev, ops/sec, duplicate
 * rate, and optional memory delta) rendered in terminal tables via KliTable.
 *
 * Typical usage:
 *
 *   // Collect a baseline, persist it, then compare in CI:
 *   $bm = Benchmark::create()
 *       ->maxDuration(2)
 *       ->warmup(5)
 *       ->trackMemory()
 *       ->run(['sha256' => fn() => hash('sha256', 'test'),
 *              'sha512' => fn() => hash('sha512', 'test')]);
 *
 *   $bm->prettyPrint();
 *   file_put_contents('baseline.json', $bm->exportJson());
 *
 *   // Later, in a regression check:
 *   $baseline = Benchmark::fromJson(file_get_contents('baseline.json'));
 *   $current  = Benchmark::create()->maxDuration(2)->warmup(5)
 *       ->run(['sha256' => ..., 'sha512' => ...]);
 *
 *   $current->compareWith($baseline);
 *
 * Result record keys (returned by getResults() / getResult()):
 *   - ref          string  callable label
 *   - iterations   int     number of measured calls
 *   - ops_per_sec  float   theoretical ops/sec derived from avg_ns
 *   - avg_ns       float   mean call duration in nanoseconds
 *   - min_ns       float   fastest call in nanoseconds
 *   - max_ns       float   slowest call in nanoseconds
 *   - median_ns    float   median call duration in nanoseconds
 *   - p95_ns       float   95th-percentile call duration in nanoseconds
 *   - stddev_ns    float   sample standard deviation in nanoseconds
 *   - total_s      float   sum of measured durations in seconds (pure CPU time)
 *   - wall_s       float   total wall-clock time in seconds (incl. loop overhead)
 *   - dup_count    int     number of duplicate return values (0 if disabled)
 *   - dup_rate     float   duplicate percentage (0.0 if disabled)
 *   - memory_kb    ?float  peak memory usage change in KB (null if disabled)
 */
class Benchmark
{
	/**
	 * Default number of warmup calls before measurement begins.
	 */
	private const DEFAULT_WARMUP = 3;

	/**
	 * Default regression detection threshold in percent.
	 */
	private const DEFAULT_THRESHOLD = 5.0;

	/**
	 * Number of unmeasured warmup calls fired before each callable's timed loop.
	 * Warmup exercises CPU caches, op-caches, and branch predictors without
	 * skewing results.
	 */
	private int $warmup = self::DEFAULT_WARMUP;

	/**
	 * Maximum wall-clock seconds to run a single callable.
	 * Measurement stops once this duration is exceeded.
	 * At least one of $maxDuration or $maxIterations must be set before run().
	 */
	private ?float $maxDuration = null;

	/**
	 * Maximum number of measured calls per callable.
	 * Measurement stops once this count is reached.
	 * At least one of $maxDuration or $maxIterations must be set before run().
	 */
	private ?int $maxIterations = null;

	/**
	 * When true, each call's return value is compared against previously seen
	 * values; repeats increment dup_count. Useful for validating randomness.
	 */
	private bool $checkDuplicate = false;

	/**
	 * When true, the peak memory usage change across each callable's run is
	 * recorded in the memory_kb result column.
	 */
	private bool $trackMemory = false;

	/**
	 * Percentage band used by compareWith() to classify a change as STABLE.
	 * Changes above this value are REGRESSION; below its negative, IMPROVEMENT.
	 */
	private float $regressionThreshold = self::DEFAULT_THRESHOLD;

	/**
	 * Collected result records, keyed by callable name.
	 *
	 * @var null|array<string, array>
	 */
	private ?array $results = null;

	/**
	 * Machine / environment metadata embedded by exportJson() and decoded by
	 * fromJson(). Populated only on instances created via fromJson().
	 * Used by callers to detect machine changes before comparing baselines.
	 *
	 * @var array<string, mixed>
	 */
	private array $exportedMeta = [];

	/**
	 * Named constructor for fluent chaining.
	 */
	public static function create(): static
	{
		return new static();
	}

	/**
	 * Sets the number of unmeasured warmup calls executed before measurement.
	 *
	 * @param int $iterations number of warmup calls (>= 0, default 3)
	 */
	public function warmup(int $iterations): static
	{
		if ($iterations < 0) {
			throw new InvalidArgumentException('Warmup iterations must be >= 0.');
		}

		$this->warmup = $iterations;

		return $this;
	}

	/**
	 * Stops measuring a callable after this many wall-clock seconds have elapsed.
	 * Either maxDuration() or maxIterations() must be configured before run().
	 *
	 * @param float $seconds wall-clock cutoff per callable (> 0)
	 */
	public function maxDuration(float $seconds): static
	{
		if ($seconds <= 0.0) {
			throw new InvalidArgumentException('Max duration must be > 0.');
		}

		$this->maxDuration = $seconds;

		return $this;
	}

	/**
	 * Stops measuring a callable once this many iterations have completed.
	 * Either maxDuration() or maxIterations() must be configured before run().
	 *
	 * @param int $n iteration hard limit (> 0)
	 */
	public function maxIterations(int $n): static
	{
		if ($n <= 0) {
			throw new InvalidArgumentException('Max iterations must be > 0.');
		}

		$this->maxIterations = $n;

		return $this;
	}

	/**
	 * Enables duplicate return-value tracking.
	 * When enabled each call's return value is serialized and tracked; repeated
	 * values accumulate dup_count and dup_rate in the result.
	 * Useful for verifying that generators produce sufficiently unique output.
	 *
	 * @param bool $check default true
	 */
	public function checkDuplicate(bool $check = true): static
	{
		$this->checkDuplicate = $check;

		return $this;
	}

	/**
	 * Enables per-callable peak memory tracking.
	 * When enabled the change in peak memory between the start and end of each
	 * callable's run loop is recorded in the memory_kb result column.
	 * Note: peak tracking is approximate; GC activity can affect readings.
	 *
	 * @param bool $track default true
	 */
	public function trackMemory(bool $track = true): static
	{
		$this->trackMemory = $track;

		return $this;
	}

	/**
	 * Sets the percentage threshold used by compareWith() to classify changes.
	 * A change strictly above +$pct is REGRESSION; strictly below -$pct is
	 * IMPROVEMENT; anything within the band is STABLE.
	 * Default: 5.0 (5%).
	 *
	 * @param float $pct positive percentage, e.g. 5.0 for +/- 5%
	 */
	public function regressionThreshold(float $pct): static
	{
		if ($pct <= 0.0) {
			throw new InvalidArgumentException('Regression threshold must be > 0.');
		}

		$this->regressionThreshold = $pct;

		return $this;
	}

	/**
	 * Benchmarks each callable in $callables and stores the results.
	 * Overwrites any previously stored results; call reset() first if accumulation
	 * across multiple run() calls is needed (e.g. adding entries incrementally).
	 * At least one of maxDuration() or maxIterations() must be configured first.
	 *
	 * Integer-keyed entries are auto-named: string callables use their name,
	 * others fall back to their numeric index cast to string.
	 *
	 * @param array<array-key, callable> $callables
	 */
	public function run(array $callables): static
	{
		if (null === $this->maxDuration && null === $this->maxIterations) {
			throw new LogicException('Call maxDuration() or maxIterations() before run().');
		}

		$measured = [];

		foreach ($callables as $name => $fn) {
			if (!\is_callable($fn)) {
				throw new InvalidArgumentException(\sprintf('Entry "%s" is not callable.', $name));
			}

			if (\is_int($name)) {
				$name = \is_string($fn) ? $fn : (string) $name;
			}

			$measured[(string) $name] = $this->measureCallable((string) $name, $fn);
		}

		$this->results = $measured;

		return $this;
	}

	/**
	 * Clears all stored results so the Benchmark instance can be reused for a
	 * fresh run without creating a new object.
	 */
	public function reset(): static
	{
		$this->results = null;

		return $this;
	}

	/**
	 * Returns all result records keyed by callable name.
	 *
	 * @return array<string, array>
	 */
	public function getResults(): array
	{
		$this->assertRan();

		return $this->results;
	}

	/**
	 * Returns the result record for a single callable, or null if not found.
	 *
	 * @param string $ref callable name as provided to run()
	 *
	 * @return null|array
	 */
	public function getResult(string $ref): ?array
	{
		$this->assertRan();

		return $this->results[$ref] ?? null;
	}

	/**
	 * Sorts stored results by ascending average call time (fastest first).
	 */
	public function orderByFastest(): static
	{
		$this->assertRan();
		\uasort($this->results, static fn ($a, $b) => $a['avg_ns'] <=> $b['avg_ns']);

		return $this;
	}

	/**
	 * Sorts stored results by descending average call time (slowest first).
	 *
	 * @return $this
	 */
	public function orderBySlowest(): static
	{
		$this->assertRan();
		\uasort($this->results, static fn ($a, $b) => $b['avg_ns'] <=> $a['avg_ns']);

		return $this;
	}

	/**
	 * Sorts stored results by ascending duplicate rate (lowest dup_rate first).
	 * Most useful after checkDuplicate(true); without it all rates are 0.
	 */
	public function orderByBestEntropy(): static
	{
		$this->assertRan();
		\uasort($this->results, static fn ($a, $b) => $a['dup_rate'] <=> $b['dup_rate']);

		return $this;
	}

	/**
	 * Prints a full statistics table to stdout.
	 * All collected metrics are displayed, plus a "Relative" column that shows
	 * each callable's average time relative to the fastest in this run
	 * (1.00x = fastest, highlighted in green).
	 */
	public function prettyPrint(): static
	{
		$this->assertRan();

		if (empty($this->results)) {
			echo 'No benchmark results.' . \PHP_EOL;

			return $this;
		}

		$fastestAvg = $this->fastestAvg();
		$rows       = [];

		foreach ($this->results as $result) {
			$row             = $result;
			$row['relative'] = $fastestAvg > 0.0 ? $result['avg_ns'] / $fastestAvg : 0.0;
			$rows[]          = $row;
		}

		$table = new KliTable();
		$this->buildMainTable($table);
		$table->addRows($rows);
		echo $table;

		return $this;
	}

	/**
	 * Prints a compact summary table to stdout showing only the most essential
	 * columns: Reference, Iterations, Ops/sec, Avg (ns), and Relative speed.
	 * Faster to scan at a glance than the full prettyPrint() table.
	 */
	public function printSummary(): static
	{
		$this->assertRan();

		if (empty($this->results)) {
			echo 'No benchmark results.' . \PHP_EOL;

			return $this;
		}

		$fastestAvg = $this->fastestAvg();
		$rows       = [];

		foreach ($this->results as $result) {
			$rows[] = [
				'ref'         => $result['ref'],
				'iterations'  => $result['iterations'],
				'ops_per_sec' => $result['ops_per_sec'],
				'avg_ns'      => $result['avg_ns'],
				'relative'    => $fastestAvg > 0.0 ? $result['avg_ns'] / $fastestAvg : 0.0,
			];
		}

		$table = new KliTable();
		$table->addHeader('Reference', 'ref')->alignLeft();
		$table->addHeader('Iterations', 'iterations')->alignRight()
			->setCellFormatter(KliTableFormatter::number());
		$table->addHeader('Ops/sec', 'ops_per_sec')->alignRight()
			->setCellFormatter(KliTableFormatter::number(2));
		$table->addHeader('Avg (ns)', 'avg_ns')->alignRight()
			->setCellFormatter($this->nsFormatter());
		$table->addHeader('Relative', 'relative')->alignRight()
			->setCellFormatter($this->relativeFormatter());
		$table->addRows($rows);
		echo $table;

		return $this;
	}

	/**
	 * Prints a regression comparison table contrasting this run against $baseline.
	 *
	 * Each callable present in both runs shows its current avg, baseline avg,
	 * the absolute delta, the percentage change, and a status label:
	 *   - REGRESSION  : avg increased beyond regressionThreshold() (red, bold)
	 *   - IMPROVEMENT : avg decreased beyond regressionThreshold() (green, bold)
	 *   - STABLE      : change within the threshold band (yellow)
	 *   - NEW         : callable present in current run but not in baseline (cyan)
	 *   - REMOVED     : callable present in baseline but not in current run (dark gray)
	 *
	 * @param Benchmark $baseline the reference run to compare against
	 */
	public function compareWith(self $baseline): static
	{
		$this->assertRan();
		$baseline->assertRan();

		$baseResults = $baseline->results;
		$rows        = [];

		foreach ($this->results as $ref => $current) {
			$base = $baseResults[$ref] ?? null;

			if (null === $base) {
				$rows[] = [
					'ref'         => $ref,
					'current_avg' => $current['avg_ns'],
					'base_avg'    => null,
					'delta_ns'    => null,
					'change_pct'  => null,
					'status'      => 'NEW',
				];
			} else {
				$delta     = $current['avg_ns'] - $base['avg_ns'];
				$changePct = $base['avg_ns'] > 0.0
					? ($delta / $base['avg_ns']) * 100.0
					: 0.0;

				if ($changePct > $this->regressionThreshold) {
					$status = 'REGRESSION';
				} elseif ($changePct < -$this->regressionThreshold) {
					$status = 'IMPROVEMENT';
				} else {
					$status = 'STABLE';
				}

				$rows[] = [
					'ref'         => $ref,
					'current_avg' => $current['avg_ns'],
					'base_avg'    => $base['avg_ns'],
					'delta_ns'    => $delta,
					'change_pct'  => $changePct,
					'status'      => $status,
				];
			}
		}

		// Baseline-only entries (removed from current run).
		foreach ($baseResults as $ref => $base) {
			if (!isset($this->results[$ref])) {
				$rows[] = [
					'ref'         => $ref,
					'current_avg' => null,
					'base_avg'    => $base['avg_ns'],
					'delta_ns'    => null,
					'change_pct'  => null,
					'status'      => 'REMOVED',
				];
			}
		}

		$nsFormat = $this->nsFormatter();
		$table    = new KliTable();
		$table->addHeader('Reference', 'ref')->alignLeft();
		$table->addHeader('Current (ns)', 'current_avg')->alignRight()->setCellFormatter($nsFormat);
		$table->addHeader('Baseline (ns)', 'base_avg')->alignRight()->setCellFormatter($nsFormat);
		$table->addHeader('Delta (ns)', 'delta_ns')->alignRight()->setCellFormatter($this->deltaFormatter());
		$table->addHeader('Change %', 'change_pct')->alignRight()->setCellFormatter($this->pctFormatter());
		$table->addHeader('Status', 'status')->alignCenter()->setCellFormatter($this->statusFormatter());
		$table->addRows($rows);
		echo $table;

		return $this;
	}

	/**
	 * Serialises all results to a JSON string, optionally embedding machine
	 * metadata in a wrapping envelope.
	 *
	 * When $meta is provided the output format is:
	 *   { "meta": { ...meta... }, "results": { ...results... } }
	 *
	 * This envelope lets fromJson() detect a machine change when the baseline
	 * was recorded on a different CPU/OS/PHP version and warn the caller
	 * instead of silently producing misleading regression reports.
	 *
	 * When $meta is empty the output is the flat results map (backward-compatible
	 * with baselines written before the envelope was introduced).
	 *
	 * @param array<string, mixed> $meta optional environment fingerprint
	 *
	 * @return string
	 */
	public function exportJson(array $meta = []): string
	{
		$this->assertRan();

		$payload = empty($meta)
			? $this->results
			: ['meta' => $meta, 'results' => $this->results];

		return \json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Creates a Benchmark instance from JSON previously exported by exportJson().
	 * The returned instance can be passed directly to compareWith() as a baseline
	 * without re-running any callables.
	 *
	 * Accepts both the legacy flat format (plain results map) and the envelope
	 * format ({ "meta": {...}, "results": {...} }) introduced alongside machine
	 * fingerprinting. When the envelope is detected the meta is stored and
	 * accessible via getExportedMeta().
	 *
	 * @param string $json JSON string produced by exportJson()
	 *
	 * @throws JsonException            if the JSON is malformed
	 * @throws InvalidArgumentException if the decoded value is not an object/array
	 */
	public static function fromJson(string $json): static
	{
		$data = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

		if (!\is_array($data)) {
			throw new InvalidArgumentException('Invalid benchmark JSON: expected an object.');
		}

		$instance = new self();

		// Detect envelope format: { "meta": {...}, "results": {...} }.
		// Legacy flat format has string-keyed records without a "results" sub-key
		// at the top level whose value is also an array of records.
		if (isset($data['meta'], $data['results']) && \is_array($data['results'])) {
			$instance->exportedMeta = (array) $data['meta'];
			$instance->results      = $data['results'];
		} else {
			$instance->results = $data; // legacy flat format
		}

		return $instance;
	}

	/**
	 * Returns the machine/environment metadata that was embedded when the JSON
	 * was exported via exportJson(array $meta). Returns an empty array when the
	 * instance was created via run() (not loaded from JSON) or when the JSON was
	 * in the legacy flat format without a meta envelope.
	 *
	 * @return array<string, mixed>
	 */
	public function getExportedMeta(): array
	{
		return $this->exportedMeta;
	}

	/**
	 * Runs warmup calls, then executes the timed loop until the configured
	 * stopping condition is met, and returns the statistics record for $ref.
	 *
	 * Uses hrtime(true) for nanosecond-resolution monotonic timing; this clock
	 * is unaffected by NTP adjustments or DST changes.
	 *
	 * @param string   $ref human-readable label for this callable
	 * @param callable $fn  the callable to measure
	 *
	 * @return array
	 */
	private function measureCallable(string $ref, callable $fn): array
	{
		// Warmup: prime CPU caches and JIT without affecting stats.
		for ($i = 0; $i < $this->warmup; ++$i) {
			$fn();
		}

		$wallStart = \microtime(true);
		$durations = [];
		$retList   = [];
		$dupCount  = 0;
		$memBefore = $this->trackMemory ? \memory_get_peak_usage(true) : 0;

		while (true) {
			if (null !== $this->maxDuration && (\microtime(true) - $wallStart) >= $this->maxDuration) {
				break;
			}

			if (null !== $this->maxIterations && \count($durations) >= $this->maxIterations) {
				break;
			}

			$tStart = \hrtime(true);
			$ret    = $fn();
			$ns     = \hrtime(true) - $tStart;

			$durations[] = $ns;

			if ($this->checkDuplicate) {
				$key = \is_scalar($ret) ? (string) $ret : \serialize($ret);

				if (isset($retList[$key])) {
					++$dupCount;
				} else {
					$retList[$key] = true;
				}
			}
		}

		$wallTotal = \microtime(true) - $wallStart;
		$count     = \count($durations);
		$memDelta  = $this->trackMemory
			? \round((\memory_get_peak_usage(true) - $memBefore) / 1024, 2)
			: null;

		if (0 === $count) {
			return $this->emptyResult($ref, $wallTotal, $memDelta);
		}

		$stats = $this->computeStats($durations);

		return [
			'ref'         => $ref,
			'iterations'  => $count,
			'ops_per_sec' => $stats['avg'] > 0.0 ? 1e9 / $stats['avg'] : 0.0,
			'avg_ns'      => $stats['avg'],
			'min_ns'      => $stats['min'],
			'max_ns'      => $stats['max'],
			'median_ns'   => $stats['median'],
			'p95_ns'      => $stats['p95'],
			'stddev_ns'   => $stats['stddev'],
			'total_s'     => \array_sum($durations) / 1e9,
			'wall_s'      => $wallTotal,
			'dup_count'   => $this->checkDuplicate ? $dupCount : 0,
			'dup_rate'    => ($this->checkDuplicate && $count > 0)
				? ($dupCount / $count) * 100.0
				: 0.0,
			'memory_kb'   => $memDelta,
		];
	}

	/**
	 * Returns a zeroed statistics record used when no iterations completed
	 * (e.g. maxDuration was already exceeded before the first call finished).
	 *
	 * @param string     $ref
	 * @param float      $wallTotal
	 * @param null|float $memDelta
	 *
	 * @return array
	 */
	private function emptyResult(string $ref, float $wallTotal, ?float $memDelta): array
	{
		return [
			'ref'         => $ref,
			'iterations'  => 0,
			'ops_per_sec' => 0.0,
			'avg_ns'      => 0.0,
			'min_ns'      => 0.0,
			'max_ns'      => 0.0,
			'median_ns'   => 0.0,
			'p95_ns'      => 0.0,
			'stddev_ns'   => 0.0,
			'total_s'     => 0.0,
			'wall_s'      => $wallTotal,
			'dup_count'   => 0,
			'dup_rate'    => 0.0,
			'memory_kb'   => $memDelta,
		];
	}

	/**
	 * Computes descriptive statistics from an array of nanosecond durations.
	 * The input array is sorted ascending as a side effect.
	 *
	 * Statistics returned:
	 *   - min    : smallest value
	 *   - max    : largest value
	 *   - avg    : arithmetic mean
	 *   - median : middle value (average of two middle values for even counts)
	 *   - p95    : 95th percentile via nearest-rank method
	 *   - stddev : sample standard deviation (Bessel's correction, n-1)
	 *
	 * @param int[] $durations nanosecond per-call durations (modified in place)
	 *
	 * @return array{min: float, max: float, avg: float, median: float, p95: float, stddev: float}
	 */
	private function computeStats(array $durations): array
	{
		$count = \count($durations);
		\sort($durations);

		$min = (float) $durations[0];
		$max = (float) $durations[$count - 1];
		$avg = \array_sum($durations) / $count;

		$mid    = (int) ($count / 2);
		$median = (0 === $count % 2)
			? ($durations[$mid - 1] + $durations[$mid]) / 2.0
			: (float) $durations[$mid];

		$p95Idx = (int) \ceil(0.95 * $count) - 1;
		$p95    = (float) $durations[\max(0, $p95Idx)];

		$variance = 0.0;

		foreach ($durations as $d) {
			$variance += ($d - $avg) ** 2;
		}

		$stddev = $count > 1 ? \sqrt($variance / ($count - 1)) : 0.0;

		return [
			'min'    => $min,
			'max'    => $max,
			'avg'    => $avg,
			'median' => $median,
			'p95'    => $p95,
			'stddev' => $stddev,
		];
	}

	/**
	 * Attaches all standard column headers to $table for the full stats view.
	 * Optional columns (dup_count, dup_rate, memory_kb) are added only when
	 * the corresponding feature was enabled at configuration time.
	 *
	 * @param KliTable $table the table to configure
	 */
	private function buildMainTable(KliTable $table): void
	{
		$nsFormat = $this->nsFormatter();

		$table->addHeader('Reference', 'ref')->alignLeft();
		$table->addHeader('Iterations', 'iterations')->alignRight()
			->setCellFormatter(KliTableFormatter::number());
		$table->addHeader('Ops/sec', 'ops_per_sec')->alignRight()
			->setCellFormatter(KliTableFormatter::number(2));
		$table->addHeader('Avg (ns)', 'avg_ns')->alignRight()
			->setCellFormatter($nsFormat);
		$table->addHeader('Min (ns)', 'min_ns')->alignRight()
			->setCellFormatter($nsFormat);
		$table->addHeader('Max (ns)', 'max_ns')->alignRight()
			->setCellFormatter($nsFormat);
		$table->addHeader('Median (ns)', 'median_ns')->alignRight()
			->setCellFormatter($nsFormat);
		$table->addHeader('p95 (ns)', 'p95_ns')->alignRight()
			->setCellFormatter($nsFormat);
		$table->addHeader('Std Dev (ns)', 'stddev_ns')->alignRight()
			->setCellFormatter($nsFormat);
		$table->addHeader('Total (s)', 'total_s')->alignRight()
			->setCellFormatter(KliTableFormatter::number(6));
		$table->addHeader('Wall (s)', 'wall_s')->alignRight()
			->setCellFormatter(KliTableFormatter::number(6));
		$table->addHeader('Relative', 'relative')->alignRight()
			->setCellFormatter($this->relativeFormatter());

		if ($this->checkDuplicate) {
			$table->addHeader('Dups', 'dup_count')->alignRight()
				->setCellFormatter(KliTableFormatter::number());
			$table->addHeader('Dup %', 'dup_rate')->alignRight()
				->setCellFormatter(KliTableFormatter::number(2));
		}

		if ($this->trackMemory) {
			$table->addHeader('Mem (KB)', 'memory_kb')->alignRight()
				->setCellFormatter(KliTableFormatter::number(2));
		}
	}

	/**
	 * Returns a formatter that renders float nanosecond values to 2 decimal
	 * places, falling back to "N/A" for null values.
	 *
	 * @return KliTableCellFormatterInterface
	 */
	private function nsFormatter(): KliTableCellFormatterInterface
	{
		return new class implements KliTableCellFormatterInterface {
			public function format(mixed $value, KliTableHeader $header, array $row): string
			{
				return null === $value ? 'N/A' : \number_format((float) $value, 2);
			}

			public function getStyle(mixed $value, KliTableHeader $header, array $row): ?KliStyle
			{
				return null;
			}
		};
	}

	/**
	 * Returns a formatter that renders relative speed as "X.XXx".
	 * The fastest callable (1.00x) is highlighted in green+bold.
	 *
	 * @return KliTableCellFormatterInterface
	 */
	private function relativeFormatter(): KliTableCellFormatterInterface
	{
		return new class implements KliTableCellFormatterInterface {
			public function format(mixed $value, KliTableHeader $header, array $row): string
			{
				return null === $value ? 'N/A' : \number_format((float) $value, 2) . 'x';
			}

			public function getStyle(mixed $value, KliTableHeader $header, array $row): ?KliStyle
			{
				// Highlight the fastest entry (with a tiny epsilon for float rounding).
				return (null !== $value && (float) $value <= 1.001)
					? (new KliStyle())->green()->bold()
					: null;
			}
		};
	}

	/**
	 * Returns a formatter for nanosecond delta values in the regression table.
	 * Positive deltas (slower) are red; negative deltas (faster) are green.
	 * A "+" prefix is added for positive values.
	 *
	 * @return KliTableCellFormatterInterface
	 */
	private function deltaFormatter(): KliTableCellFormatterInterface
	{
		return new class implements KliTableCellFormatterInterface {
			public function format(mixed $value, KliTableHeader $header, array $row): string
			{
				if (null === $value) {
					return 'N/A';
				}

				$prefix = (float) $value > 0.0 ? '+' : '';

				return $prefix . \number_format((float) $value, 2);
			}

			public function getStyle(mixed $value, KliTableHeader $header, array $row): ?KliStyle
			{
				if (null === $value) {
					return null;
				}

				return (float) $value > 0.0
					? (new KliStyle())->red()
					: (new KliStyle())->green();
			}
		};
	}

	/**
	 * Returns a formatter for percentage change values in the regression table.
	 * Values above +regressionThreshold are red; below -regressionThreshold
	 * are green; values within the stable band are yellow.
	 *
	 * @return KliTableCellFormatterInterface
	 */
	private function pctFormatter(): KliTableCellFormatterInterface
	{
		$threshold = $this->regressionThreshold;

		return new class($threshold) implements KliTableCellFormatterInterface {
			public function __construct(private readonly float $threshold) {}

			public function format(mixed $value, KliTableHeader $header, array $row): string
			{
				if (null === $value) {
					return 'N/A';
				}

				$prefix = (float) $value > 0.0 ? '+' : '';

				return $prefix . \number_format((float) $value, 2) . '%';
			}

			public function getStyle(mixed $value, KliTableHeader $header, array $row): ?KliStyle
			{
				if (null === $value) {
					return null;
				}

				if (\abs((float) $value) <= $this->threshold) {
					return (new KliStyle())->yellow();
				}

				return (float) $value > 0.0
					? (new KliStyle())->red()
					: (new KliStyle())->green();
			}
		};
	}

	/**
	 * Returns a formatter for the Status column in the regression comparison table.
	 *   REGRESSION  : red + bold
	 *   IMPROVEMENT : green + bold
	 *   STABLE      : yellow
	 *   NEW         : cyan
	 *   REMOVED     : dark gray.
	 *
	 * @return KliTableCellFormatterInterface
	 */
	private function statusFormatter(): KliTableCellFormatterInterface
	{
		return new class implements KliTableCellFormatterInterface {
			public function format(mixed $value, KliTableHeader $header, array $row): string
			{
				return (string) $value;
			}

			public function getStyle(mixed $value, KliTableHeader $header, array $row): ?KliStyle
			{
				return match ($value) {
					'REGRESSION'  => (new KliStyle())->red()->bold(),
					'IMPROVEMENT' => (new KliStyle())->green()->bold(),
					'STABLE'      => (new KliStyle())->yellow(),
					'NEW'         => (new KliStyle())->cyan(),
					'REMOVED'     => (new KliStyle())->darkGray(),
					default       => null,
				};
			}
		};
	}

	/**
	 * Returns the smallest avg_ns across all stored results.
	 * Returns 1e-9 as a floor to guarantee a safe divisor for relative speed.
	 *
	 * @return float
	 */
	private function fastestAvg(): float
	{
		$avgs = \array_column($this->results, 'avg_ns');

		return $avgs ? \max(1e-9, \min($avgs)) : 1e-9;
	}

	/**
	 * Asserts that run() has been called at least once, throwing otherwise.
	 *
	 * @throws LogicException if no results are stored
	 */
	private function assertRan(): void
	{
		if (null === $this->results) {
			throw new LogicException(\sprintf('No results yet. Call %s first.', Str::callableName($this->run(...))));
		}
	}
}
