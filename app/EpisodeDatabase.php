<?php

require_once(__DIR__.'/XmlDatabase.php');

/**
 * Handling of episode database.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class EpisodeDatabase extends XmlDatabase {

	public function getEpisodes() {
		return $this->getData();
	}

	public function getEpisodeIds($seasonId, $language) {
		$episodes = $this->getEpisodes()->xpath(sprintf('/seasons/season[@id="%u"]/episodes/episode[language[@id="%s"]]',
			$seasonId,
			strtolower($language)));

		if (empty($episodes)) {
			throw new RuntimeException(sprintf('No episodes found for S%02u with language "%s".',
					$seasonId, $language));
		}

		$episodeIds = array();
		foreach ($episodes as $episode) {
			$episodeIds[] = (int) $episode->attributes()->id;
		}
		return $episodeIds;
	}

	public function findEpisode($seasonId, $episodeId, $language) {
		$episode = $this->getEpisodes()->xpath(sprintf('/seasons/season[@id="%u"]/episodes/episode%s/language%s',
			$seasonId,
			!empty($episodeId) ? sprintf('[@id="%u"]', $episodeId) : '',
			!empty($language) ? sprintf('[@id="%s"]', strtolower($language)) : ''));

		if (empty($episode)) {
			throw new RuntimeException(sprintf('No entry found for S%02uE%02u with language "%s".',
					$seasonId, $episodeId, $language));
		}
		return $episode;
	}

	public function getUrl($seasonId, $episodeId, $language, $actId, $resolution) {
		$episode = $this->findEpisode($seasonId, $episodeId, strtolower($language));
		$urlNode = $episode[0]->xpath(sprintf('acts/act[@id="%u"]/url[@resolution="%s"]',
						$actId,
						$resolution));
		return (string) $urlNode[0];
	}

	public function getTitle($seasonId, $episodeId, $language) {
		$episode = $this->findEpisode($seasonId, $episodeId, strtolower($language));
		if (empty($episode)) {
			throw new RuntimeException(sprintf('No title found for S%02uE%02u with language "%s".',
					$seasonId, $episodeId, $language));
		}
		return (string) $episode[0]->attributes()->title;
	}

	/**
	 * @return array
	 */
	public function getActs($seasonId, $episodeId, $language) {
		return $this->getEpisodes()->xpath(sprintf('/seasons/season[@id="%u"]/episodes/episode[@id="%u"]/language[@id="%s"]/acts/act',
				$seasonId,
				$episodeId,
				strtolower($language)));
	}

	public function getAct($seasonId, $episodeId, $language, $actId) {
		$act = $this->getEpisodes()->xpath(sprintf('/seasons/season[@id="%u"]/episodes/episode[@id="%u"]/language[@id="%s"]/acts/act[@id="%u"]',
				$seasonId,
				$episodeId,
				strtolower($language),
				$actId));
		if (empty($act)) {
			throw new RuntimeException(sprintf('Act S%02uE%02uA%02u not found with language "%s".',
					$seasonId, $episodeId, $actId, $language));
		}
		return $act[0];
	}

	public function getActAudioDelay($seasonId, $episodeId, $language, $actId) {
		$act = $this->getAct($seasonId, $episodeId, $language, $actId);
		return (int) $act->{'audio-delay'};
	}

}
