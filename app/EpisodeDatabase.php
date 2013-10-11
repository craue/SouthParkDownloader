<?php

require_once(__DIR__.'/XmlDatabase.php');

/**
 * Handling of episode database.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2012 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class EpisodeDatabase extends XmlDatabase {

	public function getEpisodes() {
		return $this->addXPathNamespace($this->getData(), 'e');
	}

	public function getEpisodeIds($seasonId, $language) {
		$episodes = $this->getEpisodes()->xpath(sprintf('/e:seasons/e:season[@id="%u"]/e:episodes/e:episode[e:language[@id="%s"]]',
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
		$episodes = $this->getEpisodes()->xpath(sprintf('/e:seasons/e:season[@id="%u"]/e:episodes/e:episode%s/e:language%s',
			$seasonId,
			!empty($episodeId) ? sprintf('[@id="%u"]', $episodeId) : '',
			!empty($language) ? sprintf('[@id="%s"]', strtolower($language)) : ''));

		if (empty($episodes)) {
			throw new RuntimeException(sprintf('No entry found for S%02uE%02u with language "%s".',
					$seasonId, $episodeId, $language));
		}

		return $this->addXPathNamespace($episodes[0], 'e');
	}

	public function getUrl($seasonId, $episodeId, $language, $actId, $resolution) {
		$episode = $this->findEpisode($seasonId, $episodeId, strtolower($language));
		$urlNode = $episode->xpath(sprintf('e:acts/e:act[@id="%u"]/e:url[@resolution="%s"]/e:mirror',
						$actId,
						$resolution));

		return trim((string) $urlNode[0]);
	}

	public function getSha1($seasonId, $episodeId, $language, $actId, $resolution) {
		$episode = $this->findEpisode($seasonId, $episodeId, strtolower($language));
		$urlNode = $episode->xpath(sprintf('e:acts/e:act[@id="%u"]/e:url[@resolution="%s"]',
						$actId,
						$resolution));

		return (string) $urlNode[0]->attributes()->sha1;
	}

	public function getTitle($seasonId, $episodeId, $language) {
		$episode = $this->findEpisode($seasonId, $episodeId, strtolower($language));

		return (string) $episode->attributes()->title;
	}

	/**
	 * @return boolean
	 */
	public function getAudioReencode($seasonId, $episodeId, $language) {
		foreach ($this->getActs($seasonId, $episodeId, $language) as $act) {
			$actId = $act->attributes()->id;

			if ($this->getActAudioReencode($seasonId, $episodeId, $language, $actId)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array
	 */
	public function getActs($seasonId, $episodeId, $language) {
		$acts = $this->getEpisodes()->xpath(sprintf('/e:seasons/e:season[@id="%u"]/e:episodes/e:episode[@id="%u"]/e:language[@id="%s"]/e:acts/e:act',
				$seasonId,
				$episodeId,
				strtolower($language)));

		if (empty($acts)) {
			throw new RuntimeException(sprintf('No acts found for S%02uE%02u with language "%s".',
					$seasonId, $episodeId, $language));
		}

		return $acts;
	}

	public function getAct($seasonId, $episodeId, $language, $actId) {
		$acts = $this->getEpisodes()->xpath(sprintf('/e:seasons/e:season[@id="%u"]/e:episodes/e:episode[@id="%u"]/e:language[@id="%s"]/e:acts/e:act[@id="%u"]',
				$seasonId,
				$episodeId,
				strtolower($language),
				$actId));

		if (empty($acts)) {
			throw new RuntimeException(sprintf('Act S%02uE%02uA%02u not found with language "%s".',
					$seasonId, $episodeId, $actId, $language));
		}

		return $acts[0];
	}

	public function getActAudioDelay($seasonId, $episodeId, $language, $actId) {
		$act = $this->getAct($seasonId, $episodeId, $language, $actId);
		return (int) $act->{'audio-delay'};
	}

	/**
	 * @return boolean
	 */
	public function getActAudioReencode($seasonId, $episodeId, $language, $actId) {
		$act = $this->getAct($seasonId, $episodeId, $language, $actId);
		return (boolean) $act->{'audio-reencode'};
	}

}
