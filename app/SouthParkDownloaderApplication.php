<?php

namespace App;

use App\Command\DownloadCommand;
use App\Config\Config;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class SouthParkDownloaderApplication extends Application {

	/**
	 * @param Config $config
	 */
	public function __construct(Config $config) {
		parent::__construct('South Park Downloader');

		$command = new DownloadCommand($config);
		$this->add($command);
		$this->setDefaultCommand($command->getName(), true);

		$this->removeOptions(['version', 'no-interaction', 'verbose']);
	}

	public function run(InputInterface $input = null, OutputInterface $output = null) {
		if ($input === null) {
			$input = $this->createInputWithHelpArgument();
		}

		return parent::run($input, $output);
	}

	/**
	 * @return InputInterface
	 */
	protected function createInputWithHelpArgument() {
		$argv = $_SERVER['argv'];

		$input = new ArgvInput($argv);

		if ($input->getFirstArgument() === null) {
			$argv[] = '-h';
			$input = new ArgvInput($argv);
		}

		return $input;
	}

	/**
	 * @param string[] $names
	 */
	protected function removeOptions(array $names) {
		$options = $this->getDefinition()->getOptions();

		foreach ($names as $name) {
			unset($options[$name]);
		}

		$this->getDefinition()->setOptions($options);
	}

}
