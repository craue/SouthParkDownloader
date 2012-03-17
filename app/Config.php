<?php

/**
 * Configuration.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class Config {

	protected $season = 0;
	protected $episode = 0;
	protected $languages = array();
	protected $resolution = null;
	protected $playerUrl = null;

	protected $tmpFolder = null;
	protected $downloadFolder = null;
	protected $outputFolder = null;
	protected $rtmpdump = null;
	protected $ffmpeg = null;
	protected $mkvmerge = null;

	protected $verifyChecksums = true;
	protected $printCommandCalls = false;

	protected $removeTempFiles = true;
	protected $removeDownloadedFiles = false;

	public function setSeason($season) {
		$this->season = (int) $season;
	}

	public function getSeason() {
		return $this->season;
	}

	public function setEpisode($episode) {
		$this->episode = (int) $episode;
	}

	public function getEpisode() {
		return $this->episode;
	}

	public function setLanguages(array $languages) {
		$this->languages = $languages;
	}

	public function getLanguages() {
		return $this->languages;
	}

	public function getMainLanguage() {
		return $this->languages[0];
	}

	public function setResolution($resolution) {
		$this->resolution = $resolution;
	}

	public function getResolution() {
		return $this->resolution;
	}

	public function setPlayerUrl($playerUrl) {
		$this->playerUrl = $playerUrl;
	}

	public function getPlayerUrl() {
		return $this->playerUrl;
	}

	public function setTmpFolder($tmpFolder) {
		$this->tmpFolder = $this->canonizeExistingPath($tmpFolder);
	}

	public function getTmpFolder() {
		return $this->tmpFolder;
	}

	public function setDownloadFolder($downloadFolder) {
		$this->downloadFolder = $this->canonizeExistingPath($downloadFolder);
	}

	public function getDownloadFolder() {
		return $this->downloadFolder;
	}

	public function setOutputFolder($outputFolder) {
		$this->outputFolder = $this->canonizeExistingPath($outputFolder);
	}

	public function getOutputFolder() {
		return $this->outputFolder;
	}

	public function setRtmpdump($rtmpdump) {
		$this->rtmpdump = $this->canonizeExistingExecutable($rtmpdump);
	}

	public function getRtmpdump() {
		return $this->rtmpdump;
	}

	public function setFfmpeg($ffmpeg) {
		$this->ffmpeg = $this->canonizeExistingExecutable($ffmpeg);
	}

	public function getFfmpeg() {
		return $this->ffmpeg;
	}

	public function setMkvmerge($mkvmerge) {
		$this->mkvmerge = $this->canonizeExistingExecutable($mkvmerge);
	}

	public function getMkvmerge() {
		return $this->mkvmerge;
	}

	public function setVerifyChecksums($verifyChecksums) {
		$this->assertBoolean($verifyChecksums);
		$this->verifyChecksums = $verifyChecksums;
	}

	public function isVerifyChecksums() {
		return $this->verifyChecksums;
	}

	public function setPrintCommandCalls($printCommandCalls) {
		$this->assertBoolean($printCommandCalls);
		$this->printCommandCalls = $printCommandCalls;
	}

	public function isPrintCommandCalls() {
		return $this->printCommandCalls;
	}

	public function setRemoveTempFiles($removeTempFiles) {
		$this->assertBoolean($removeTempFiles);
		$this->removeTempFiles = $removeTempFiles;
	}

	public function isRemoveTempFiles() {
		return $this->removeTempFiles;
	}

	public function setRemoveDownloadedFiles($removeDownloadedFiles) {
		$this->assertBoolean($removeDownloadedFiles);
		$this->removeDownloadedFiles = $removeDownloadedFiles;
	}

	public function isRemoveDownloadedFiles() {
		return $this->removeDownloadedFiles;
	}

	protected function canonizeExistingPath($path) {
		if (!is_dir($path)) {
			throw new RuntimeException(sprintf('Folder "%s" doesn\'t exist.', $path));
		}
		return realpath($path).DIRECTORY_SEPARATOR;
	}

	protected function canonizeExistingExecutable($path) {
		if (!is_executable($path)) {
			throw new RuntimeException(sprintf('Program "%s" doesn\'t exist or isn\'t executable.', $path));
		}
		return realpath($path);
	}

	protected function assertBoolean($value) {
		if (!is_bool($value)) {
			throw new InvalidArgumentException(sprintf('Boolean value expected, but %s given.',
					$this->getTypeOf($value)));
		}
	}

	protected function getTypeOf($value) {
		return is_object($value) ? get_class($value) : gettype($value);
	}

}
