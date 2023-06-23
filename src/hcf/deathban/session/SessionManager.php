<?php

declare(strict_types=1);

namespace hcf\deathban\session;

use hcf\deathban\DeathBan;
use JsonException;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;

final class SessionManager {
    use SingletonTrait;

    /** @var Session[] */
    private array $sessions = [];

    public function getSession(Player $player): ?Session {
        return $this->sessions[$player->getXuid()] ?? null;
    }

    public function createSession(Player $player): Session {
        $this->sessions[$player->getXuid()] = $session = new Session();
        return $session;
    }

    /**
     * @throws JsonException
     */
    public function saveAll(): void {
        $config = new Config(DeathBan::getInstance()->getDataFolder() . 'players.json', Config::JSON);
        $data = [];

        foreach ($this->sessions as $xuid => $session) {
            $data[$xuid] = $session->serializeData();
        }
        $config->setAll($data);
        $config->save();
    }

    public function loadAll(): void {
        $config = new Config(DeathBan::getInstance()->getDataFolder() . 'players.json', Config::JSON);

        foreach ($config->getAll() as $xuid => $data) {
            $this->sessions[$xuid] = new Session((int) $data['lives'], $data['deathban'], $data['deathPosition'], $data['killer'], $data['deathbanTime']);
        }
    }
}