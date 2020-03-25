<?php

namespace App\Database;

/**
 * Handling of the database for anomalies.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class AnomalyDatabase extends JsonDatabase {

	/**
	 * @param int $season
	 * @param int $episode
	 * @param string $language
	 * @return string|null
	 */
	public function getTitle($season, $episode, $language) {
		$keys = array(
			sprintf('S%02uE%02u', $season, $episode),
			sprintf('S%02uE%02u%s', $season, $episode, strtoupper($language)),
		);

		$data = $this->getData();

		foreach ($keys as $key) {
			if (isset($data[$key]['title'])) {
				return $data[$key]['title'];
			}
		}

		return null;
	}

	/**
	 * @param int $season
	 * @param int $episode
	 * @param int $act
	 * @param string $language
	 * @return int|null
	 */
	public function getVideoSync($season, $episode, $act, $language) {
		$keys = array(
			sprintf('S%02uE%02uA%u%s', $season, $episode, $act, strtoupper($language)),
		);

		$data = $this->getData();

		foreach ($keys as $key) {
			if (isset($data[$key]['video-sync'])) {
				return $data[$key]['video-sync'];
			}
		}

		return null;
	}

	/**
	 * @param int $season
	 * @param int $episode
	 * @param int $act
	 * @param string $language
	 * @return int|null
	 */
	public function getAudioSync($season, $episode, $act, $language) {
		$keys = array(
			sprintf('S%02uE%02uA%u%s', $season, $episode, $act, strtoupper($language)),
		);

		$data = $this->getData();

		foreach ($keys as $key) {
			if (isset($data[$key]['audio-sync'])) {
				return $data[$key]['audio-sync'];
			}
		}

		return null;
	}

	/**
	 * @param int $season
	 * @param int $episode
	 * @param string $language
	 * @return boolean
	 */
	public function needsVideoReencode($season, $episode, $language) {
		$keys = array(
			sprintf('S%02uE%02u', $season, $episode),
			sprintf('S%02uE%02u%s', $season, $episode, strtoupper($language)),
		);

		foreach ($keys as $key) {
			if ($this->hasEntryWithValueTrue($key, 'video-reencode')) {
				return true;
			}
		}

		return false;
	}

	protected function hasEntryWithValueTrue($key, $option) {
		$data = $this->getData();

		return isset($data[$key][$option]) && $data[$key][$option] === true;
	}

}
