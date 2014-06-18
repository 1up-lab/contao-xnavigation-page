<?php

/**
 * xNavigation - Highly extendable and flexible navigation module for the Contao Open Source CMS
 *
 * Copyright (C) 2013 bit3 UG <http://bit3.de>
 *
 * @package    xNavigation
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @link       http://www.themeplus.de
 * @license    http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace Bit3\Contao\XNavigation\Page\Provider;

use Bit3\Contao\XNavigation\XNavigationEvents;
use Bit3\FlexiTree\Event\CollectItemsEvent;
use Bit3\FlexiTree\Event\CreateItemEvent;
use Contao\PageModel;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class Page
 */
class PageProvider extends \Controller implements EventSubscriberInterface
{
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents()
	{
		return array(
			XNavigationEvents::CREATE_ITEM   => 'createItem',
			XNavigationEvents::COLLECT_ITEMS => array('collectItems', 100),
		);
	}

	public function collectItems(CollectItemsEvent $event)
	{
		$item = $event->getParentItem();

		if ($item->getType() == 'page') {
			$table   = \PageModel::getTable();
			$columns = array("$table.pid=?");

			if (!BE_USER_LOGGED_IN) {
				$time      = time();
				$columns[] = "($table.start='' OR $table.start<$time) AND ($table.stop='' OR $table.stop>$time) AND $table.published=1";
			}

			$pages = \PageModel::findBy(
				$columns,
				array($item->getExtra('id')),
				array('order' => 'sorting')
			);

			if ($pages) {
				$factory = $event->getFactory();

				foreach ($pages as $page) {
					$factory->createItem('page', $page->id, $item);
				}
			}
		}
	}

	public function createItem(CreateItemEvent $event)
	{
		$item = $event->getItem();

		if ($item->getType() == 'page') {
			$page = \PageModel::findByPk($item->getName());

			if ($page) {
				if ($page->type == 'redirect') {
					$uri = $page->url;
					$uri = html_entity_decode($uri, ENT_QUOTES, 'UTF-8');
					$uri = $this->replaceInsertTags($uri);
				}
				else {
					$uri = \Frontend::generateFrontendUrl($page->row());
				}

				$item->setUri($uri);
				$item->setLabel($page->title);

				if ($page->cssClass) {
					$class = $item->getAttribute('class', '');
					$item->setAttribute('class', trim($class . ' ' . $page->cssClass));

					$class = $item->getLinkAttribute('class', '');
					$item->setLinkAttribute('class', trim($class . ' ' . $page->cssClass));

					$class = $item->getLabelAttribute('class', '');
					$item->setLabelAttribute('class', trim($class . ' ' . $page->cssClass));
				}

				if ($page->xnavigationLightbox) {
					$item->setLinkAttribute('data-lightbox', 'page-' . $page->id);

					if ($page->xnavigationLightboxWidth) {
						$item->setLinkAttribute('data-lightbox-width', $page->xnavigationLightboxWidth);
					}
					if ($page->xnavigationLightboxHeight) {
						$item->setLinkAttribute('data-lightbox-height', $page->xnavigationLightboxHeight);
					}
				}

				$currentPage = $this->getCurrentPage();

				$item->setExtras($page->row());
				$item->setCurrent($currentPage->id == $page->id);
				$item->setTrail(in_array($page->id, $currentPage->trail));
			}
		}
	}

	/**
	 * @SuppressWarnings(PHPMD.Superglobals)
	 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
	 * @return \PageModel
	 */
	protected function getCurrentPage()
	{
		return $GLOBALS['objPage'];
	}
}
