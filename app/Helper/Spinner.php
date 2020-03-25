<?php

namespace App\Helper;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A console spinner to show activity during long-running processes.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class Spinner {

	private const CHARS = '|/-\\';

	/**
	 * @var int
	 */
	private $numChars;

	/**
	 * @var OutputInterface
	 */
	private $output;

	/**
	 * @var ProgressBar|null
	 */
	private $bar;

	/**
	 * @var int
	 */
	private $step;

	public function __construct(OutputInterface $output) {
		$this->numChars = strlen(self::CHARS);
		$this->output = $output;

		$this->create();
	}

	public function create() {
		$this->step = 0;

		$this->bar = new ProgressBar($this->output);
		$this->bar->setBarCharacter('');
		$this->bar->setFormat('    %bar%');
		$this->bar->setBarWidth(1);
	}

	public function advance($step = 1) {
		$this->step += $step;
		$this->getBar()->setProgressCharacter(self::CHARS[$this->step % $this->numChars]);
		$this->getBar()->advance($step);
	}

	public function hide() {
		$this->getBar()->clear();
	}

	public function show() {
		$this->getBar()->display();
	}

	public function destroy() {
		$this->hide();
		$this->bar = null;
	}

	private function getBar() {
		if ($this->bar === null) {
			throw new \RuntimeException('The spinner needs to be created first.');
		}

		return $this->bar;
	}

}
