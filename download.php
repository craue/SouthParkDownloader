#!/usr/bin/env php
<?php

/**
 * Main script.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */

require __DIR__.'/vendor/autoload.php';
require __DIR__.'/config.php';

use App\Config\Config;
use App\SouthParkDownloaderApplication;
use Symfony\Component\Console\Output\ConsoleOutput;

$out = new ConsoleOutput();

try {
	$config = new Config();
	$config->setTmpFolder($tmp);
	$config->setDownloadFolder($download);
	$config->setOutputFolder($output);
	$config->setFfmpeg($ffmpeg);
	$config->setMkvmerge($mkvmerge);
	$config->setDownloadTimeoutInSeconds(2 * 60); // no command line option yet
	$config->setVerifyChecksums(true); // no command line option yet
	$config->setUpdateChecksumOnSuccessfulDownload(true); // no command line option yet
	$config->setPrintUrls(false); // no command line option yet
	$config->setPrintCommandCalls(false); // no command line option yet
	$config->setQuietCommands(true); // no command line option yet
	$config->setRemoveTempFiles(true); // no command line option yet
	$config->setRemoveDownloadedFiles(false); // no command line option yet

	$app = new SouthParkDownloaderApplication($config);
	$app->setCatchExceptions(false);
	$app->run(null, $out);
} catch (\Exception $e) {
	$out->getErrorOutput()->writeln(sprintf('<error>%s</>', $e->getMessage()));
}
