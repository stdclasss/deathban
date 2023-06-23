<?php

declare(strict_types=1);

namespace hcf\deathban\session;

use pocketmine\player\Player;

final class Session {

    public function __construct(
        private int $lives = 10,
        private bool $deathban = false,
        private string $deathPosition =  "N/A",
        private string $killer = "N/A",
        private int $deathbanTime = 0,
        private int $kiCooldown = 0,
    ) {}

    public function getLives(): int {
        return $this->lives;
    }

    public function isInDeathBan(): bool {
        return $this->deathban;
    }

    public function setDeathBan(): void {
        $this->deathban = true;
    }

    public function removeDeathBan(Player $player): void {
        $this->deathban = false;
        $this->deathPosition = "N/A";
        $this->killer = "N/A";
        $this->deathbanTime = 0;
        $player->teleport($player->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCraftingGrid()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->getEffects()->clear();
    }

    public function serializeData(): array {
        return [
            'lives' => $this->lives,
            'deathban' => $this->deathban,
            'killer' => $this->killer,
            'deathbanTime' => $this->deathbanTime,
            'deathPosition' => $this->deathPosition,
        ];
    }

    /**
     * @param string $deathPosition
     */
    public function setDeathPosition(string $deathPosition): void
    {
        $this->deathPosition = $deathPosition;
    }

    /**
     * @return string
     */
    public function getDeathPosition(): string
    {
        return $this->deathPosition;
    }

    /**
     * @return string
     */
    public function getKiller(): string
    {
        return $this->killer;
    }

    /**
     * @param string $killer
     */
    public function setKiller(string $killer): void
    {
        $this->killer = $killer;
    }

    /**
     * @return int
     */
    public function getDeathBanTime(): int
    {
        return $this->deathbanTime;
    }

    /**
     * @param int $deathbanTime
     */
    public function setDeathBanTime(int $deathbanTime): void
    {
        $this->deathbanTime = $deathbanTime;
    }

    public function reduceDeathBanTime(Player $player, int $amt): void {
        $this->setDeathBanTime(time() + ($this->getDeathBanTimeLeft() - $amt));
        if($this->deathbanTime <= time()) {
            $this->removeDeathBan($player);
        }
    }

    public function getDeathBanTimeLeft(): int {
        return max(0, $this->getSignedDeathBanTime());
    }

    public function getSignedDeathBanTime(): int {
        if ($this->deathbanTime == 0) return 0;
        return $this->deathbanTime - time();
    }

    /**
     * @param int $lives
     */
    public function addLives(int $lives): void
    {
        $this->lives += $lives;
    }

    /**
     * @param int $lives
     */
    public function removeLives(int $lives): void
    {
        $this->lives -= $lives;
    }

    /**
     * @return int
     */
    public function getKiCooldown(): int
    {
        return $this->kiCooldown;
    }

    /**
     * @param int $kiCooldown
     */
    public function setKiCooldown(int $kiCooldown): void
    {
        $this->kiCooldown = $kiCooldown;
    }
}