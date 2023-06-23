<?php

namespace hcf\deathban\command;

use hcf\deathban\session\SessionManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class ReviveCommand extends Command
{

    public function __construct()
    {
        parent::__construct("revive", "Use to revive a other players in deathban");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$sender instanceof Player) return;

        if(!isset($args[0])){
            $sender->sendMessage(TextFormat::colorize("&cYou need use: /revive [player]"));
            return;
        }

        $player = Server::getInstance()->getPlayerByPrefix($args[0]);

        if ($player == null) {
            $sender->sendMessage(TextFormat::colorize("&cPlayer is not online!"));
            return;
        }

        $session = SessionManager::getInstance()->getSession($player);
        $sessionSender = SessionManager::getInstance()->getSession($sender);

        if (!$session->isInDeathBan()) {
            $sender->sendMessage(TextFormat::colorize("&cPlayer is not in deathban!"));
            return;
        }

        if ($sessionSender->getLives() < 1) {
            $sender->sendMessage(TextFormat::colorize("&cYou dont have lives!"));
            return;
        }

        $player->sendMessage(TextFormat::colorize("&a" . $sender->getName() . " had revived you from deathban!"));

        $session->removeDeathBan($player);
        $sessionSender->removeLives(1);
    }


}