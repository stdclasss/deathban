<?php

namespace hcf\deathban\command;

use hcf\deathban\DeathBan;
use hcf\deathban\session\SessionManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class KitCommand extends Command
{

    public function __construct()
    {
        parent::__construct("deathbankit", "Use to edit the deathban kit");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$sender instanceof Player) return;

        $armor = [];
        foreach ($sender->getArmorInventory()->getContents() as $slot => $item) {
            $armor[$slot] = $item->jsonSerialize();
        }

        DeathBan::$kit[0] = $armor;

        $items = [];
        foreach ($sender->getInventory()->getContents() as $slot => $item) {
            $items[$slot] = $item->jsonSerialize();
        }

        DeathBan::$kit[1] = $items;
    }
}