<?php
declare(strict_types = 1);

namespace Ifera\ScoreHud\libs\jackmd\scorefactory;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use BadFunctionCallException;
use OutOfBoundsException;
use function array_map;
use function array_values;

class ScoreFactory{

	private const OBJECTIVE_NAME = "objective";
	private const CRITERIA_NAME = "dummy";

	private const MIN_LINES = 1;
	private const MAX_LINES = 15;

	public const SORT_ASCENDING = 0;
	public const SORT_DESCENDING = 1;

	public const SLOT_LIST = "list";
	public const SLOT_SIDEBAR = "sidebar";
	public const SLOT_BELOW_NAME = "belowname";

	/** @var ScoreCache[] */
	private static array $cache = [];

	/**
	 * Adds a Scoreboard to the player if he doesn't have one.
	 * Can also be used to update a scoreboard.
	 */
	public static function setScore(Player $player, string $displayName, int $slotOrder = self::SORT_ASCENDING, string $displaySlot = self::SLOT_SIDEBAR, string $objectiveName = self::OBJECTIVE_NAME, string $criteriaName = self::CRITERIA_NAME): void{
		$pk = new SetDisplayObjectivePacket();
		$pk->displaySlot = $displaySlot;
		$pk->objectiveName = $objectiveName;
		$pk->displayName = $displayName;
		$pk->criteriaName = $criteriaName;
		$pk->sortOrder = $slotOrder;

		self::$cache[$player->getUniqueId()->getBytes()] = ScoreCache::init($player, $objectiveName, $pk);
	}

	/**
	 * Removes a scoreboard from the player specified.
	 */
	public static function removeScore(Player $player): void{
		$objectiveName = isset(self::$cache[$player->getUniqueId()->getBytes()]) ? self::$cache[$player->getUniqueId()->getBytes()]->getObjective() : self::OBJECTIVE_NAME;

		$pk = new RemoveObjectivePacket();
		$pk->objectiveName = $objectiveName;
		//$player->sendDataPacket($pk);
		$player->getNetworkSession()->sendDataPacket($pk);

		unset(self::$cache[$player->getUniqueId()->getBytes()]);
	}

	/**
	 * @return Player[]
	 */
	public static function getActivePlayers(): array{
		return array_values(array_map(function(ScoreCache $cache){
			return $cache->getPlayer();
		}, self::$cache));
	}

	/**
	 * Returns true or false if a player has a scoreboard or not.
	 */
	public static function hasScore(Player $player): bool{
		return isset(self::$cache[$player->getUniqueId()->getBytes()]);
	}

	/**
	 * Set a message at the line specified to the players scoreboard.
	 */
	public static function setScoreLine(Player $player, int $line, string $message, int $type = ScorePacketEntry::TYPE_FAKE_PLAYER): void{
		if(!isset(self::$cache[$player->getUniqueId()->getBytes()])){
			throw new BadFunctionCallException("Cannot set a score to a player without a scoreboard. Please call ScoreFactory::setScore() beforehand.");
		}

		if($line < self::MIN_LINES || $line > self::MAX_LINES){
			throw new OutOfBoundsException("Line: $line is out of range, expected value between " . self::MIN_LINES . " and " . self::MAX_LINES);
		}

		$cache = self::$cache[$player->getUniqueId()->getBytes()];

		$entry = new ScorePacketEntry();
		$entry->objectiveName = $cache->getObjective();
		$entry->type = $type;
		$entry->customName = $message;
		$entry->score = $line;
		$entry->scoreboardId = $line;

		$cache->setEntry($line, $entry);
	}

	/**
	 * Send scoreboard to the player by first removing the existing scoreboard, creating a new one
	 * and then sending its lines.
	 */
	public static function send(Player $player, bool $sendObjective = true, bool $sendLines = true, bool $removeObjective = true){
		if(!isset(self::$cache[$player->getUniqueId()->getBytes()])){
			throw new BadFunctionCallException("Cannot send score to a player without a scoreboard. Please call ScoreFactory::setScore() beforehand.");
		}

		$cache = self::$cache[$player->getUniqueId()->getBytes()];

		if($removeObjective) self::removeScore($player);
		if($sendObjective) $player->getNetworkSession()->sendDataPacket($cache->getObjectivePacket());

		if($sendLines){
			$pk = new SetScorePacket();
			$pk->type = $pk::TYPE_CHANGE;
			$pk->entries = $cache->getEntries();
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}
}