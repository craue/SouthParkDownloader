<?php

namespace App\Command;

use App\Config\Config;
use App\Database\AnomalyDatabase;
use App\Database\ChecksumDatabase;
use App\Database\SettingDatabase;
use App\Exception\ChecksumMismatchException;
use App\Exception\FileAlreadyExistsException;
use App\Exception\FileDoesNotExistException;
use App\Helper\Ffmpeg;
use App\Helper\Mkvmerge;
use App\Helper\Spinner;
use App\Vo\Act;
use App\Vo\Download;
use App\Vo\Episode;
use App\Vo\Season;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Implementation of control flow and logic.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class DownloadCommand extends Command {

	const EXITCODE_SUCCESS = 0;

	const EXITCODE_MKVMERGE_WARNINGS = 1; // at least one warning
	const EXITCODE_MKVMERGE_ERROR = 2;

	const MKVMERGE_MIN_VERSION = '9.7'; // support for option files (@options.json) is needed, which was added in 9.7.0 according to https://www.videohelp.com/software/MKVToolNix/version-history

	protected static $defaultName = 'php download.php';

	/**
	 * @var SymfonyStyle
	 */
	private $out;

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

	/**
	 * @var bool
	 */
	private $ffmpegHideBanner;

	public function __construct(Config $config) {
		parent::__construct();

		$this->config = $config;
	}

	protected function configure() {
		$this
			->addArgument('language', InputArgument::REQUIRED, 'Language(s): Must be "en", "de", "en+de", "de+en".')
			->addArgument('season', InputArgument::REQUIRED, 'Season: Must be a number.')
			->addArgument('episode', InputArgument::OPTIONAL, 'Episode(s): If not given, means "all season\'s episodes". If given, must be a number or a range of numbers (see examples below).')
			->setHelp(<<<HERE
<comment>Usage examples:</comment>
  <info>%command.name% en 15</info>
    Download all episodes of season 15 in English.

  <info>%command.name% de 23 7-10</info>
    Download episodes 7, 8, 9 & 10 of season 23 in German.

  <info>%command.name% de 23 7,10</info>
    Download episodes 7 & 10 of season 23 in German.

  <info>%command.name% de 23 1-3,9,10</info>
    Download episodes 1, 2, 3, 9 & 10 of season 23 in German.

  <info>%command.name% de+en 13 8</info>
    Download episode 8 of season 13 in German and English, while taking video
    from the German source and setting the default audio language to German.
HERE)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->out = new SymfonyStyle($input, $output);

		$this->applyArguments($input);
		$this->assertMkvmergeMinVersion();

		$this->anomalyDb = new AnomalyDatabase(__DIR__.'/../../data/anomalies.json');
		$this->checksumDb = new ChecksumDatabase(__DIR__.'/../../data/checksums.json');
		$this->settingDb = new SettingDatabase(__DIR__.'/../../data/settings.json');

		if (!in_array($this->config->getSeasonNumber(), $this->getAvailableSeasons($this->config->getMainLanguage()), true)) {
			throw new \RuntimeException(sprintf('Invalid season: %s', $this->config->getSeasonNumber()));
		}

		$this->ffmpegHideBanner = $this->isFfmpegSupportingHideBanner();

		$this->season = $this->buildSeason($this->config->getSeasonNumber());

		/* @var $episodesToProcess Episode[] */
		$episodesToProcess = count($this->config->getEpisodeNumbers()) > 0 ? $this->season->getEpisodes($this->config->getEpisodeNumbers()) : $this->season->getAllEpisodes();

		foreach ($episodesToProcess as $episode) {
			$this->episode = $episode;

			$this->logStep(0, sprintf('processing S%02uE%02u', $this->episode->getSeason()->getNumber(), $this->episode->getNumber()));

			if ($this->download()) {
				$this->merge();
				$this->cleanUp();
			}
		}

		return 0;
	}

	public function download() {
		// different URIs which only work depending on the user's location
		$episodeUris = array(
			sprintf('mgid:arc:episode:southparkstudios.com:%s', $this->episode->getItemId()),
			sprintf('mgid:arc:episode:southpark.de:%s', $this->episode->getItemId()),
		);

		$this->logStep(1, 'fetching episode metadata', sprintf('S%02uE%02u', $this->episode->getSeason()->getNumber(), $this->episode->getNumber()));

		foreach ($episodeUris as $episodeUri) {
			$metaUrl = sprintf('http://media.mtvnservices.com/pmt/e1/access/index.html?uri=%s&configtype=edge', $episodeUri);

			$this->logUrl(2, $metaUrl);

			$metaData = json_decode(file_get_contents($metaUrl), true);

			if (isset($metaData['feed']['items'])) {
				break;
			}

			$this->logWarning(3, 'failed');
		}

		if (!isset($metaData['feed']['items'])) {
			$this->logWarning(2, 'Failed fetching episode metadata. Maybe it\'s just not available currently. Skipping this episode.', sprintf('S%02uE%02u', $this->episode->getSeason()->getNumber(), $this->episode->getNumber()));
			return false;
		}

		$downloads = array();

		foreach ($metaData['feed']['items'] as $index => $item) {
			$itemUrlTemplate = $item['group']['content']; // e.g. "http://media-utils.mtvnservices.com/services/MediaGenerator/mgid:arc:video:southparkstudios.com:bc766f29-be39-4f66-82a7-e74e49c1898c?device={device}&aspectRatio=16:9&lang=de"

			foreach ($this->config->getLanguages() as $language) {
				$itemUrl = strtr($itemUrlTemplate . '&format=json', array(
					'{device}' => 'iPad',
					'lang=de' => sprintf('lang=%s', $language),
					'lang=en' => sprintf('lang=%s', $language),
				));

				$actNumber = $index + 1;

				$act = $this->episode->getAct($actNumber);
				if ($act === null) {
					$act = new Act($this->episode);
					$act->setNumber($actNumber);
					$this->episode->addAct($act);
				}

				$act->setDownloadMetadataUrlForLanguage($language, $itemUrl);

				$download = $this->findDownload($act, $language);

				if ($download !== null) {
					$downloads[] = $download;
				}
			}
		}

		if (!empty($downloads)) {
			$this->logStep(1, sprintf('starting %u parallel downloads...', count($downloads)));
		}

		foreach ($downloads as $download) {
			$this->downloadFile($download);
			$this->downloadedFiles[] = $download->getTargetFile();
		}

		$spinner = new Spinner($this->out);

		while (true) {
			usleep(250000); // idle
			$spinner->advance();

			$allDone = true;

			foreach ($downloads as $download) {
				if ($download->getProcess() === null) {
					continue;
				}

				if ($download->getProcess()->isRunning()) {
					$allDone = false;
					continue;
				}

				$exitCode = $download->getProcess()->getExitCode();

				if ($exitCode !== self::EXITCODE_SUCCESS) {
					$spinner->destroy();
					$this->abort($exitCode);
				}

				$download->setProcess(null);
				$spinner->hide();
				$this->logInfo(2, sprintf('took %.1f s for %.1f MB', microtime(true) - $download->getStartTime(), filesize($download->getTargetFile()) / 1024 / 1024), $download->getProcessName());
				$spinner->show();
			}

			if ($allDone) {
				break;
			}
		}

		$spinner->destroy();

		$this->logStep(1, sprintf('%s checksums...', $this->config->isUpdateChecksumOnSuccessfulDownload() ? 'verifying/updating' : 'verifying'));
		foreach ($downloads as $download) {
			$this->verifyChecksum($download->getTargetFile(), $download->getAct()->getEpisode()->getSeason()->getNumber(), $download->getAct()->getEpisode()->getNumber(), $download->getAct()->getNumber(), $download->getLanguage(), $this->config->isUpdateChecksumOnSuccessfulDownload());
		}

		return true;
	}

	/**
	 * @param Act $act
	 * @param string $language
	 * @throws FileAlreadyExistsException
	 * @return Download|null
	 */
	protected function findDownload(Act $act, $language) {
		$actDownloadMetadataUrl = $act->getDownloadMetadataUrlForLanguage($language);

		$this->logStep(1, 'fetching act metadata', sprintf('S%02uE%02uA%u%s', $this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $act->getNumber(), strtoupper($language)));
		$this->logUrl(2, $actDownloadMetadataUrl);

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

		$this->logStep(1, sprintf('will download to %s', $targetFile), sprintf('S%02uE%02uA%u%s', $this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $act->getNumber(), strtoupper($language)));
		$this->logUrl(2, $actUrl);

		if (file_exists($targetFile)) {
			if ($this->config->isGoOnIfDownloadedFileAlreadyExists()) {
				$hashWrong = false;
				try {
					$this->verifyChecksum($targetFile, $this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $act->getNumber(), $language, false);
				} catch (ChecksumMismatchException $e) {
					$hashWrong = true;
				}

				if (!$hashWrong) {
					$this->logWarning(2, 'skipped');
					$this->verifyChecksum($targetFile, $this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $act->getNumber(), $language, $this->config->isUpdateChecksumOnSuccessfulDownload());

					return null;
				} else {
					unlink($targetFile);
				}
			} else {
				throw new FileAlreadyExistsException($targetFile);
			}
		}

		return new Download($act, $language, $actUrl, $targetFile);
	}

	protected function downloadFile(Download $download) {
		$timeout = $this->config->getDownloadTimeoutInSeconds();

		try {
			$download->setStartTime(microtime(true));

			$command = array($this->config->getFfmpeg());

			if ($this->config->isQuietCommands()) {
				$command[] = '-loglevel';
				$command[] = 'quiet';
			}

			if ($this->ffmpegHideBanner) {
				$command[] = '-hide_banner';
			}

			$command[] = '-i';
			$command[] = $download->getUrl();

			$command[] = '-codec';
			$command[] = 'copy';

			$command[] = $download->getTargetFile();

			$this->call($command, $timeout, true, $process);

			$download->setProcess($process);
		} catch (ProcessTimedOutException $e) {
			$this->logWarning(2, sprintf('timeout of %u s reached, trying again', $timeout), $download->getProcessName());
			@unlink($download->getTargetFile());
			$this->downloadFile($download);
		}
	}

	protected function verifyChecksum($file, $seasonNumber, $episodeNumber, $actNumber, $language, $updateDatabase) {
		$hashAlgo = 'sha256';
		$expectedChecksum = $this->checksumDb->getHash($seasonNumber, $episodeNumber, $actNumber, $language);

		if ($this->config->isVerifyChecksums() && !empty($expectedChecksum)) {
			$actualChecksum = hash_file($hashAlgo, $file);
			if ($actualChecksum !== $expectedChecksum) {
				throw new ChecksumMismatchException($file, $expectedChecksum, $actualChecksum);
			}

			if ($updateDatabase) {
				// update "last-checked" timestamp
				$this->checksumDb->updateLastChecked($seasonNumber, $episodeNumber, $actNumber, $language);
			}
		}

		if ($updateDatabase) {
			$actualChecksum = hash_file($hashAlgo, $file);
			$this->checksumDb->updateHash($seasonNumber, $episodeNumber, $actNumber, $language, $actualChecksum);
			$this->checksumDb->save();
		}
	}

	protected function isFfmpegSupportingHideBanner() {
		// according to https://stackoverflow.com/a/22705820
		return version_compare($this->getFfmpegVersion(), '2.2', '>=');
	}

	protected function getFfmpegVersion() {
		$process = new Process(array($this->config->getFfmpeg(), '-version'));
		$process->mustRun();

		return Ffmpeg::extractVersion($process->getOutput());
	}

	protected function assertMkvmergeMinVersion() {
		$mkvmergeVersion = $this->getMkvmergeVersion();
		if (version_compare($mkvmergeVersion, self::MKVMERGE_MIN_VERSION, '>=') === false) {
			throw new \RuntimeException(sprintf('The version of mkvmerge does not meet the minimum requirements. Expected at least %s, but found %s.', self::MKVMERGE_MIN_VERSION, $mkvmergeVersion));
		}
	}

	protected function getMkvmergeVersion() {
		$process = new Process(array($this->config->getMkvmerge(), '--version'));
		$process->mustRun();

		return Mkvmerge::extractVersion($process->getOutput());
	}

	protected function getFrameRateFromFile($file) {
		$command = array($this->config->getFfmpeg());

		if ($this->ffmpegHideBanner) {
			$command[] = '-hide_banner';
		}

		$command[] = '-i';
		$command[] = $file;

		$process = new Process($command);
		$process->run();

		$output = $process->getErrorOutput(); // All output is sent to STDERR because of "At least one output file must be specified".

		return Ffmpeg::extractFramerate($output);
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
			$this->logStep(1, 'will create separate output files per language due to different frame rates of source files');
			foreach ($this->config->getLanguages() as $language) {
				$this->mergeParts($language, array($language));
			}
		} else {
			$this->mergeParts($this->config->getMainLanguage(), $this->config->getLanguages());
		}
	}

	protected function mergeParts($mainLanguage, array $languages) {
		$targetFile = $this->config->getOutputFolder() . $this->getEpisodeTitleForFilename($languages);

		$this->logStep(1, sprintf('merging acts to %s', $targetFile), sprintf('S%02uE%02u%s', $this->episode->getSeason()->getNumber(), $this->episode->getNumber(), strtoupper(implode('+', $languages))));

		if (file_exists($targetFile)) {
			if ($this->config->isGoOnIfFinalFileAlreadyExists()) {
				$this->logWarning(2, 'skipped');
				return;
			}

			throw new FileAlreadyExistsException($targetFile);
		}

		// use an options file to properly pass the UTF-8-encoded episode title to mkvmerge (not possible via command line on Windows)
		$optionsFile = $this->config->getTmpFolder() . $this->getFilename($this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $languages, 'json');

		if (file_exists($optionsFile)) {
			throw new FileAlreadyExistsException($optionsFile);
		}

		$this->tempFiles[] = $optionsFile;

		if (file_put_contents($optionsFile, json_encode(array('--title', $this->getEpisodeTitle($languages)))) === false) {
			throw new \RuntimeException(sprintf('Error writing file "%s".', $optionsFile));
		}

		$command = array($this->config->getMkvmerge());

		if ($this->config->isQuietCommands()) {
			$command[] = '--quiet';
		}

		$command[] = sprintf('@%s', $optionsFile);

		$command[] = '-o';
		$command[] = $targetFile;

		$command[] = '--chapter-language';
		$command[] = $mainLanguage;

		$command[] = '--generate-chapters';
		$command[] = 'when-appending';

		$command[] = '--generate-chapters-name-template';
		$command[] = sprintf('%s <NUM>', $this->settingDb->getChapterName($mainLanguage));

		$command[] = '--default-track';
		$command[] = '1';

		foreach ($languages as $language) {
			$command[] = '--language';
			$command[] = sprintf('1:%s', $language);

			foreach ($this->episode->getActs() as $act) {
				$actNumber = $act->getNumber();

				if ($language !== $mainLanguage) {
					$command[] = '--no-video';
				}

				$videoSync = $this->anomalyDb->getVideoSync($this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $actNumber, $language);
				if ($videoSync !== null) {
					$command[] = '--sync';
					$command[] = sprintf('0:%d', $videoSync);
				}

				$audioSync = $this->anomalyDb->getAudioSync($this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $actNumber, $language);
				if ($audioSync !== null) {
					$command[] = '--sync';
					$command[] = sprintf('1:%d', $audioSync);
				}

				if ($actNumber > 1) {
					$command[] = '+';
				}

				$actSourceFile = $this->config->getDownloadFolder() . $this->getFilename($this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $language, 'mp4', $actNumber);
				if (!file_exists($actSourceFile)) {
					throw new FileDoesNotExistException($actSourceFile);
				}

				$command[] = $actSourceFile;
			}
		}

		$exitCode = $this->call($command);

		if ($exitCode !== self::EXITCODE_SUCCESS && $exitCode !== self::EXITCODE_MKVMERGE_WARNINGS) {
			$this->abort($exitCode);
		}
	}

	public function cleanUp() {
		$this->logStep(1, 'cleaning up');

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

	public function applyArguments(InputInterface $input) {
		$language = $input->getArgument('language');
		$this->config->setLanguages(explode('+', $language));

		$season = $input->getArgument('season');
		if ((int) $season < 1) {
			throw new \RuntimeException(sprintf('Invalid season: %s', $season));
		}
		$this->config->setSeasonNumber((int) $season);

		$episode = $input->getArgument('episode');
		if (!empty($episode)) {
			$this->config->setEpisodeNumbers($episode);
		}

		if ($input->getOption('quiet') === true) {
			$this->out = new NullOutput();
		}
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
		$episodeTitleForFilename = preg_replace("/[^a-z0-9äöüßè()!%&_' .-]/iu", '', $episodeTitleForFilename);

		// fix filename on Windows
		if (defined('PHP_WINDOWS_VERSION_BUILD')) {
			$episodeTitleForFilename = iconv('UTF-8', 'ISO-8859-1//IGNORE', $episodeTitleForFilename);
		}

		return 'South Park ' . $this->getFilename($this->episode->getSeason()->getNumber(), $this->episode->getNumber(), $languages, 'mkv', null, $episodeTitleForFilename);
	}

	protected function getFilename($seasonNumber, $episodeNumber, $language = null, $extension = null, $actNumber = null, $title = null) {
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

	protected function call(array $command, $timeout = null, $async = false, &$process = null) {
		$process = new Process($command);
		$process->setTimeout($timeout);

		$this->logCall(2, $process->getCommandLine());

		$callback = function($type, $buffer) {
			$this->out->block($buffer, null, sprintf('fg=%s', $type === Process::ERR ? 'red' : 'yellow'), '    ');
		};

		if ($async) {
			$process->start($callback);
		} else {
			$process->run($callback);
		}

		return $process->getExitCode();
	}

	protected function abort($exitCode) {
		throw new \RuntimeException(sprintf(
				'External program aborted with an exit code of "%d" which seems to be an error. Will abort now.',
				$exitCode));
	}

	protected function logRaw($msg) {
		$this->out->writeln($msg);
	}

	protected function log($indentLevel, $textColor, $msg) {
		assert(is_int($indentLevel));
		assert(is_string($msg));

		$this->logRaw(sprintf('<fg=%s>%s%s</>', $textColor, str_repeat(' ', 2 * $indentLevel), $msg));
	}

	protected function logWarning($indentLevel, $msg, $section = '') {
		$this->log($indentLevel, 'yellow', $this->getLogSection($section) . $msg);
	}

	protected function logInfo($indentLevel, $msg, $section = '') {
		$this->log($indentLevel, 'cyan', $this->getLogSection($section) . $msg);
	}

	protected function logStep($indentLevel, $msg, $section = '') {
		$this->log($indentLevel, 'green', '> ' . $this->getLogSection($section) . $msg);
	}

	protected function logUrl($indentLevel, $msg) {
		if ($this->config->isPrintUrls()) {
			$this->log($indentLevel, 'magenta', '└ URL: ' . $msg);
		}
	}

	protected function logCall($indentLevel, $msg) {
		if ($this->config->isPrintCommandCalls()) {
			$this->log($indentLevel, 'magenta', '> ' . $msg);
		}
	}

	protected function getLogSection($section) {
		assert(is_string($section));

		if (empty($section)) {
			return '';
		}

		return sprintf('[<fg=white>%s</>] ', $section);
	}

	public function getAvailableSeasons($language) {
		$url = $this->settingDb->getAvailableSeasonsUrl($language);

		$this->logStep(0, 'fetching available seasons');
		$this->logUrl(1, $url);

		$domDocument = new \DOMDocument();
		libxml_use_internal_errors(true); // disable warnings while parsing
		$domDocument->loadHTML(file_get_contents($url));

		$seasons = array();

		// section#main_content nav a.season-filter[data-value]
		$seasonFilters = (new \DOMXPath($domDocument))->query('//section[@id="main_content"]//nav/a[contains(@class,"season-filter")][starts-with(@data-value,"season-")]');
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

			$this->logStep(0, sprintf('fetching episodes for S%02u%s', $seasonNumber, strtoupper($language)));
			$this->logUrl(1, $url);

			$data = json_decode(file_get_contents($url), true);

			foreach ($data['results'] as $result) {
				$episodeNumber = (int) substr($result['episodeNumber'], 2); // e.g. "0101" or "1809"

				$availability = $result['_availability']; // should be "true", but is "banned" for e.g. S14E05+S14E06, "huluplus" for e.g. S01E02EN, "beforepremiere" for e.g. S10E03DE
				if (!in_array($availability, array('true', 'huluplus'), true)) {
					// if this episode is requested explicitly, tell why it's not available
					if ($this->config->getSeasonNumber() === $seasonNumber && in_array($episodeNumber, $this->config->getEpisodeNumbers(), true)) {
						throw new \RuntimeException(sprintf('S%02uE%02u is not available: %s', $seasonNumber, $episodeNumber, $availability));
					}

					// otherwise just skip it
					continue;
				}

				if ($season->hasEpisode($episodeNumber)) {
					$episode = $season->getEpisode($episodeNumber);
				} else {
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
