<?php

namespace App\Exception;

/**
 * Exception to raise if a file doesn't exist.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class FileDoesNotExistException extends \RuntimeException {

	public function __construct($path) {
		parent::__construct(sprintf('File "%s" doesn\'t exist.', $path));
	}

}
