<?php

namespace hcf\deathban;

use hcf\deathban\command\KitCommand;
use hcf\deathban\command\LivesCommand;
use hcf\deathban\command\ReviveCommand;
use hcf\deathban\session\SessionManager;
use hcf\deathban\util\StringUtils;
use hcf\deathban\util\TimeUtils;
use hcf\deathban\util\Vector3Utils;
use JsonException;
use pocketmine\block\tile\Sign;
use pocketmine\block\tile\Tile;
use pocketmine\block\tile\TileFactory;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\PermissionManager;
use pocketmine\permission\PermissionParser;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginDescription;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;

class DeathBan extends PluginBase
{
    use SingletonTrait;

    public static array $kit;

    public static World $world;

    public static array $signs;

    public static Vector3 $useLifeSign;

    public static Vector3 $kitSign;

    protected function onEnable(): void {
        $this->saveDefaultConfig();

        SessionManager::getInstance()->loadAll();

        $this->getServer()->getPluginManager()->registerEvents(new EventHandler(), $this);

        $this->getServer()->getCommandMap()->register("lives", new LivesCommand());
        $this->getServer()->getCommandMap()->register("revive", new ReviveCommand());
        $this->getServer()->getCommandMap()->register("deathbankit", new KitCommand());

        $perms = [
            "deathban.bypass" => [
                "default" => "false",
                "description" => "Allows a player to bypass deathban"
            ],
            "deathban.command.admin" => [
                "default" => "op",
                "description" => "Allows a player to bypass deathban"
            ]
        ];

        foreach($this->getConfig()->get("weighted") as $name => $length) {
            $perms["deathban.$name"] = [
                "default" => "false",
                "description" => "Allows a player to have the $name deathban duration"
            ];
        }

        $this->usePermissions($perms);

        $conf = $this->getConfig();
        $lName = $this->getConfig()->get("world");
        $wMan = $this->getServer()->getWorldManager();
        if($wMan->loadWorld($lName)) {
            self::$world = $wMan->getWorldByName($lName);
        } else {
            throw new \RuntimeException("Unable to load DeathBan world");
        }
        self::$signs[] = [self::$kitSign = Vector3Utils::fromString($conf->getNested("kitSign.pos")), implode("\n", $conf->getNested("kitSign.format"))];
        self::$signs[] = [self::$useLifeSign = Vector3Utils::fromString($conf->getNested("useLifeSign.pos"))->floor(), implode("\n", $conf->getNested("useLifeSign.format"))];
        self::$signs[] = [Vector3Utils::fromString($conf->getNested("killedBySign.pos"))->floor(), implode("\n", $conf->getNested("killedBySign.format"))];
        self::$signs[] = [Vector3Utils::fromString($conf->getNested("timeLeftSign.pos"))->floor(), implode("\n", $conf->getNested("timeLeftSign.format"))];
        self::$signs[] = [Vector3Utils::fromString($conf->getNested("deathLocationSign.pos"))->floor(), implode("\n", $conf->getNested("deathLocationSign.format"))];

        self::$kit[] = $conf->getNested("kit.armor");
        self::$kit[] = $conf->getNested("kit.items");

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            foreach(self::$world->getPlayers() as $player) {
                if(!$player->spawned) continue;
                if (!$player->isOnline()) return;
                $session = SessionManager::getInstance()->getSession($player);
                if(!$session->isInDeathBan()) continue;
                $this->update($player);
            }
        }), 10);
    }

    protected function onLoad(): void {
        self::setInstance($this);
    }

    /**
     * @throws JsonException
     */
    protected function onDisable(): void
    {
        SessionManager::getInstance()->saveAll();
        $this->saveKit();
    }

    /**
     * @param array $permissions
     */
    final protected function usePermissions(array $permissions): void {
        $refClass = new \ReflectionClass(PluginDescription::class);
        $refProp = $refClass->getProperty("permissions");
        $refProp->setAccessible(true);

        $permissions = PermissionParser::loadPermissions($permissions);

        $desc = $this->getDescription();
        $pluginPerms = $refProp->getValue($desc);
        $permManager = PermissionManager::getInstance();

        $opROOT = $permManager->getPermission(DefaultPermissions::ROOT_OPERATOR);
        $evROOT = $permManager->getPermission(DefaultPermissions::ROOT_OPERATOR);

        foreach($permissions as $default => $_permissions) {
            foreach($_permissions as $permission) {
                switch($default){
                    case PermissionParser::DEFAULT_OP:
                        $opROOT->addChild($permission->getName(), true);
                        break;
                    case PermissionParser::DEFAULT_NOT_OP:
                        $evROOT->addChild($permission->getName(), true);
                        $opROOT->addChild($permission->getName(), false);
                        break;
                    case PermissionParser::DEFAULT_TRUE:
                        $evROOT->addChild($permission->getName(), true);
                        break;
                }
                $pluginPerms[$default][] = $permission;
                $permManager->addPermission($permission);
                $this->getLogger()->debug("Registered permission: " . $permission->getName());
            }
        }

        $refProp->setValue($desc, $pluginPerms);
    }

    public function update(Player $player): void
    {
        $session = SessionManager::getInstance()->getSession($player);
        $args = [
            "lives" => $session->getLives(),
            "killer" =>$session->getKiller(),
            "time" => TimeUtils::secsToMMSS($time = $session->getDeathBanTimeLeft()),
            "death_pos" => $session->getDeathPosition(),
        ];
        if($time < 1) {
            $session->removeDeathBan($player);
            return;
        }
        foreach(DeathBan::$signs as $sign) {
            [$signPos, $text] = $sign;
            $pk = new BlockActorDataPacket();
            $pk->blockPosition = BlockPosition::fromVector3($signPos);
            $nbt = new CompoundTag();
            $nbt->setString(Tile::TAG_ID, TileFactory::getInstance()->getSaveId(Sign::class));
            $nbt->setInt(Tile::TAG_X, $signPos->x);
            $nbt->setInt(Tile::TAG_Y, $signPos->y);
            $nbt->setInt(Tile::TAG_Z, $signPos->z);
            $nbt->setString(Sign::TAG_TEXT_BLOB, TextFormat::colorize(StringUtils::substituteString($text, $args)));
            $pk->nbt = new CacheableNbt($nbt);
            $player->getNetworkSession()->sendDataPacket($pk);
        }
    }

    public static function giveKit(Player $player) {
        $session = SessionManager::getInstance()->getSession($player);

        if (($cooldown = $session->getKiCooldown()) > time()) {
            $player->sendMessage(TextFormat::colorize("&cYou have deathban kit cooldown, for use you need wait " . ($cooldown - time())) . " seconds!");
            return;
        }

        $armor = [];
        foreach (self::$kit[0] as $slot => $item) {
            $armor[$slot] = Item::jsonDeserialize($item);
        }
        $player->getArmorInventory()->setContents($armor);

        $items = [];
        foreach (self::$kit[1] as $slot => $item) {
            $items[$slot] = Item::jsonDeserialize($item);
        }
        $player->getInventory()->setContents($items);
        $session->setKiCooldown(time() + 10);
    }

    public function saveKit(): void {
        $this->getConfig()->setNested("kit.armor", self::$kit[0]);
        $this->getConfig()->setNested("kit.items", self::$kit[1]);
        $this->getConfig()->save();
    }

}