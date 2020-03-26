<?php

namespace App\Config;

/**
 * Configuration.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class Config {

	protected $seasonNumber = 0;
	protected $episodeNumbers = [];
	protected $languages = array();

	protected $tmpFolder = null;
	protected $downloadFolder = null;
	protected $outputFolder = null;
	protected $ffmpeg = null;
	protected $mkvmerge = null;

	protected $downloadTimeoutInSeconds = null;

	protected $verifyChecksums = true;
	protected $updateChecksumOnSuccessfulDownload = false;

	protected $printUrls = false;
	protected $printCommandCalls = false;
	protected $quietCommands = true;

	protected $goOnIfDownloadedFileAlreadyExists = true;
	protected $goOnIfFinalFileAlreadyExists = true;

	protected $removeTempFiles = true;
	protected $removeDownloadedFiles = false;

	public function setSeasonNumber($seasonNumber) {
		$this->seasonNumber = (int) $seasonNumber;
	}

	public function getSeasonNumber() {
		return $this->seasonNumber;
	}

	public function setEpisodeNumbers($arg) {
		$this->episodeNumbers = [];

		foreach ($this->extractEpisodeNumbers($arg) as $number) {
			if (in_array($number, $this->episodeNumbers, true)) {
				throw new \InvalidArgumentException(sprintf('Duplicate episode: %s', $number));
			}

			$this->episodeNumbers[] = $number;
		}
	}

	/**
	 * @param int|string|int[]|string[] $arg
	 * @return int[]
	 */
	private function extractEpisodeNumbers($arg) {
		if (is_int($arg)) {
			if ($arg > 0) {
				return [$arg];
			}

			throw new \InvalidArgumentException(sprintf('Invalid episode: %s', $arg));
		}

		if (is_array($arg)) {
			$result = [];

			foreach ($arg as $value) {
				$result = array_merge($result, $this->extractEpisodeNumbers($value));
			}

			return $result;
		}

		if (is_string($arg)) {
			if (strpos($arg, ',') !== false) {
				$splits = explode(',', $arg);

				foreach ($splits as $split) {
					if (strlen($split) === 0) {
						throw new \InvalidArgumentException(sprintf('Invalid episode: %s', $split));
					}
				}

				return $this->extractEpisodeNumbers($splits);
			}

			if (strpos($arg, '-') !== false) {
				$splits = explode('-', $arg);

				if (count($splits) !== 2 || strlen($splits[0]) === 0 || strlen($splits[1]) === 0) {
					throw new \InvalidArgumentException(sprintf('Invalid episode: %s', $arg));
				}

				$numbers = $this->extractEpisodeNumbers($splits);

				return range($numbers[0], $numbers[1]);
			}

			if (is_numeric($arg)) {
				return $this->extractEpisodeNumbers((int) $arg);
			}
		}

		throw new \InvalidArgumentException(sprintf('Invalid episode: %s', $arg));
	}

	public function getEpisodeNumbers() {
		return $this->episodeNumbers;
	}

	public function setLanguages(array $languages) {
		$this->languages = [];

		foreach ($languages as $language) {
			if (empty($language) || !is_string($language)) {
				throw new \InvalidArgumentException(sprintf('Invalid language: %s', $language));
			}

			if (in_array($language, $this->languages, true)) {
				throw new \InvalidArgumentException(sprintf('Duplicate language: %s', $language));
			}

			$this->languages[] = $language;
		}
	}

	public function getLanguages() {
		return $this->languages;
	}

	public function getMainLanguage() {
		return $this->languages[0];
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

	public function setDownloadTimeoutInSeconds($downloadTimeoutInSeconds) {
		$this->downloadTimeoutInSeconds = (int) $downloadTimeoutInSeconds;
	}

	public function getDownloadTimeoutInSeconds() {
		return $this->downloadTimeoutInSeconds;
	}

	public function setVerifyChecksums($verifyChecksums) {
		$this->assertBoolean($verifyChecksums);
		$this->verifyChecksums = $verifyChecksums;
	}

	public function isVerifyChecksums() {
		return $this->verifyChecksums;
	}

	public function setUpdateChecksumOnSuccessfulDownload($updateChecksumOnSuccessfulDownload) {
		$this->assertBoolean($updateChecksumOnSuccessfulDownload);
		$this->updateChecksumOnSuccessfulDownload = $updateChecksumOnSuccessfulDownload;
	}

	public function isUpdateChecksumOnSuccessfulDownload() {
		return $this->updateChecksumOnSuccessfulDownload;
	}

	public function setPrintUrls($printUrls) {
		$this->assertBoolean($printUrls);
		$this->printUrls = $printUrls;
	}

	public function isPrintUrls() {
		return $this->printUrls;
	}

	public function setPrintCommandCalls($printCommandCalls) {
		$this->assertBoolean($printCommandCalls);
		$this->printCommandCalls = $printCommandCalls;
	}

	public function isPrintCommandCalls() {
		return $this->printCommandCalls;
	}

	public function setQuietCommands($quietCommands) {
		$this->assertBoolean($quietCommands);
		$this->quietCommands = $quietCommands;
	}

	public function isQuietCommands() {
		return $this->quietCommands;
	}

	public function setGoOnIfDownloadedFileAlreadyExists($goOnIfDownloadedFileAlreadyExists) {
		$this->assertBoolean($goOnIfDownloadedFileAlreadyExists);
		$this->goOnIfDownloadedFileAlreadyExists = $goOnIfDownloadedFileAlreadyExists;
	}

	public function isGoOnIfDownloadedFileAlreadyExists() {
		return $this->goOnIfDownloadedFileAlreadyExists;
	}

	public function setGoOnIfFinalFileAlreadyExists($goOnIfFinalFileAlreadyExists) {
		$this->assertBoolean($goOnIfFinalFileAlreadyExists);
		$this->goOnIfFinalFileAlreadyExists = $goOnIfFinalFileAlreadyExists;
	}

	public function isGoOnIfFinalFileAlreadyExists() {
		return $this->goOnIfFinalFileAlreadyExists;
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
			throw new \RuntimeException(sprintf('Folder "%s" doesn\'t exist.', $path));
		}
		return realpath($path).DIRECTORY_SEPARATOR;
	}

	protected function canonizeExistingExecutable($path) {
		if (!is_executable($path)) {
			throw new \RuntimeException(sprintf('Program "%s" doesn\'t exist or isn\'t executable.', $path));
		}
		return realpath($path);
	}

	protected function assertBoolean($value) {
		if (!is_bool($value)) {
			throw new \InvalidArgumentException(sprintf('Boolean value expected, but %s given.',
					$this->getTypeOf($value)));
		}
	}

	protected function getTypeOf($value) {
		return is_object($value) ? get_class($value) : gettype($value);
	}

}
