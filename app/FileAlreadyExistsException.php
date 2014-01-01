<?php

/**
 * Exception to raise if a file already exists.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2014 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class FileAlreadyExistsException extends RuntimeException {

	public function __construct($path) {
		parent::__construct(sprintf('File "%s" already exists. Will abort now instead of overwriting it. Delete it manually and try again if you want to continue.', $path));
	}

}
