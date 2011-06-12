<?php

require_once(__DIR__.'/XmlDatabase.php');

/**
 * Handling of players database.
 * This file is part of the South Park Downloader package.
 *
 * @author Christian Raue <christian.raue@gmail.com>
 * @copyright 2011 Christian Raue
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 License
 */
class PlayerDatabase extends XmlDatabase {

	public function getPlayers() {
		return $this->getData();
	}

	public function findPlayer($playerUrl) {
		if (empty($playerUrl)) {
			return $this->findLatestPlayer();
		}

		$playerData = $this->getPlayers()->xpath(sprintf('/players/player[swfurl="%s"]', $playerUrl));
		if (empty($playerData)) {
			throw new RuntimeException(sprintf(
					'Invalid or unknown player URL. Verify "%s" and update "%s" if necessary.',
					$playerUrl,
					$this->source));
		}
		return $playerData[0];
	}

	public function findLatestPlayer() {
		$playerData = $this->getPlayers()->xpath('/players/player[@latest="true"]');
		if (empty($playerData)) {
			throw new RuntimeException(sprintf(
					'There is no player marked as "latest". Please do so in "%s".',
					$this->source));
		}
		return $playerData[0];
	}

}
