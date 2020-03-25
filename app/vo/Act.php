<?php

/**
 * Representation of an act.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class Act {

	/**
	 * @var Episode
	 */
	private $episode;

	/**
	 * @var int act number
	 */
	private $number;

	/**
	 * @var string[] metadata URLs for downloads (one per language)
	 */
	private $downloadMetadataUrls = array();

	public function __construct(Episode $episode) {
		$this->episode = $episode;
	}

	/**
	 * @return Episode
	 */
	public function getEpisode() {
		return $this->episode;
	}

	/**
	 * @param int $number act number
	 */
	public function setNumber($number) {
		$this->number = (int) $number;
	}

	/**
	 * @return int act number
	 */
	public function getNumber() {
		return $this->number;
	}

	/**
	 * @param string $language language
	 * @param string $url
	 */
	public function setDownloadMetadataUrlForLanguage($language, $url) {
		$this->downloadMetadataUrls[$language] = $url;
	}

	/**
	 * @param string $language language
	 * @return string
	 */
	public function getDownloadMetadataUrlForLanguage($language) {
		return $this->downloadMetadataUrls[$language];
	}

}
