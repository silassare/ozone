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

namespace OZONE\Tests\Loader;

use InvalidArgumentException;
use Override;
use OZONE\Core\Loader\ClassLoader;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class ClassLoaderTest.
 *
 * Each test indexes its own directory and declares classes with unique names: the loader
 * state and the declared classes live for the whole process.
 *
 * @internal
 *
 * @covers \OZONE\Core\Loader\ClassLoader
 */
final class ClassLoaderTest extends TestCase
{
	private static string $dir;

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$dir = \sys_get_temp_dir() . '/oz_class_loader_' . \bin2hex(\random_bytes(6));

		\mkdir(self::$dir, 0o775, true);
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(self::$dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($files as $file) {
			$file->isDir() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
		}

		\rmdir(self::$dir);

		parent::tearDownAfterClass();
	}

	public function testAddDirIndexesClassesWithoutNamespace(): void
	{
		$class = self::uniqueName('OzLoaderFlat');
		$file  = self::write("flat/{$class}.php", "<?php\n\nfinal class {$class} {}\n");

		ClassLoader::addDir(self::$dir . '/flat');

		self::assertSame($file, ClassLoader::report()['class_map'][$class] ?? null);
		self::assertTrue(ClassLoader::exists($class));
	}

	public function testAddDirOnlyIndexesClassFileNames(): void
	{
		$class = self::uniqueName('OzLoaderNamed');

		self::write("named/{$class}.php", "<?php\n\nfinal class {$class} {}\n");
		self::write('named/lower_case.php', "<?php\n");
		self::write('named/Notes.txt', 'not php');

		ClassLoader::addDir(self::$dir . '/named');

		$map = ClassLoader::report()['class_map'];

		self::assertArrayHasKey($class, $map);
		self::assertArrayNotHasKey('lower_case', $map);
		self::assertArrayNotHasKey('Notes', $map);
	}

	public function testAddDirGoesOnlyAsDeepAsAsked(): void
	{
		$shallow = self::uniqueName('OzLoaderShallow');
		$deep    = self::uniqueName('OzLoaderDeep');

		self::write("tree/sub/{$shallow}.php", "<?php\n\nfinal class {$shallow} {}\n");
		self::write("tree/sub/deeper/{$deep}.php", "<?php\n\nfinal class {$deep} {}\n");

		ClassLoader::addDir(self::$dir . '/tree', true, 1);

		$map = ClassLoader::report()['class_map'];

		self::assertArrayHasKey($shallow, $map);
		self::assertArrayNotHasKey($deep, $map);
	}

	public function testAddDirRejectsAMissingDirectory(): void
	{
		$this->expectException(InvalidArgumentException::class);

		ClassLoader::addDir(self::$dir . '/missing');
	}

	public function testAddNamespaceLoadsPsr4Classes(): void
	{
		$ns = self::uniqueName('OzLoaderNs') . '\Models';

		self::write('psr4/Models/User.php', "<?php\n\nnamespace {$ns};\n\nfinal class User {}\n");
		$post = self::write('psr4/Models/Blog/Post.php', "<?php\n\nnamespace {$ns}\\Blog;\n\nfinal class Post {}\n");

		ClassLoader::addNamespace($ns, self::$dir . '/psr4/Models');

		self::assertTrue(\class_exists($ns . '\User'));
		self::assertSame($post, ClassLoader::loadClass($ns . '\Blog\Post'));
		self::assertFalse(ClassLoader::loadClass($ns . '\Missing'));
	}

	public function testPrependedBaseDirectoryIsSearchedFirst(): void
	{
		$ns = self::uniqueName('OzLoaderOrder');

		self::write('order/a/Thing.php', "<?php\n\nnamespace {$ns};\n\nfinal class Thing { public const FROM = 'a'; }\n");
		self::write('order/b/Thing.php', "<?php\n\nnamespace {$ns};\n\nfinal class Thing { public const FROM = 'b'; }\n");

		ClassLoader::addNamespace($ns, self::$dir . '/order/b');
		ClassLoader::addNamespace($ns, self::$dir . '/order/a', true);

		self::assertSame(
			[self::$dir . '/order/a/', self::$dir . '/order/b/'],
			ClassLoader::report()['namespaces'][$ns . '\\']
		);
		self::assertSame('a', \constant($ns . '\Thing::FROM'));
	}

	public function testALazyNamespaceIsResolvedByItsFirstClass(): void
	{
		$ns    = self::uniqueName('OzLoaderLazy');
		$calls = 0;

		self::write('lazy/One.php', "<?php\n\nnamespace {$ns};\n\nfinal class One {}\n");
		$two = self::write('lazy/Two.php', "<?php\n\nnamespace {$ns};\n\nfinal class Two {}\n");

		ClassLoader::addLazyNamespace($ns, static function () use (&$calls): string {
			++$calls;

			return self::$dir . '/lazy';
		});

		self::assertSame(0, $calls);

		// Found without being loaded.
		self::assertSame($two, ClassLoader::findFile($ns . '\Two'));
		self::assertFalse(\class_exists($ns . '\Two', false));

		self::assertTrue(\class_exists($ns . '\One'));
		self::assertSame(1, $calls);
		self::assertSame([self::$dir . '/lazy/'], ClassLoader::report()['namespaces'][$ns . '\\']);
	}

