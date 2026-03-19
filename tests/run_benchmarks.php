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

/**
 * Standalone benchmark runner.
 *
 * Run explicitly:
 *   ./run_benchmark
 *
 * Output policy:
 *   - Silent when all results are STABLE relative to the saved baseline.
 *   - Prints a comparison table only when at least one callable is classified
 *     as REGRESSION or IMPROVEMENT.
 *   - Exits with code 1 when at least one REGRESSION is detected (useful in CI).
 *
 * Noise suppression strategy:
 *   Regression detection uses min_ns (fastest observed time) rather than the
 *   mean. The minimum is unaffected by OS scheduling interruptions and
 *   background process spikes - it represents the true cost of the code path
 *   without interference. A genuine regression will raise the minimum because
 *   the fast path itself is slower; random OS noise only ever adds to a run,
 *   never subtracts from the observed minimum.
 *
 * Baseline file: tests/benchmark-baseline.json
 *   Updated after every stable run (no regressions). Noisy spikes do not
 *   overwrite the baseline, preserving the reference for future comparisons.
 *
 * Adding new benchmarks:
 *   1. Create (or edit) a *Benchmark.php class in tests/Benchmarks/.
 *   2. Implement BenchmarkSuiteInterface::callables() returning your entries.
 *   3. Follow the label convention: area_operation[_variant]
 *      e.g. router_find_static, hasher_hash64, docrypt_encrypt_aes256
 *   4. The runner discovers the file automatically - no registration needed.
 */

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/autoload.php';

use OZONE\Tests\Benchmark;

const BASELINE_FILE = __DIR__ . '/benchmark-baseline.json';
const THRESHOLD_PCT = 20.0;

// ---------------------------------------------------------------------------
// Machine fingerprint
// ---------------------------------------------------------------------------
// The fingerprint is stored in the baseline JSON envelope. On load it is
// compared with the current environment. When they differ the comparison is
// skipped and a warning is printed instead of misleading regression output.
// Regression detection uses min_ns (fastest observed time), which is immune
// to OS scheduling spikes - noise only ever adds latency to individual
// samples, so the minimum stays stable across clean runs on the same machine.

/**
 * Returns a best-effort snapshot of the current hardware and runtime
 * environment. Used to detect when a baseline was recorded on a different
 * machine so comparisons are not silently invalidated.
 *
 * @return array<string, mixed>
 */
function buildMachineFingerprint(): array
{
	$cpuModel = 'unknown';
	$cpuCount = 1;

	if (\PHP_OS_FAMILY === 'Linux') {
		$cpuinfo = @\file_get_contents('/proc/cpuinfo') ?: '';

		if (\preg_match('/model name\s*:\s*(.+)/i', $cpuinfo, $m)) {
			$cpuModel = \trim($m[1]);
		}

		$nproc = @\shell_exec('nproc 2>/dev/null');

		if ($nproc) {
			$cpuCount = (int) \trim($nproc);
		}
	} elseif (\PHP_OS_FAMILY === 'Darwin') {
		$brand = @\shell_exec('sysctl -n machdep.cpu.brand_string 2>/dev/null');

		if ($brand) {
			$cpuModel = \trim($brand);
		}

		$ncpu = @\shell_exec('sysctl -n hw.ncpu 2>/dev/null');

		if ($ncpu) {
			$cpuCount = (int) \trim($ncpu);
		}
	} elseif (\PHP_OS_FAMILY === 'Windows') {
		$cpu = @\shell_exec('wmic cpu get name /value 2>NUL');

		if ($cpu && \preg_match('/Name=(.+)/i', $cpu, $m)) {
			$cpuModel = \trim($m[1]);
		}

		$cores = @\shell_exec('wmic cpu get NumberOfCores /value 2>NUL');

		if ($cores && \preg_match('/NumberOfCores=(\d+)/i', $cores, $m)) {
			$cpuCount = (int) $m[1];
		}
	}

	return [
		'php_version' => \PHP_VERSION,
		'os'          => \PHP_OS_FAMILY,
		'arch'        => \php_uname('m'),
		'cpu_model'   => $cpuModel,
		'cpu_count'   => $cpuCount,
	];
}

/**
 * Returns true when two fingerprints describe the same environment.
 * Treats unknown cpu_model as a wildcard - avoids false positives on systems
 * where /proc/cpuinfo is unavailable.
 *
 * @param array<string, mixed> $a
 * @param array<string, mixed> $b
 */
