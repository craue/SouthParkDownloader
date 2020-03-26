<?php

namespace App\Tests\Config;

use App\Config\Config;
use PHPUnit\Framework\TestCase;

/**
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class ConfigTest extends TestCase {

	private $config;

	protected function setUp() : void {
		$this->config = new Config();
	}

	/**
	 * @dataProvider dataEpisodeNumbers
	 */
	public function testEpisodeNumbers($expectedResult, $arg) {
		$this->config->setEpisodeNumbers($arg);
		$this->assertSame($expectedResult, $this->config->getEpisodeNumbers());
	}

	public function dataEpisodeNumbers() {
		return [
			[[], []],
			[[1], 1],
			[[1], '1'],
			[[1, 2], [1, 2]],
			[[1, 2], [1, '2']],
			[[1, 2], ['1,2']],
			[[1, 2, 3], ['1,2,3']],
			[[1, 2, 3], ['1-3']],
			[[1], ['1-1']],
			[[1, 2, 5, 6, 7, 10, 19, 20, 30], [1, '2,5-7,10,19-20', 30]],
			[[7, 6], ['7-6']],
		];
	}

	/**
	 * @dataProvider dataEpisodeNumbers_invalid
	 */
	public function testEpisodeNumbers_invalid($expectedException, $expectedExceptionMessage, $arg) {
		$this->expectException($expectedException);
		$this->expectExceptionMessage($expectedExceptionMessage);
		$this->config->setEpisodeNumbers($arg);
	}

	public function dataEpisodeNumbers_invalid() {
		return [
			[\InvalidArgumentException::class, 'Invalid episode: ', ''],
			[\InvalidArgumentException::class, 'Invalid episode: 0', 0],
			[\InvalidArgumentException::class, 'Invalid episode: -1', -1],
			[\InvalidArgumentException::class, 'Invalid episode: -1', '-1'],
			[\InvalidArgumentException::class, 'Invalid episode: 1-', '1-'],
			[\InvalidArgumentException::class, 'Invalid episode: -x', '-x'],
			[\InvalidArgumentException::class, 'Invalid episode: x-', 'x-'],
			[\InvalidArgumentException::class, 'Invalid episode: x', 'x'],
			[\InvalidArgumentException::class, 'Invalid episode: x', 'x'],
			[\InvalidArgumentException::class, 'Invalid episode: -2', '1,-2'],
			[\InvalidArgumentException::class, 'Invalid episode: ', '1,'],
			[\InvalidArgumentException::class, 'Invalid episode: ', '1,,2'],
			[\InvalidArgumentException::class, 'Invalid episode: x', '1,x,2'],
			[\InvalidArgumentException::class, 'Invalid episode: x', '1-x'],
			[\InvalidArgumentException::class, 'Invalid episode: 1-2-3', '1-2-3'],
			[\InvalidArgumentException::class, 'Invalid episode: 1--x', '1--x'],
			[\InvalidArgumentException::class, 'Invalid episode: ', null],
			[\InvalidArgumentException::class, 'Invalid episode: ', false],

			[\InvalidArgumentException::class, 'Duplicate episode: 1', '1,1'],
			[\InvalidArgumentException::class, 'Duplicate episode: 1', [1, '1']],
		];
	}

	/**
	 * @dataProvider dataLanguages
	 */
	public function testLanguages($expectedResult, $arg) {
		$this->config->setLanguages($arg);
		$this->assertSame($expectedResult, $this->config->getLanguages());
	}

	public function dataLanguages() {
		return [
			[[], []],
			[['de'], ['de']],
			[['de', 'en'], ['de', 'en']],
		];
	}

	/**
	 * @dataProvider dataLanguages_invalid
	 */
	public function testLanguages_invalid($expectedException, $expectedExceptionMessage, $arg) {
		$this->expectException($expectedException);
		$this->expectExceptionMessage($expectedExceptionMessage);
		$this->config->setLanguages($arg);
	}

	public function dataLanguages_invalid() {
		return [
			[\InvalidArgumentException::class, 'Invalid language: ', ['']],
			[\InvalidArgumentException::class, 'Invalid language: 0', [0]],
			[\InvalidArgumentException::class, 'Invalid language: ', [null]],
			[\InvalidArgumentException::class, 'Invalid language: ', [false]],

			[\InvalidArgumentException::class, 'Duplicate language: de', ['de', 'de']],
		];
	}

}
