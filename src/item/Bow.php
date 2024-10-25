<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\item;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\BowShootSound;
use function min;

class Bow extends Tool implements Releasable{

    public function getFuelTime() : int{
        return 200;
    }

    public function getMaxDurability() : int{
        return 385;
    }

    /**
     * @param Player $player
     * @param array $returnedItems
     * @return ItemUseResult
     */
    public function onReleaseUsing(Player $player, array &$returnedItems): ItemUseResult {
        $p = $player->getItemUseDuration() / 20;
        $baseForce = min((($p ** 2) + $p * 2) / 3, 1);
        $location = $player->getLocation();
        $yaw = $location->getYaw();
        $entity = new Arrow(Location::fromObject(
            new Vector3($location->getX(), $location->getY() + $player->getEyeHeight(), $location->getZ()),
            $player->getWorld(),
            ($yaw > 180 ? 360 : 0) - $yaw,
            -$location->getPitch()
        ), $player, $baseForce >= 1);
        $entity->setMotion($player->getDirectionVector());
        if (($punchLevel = $this->getEnchantmentLevel(VanillaEnchantments::PUNCH())) > 0) {
            $entity->setPunchKnockback($punchLevel);
        }
        ($ev = new EntityShootBowEvent($player, $this, $entity, $baseForce * 3))->call();
        if (!$ev->isCancelled()) {
            $entity = $ev->getProjectile();
            $entity->setMotion($entity->getMotion()->multiply($ev->getForce()));
            if ($entity instanceof Projectile) {
                $projectileEv = new ProjectileLaunchEvent($entity);
                $projectileEv->call();
                if ($projectileEv->isCancelled()) {
                    $ev->getProjectile()->flagForDespawn();
                    return ItemUseResult::FAIL();
                }
                $ev->getProjectile()->spawnToAll();
                $location->getWorld()->addSound($location, new BowShootSound());
            } else {
                $entity->spawnToAll();
            }
            $this->applyDamage(1);
        } else {
            $entity->flagForDespawn();
            return ItemUseResult::FAIL();
        }
        return ItemUseResult::SUCCESS();
    }

    public function canStartUsingItem(Player $player) : bool{
        return true;
    }

}
