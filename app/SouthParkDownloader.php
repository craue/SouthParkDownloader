<?php

require_once(__DIR__.'/Config.php');
require_once(__DIR__.'/EpisodeDatabase.php');
require_once(__DIR__.'/PlayerDatabase.php');
require_once(__DIR__.'/ChecksumMismatchException.php');
require_once(__DIR__.'/FileAlreadyExistsException.php');
require_once(__DIR__.'/FileDoesNotExistException.php');

/**
 * Implementation of control flow and logic.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2012 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class SouthParkDownloader {

	const EXITCODE_SUCCESS = 0;

	const EXITCODE_RTMPDUMP_FAILED = 1;
	const EXITCODE_RTMPDUMP_INCOMPLETE = 2; // e.g. "Download may be incomplete (downloaded about 71.10%), try resuming"

	const EXITCODE_MKVMERGE_WARNINGS = 1; // at least one warning
	const EXITCODE_MKVMERGE_ERROR = 2;

	protected $episodeDb;
	protected $config;
	protected $player;

	protected $tempFiles = array();
	protected $downloadedFiles = array();

	public function setConfig(Config $config) {
		$this->config = $config;
	}

	public function run() {
		$playerDb = new PlayerDatabase(__DIR__.'/../data/players.xml');
		$this->player = $playerDb->findPlayer($this->config->getPlayerUrl());

		$this->episodeDb = new EpisodeDatabase(__DIR__.'/../data/episodes.xml');

		$episodesToProcess = array();

		if ($this->config->getEpisode() > 0) {
			$episodesToProcess[] = $this->config->getEpisode();
		} else {
			$episodesToProcess = $this->episodeDb->getEpisodeIds($this->config->getSeason(), $this->config->getMainLanguage());
		}

		foreach ($episodesToProcess as $episode) {
			$this->config->setEpisode($episode);
			$this->download();
			$this->merge();
			$this->rename();
			$this->cleanUp();
		}
	}

	public function download() {
		foreach ($this->config->getLanguages() as $language) {
			foreach ($this->episodeDb->getActs($this->config->getSeason(), $this->config->getEpisode(), $language) as $act) {
				$actId = $act->attributes()->id;
				$this->downloadPart($language, $actId);
			}
		}
	}

	protected function downloadPart($language, $actId) {
		$targetFile = $this->config->getDownloadFolder() . $this->getFilename($this->config->getSeason(), $this->config->getEpisode(), $language, 'mp4', $actId);
		if (file_exists($targetFile)) {
			throw new FileAlreadyExistsException($targetFile);
		}

		$this->downloadedFiles[] = $targetFile;

		// TODO try all defined mirrors on error / checksum failure (configurable)

		$exitCode = null;
		do {
			$exitCode = $this->call(sprintf('%s -o %s -r %s --swfUrl %s --swfsize %s --swfhash %s%s',
					escapeshellcmd($this->config->getRtmpdump()),
					escapeshellarg($targetFile),
					escapeshellarg($this->episodeDb->getUrl($this->config->getSeason(), $this->config->getEpisode(), $language, $actId)),
					escapeshellarg($this->player->swfurl),
					escapeshellarg($this->player->swfsize),
					escapeshellarg($this->player->swfhash),
					$exitCode === null ? '' : ' --resume --skip 1'));
		} while ($exitCode === self::EXITCODE_RTMPDUMP_INCOMPLETE);

		if ($exitCode !== self::EXITCODE_SUCCESS) {
			$this->abort($exitCode);
		}

		$this->verifyChecksum($targetFile, $this->config->getSeason(), $this->config->getEpisode(), $language, $actId);
	}

	protected function verifyChecksum($file, $season, $episode, $language, $actId) {
		$expectedChecksum = $this->episodeDb->getSha1($season, $episode, $language, $actId);

		if ($this->config->isVerifyChecksums() && !empty($expectedChecksum)) {
			$actualChecksum = sha1_file($file);
			if ($actualChecksum !== $expectedChecksum) {
				throw new ChecksumMismatchException($file, $expectedChecksum, $actualChecksum);
			}
		}

// 		if (empty($expectedChecksum)) {
// 			// add missing checksum
// 			$actualChecksum = sha1_file($file);
// 			$this->episodeDb->setSha1($season, $episode, $language, $actId, $actualChecksum);
// 			$this->episodeDb->save();
// 		}
	}

	public function merge() {
		$this->extractVideoParts();
		$this->extractAudioParts();
		$this->mergeVideoWithAudioParts();
		$this->mergeAllFinalParts();
	}

	/**
	 * Renames an episode's final file to contain the titles of all desired languages.
	 */
	public function rename() {
		$sourceFile = $this->config->getOutputFolder() . $this->getFilename($this->config->getSeason(), $this->config->getEpisode(), $this->config->getLanguages(), 'mkv');
		if (!file_exists($sourceFile)) {
			throw new FileDoesNotExistException($sourceFile);
		}

		$title = '';
		$languages = $this->config->getLanguages();
		for ($i = 0; $i < count($languages); $i++) {
			$currentLanguage = $languages[$i];
			if ($i > 0) {
				$title .= ' (';
			}
			$title .= $this->episodeDb->getTitle($this->config->getSeason(), $this->config->getEpisode(), $currentLanguage);
			if ($i > 0) {
				$title .= ')';
			}
		}

		$targetFile = $this->config->getOutputFolder() . 'South Park ' . $this->getFilename($this->config->getSeason(), $this->config->getEpisode(), $this->config->getLanguages(), 'mkv', null, $title);
		if (file_exists($targetFile)) {
			throw new FileAlreadyExistsException($targetFile);
		}

		rename($sourceFile, $targetFile);
	}

	public function cleanUp() {
		if ($this->config->isRemoveTempFiles()) {
			foreach ($this->tempFiles as $tempFile) {
				unlink($tempFile);
			}
		}
		$this->tempFiles = array();

		if ($this->config->isRemoveDownloadedFiles()) {
			foreach ($this->downloadedFiles as $downloadedFile) {
				unlink($downloadedFile);
			}
		}
		$this->downloadedFiles = array();
	}

	public function getCommandLineArguments(array $argv) {
		$config = new Config();

		for ($i = 1; $i < count($argv); $i++) {
			$param = explode('=', $argv[$i]);
			if (count($param) === 2) {
				list($key, $value) = $param;
				switch (strtolower($key)) {
					case 's':
						if ((int) $value < 1) {
							throw new RuntimeException(sprintf('"%s" is not a valid season number.', $value));
						}
						$config->setSeason((int) $value);
						break;
					case 'e':
						if ((int) $value < 1) {
							throw new RuntimeException(sprintf('"%s" is not a valid episode number.', $value));
						}
						$config->setEpisode((int) $value);
						break;
					case 'l':
						$config->setLanguages(explode('+', $value));
						break;
					default:
						throw new RuntimeException(sprintf('Unknown parameter "%s" used.', $key));
				}
			} else {
				throw new RuntimeException(sprintf('Invalid parameter format used for "%s".', $argv[$i]));
			}
		}

		if ($config->getSeason() < 1) {
			throw new RuntimeException('No season parameter given.');
		}
		if (count($config->getLanguages()) < 1) {
			throw new RuntimeException('No language parameter given.');
		}

		return $config;
	}

	/**
	 * Extracts video from parts with first language.
	 */
	protected function extractVideoParts() {
		foreach ($this->episodeDb->getActs($this->config->getSeason(), $this->config->getEpisode(), $this->config->getMainLanguage()) as $act) {
			$actId = $act->attributes()->id;

			$sourceFile = $this->config->getDownloadFolder() . $this->getFilename($this->config->getSeason(), $this->config->getEpisode(), $this->config->getMainLanguage(), 'mp4', $actId);
			$targetFile = $this->config->getTmpFolder() . $this->getFilename($this->config->getSeason(), $this->config->getEpisode(), null, 'mkv', $actId);
			if (!file_exists($sourceFile)) {
				throw new FileDoesNotExistException($sourceFile);
			}
			if (file_exists($targetFile)) {
				throw new FileAlreadyExistsException($targetFile);
			}

			$this->tempFiles[] = $targetFile;
			$exitCode = $this->call(sprintf('%s -loglevel quiet -i %s -vcodec copy -an %s',
					escapeshellcmd($this->config->getFfmpeg()),
					escapeshellarg($sourceFile),
					escapeshellarg($targetFile)));

			if ($exitCode !== self::EXITCODE_SUCCESS) {
				$this->abort($exitCode);
			}
		}
	}

	/**
	 * Extracts audio from all parts.
	 */
	protected function extractAudioParts() {
		foreach ($this->config->getLanguages() as $currentLanguage) {
			foreach ($this->episodeDb->getActs($this->config->getSeason(), $this->config->getEpisode(), $currentLanguage) as $act) {
				$actId = $act->attributes()->id;

				$sourceFile = $this->config->getDownloadFolder() . $this->getFilename($this->config->getSeason(), $this->config->getEpisode(), $currentLanguage, 'mp4', $actId);
				$targetFile = $this->config->getTmpFolder() . $this->getFilename($this->config->getSeason(), $this->config->getEpisode(), $currentLanguage, 'aac', $actId);
				if (!file_exists($sourceFile)) {
					throw new FileDoesNotExistException($sourceFile);
				}
				if (file_exists($targetFile)) {
					throw new FileAlreadyExistsException($targetFile);
				}

				$this->tempFiles[] = $targetFile;

				$audioCodecParam = '-acodec copy';
				if ($this->episodeDb->getAudioReencode($this->config->getSeason(), $this->config->getEpisode(), $currentLanguage, $actId)) {
					/*
					 * Not just copying audio results in reencoding it.
					 * Needed for S17E03A2EN, as FFMPEG quits with error when trying to copy it:
					 *   [adts @ 01DDFAE0] Application provided invalid, non monotonically increasing dts to muxer in stream 0: 34310340 >= 34306200
					 *   av_interleaved_write_frame(): Invalid argument
					 *
					 * Merging audio tracks with mixed copy/reencode settings doesn't work for mkvmerge:
					 *   Error: The track number 2 from the file 'S17E03A2EN.mkv' cannot be appended to the track number 2 from the file 'S17E03A1EN.mkv'. The track parameters do not match.
					 * Thus, all parts need to be reencoded.
					 */
					$audioCodecParam = '';
				}

				$exitCode = $this->call(sprintf('%s -loglevel quiet -i %s -vn %s %s',
						escapeshellcmd($this->config->getFfmpeg()),
						escapeshellarg($sourceFile),
						$audioCodecParam,
						escapeshellarg($targetFile)));

				if ($exitCode !== self::EXITCODE_SUCCESS) {
					$this->abort($exitCode);
				}
			}
		}
	}

	/**
	 * Merges all audio parts into video parts.
	 */
	// 3x mkvmerge -o SXXEYYAZZDE+EN.mkv SXXEYYAZZ.mkv SXXEYYAZZDE.aac --sync 0:870 SXXEYYAZZEN.aac
	protected function mergeVideoWithAudioParts() {
		foreach ($this->episodeDb->getActs($this->config->getSeason(), $this->config->getEpisode(), $this->config->getMainLanguage()) as $act) {
			$actId = $act->attributes()->id;

			$actVideoSourceFile = $this->config->getTmpFolder() . $this->getFilename($this->config->getSeason(), $this->config->getEpisode(), null, 'mkv', $actId);
			if (!file_exists($actVideoSourceFile)) {
				throw new FileDoesNotExistException($actVideoSourceFile);
			}

			$targetFile = $this->config->getTmpFolder() . $this->getFilename($this->config->getSeason(), $this->config->getEpisode(), $this->config->getLanguages(), 'mkv', $actId);
			if (file_exists($targetFile)) {
				throw new FileAlreadyExistsException($targetFile);
			}

			$audioParts = '';
			$languages = $this->config->getLanguages();
			for ($i = 0; $i < count($languages); $i++) {
				$currentLanguage = $languages[$i];

				if (strlen($audioParts) > 0) {
					$audioParts .= ' ';
				}

				$audioParts .= '--aac-is-sbr 0 ';

				$actAudioDelay = $this->episodeDb->getActAudioDelay($this->config->getSeason(), $this->config->getEpisode(), $currentLanguage, $actId);
				if ($actAudioDelay > 0) {
					$audioParts .= sprintf('--sync 0:%u', $actAudioDelay);
					$audioParts .= ' ';
				}

				$actAudioSourceFile = $this->config->getTmpFolder() . $this->getFilename($this->config->getSeason(), $this->config->getEpisode(), $currentLanguage, 'aac', $actId);
				if (!file_exists($actAudioSourceFile)) {
					throw new FileDoesNotExistException($actAudioSourceFile);
				}
				$audioParts .= escapeshellarg($actAudioSourceFile);
			}

			$this->tempFiles[] = $targetFile;
			$exitCode = $this->call(sprintf('%s --quiet -o %s %s %s',
					escapeshellcmd($this->config->getMkvmerge()),
					escapeshellarg($targetFile),
					escapeshellarg($actVideoSourceFile),
					$audioParts));

			if ($exitCode !== self::EXITCODE_SUCCESS && $exitCode !== self::EXITCODE_MKVMERGE_WARNINGS) {
				$this->abort($exitCode);
			}
		}
	}

	/**
	 * Merges all final parts.
	 */
	// mkvmerge -o complete.mkv --default-track 2 --language 2:ger --language 3:eng part1.mkv +part2.mkv +part3.mkv
	protected function mergeAllFinalParts() {
		$targetFile = $this->config->getOutputFolder() . $this->getFilename($this->config->getSeason(), $this->config->getEpisode(), $this->config->getLanguages(), 'mkv');
		if (file_exists($targetFile)) {
			throw new FileAlreadyExistsException($targetFile);
		}

		$audioParts = '';
		$languages = $this->config->getLanguages();
		for ($i = 0; $i < count($languages); $i++) {
			$currentLanguage = strtolower($languages[$i]);

			if (strlen($audioParts) > 0) {
				$audioParts .= ' ';
			}

			if (in_array($currentLanguage, array('de', 'en'), true)) {
				$audioParts .= sprintf('--language %u:%s', $i + 1, $currentLanguage);
			}
		}

		$mkvParts = '';
		foreach ($this->episodeDb->getActs($this->config->getSeason(), $this->config->getEpisode(), $this->config->getMainLanguage()) as $act) {
			$actId = $act->attributes()->id;

			if (strlen($mkvParts) > 0) {
				$mkvParts .= ' +';
			}

			$actSourceFile = $this->config->getTmpFolder() . $this->getFilename($this->config->getSeason(), $this->config->getEpisode(), $this->config->getLanguages(), 'mkv', $actId);
			if (!file_exists($actSourceFile)) {
				throw new FileDoesNotExistException($actSourceFile);
			}

			$mkvParts .= escapeshellarg($actSourceFile);
		}

		$exitCode = $this->call(sprintf('%s --quiet -o %s --default-track 1 %s %s',
				escapeshellcmd($this->config->getMkvmerge()),
				escapeshellarg($targetFile),
				$audioParts,
				$mkvParts));

		if ($exitCode !== self::EXITCODE_SUCCESS && $exitCode !== self::EXITCODE_MKVMERGE_WARNINGS) {
			$this->abort($exitCode);
		}
	}

	protected function getFilename($season, $episode, $language = null, $extension = null, $act = null, $title = null) {
		if (!is_array($language)) {
			$language = array($language);
		}
		return sprintf('S%02uE%02u%s%s%s%s',
				$season,
				$episode,
				!empty($act) ? 'A' . $act : '',
				!empty($title) ? ' ' . $title . ' ' : '',
				!empty($language) ? strtoupper(implode('+', $language)) : '',
				!empty($extension) ? '.' . $extension : '');
	}

	protected function call($command) {
		if ($this->config->isPrintCommandCalls()) {
			echo '> ', $command, "\n";
		}

		$exitCode = 0;

		passthru($command, $exitCode);

		return $exitCode;
	}

	protected function abort($exitCode) {
		throw new RuntimeException(sprintf(
				'External program aborted with an exit code of "%d" which seems to be an error. Will abort now.',
				$exitCode));
	}

}
