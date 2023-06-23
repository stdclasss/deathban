<?php

namespace hcf\deathban\command;

use hcf\deathban\session\SessionManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class LivesCommand extends Command
{

    public function __construct()
    {
        parent::__construct("lives", "Use to manage your lives");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!isset($args[0]) && !$sender instanceof Player) {
            $sender->sendMessage(TextFormat::colorize("&cFor use that command you need use in game!"));
            return;
        }

        if (isset($args[0])) {
            switch ($args[0]) {
                case 'give':
                    if (!$sender->hasPermission("deathban.command.admin")) return;
                    if (count($args) < 3) {
                        $sender->sendMessage(TextFormat::colorize("&cYou need use: /lives give [player] [cant]"));
                        return;
                    }
                    $player = Server::getInstance()->getPlayerExact($args[1]);

                    if ($player === null) {
                        $sender->sendMessage("Player not found!");
                        return;
                    }

                    $session = SessionManager::getInstance()->getSession($player);

                    if ($session === null) {
                        $session = SessionManager::getInstance()->createSession($player);
                    }

                    $amt = $args[2] ?? 1;
                    if ($amt <= 0) {
                        $sender->sendMessage(TextFormat::colorize("&cUse a valid number!"));
                        return;
                    }
                    $session->addLives($amt);
                    $sender->sendMessage(TextFormat::colorize("&a" . $amt . "x lives added to " . $player->getName() . " successfully!"));
                    break;
                case 'remove':
                    if (!$sender->hasPermission("deathban.command.admin")) return;
                    if (count($args) < 3) {
                        $sender->sendMessage(TextFormat::colorize("&cYou need use: /lives remove [player] [cant]"));
                        return;
                    }
                    $player = Server::getInstance()->getOfflinePlayer($args[1]);

                    if (!$player->hasPlayedBefore()) {
                        $sender->sendMessage(TextFormat::colorize("&cUse a valid player username!"));
                        return;
                    }

                    $session = SessionManager::getInstance()->getSession($player);

                    if ($session === null) {
                        $session = SessionManager::getInstance()->createSession($player);
                    }

                    $amt = $args[2] ?? 1;
                    if ($amt <= 0 or $amt > $session->getLives()) {
                        $sender->sendMessage(TextFormat::colorize("&cUse a valid amount!"));
                        return;
                    }
                    $session->removeLives($amt);
                    $sender->sendMessage(TextFormat::colorize("&a" . $amt . "x lives removed from " . $player->getName() . " successfully!"));
                    break;
            }
        }

        $target = $args[0] ?? $sender->getName();

        $player = Server::getInstance()->getPlayerExact($target);

        if ($player === null) {
            $sender->sendMessage("Player not found!");
            return;
        }

        $session = SessionManager::getInstance()->getSession($player);

        if ($session === null) {
            $session = SessionManager::getInstance()->createSession($player);
        }

        $sender->sendMessage(TextFormat::colorize("&e" . $player->getName() . "'s Lives: &c" . ($session === null ? 0 : $session->getLives())));
    }
}
