<?php

declare(strict_types=1);

namespace enchantments;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\inventory\InventoryPickupArrowEvent;
use pocketmine\entity\projectile\Arrow;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\Item;

class EnchantmentsLoader extends PluginBase implements Listener{

	public function onEnable(){
		Enchantment::registerEnchantment(new Enchantment(Enchantment::SHARPNESS, 'Sharpness', Enchantment::RARITY_UNCOMMON, Enchantment::SLOT_SWORD | Enchantment::SLOT_AXE, 5));
		Enchantment::registerEnchantment(new Enchantment(Enchantment::FORTUNE, 'Fortune', Enchantment::RARITY_UNCOMMON, Enchantment::SLOT_PICKAXE | Enchantment::SLOT_AXE | Enchantment::SLOT_SHOVEL, 3));
		Enchantment::registerEnchantment(new Enchantment(Enchantment::KNOCKBACK, 'Knockback', Enchantment::RARITY_UNCOMMON, Enchantment::SLOT_SWORD, 2));
		Enchantment::registerEnchantment(new Enchantment(Enchantment::POWER, 'Power', Enchantment::RARITY_UNCOMMON, Enchantment::SLOT_BOW, 5));
		Enchantment::registerEnchantment(new Enchantment(Enchantment::PUNCH, 'Punch',  Enchantment::RARITY_UNCOMMON, Enchantment::SLOT_BOW, 2));
		Enchantment::registerEnchantment(new Enchantment(Enchantment::INFINITY, 'Infinity', Enchantment::RARITY_UNCOMMON, Enchantment::SLOT_BOW, 1));
		Enchantment::registerEnchantment(new Enchantment(Enchantment::FIRE_ASPECT, 'Fire aspect', Enchantment::RARITY_UNCOMMON, Enchantment::SLOT_SWORD, 2));
		//Убрано из-за одного бага (при отмененном событии все равно может немного поджигать).
		//Enchantment::registerEnchantment(new Enchantment(Enchantment::FLAME, 'Flame', Enchantment::RARITY_UNCOMMON, Enchantment::SLOT_BOW, 1)); 

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	/**
	 * @param EntityShootBowEvent $event
	 *
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 */
	public function onEntityShootBow(EntityShootBowEvent $event): void{
		$entity = $event->getEntity();

		if($entity instanceof Player){
			$projectile = $event->getProjectile();
			$bow = $event->getBow();
			
			if($bow->hasEnchantment(Enchantment::INFINITY)){
				if(!($entity->getGamemode() % 2)){
					$entity->getInventory()->addItem(Item::get(262));
				}

				$projectile->namedtag->setShort('isInfinity', 1);
			}

			$level = $bow->getEnchantmentLevel(Enchantment::POWER);

			if($level > 0){
				$projectile->namedtag->setShort('PowerEnch', $level);
			}

			$level = $bow->getEnchantmentLevel(Enchantment::PUNCH);

			if($level > 0){
				$projectile->namedtag->setShort('PunchEnch', $level);
			}
		}
	}
	
	/**
	 * @param InventoryPickupArrowEvent $event
	 *
	 * @priority HIGHEST
	 * @ignoreCancelled false
	 */
	public function onInventoryPickupArrow(InventoryPickupArrowEvent $event): void{
		$entity = $event->getArrow();

		if($entity->namedtag->getShort('isInfinity', 0)){
			$event->setCancelled();
			$entity->close();
		}
	}

	/**
	 * @param BlockBreakEvent $event
	 *
	 * @priority LOWEST
	 * @ignoreCancelled true
	 */
	public function onBlockBreak(BlockBreakEvent $event): void{
		$drops = $event->getDrops();

		if(count($drops) > 0){
			$player = $event->getPlayer();
			$level = $player->getInventory()->getItemInHand()->getEnchantmentLevel(Enchantment::FORTUNE);

			if($level > 0){
				$count = 0;

				switch($event->getBlock()->getId()){
					case Item::EMERALD_ORE: {
						$count = rand(0, $level);
						$item = Item::get(Item::EMERALD);
						break;
					}

					case Item::DIAMOND_ORE: {
						$count = rand(0, $level);
						$item = Item::get(Item::DIAMOND);
						break;
					}

					case Item::REDSTONE_ORE: {
						$count = rand(1, $level + 4);
						$item = Item::get(Item::REDSTONE_DUST);
						break;
					}

					case Item::LAPIS_ORE: {
						$count = rand(1, $level + 4);
						$item = Item::get(Item::DYE, 4);
						break;
					}

					case Item::COAL_ORE: {
						$count = rand(0, $level + 1);
						$item = Item::get(Item::COAL);
						break;
					}

					case Item::LEAVES: {
						if($ev->getBlock()->getDamage() !== 0){
							return;
						}

						if($level * 3 >= rand(0, 100)){ //TODO: как-то улучшить это говно.
							$ev->setDrops([Item::get(Item::APPLE)]); 
							return;
						}
						break;
					}

					//TODO: добавить еще блоки.
					default: {
						return;
					}
				}
			}

			if($count > 0){
				while($count-- > 0){
					$drops[] = $item;
				}

				$event->setDrops($drops);
			}
		}
	}

	/**
	 * @param EntityDamageEvent $event
	 *
	 * @priority HIGHEST
	 * @ignoreCancelled true
	 */
	public function onEntityDamage(EntityDamageEvent $event): void{
		if($event instanceof EntityDamageByEntityEvent){
			$damager = $event->getDamager();

			if($damager instanceof Player){
				if($event instanceof EntityDamageByChildEntityEvent){
					$child = $event->getChild();

					if($child instanceof Arrow){
						$level = $child->namedtag->getShort('PowerEnch', 0);

						if($level > 0){
							$event->setDamage($event->getOriginalDamage() * 0.25 * ($level + 1) + $event->getDamage());
						}

						$level = $child->namedtag->getShort('PunchEnch', 0);

						if($level > 0){
							$event->setKnockBack(0.2 * $level + $event->getKnockBack());
						}
					}
				} else {
					$item = $damager->getInventory()->getItemInHand();
					$level = $item->getEnchantmentLevel(Enchantment::KNOCKBACK);

					if($level > 0){
						$event->setKnockBack(0.2 * $level + $event->getKnockBack());
					}

					$level = $item->getEnchantmentLevel(Enchantment::SHARPNESS);

					if($level > 0){
						$damage = 0;

						while($level-- > 0){
							$damage += rand(1, 3);
						}
						
						$event->setDamage($damage + $event->getDamage());
					}

					$level = $item->getEnchantmentLevel(Enchantment::FIRE_ASPECT);

					if($level > 0){
						$entity->setOnFire($level * 3 + 1);
					}
				} 
			}
		}
	}
}