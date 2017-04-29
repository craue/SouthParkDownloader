<?php


/**
 * Implementation of control flow and logic.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2017 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class SouthParkDownloader {

	const EXITCODE_SUCCESS = 0;

	const EXITCODE_MKVMERGE_WARNINGS = 1; // at least one warning
	const EXITCODE_MKVMERGE_ERROR = 2;

	/**
	 * @var AnomalyDatabase
	 */
	protected $anomalyDb;

	/**
	 * @var ChecksumDatabase
	 */
	protected $checksumDb;

	/**
	 * @var SettingDatabase
	 */
	protected $settingsDb;

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * @var Season|null
	 */
	protected $season;

	/**
	 * @var Episode|null
	 */
	protected $episode;

	protected $tempFiles = array();
	protected $downloadedFiles = array();

	public function setConfig(Config $config) {
		$this->config = $config;
	}

	public function run() {
		$this->anomalyDb = new AnomalyDatabase(__DIR__.'/../data/anomalies.json');
		$this->checksumDb = new ChecksumDatabase(__DIR__.'/../data/checksums.json');
		$this->settingDb = new SettingDatabase(__DIR__.'/../data/settings.json');

		if (!in_array($this->config->getSeasonNumber(), $this->getAvailableSeasons($this->config->getMainLanguage()), true)) {
			throw new RuntimeException(sprintf('Unknown season "%s".', $this->config->getSeasonNumber()));
		}

		$this->season = $this->buildSeason($this->config->getSeasonNumber());

		/* @var $episodesToProcess Episode[] */
		$episodesToProcess = $this->config->getEpisodeNumber() > 0 ? array($this->season->getEpisode($this->config->getEpisodeNumber())) : $this->season->getEpisodes();

		foreach ($episodesToProcess as $episode) {
			$this->episode = $episode;
			$this->log(sprintf('processing S%02uE%02u', $this->episode->getSeason()->getNumber(), $this->episode->getNumber()));
			if ($this->download()) {
				$this->merge();
				$this->cleanUp();
			}
		}
	}

	public function download() {
		// different URIs which only work depending on the user's location
		$episodeUris = array(
			sprintf('mgid:arc:episode:southparkstudios.com:%s', $this->episode->getItemId()),
			sprintf('mgid:arc:episode:southpark.de:%s', $this->episode->getItemId()),
		);

		foreach ($episodeUris as $episodeUri) {
			$metaUrl = sprintf('http://media.mtvnservices.com/pmt/e1/access/index.html?uri=%s&configtype=edge', $episodeUri);

			$this->log(sprintf('  fetching episode metadata for S%02uE%02u%s', $this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $this->config->isPrintUrls() ? sprintf(' from %s', $metaUrl) : ''));
			$metaContent = file_get_contents($metaUrl, false, stream_context_create(array(
				'http' => array(
					'method' => 'GET',
					'header' => sprintf("Referer: %s\r\n", $this->settingDb->getEpisodeDataUrl($this->config->getMainLanguage(), $this->episode->getSeason()->getNumber(), $this->episode->getNumber()))
				)
			)));

			$metaData = json_decode($metaContent, true);

			if (isset($metaData['feed']['items'])) {
				break;
			}

			$this->log('    (failed)');
		}

		if (!isset($metaData['feed']['items'])) {
			$this->log(sprintf('  Failed fetching episode metadata for S%02uE%02u. Maybe it\'s just not available currently. Skipping this episode.', $this->episode->getSeason()->getNumber(), $this->episode->getNumber()));
			return false;
		}

		foreach ($metaData['feed']['items'] as $index => $item) {
			$itemUrlTemplate = $item['group']['content']; // e.g. "http://media-utils.mtvnservices.com/services/MediaGenerator/mgid:arc:video:southparkstudios.com:bc766f29-be39-4f66-82a7-e74e49c1898c?device={device}&aspectRatio=16:9&lang=de"

			foreach ($this->config->getLanguages() as $language) {
				$itemUrl = strtr($itemUrlTemplate . '&format=json', array(
					'{device}' => 'iPad',
					'lang=de' => sprintf('lang=%s', $language),
				));

				$actNumber = $index + 1;

				$act = $this->episode->getAct($actNumber);
				if ($act === null) {
					$act = new Act($this->episode);
					$act->setNumber($actNumber);
					$this->episode->addAct($act);
				}

				$act->setDownloadMetadataUrlForLanguage($language, $itemUrl);

				$this->downloadPart($act, $language);
			}
		}

		return true;
	}

	protected function downloadPart(Act $act, $language) {
		$actDownloadMetadataUrl = $act->getDownloadMetadataUrlForLanguage($language);
		$this->log(sprintf('  fetching act metadata for S%02uE%02uA%u%s%s', $this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $act->getNumber(), strtoupper($language), $this->config->isPrintUrls() ? sprintf(' from %s', $actDownloadMetadataUrl) : ''));
		$itemContent = file_get_contents($actDownloadMetadataUrl);
		$itemData = json_decode($itemContent, true);

		$hlsUrl = $itemData['package']['video']['item'][0]['rendition'][0]['src'];
		// [en] e.g. "https://cp541867-vh.akamaihd.net/i/mtvnorigin/gsp.comedystor/com/sp/season-20/2001/acts/sp_2001_actHD_01_B_,384x216_300,512x288_450,768x432_750,960x540_1000,1280x720_1200,.mp4.csmil/master.m3u8?hdnea=st%3D1489348840%7Eexp%3D1489363240%7Eacl%3D%2Fi%2Fmtvnorigin%2Fgsp.comedystor%2Fcom%2Fsp%2Fseason-20%2F2001%2Facts%2Fsp_2001_actHD_01_B_%2C384x216_300%2C512x288_450%2C768x432_750%2C960x540_1000%2C1280x720_1200%2C.mp4.csmil%2F*%7Ehmac%3D17bb40380852dfcde94a43ec2b2a8dde34d1cf87406130effe9bb97a91a3c1f7&__a__=off&__b__=450&__viacc__=NONE"
		// [de] e.g. "https://cp541867-vh.akamaihd.net/i/mtviestor/_!/intlod/southpark/video/Deutsche/Season_01/0101/acts/0101_1_DI_DEU_,480x360_400,640x480_600,640x480_800,.mp4.csmil/master.m3u8?hdnea=st%3D1489356737%7Eexp%3D1489371137%7Eacl%3D%2Fi%2Fmtviestor%2F_*%2Fintlod%2Fsouthpark%2Fvideo%2FDeutsche%2FSeason_01%2F0101%2Facts%2F0101_1_DI_DEU_%2C480x360_400%2C640x480_600%2C640x480_800%2C.mp4.csmil%2F*%7Ehmac%3D73a07f4b00e14d17d55b1b9ba8753b909257fbec61414f6ce800ed81cd8f147c&__a__=off&__b__=450&__viacc__=NONE"

		// let entry with highest bitrate appear first in resulting list (has no effect on newer episodes with quality 1280x720_1200, but makes a difference for older episodes with either 640x480_600 or 640x480_800)
		$actUrl = strtr($hlsUrl, array(
			'__b__=450' => '__b__=10000',
		));

		$targetFile = $this->config->getDownloadFolder() . $this->getFilename($this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $language, 'mp4', $act->getNumber());

		$this->log(sprintf('  downloading S%02uE%02uA%u%s to %s%s', $this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $act->getNumber(), strtoupper($language), $targetFile, $this->config->isPrintUrls() ? sprintf(' from %s', $actUrl) : ''));

		if (file_exists($targetFile)) {
			if ($this->config->isGoOnIfDownloadedFileAlreadyExists()) {
				$this->log('    (skipped)');
				$this->verifyChecksum($targetFile, $this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $act->getNumber(), $language);

				return;
			}

			throw new FileAlreadyExistsException($targetFile);
		}

		$this->downloadedFiles[] = $targetFile;

		$startTime = microtime(true);

		$exitCode = $this->call(sprintf('%s%s -hide_banner -i %s -codec copy %s',
				escapeshellcmd($this->config->getFfmpeg()),
				$this->config->isQuietCommands() ? ' -loglevel quiet' : '',
				escapeshellcmd($actUrl),
				escapeshellarg($targetFile)));

		if ($exitCode !== self::EXITCODE_SUCCESS) {
			$this->abort($exitCode);
		}

		$this->log(sprintf('    took %.1f s for %.1f MB', microtime(true) - $startTime, filesize($targetFile) / 1024 / 1024));

		$this->verifyChecksum($targetFile, $this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $act->getNumber(), $language);
	}

	protected function verifyChecksum($file, $seasonNumber, $episodeNumber, $language, $actNumber) {
		$hashAlgo = 'sha256';
		$expectedChecksum = $this->checksumDb->getHash($seasonNumber, $episodeNumber, $language, $actNumber);

		if ($this->config->isVerifyChecksums() && !empty($expectedChecksum)) {
			$actualChecksum = hash_file($hashAlgo, $file);
			if ($actualChecksum !== $expectedChecksum) {
				throw new ChecksumMismatchException($file, $expectedChecksum, $actualChecksum);
			}

			if ($this->config->isUpdateChecksumOnSuccessfulDownload()) {
				// update "last-checked" timestamp
				$this->checksumDb->updateLastChecked($seasonNumber, $episodeNumber, $language, $actNumber);
			}
		}

		if ($this->config->isUpdateChecksumOnSuccessfulDownload()) {
			if (empty($expectedChecksum)) {
				// add missing checksum
				$actualChecksum = hash_file($hashAlgo, $file);
				$this->checksumDb->updateHash($seasonNumber, $episodeNumber, $language, $actNumber, $actualChecksum);
			}

			$this->checksumDb->save();
		}
	}

	protected function getFrameRateFromFile($file) {
		$command = sprintf('%s -hide_banner -i %s 2>&1',
				escapeshellcmd($this->config->getFfmpeg()),
				escapeshellarg($file));

		exec($command, $output, $exitCode);

		if (preg_match('#kb/s, (\d|.+) fps,#', implode("\n", $output), $matches) !== false) {
			return $matches[1];
		}

		return null;
	}

	public function merge() {
		$videosHaveDifferentFrameRates = false;

		if (count($this->config->getLanguages()) > 1) {
			/*
			 * Before merging more that audio track into the main video track, check if video frame rate differs between source files.
			 * Occurs with S20E01-04: EN (23.976 fps) and DE (25 fps).
			 */
			$frameRates = array();

			foreach ($this->config->getLanguages() as $language) {
				foreach ($this->episode->getActs() as $act) {
					$actNumber = $act->getNumber();

					$actSourceFile = $this->config->getDownloadFolder() . $this->getFilename($this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $language, 'mp4', $actNumber);
					if (!file_exists($actSourceFile)) {
						throw new FileDoesNotExistException($actSourceFile);
					}

					$frameRates[] = $this->getFrameRateFromFile($actSourceFile);
				}
			}

			$videosHaveDifferentFrameRates = count(array_unique($frameRates)) > 1;
		}

		if ($videosHaveDifferentFrameRates) {
			$this->log('  will create separate output files per language due to different frame rates of source files');
			foreach ($this->config->getLanguages() as $language) {
				$this->mergeParts($language, array($language));
			}
		} else {
			$this->mergeParts($this->config->getMainLanguage(), $this->config->getLanguages());
		}
	}

	protected function mergeParts($mainLanguage, array $languages) {
		$targetFile = $this->config->getOutputFolder() . $this->getEpisodeTitleForFilename($languages);

		$this->log(sprintf('  merging acts of S%02uE%02u%s to %s', $this->episode->getSeason()->getNumber(), $this->episode->getNumber(), strtoupper(implode('+', $languages)), $targetFile));

		if (file_exists($targetFile)) {
			if ($this->config->isGoOnIfFinalFileAlreadyExists()) {
				$this->log('    (skipped)');
				return;
			}

			throw new FileAlreadyExistsException($targetFile);
		}

		$arguments = array();
		foreach ($languages as $language) {
			$arguments[] = sprintf('--language 1:%s', $language);

			foreach ($this->episode->getActs() as $act) {
				$actNumber = $act->getNumber();

				if ($language !== $mainLanguage) {
					$arguments[] = '--no-video';
				}

				$videoSync = $this->anomalyDb->getVideoSync($this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $actNumber, $language);
				if ($videoSync !== null) {
					$arguments[] = sprintf('--sync 0:%d', $videoSync);
				}

				$audioSync = $this->anomalyDb->getAudioSync($this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $actNumber, $language);
				if ($audioSync !== null) {
					$arguments[] = sprintf('--sync 1:%d', $audioSync);
				}

				if ($actNumber > 1) {
					$arguments[] = '+';
				}

				$actSourceFile = $this->config->getDownloadFolder() . $this->getFilename($this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $language, 'mp4', $actNumber);
				if (!file_exists($actSourceFile)) {
					throw new FileDoesNotExistException($actSourceFile);
				}

				$arguments[] = escapeshellarg($actSourceFile);
			}
		}

		// use an options file to properly pass the UTF-8-encoded episode title to mkvmerge (not possible via command line on Windows)
		$optionsFile = $this->config->getTmpFolder() . $this->getFilename($this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $languages, 'json');

		if (file_exists($optionsFile)) {
			throw new FileAlreadyExistsException($optionsFile);
		}

		$this->tempFiles[] = $optionsFile;

		if (file_put_contents($optionsFile, json_encode(array('--title', $this->getEpisodeTitle($languages)))) === false) {
			throw new RuntimeException(sprintf('Error writing file "%s".', $optionsFile));
		}

		$exitCode = $this->call(sprintf('%s%s @%s -o %s --chapter-language %s --generate-chapters when-appending --generate-chapters-name-template %s --default-track 1 %s',
				escapeshellcmd($this->config->getMkvmerge()),
				$this->config->isQuietCommands() ? ' --quiet' : '',
				escapeshellarg($optionsFile),
				escapeshellarg($targetFile),
				escapeshellarg($mainLanguage),
				escapeshellarg(sprintf('%s <NUM>', $this->settingDb->getChapterName($mainLanguage))),
				implode(' ', $arguments)));

		if ($exitCode !== self::EXITCODE_SUCCESS && $exitCode !== self::EXITCODE_MKVMERGE_WARNINGS) {
			$this->abort($exitCode);
		}
	}

	public function cleanUp() {
		$this->log('  cleaning up');

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
						$config->setSeasonNumber((int) $value);
						break;
					case 'e':
						if ((int) $value < 1) {
							throw new RuntimeException(sprintf('"%s" is not a valid episode number.', $value));
						}
						$config->setEpisodeNumber((int) $value);
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

		if ($config->getSeasonNumber() < 1) {
			throw new RuntimeException('No season parameter given.');
		}
		if (count($config->getLanguages()) < 1) {
			throw new RuntimeException('No language parameter given.');
		}

		return $config;
	}

	protected function getEpisodeTitle(array $languages) {
		$episodeTitle = '';

		foreach ($languages as $index => $language) {
			if ($index > 0) {
				$episodeTitle .= ' | ';
			}
			$episodeTitle .= $this->episode->getTitleForLanguage($language);
		}

		return sprintf('South Park %s: %s', $this->getFilename($this->episode->getSeason()->getNumber(), $this->episode->getNumber()), $episodeTitle);
	}

	protected function getEpisodeTitleForFilename(array $languages) {
		$episodeTitleForFilename = '';

		foreach ($languages as $index => $language) {
			if ($index > 0) {
				$episodeTitleForFilename .= ' (';
			}
			$episodeTitleForFilename .= $this->episode->getTitleForLanguage($language);
			if ($index > 0) {
				$episodeTitleForFilename .= ')';
			}
		}

		// replace some characters for the filename
		$episodeTitleForFilename = strtr($episodeTitleForFilename, array(
			'„' => "'",
			'“' => "'",
		));

		// remove characters which would be invalid for the filename
		$episodeTitleForFilename = preg_replace("/[^a-z0-9äöüßè*()!%_' .\-]/iu", '', $episodeTitleForFilename);

		// fix filename on Windows
		if (defined('PHP_WINDOWS_VERSION_BUILD')) {
			$episodeTitleForFilename = iconv('UTF-8', 'ISO-8859-1//IGNORE', $episodeTitleForFilename);
		}

		return 'South Park ' . $this->getFilename($this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $languages, 'mkv', null, $episodeTitleForFilename);
	}

	protected function getFilename($seasonNumber, $episodeNumber, $language = null, $extension = null, $actNumber= null, $title = null) {
		if (!is_array($language)) {
			$language = array($language);
		}

		return sprintf('S%02uE%02u%s%s%s%s',
				$seasonNumber,
				$episodeNumber,
				!empty($actNumber) ? 'A' . $actNumber : '',
				!empty($title) ? ' ' . $title . ' ' : '',
				!empty($language) ? strtoupper(implode('+', $language)) : '',
				!empty($extension) ? '.' . $extension : '');
	}

	protected function call($command) {
		if ($this->config->isPrintCommandCalls()) {
			$this->log('  > ' . $command);
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

	protected function log($line) {
		echo $line, "\n";
	}

	public function getAvailableSeasons($language) {
		$url = $this->settingDb->getAvailableSeasonsUrl($language);

		$this->log(sprintf('fetching available seasons%s', $this->config->isPrintUrls() ? sprintf(' from %s', $url) : ''));

		$domDocument = new DOMDocument();
		libxml_use_internal_errors(true); // disable warnings while parsing
		$domDocument->loadHTML(file_get_contents($url));

		$seasons = array();

		// section#main_content nav a.season-filter[data-value]
		$seasonFilters = (new DOMXPath($domDocument))->query('//section[@id="main_content"]//nav/a[contains(@class,"season-filter")][starts-with(@data-value,"season-")]');
		foreach ($seasonFilters as $seasonFilter) {
			$seasons[] = (int) str_replace('season-', '', $seasonFilter->getAttribute('data-value'));
		}

		return $seasons;
	}

	/**
	 * @param int $seasonNumber Season number.
	 * @return Season
	 */
	public function buildSeason($seasonNumber) {
		$season = new Season($seasonNumber);

		foreach ($this->config->getLanguages() as $language) {
			$url = $this->settingDb->getAvailableEpisodesUrl($language, $seasonNumber);

			$this->log(sprintf('fetching episodes for S%02u%s%s', $seasonNumber, strtoupper($language), $this->config->isPrintUrls() ? sprintf(' from %s', $url) : ''));

			$data = json_decode(file_get_contents($url), true);

			foreach ($data['results'] as $result) {
				$episodeNumber = (int) substr($result['episodeNumber'], 2); // e.g. "0101" or "1809"

				$availability = $result['_availability']; // should be "true", but is "banned" for e.g. S14E05+S14E06, "huluplus" for e.g. S01E02EN, "beforepremiere" for e.g. S10E03DE
				if (!in_array($availability, array('true', 'huluplus'), true)) {
					// if this episode is requested explicitly, tell why it's is not available
					if ($this->config->getSeasonNumber() === $seasonNumber && $this->config->getEpisodeNumber() === $episodeNumber) {
						throw new RuntimeException(sprintf('S%02uE%02u is not available: %s', $seasonNumber, $episodeNumber, $availability));
					}

					// otherwise just skip it
					continue;
				}

				$episode = $season->getEpisode($episodeNumber);
				if ($episode === null) {
					$episode = new Episode($season);
					$episode->setNumber($episodeNumber);
					$episode->setItemId($result['itemId']); // e.g. "ebb343ef-711d-450e-baa5-60ec0848e977"
					$season->addEpisode($episode);
				}

				$fixedTitle = $this->anomalyDb->getTitle($seasonNumber, $episodeNumber, $language);
				$episode->setTitleForLanguage($language, !empty($fixedTitle) ? $fixedTitle : $result['title']); // e.g. "Hashtag „Aufwärmen“"
			}
		}

		return $season;
	}

}
