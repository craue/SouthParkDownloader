<?php

require_once(__DIR__.'/JsonDatabase.php');

/**
 * Handling of the database for checksums.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2017 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class ChecksumDatabase extends JsonDatabase {

	protected $decodeAsArray = false;

	/**
	 * @param int $season
	 * @param int $episode
	 * @param int $act
	 * @param string $language
	 * @return string|null
	 */
	public function getHash($season, $episode, $act, $language) {
		$key = $this->getKey($season, $episode, $act, $language);

		$data = $this->getData();

		if (isset($data->{$key}->hash)) {
			return $data->{$key}->hash;
		}

		return null;
	}

	/**
	 * @param int $season
	 * @param int $episode
	 * @param int $act
	 * @param string $language
	 * @param string $hash
	 */
	public function updateHash($season, $episode, $act, $language, $hash) {
		$this->setValue($season, $episode, $act, $language, 'hash', $hash);
		$this->updateLastChecked($season, $episode, $act, $language);
	}

	/**
	 * @param int $season
	 * @param int $episode
	 * @param int $act
	 * @param string $language
	 */
	public function updateLastChecked($season, $episode, $act, $language) {
		$this->setValue($season, $episode, $act, $language, 'last-checked', gmdate('c'));
	}

	/**
	 * @param int $season
	 * @param int $episode
	 * @param int $act
	 * @param string $language
	 * @param string $name
	 * @param mixed $value
	 */
	protected function setValue($season, $episode, $act, $language, $name, $value) {
		$key = $this->getKey($season, $episode, $act, $language);

		$data = $this->getData();

		if (!isset($data->{$key})) {
			$data->{$key} = (object) array();
		}

		$data->{$key}->{$name} = $value;
	}

	/**
	 * @param int $season
	 * @param int $episode
	 * @param int $act
	 * @param string $language
	 * @return string
	 */
	protected function getKey($season, $episode, $act, $language) {
		return sprintf('S%02uE%02uA%u%s', $season, $episode, $act, strtoupper($language));
	}

}
