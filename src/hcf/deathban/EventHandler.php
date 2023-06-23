<?php

namespace hcf\deathban;

use hcf\deathban\session\SessionManager;
use hcf\deathban\util\ChunkLoadPromise;
use hcf\deathban\util\StringUtils;
use hcf\deathban\util\Vector3Utils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\ClosureTask;
use HCF\event\pocketmine\PlayerInvincibilityEvent;

class EventHandler implements Listener
{

    public function handlerJoin(PlayerJoinEvent $ev): void {
        $p = $ev->getPlayer();
        $session = SessionManager::getInstance()->getSession($p);

        if ($session !== null) {
            if($session->isInDeathBan()) {
                $p = $ev->getPlayer();
                $spawn = DeathBan::$world->getSpawnLocation();
                ChunkLoadPromise::create(DeathBan::$world, $spawn->x >> 4, $spawn->z >> 4, function() use ($p): void {
                    $p->sendMessage(TextFormat::colorize("§r§4Welcome to the Deathban"));
                    $p->teleport(DeathBan::$world->getSafeSpawn());
                    # $p->sendMessage("Give deathban kit");
                });
            }
            return;
        }
        SessionManager::getInstance()->createSession($p);
    }

    public function handlerItemDrop(PlayerDropItemEvent $ev): void {
        $p = $ev->getPlayer();
        $session = SessionManager::getInstance()->getSession($p);
        if(!$session->isInDeathBan()) return;
        $ev->cancel();
    }

    public function handlerDeathEvent(PlayerDeathEvent $ev): void {
        $p = $ev->getPlayer();
        $session = SessionManager::getInstance()->getSession($p);
        $lastDamage = $p->getLastDamageCause();
        if($p->hasPermission("deathban.bypass")) return;
        if(!$session->isInDeathBan()) {
            $session->setDeathBan();
            if($lastDamage instanceof EntityDamageByEntityEvent) {
                $dmg = $lastDamage->getDamager();
                if($dmg !== null) {
                    $session->setKiller($dmg->getNameTag());
                }
            }
            $duration = DeathBan::getInstance()->getConfig()->get("duration");
            foreach(DeathBan::getInstance()->getConfig()->get("weighted") as $name => $length) {
                if(!$p->hasPermission("deathban.$name")) continue;
                if($length < $duration) {
                    $duration = $length;
                }
            }
            $session->setDeathPosition(Vector3Utils::toString($p->getPosition()->floor()));
            $session->setDeathBanTime(time() + $duration);
        } else {
            if(!$lastDamage instanceof EntityDamageByEntityEvent) return;
            $p = $lastDamage->getDamager();
            $session = SessionManager::getInstance()->getSession($p);
            if(!$p instanceof Player) return;
            $session->reduceDeathBanTime($p, DeathBan::getInstance()->getConfig()->get("deathbanReduction"));
        }
    }

    public function handlerRespawn(PlayerRespawnEvent $ev) {
        $p = $ev->getPlayer();
        if($p->hasPermission("deathban.bypass")) return;
        $ev->setRespawnPosition(DeathBan::$world->getSpawnLocation());
        $p->sendMessage(TextFormat::colorize("§r§4Welcome to the Deathban"));
        # $p->sendMessage("Give deathban kit");
    }

    public function handlerInteract(PlayerInteractEvent $ev): void {
        if($ev->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) return;
        if(($p = $ev->getPlayer())->getWorld() !== DeathBan::$world) return;
        $clickPos = $ev->getBlock()->getPosition();
        if($clickPos->equals(DeathBan::$useLifeSign)) {
            $session = SessionManager::getInstance()->getSession($p);
            $lives = $session->getLives();
            if($lives < 1) {
                $p->sendMessage(TextFormat::colorize("&cYou have no lives! You can get some more at &ostore.deryxmc.net"));
                return;
            }
            $session->removeLives(1);
            $session->removeDeathBan($p);
        } elseif($clickPos->equals(DeathBan::$kitSign)) {
            DeathBan::giveKit($p);
        }
    }

    public function onCommandPreProcess(PlayerCommandPreprocessEvent $ev): void {
        $p = $ev->getPlayer();
        $session = SessionManager::getInstance()->getSession($p);
        if(!$session->isInDeathBan() || ($m = $ev->getMessage())[0] !== "/") return;
        $c = StringUtils::sanitizeCommand($m);
        foreach(DeathBan::getInstance()->getConfig()->get("allowedCommands") as $cmd) {
            if(StringUtils::startsWith($c, $cmd)) {
                return;
            }
        }
        $p->sendMessage(TextFormat::colorize("&cYou can't run commands whilst deathbanned!"));
        $ev->cancel();
    }
}