function fingerprintsMatch(array $a, array $b): bool
{
	foreach (['php_version', 'os', 'arch'] as $key) {
		if (($a[$key] ?? null) !== ($b[$key] ?? null)) {
			return false;
		}
	}

	// cpu_model unknown on either side - skip model check.
	if (($a['cpu_model'] ?? 'unknown') !== 'unknown' && ($b['cpu_model'] ?? 'unknown') !== 'unknown') {
		if ($a['cpu_model'] !== $b['cpu_model']) {
			return false;
		}
	}

	return true;
}

$fingerprint = buildMachineFingerprint();

// ---------------------------------------------------------------------------
// Discover and load all benchmark suites from tests/Benchmarks/*Benchmark.php
// ---------------------------------------------------------------------------

$callables = [];

foreach (\glob(__DIR__ . '/Benchmarks/*Benchmark.php') ?: [] as $file) {
	require_once $file;
	$class     = 'OZONE\Tests\Benchmarks\\' . \basename($file, '.php');
	$callables = \array_merge($callables, $class::callables());
}

if (empty($callables)) {
	echo 'No benchmark suites found in tests/Benchmarks/.' . \PHP_EOL;

	exit(0);
}

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------

$bm = Benchmark::create()
	->warmup(10)
	->maxDuration(0.5)
	->regressionThreshold(THRESHOLD_PCT)
	->run($callables);

// ---------------------------------------------------------------------------
// Compare with baseline if one exists
// ---------------------------------------------------------------------------

$hasRegression  = false;
$hasImprovement = false;
$baselineExists = \is_file(BASELINE_FILE);

if ($baselineExists) {
	$baseline        = Benchmark::fromJson(\file_get_contents(BASELINE_FILE));
	$baseFingerprint = $baseline->getExportedMeta();

	// If the baseline was recorded without a fingerprint (legacy format) or on
	// a different machine, skip numerical comparison and just update the baseline.
	if (!empty($baseFingerprint) && !fingerprintsMatch($fingerprint, $baseFingerprint)) {
		echo \PHP_EOL . '=== Benchmark baseline was recorded on a different machine — resetting. ===' . \PHP_EOL;
		echo '  Baseline : ' . ($baseFingerprint['cpu_model'] ?? '?') . ' / ' . ($baseFingerprint['os'] ?? '?') . ' / PHP ' . ($baseFingerprint['php_version'] ?? '?') . \PHP_EOL;
		echo '  Current  : ' . $fingerprint['cpu_model'] . ' / ' . $fingerprint['os'] . ' / PHP ' . $fingerprint['php_version'] . \PHP_EOL;
		echo '  New baseline saved. Run again to start tracking regressions.' . \PHP_EOL;
		\file_put_contents(BASELINE_FILE, $bm->exportJson($fingerprint));

		exit(0);
	}

	$baseData = $baseline->getResults();

	foreach ($bm->getResults() as $ref => $current) {
		if (!isset($baseData[$ref])) {
			continue; // NEW entries do not trigger output by themselves
		}

		$base = $baseData[$ref];

		// Use min_ns (fastest observed time) for comparison rather than the
		// mean. The minimum is immune to OS scheduling spikes - noise only
		// ever adds latency, so the minimum stays stable across clean runs.
		// A real regression will raise the minimum because the fast path
		// itself is slower; random jitter only inflates individual samples.
		$baseMin = $base['min_ns'];
		$currMin = $current['min_ns'];

		$changePct = $baseMin > 0.0
			? (($currMin - $baseMin) / $baseMin) * 100.0
			: 0.0;

		if ($changePct > THRESHOLD_PCT) {
			$hasRegression = true;
		} elseif ($changePct < -THRESHOLD_PCT) {
			$hasImprovement = true;
		}
	}

	if ($hasRegression || $hasImprovement) {
		echo \PHP_EOL . '=== Benchmark results (vs baseline) ===' . \PHP_EOL;
		$bm->orderByFastest()->compareWith($baseline);
	}
} else {
	// First run: print initial numbers so the developer has a reference.
	echo \PHP_EOL . '=== Benchmark results (initial run — no baseline yet) ===' . \PHP_EOL;
	$bm->orderByFastest()->printSummary();
}

// ---------------------------------------------------------------------------
// Save updated baseline (only when no regressions - avoids saving noisy spikes
// as the new reference, which would mask future real regressions)
// ---------------------------------------------------------------------------

if (!$hasRegression) {
	\file_put_contents(BASELINE_FILE, $bm->exportJson($fingerprint));
}

// ---------------------------------------------------------------------------
// Exit with error code on regression so CI catches it
// ---------------------------------------------------------------------------

exit($hasRegression ? 1 : 0);
