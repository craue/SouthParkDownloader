<?php

namespace App\Tests\Helper;

use App\Helper\Ffmpeg;
use PHPUnit\Framework\TestCase;

/**
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class FfmpegTest extends TestCase {

	/**
	 * @dataProvider dataExtractVersion
	 */
	public function testExtractVersion($expectedResult, $output) {
		$this->assertSame($expectedResult, Ffmpeg::extractVersion($output));
	}

	public function dataExtractVersion() {
		return [
			// real world values
			['4.2.2',	'ffmpeg version 4.2.2 Copyright (c) 2000-2019 the FFmpeg developers'],
			['3.4.1',	'ffmpeg version 3.4.1-1~16.04.york0 Copyright (c) 2000-2017 the FFmpeg developers'],
			['3.4',		'ffmpeg version 3.4 Copyright (c) 2000-2017 the FFmpeg developers'],
			['3.0.7',	'ffmpeg version 3.0.7-0ubuntu0.16.10.1 Copyright (c) 2000-2017 the FFmpeg developers'],
			['0.8.21',	'ffmpeg version 0.8.21-6:0.8.21-0+deb7u1, Copyright (c) 2000-2014 the Libav developers'],
			['0.6.2',	'FFmpeg version 0.6.2, Copyright (c) 2000-2010 the FFmpeg developers'],
		];
	}

	/**
	 * @dataProvider dataExtractVersion_invalid
	 */
	public function testExtractVersion_invalid($expectedException, $expectedExceptionMessage, $output) {
		$this->expectException($expectedException);
		$this->expectExceptionMessage($expectedExceptionMessage);
		Ffmpeg::extractVersion($output);
	}

	public function dataExtractVersion_invalid() {
		return [
			[\RuntimeException::class, 'The version of ffmpeg could not be extracted from "whatever". Please report this issue.', 'whatever'],
		];
	}

	/**
	 * @dataProvider dataExtractFramerate
	 */
	public function testExtractFramerate($expectedResult, $output) {
		$this->assertSame($expectedResult, Ffmpeg::extractFramerate($output));
	}

	public function dataExtractFramerate() {
		return [
			[null,		''],
			// real world values
			['23.98',	'Stream #0:0(und): Video: h264 (High) (avc1 / 0x31637661), yuv420p(tv, bt709), 1920x1080 [SAR 1:1 DAR 16:9], 3437 kb/s, 23.98 fps, 23.98 tbr, 90k tbn, 180k tbc (default)'],
			['23.98',	'Stream #0:0(und): Video: h264 (Baseline) (avc1 / 0x31637661), yuv420p(tv), 1280x720 [SAR 1:1 DAR 16:9], 1097 kb/s, 23.98 fps, 23.98 tbr, 90k tbn, 23.98 tbc (default)'],
			['25',		'Stream #0:0(und): Video: h264 (Baseline) (avc1 / 0x31637661), yuv420p(tv), 1280x720 [SAR 1:1 DAR 16:9], 1101 kb/s, 25 fps, 25 tbr, 90k tbn, 25 tbc (default)'],
		];
	}

}