	public function testALazyNamespaceNotAvailableYetIsAskedAgain(): void
	{
		$ns    = self::uniqueName('OzLoaderLater');
		$ready = false;
		$file  = self::write('later/Thing.php', "<?php\n\nnamespace {$ns};\n\nfinal class Thing {}\n");

		ClassLoader::addLazyNamespace($ns, static function () use (&$ready): ?string {
			return $ready ? self::$dir . '/later' : null;
		});

		self::assertNull(ClassLoader::findFile($ns . '\Thing'));
		self::assertContains($ns . '\\', ClassLoader::report()['lazy_namespaces']);

		$ready = true;

		self::assertSame($file, ClassLoader::findFile($ns . '\Thing'));
		self::assertNotContains($ns . '\\', ClassLoader::report()['lazy_namespaces']);
	}

	public function testAClassOfAnotherRootNamespaceIsLeftToComposer(): void
	{
		$root  = self::uniqueName('OzLoaderRoot');
		$calls = 0;

		self::write('rooted/Thing.php', "<?php\n\nnamespace {$root}\\Lazy;\n\nfinal class Thing {}\n");

		ClassLoader::addLazyNamespace($root . '\Lazy', static function () use (&$calls): string {
			++$calls;

			return self::$dir . '/rooted';
		});

		// Registered ahead of Composer, the loader is asked for every class: one of a root namespace
		// it has none of is not looked for, and no lazy provider runs for it.
		self::assertNull(ClassLoader::findFile(self::uniqueName('OzLoaderOther') . '\Lazy\Thing'));
		self::assertSame(0, $calls);

		// Same root, another namespace: looked for, and the lazy one is left alone.
		self::assertNull(ClassLoader::findFile($root . '\Other\Thing'));
		self::assertSame(0, $calls);

		self::assertTrue(\class_exists($root . '\Lazy\Thing'));
		self::assertSame(1, $calls);
	}

	public function testAClassMapFindsAClassWithoutSearching(): void
	{
		$ns   = self::uniqueName('OzLoaderMapped');
		$file = self::write('mapped/NotWhereItsNameSays.php', "<?php\n\nnamespace {$ns};\n\nfinal class Mapped {}\n");

		ClassLoader::useClassMap([$ns . '\Mapped' => $file]);

		self::assertSame($file, ClassLoader::findFile($ns . '\Mapped'));
		self::assertTrue(\class_exists($ns . '\Mapped'));
	}

	public function testAMappedClassOfALazyNamespaceStillResolvesIt(): void
	{
		$ns    = self::uniqueName('OzLoaderMappedLazy');
		$calls = 0;
		$file  = self::write('mapped-lazy/Thing.php', "<?php\n\nnamespace {$ns};\n\nfinal class Thing {}\n");

		ClassLoader::addLazyNamespace($ns, static function () use (&$calls): string {
			++$calls;

			return self::$dir . '/mapped-lazy';
		});
		ClassLoader::useClassMap([$ns . '\Thing' => $file]);

		self::assertSame($file, ClassLoader::findFile($ns . '\Thing'));
		self::assertSame(1, $calls);
	}

	public function testNamespacesAreMappedByTheirFileNames(): void
	{
		$ns = self::uniqueName('OzLoaderListed');
		$a  = self::write('listed/A.php', "<?php\n");
		$b  = self::write('listed/Sub/B.php', "<?php\n");

		self::write('listed/not-a-class.php', "<?php\n");

		ClassLoader::addNamespace($ns, self::$dir . '/listed');

		$map = ClassLoader::mapNamespaces();

		self::assertSame($a, $map[$ns . '\A'] ?? null);
		self::assertSame($b, $map[$ns . '\Sub\B'] ?? null);
		self::assertArrayNotHasKey($ns . '\not-a-class', $map);
	}

	public function testUnknownClassesAreNotFound(): void
	{
		self::assertFalse(ClassLoader::loadClass(self::uniqueName('OzLoaderMissing')));
		self::assertFalse(ClassLoader::exists(''));
	}

	private static function uniqueName(string $prefix): string
	{
		return $prefix . \bin2hex(\random_bytes(4));
	}

	/**
	 * Writes a file under the test directory and returns its path.
	 */
	private static function write(string $relative_path, string $content): string
	{
		$path = self::$dir . '/' . $relative_path;
		$dir  = \dirname($path);

		if (!\is_dir($dir)) {
			\mkdir($dir, 0o775, true);
		}

		\file_put_contents($path, $content);

		return $path;
	}
}
