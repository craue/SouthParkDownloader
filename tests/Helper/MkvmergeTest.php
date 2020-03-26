<?php

namespace App\Tests\Helper;

use App\Helper\Mkvmerge;
use PHPUnit\Framework\TestCase;

/**
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class MkvmergeTest extends TestCase {

	/**
	 * @dataProvider dataExtractVersion
	 */
	public function testExtractVersion($expectedResult, $output) {
		$this->assertSame($expectedResult, Mkvmerge::extractVersion($output));
	}

	public function dataExtractVersion() {
		return [
			// real world values
			['44.0.0',	"mkvmerge v44.0.0 ('Domino') 64-bit"],
			['20.0.0',	"mkvmerge v20.0.0 ('I Am The Sun') 64-bit"],
			['18.0.0',	"mkvmerge v18.0.0 ('Apricity') 64-bit"],
			['17.0.0',	"mkvmerge v17.0.0 ('Be Ur Friend') 64-bit"],
			['11.0.0',	"mkvmerge v11.0.0 ('Alive') 32bit"],
			['10.0.0',	"mkvmerge v10.0.0 ('To Drown In You') 64bit"],
			['9.7.1',	"mkvmerge v9.7.1 ('Pandemonium') 64bit"],
			['9.1.0',	"mkvmerge v9.1.0 ('Little Earthquakes') 64bit"],
			['8.8.0',	"mkvmerge v8.8.0 ('Wind at my back') 64bit"],
			['1.5.6',	"mkvmerge v1.5.6 ('Breathe me') built on Sep 9 2005 03:05:48"],
		];
	}

	/**
	 * @dataProvider dataExtractVersion_invalid
	 */
	public function testExtractVersion_invalid($expectedException, $expectedExceptionMessage, $output) {
		$this->expectException($expectedException);
		$this->expectExceptionMessage($expectedExceptionMessage);
		Mkvmerge::extractVersion($output);
	}

	public function dataExtractVersion_invalid() {
		return [
			[\RuntimeException::class, 'The version of mkvmerge could not be extracted from "whatever". Please report this issue.', 'whatever'],
		];
	}

}
