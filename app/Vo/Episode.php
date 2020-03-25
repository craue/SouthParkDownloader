<?php

namespace App\Vo;

/**
 * Representation of an episode.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class Episode {

	/**
	 * @var Season
	 */
	private $season;

	/**
	 * @var int episode number
	 */
	private $number;

	/**
	 * There's one specific itemId for each episode, regardless of the language, e.g. "ebb343ef-711d-450e-baa5-60ec0848e977".
	 * @var string
	 */
	private $itemId;

	/**
	 * @var string[] episode titles (one per language)
	 */
	private $titles = array();

	/**
	 * @var Act[] acts
	 */
	private $acts = array();

	public function __construct(Season $season) {
		$this->season = $season;
	}

	/**
	 * @return Season
	 */
	public function getSeason() {
		return $this->season;
	}

	/**
	 * @param int $number episode number
	 */
	public function setNumber($number) {
		$this->number = (int) $number;
	}

	/**
	 * @return int episode number
	 */
	public function getNumber() {
		return $this->number;
	}

	/**
	 * @param string $itemId
	 */
	public function setItemId($itemId) {
		$this->itemId = $itemId;
	}

	/**
	 * @return string
	 */
	public function getItemId() {
		return $this->itemId;
	}

	/**
	 * @param string $language language
	 * @param string $title episode title
	 */
	public function setTitleForLanguage($language, $title) {
		$this->titles[$language] = $title;
	}

	/**
	 * @param string $language language
	 * @return string episode title
	 */
	public function getTitleForLanguage($language) {
		return $this->titles[$language];
	}

	/**
	 * @param Act $newAct
	 */
	public function addAct(Act $newAct) {
		foreach ($this->acts as $act) {
			if ($act->getNumber() === $newAct->getNumber()) {
				throw new \RuntimeException(sprintf('Act %u for S%02uE%02u already exists.', $act->getNumber(), $this->season->getNumber(), $this->number));
			}
		}

		$this->acts[] = $newAct;
	}

	/**
	 * @return Act|null
	 */
	public function getAct($number) {
		foreach ($this->acts as $act) {
			if ($act->getNumber() === $number) {
				return $act;
			}
		}

		return null;
	}

	/**
	 * @return Act[]
	 */
	public function getActs() {
		return $this->acts;
	}

}
