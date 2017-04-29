<?php

/**
 * Handling of the database for settings.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2017 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class SettingDatabase extends JsonDatabase {

	public function getAvailableSeasonsUrl($language) {
		return $this->getData()['availableSeasonsUrl'][$language];
	}

	public function getAvailableEpisodesUrl($language, $season) {
		return strtr($this->getData()['availableEpisodesUrl'][$language], array(
			'{resultsPerPage}' => 30,
			'{currentPage}' => 1,
			'{sort}' => '!airdate',
			'{relatedItemId}' => sprintf('season-%u', $season),
		));
	}

	public function getEpisodeDataUrl($language, $seasonNumber, $episodeNumber) {
		return strtr($this->getData()['episodeDataUrl'][$language], array(
			'{season}' => sprintf('%02u', $seasonNumber),
			'{episode}' => sprintf('%02u', $episodeNumber),
		));
	}

	public function getChapterName($language) {
		return $this->getData()['chapterName'][$language];
	}

}
