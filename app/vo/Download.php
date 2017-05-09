<?php

use Symfony\Component\Process\Process;

/**
 * Representation of a download.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2017 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class Download {

	/**
	 * @var Act
	 */
	private $act;

	/**
	 * @var string
	 */
	private $language;

	/**
	 * @var string
	 */
	private $url;

	/**
	 * @var string
	 */
	private $targetFile;

	/**
	 * @var float
	 */
	private $startTime;

	/**
	 * @var Process
	 */
	private $process;

	/**
	 * @param Act $act
	 * @param string $language
	 * @param string $url
	 * @param string $targetFile
	 */
	public function __construct(Act $act, $language, $url, $targetFile) {
		$this->act = $act;
		$this->language = $language;
		$this->url = $url;
		$this->targetFile = $targetFile;
	}

	/**
	 * @return Act
	 */
	public function getAct() {
		return $this->act;
	}

	/**
	 * @return string
	 */
	public function getLanguage() {
		return $this->language;
	}

	/**
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * @return string
	 */
	public function getTargetFile() {
		return $this->targetFile;
	}

	/**
	 * @param float $startTime
	 */
	public function setStartTime($startTime) {
		$this->startTime = $startTime;
	}

	/**
	 * @return float
	 */
	public function getStartTime() {
		return $this->startTime;
	}

	/**
	 * @param Process|null $process
	 */
	public function setProcess(Process $process = null) {
		$this->process = $process;
	}

	/**
	 * @return Process|null
	 */
	public function getProcess() {
		return $this->process;
	}

	/**
	 * @return string
	 */
	public function getProcessName() {
		return sprintf('S%02uE%02uA%u%s', $this->act->getEpisode()->getSeason()->getNumber(), $this->act->getEpisode()->getNumber(), $this->act->getNumber(), strtoupper($this->language));
	}

}
