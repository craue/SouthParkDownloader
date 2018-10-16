<?php

/**
 * Base class for reading/writing a JSON database.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2018 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class JsonDatabase {

	protected $decodeAsArray = true;

	protected $source;
	protected $data;

	public function __construct($source) {
		$this->source = $source;

		if (file_exists($this->source)) {
			$this->data = json_decode(file_get_contents($this->source), $this->decodeAsArray);
		} else {
			$this->data = $this->decodeAsArray ? array() : (object) array();
		}
	}

	public function getData() {
		return $this->data;
	}

	public function save() {
		// sort keys
		$array = json_decode(json_encode($this->data), true);
		ksort($array);

		file_put_contents($this->source, json_encode($array, JSON_PRETTY_PRINT));
	}

}
