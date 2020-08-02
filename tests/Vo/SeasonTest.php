<?php

namespace App\Tests\Vo;

use App\Vo\Episode;
use App\Vo\Season;
use PHPUnit\Framework\TestCase;

/**
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011-2020 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class SeasonTest extends TestCase {

	public function testGetNumber() {
		$season = new Season(15);
		$this->assertSame(15, $season->getNumber());
	}

	public function testAddHasGetEpisode() {
		$season = new Season(15);
		$this->assertFalse($season->hasEpisode(1));

		$this->createEpisode($season, 1);
		$this->assertTrue($season->hasEpisode(1));
	}

	public function testAddEpisode_twice() {
		$season = new Season(15);

		$episode1 = $this->createEpisode($season, 1);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Episode 1 for S15 already exists.');
		$season->addEpisode($episode1);
	}

	public function testGetEpisode_nonexistent() {
		$season = new Season(15);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Episode 1 for S15 does not exist.');
		$season->getEpisode(1);
	}

	public function testGetEpisodes_nonexistent() {
		$season = new Season(15);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Episode 1 for S15 does not exist.');
		$season->getEpisodes(array(1));
	}

	public function testGetEpisodes_none() {
		$season = new Season(15);

		$this->createEpisode($season, 1);
		$this->assertSame(array(), $season->getEpisodes(array()));
	}

	public function testGetEpisodes() {
		$season = new Season(15);

		$this->createEpisode($season, 1);
		$episode2 = $this->createEpisode($season, 2);

		$this->assertSame(array($episode2), $season->getEpisodes(array(2)));
	}

	public function testGetAllEpisodes() {
		$season = new Season(15);
		$this->assertSame(array(), $season->getAllEpisodes());

		$episode1 = $this->createEpisode($season, 1);
		$this->assertSame(array($episode1), $season->getAllEpisodes());
	}

	/**
	 * @param Season $season
	 * @param int $number Episode number.
	 * @return Episode
	 */
	private function createEpisode(Season $season, $number) {
		$episode = new Episode($season);
		$episode->setNumber($number);
		$season->addEpisode($episode);

		return $episode;
	}

}
