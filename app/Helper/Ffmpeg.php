<?php

namespace App\Helper;

/**
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class Ffmpeg {

	/**
	 * @param string $output
	 * @return string
	 */
	public static function extractVersion($output) {
		if (preg_match('#ffmpeg version ((\d|\.)+)(?:\s|-|,)#i', $output, $matches) !== false && $matches[1] !== null) {
			return $matches[1];
		}

		throw new \RuntimeException(sprintf('The version of ffmpeg could not be extracted from "%s". Please report this issue.', $output));
	}

	/**
	 * @param string $output
	 * @return string|null
	 */
	public static function extractFramerate($output) {
		if (preg_match('#kb/s, ((\d|\.)+) fps,#', $output, $matches) !== false) {
			return $matches[1];
		}

		return null;
	}

}
