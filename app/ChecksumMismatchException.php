<?php

/**
 * Exception to raise if checksum verification fails.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2012 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class ChecksumMismatchException extends RuntimeException {

	public function __construct($file, $expectedChecksum, $actualChecksum) {
		parent::__construct(sprintf('Checksum mismatch for "%s". Expected "%s", but got "%s". This occurs either if there was a real error while downloading or, depending on the time of downloading, you just received the censored (bleeped) version. Try to download the file again later.', $file, $expectedChecksum, $actualChecksum));
	}

}
