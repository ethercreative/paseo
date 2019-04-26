<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\services;

use Craft;
use craft\commerce\elements\Product;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin as Commerce;
use craft\elements\Category;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use craft\models\CategoryGroup;
use craft\models\Section;
use ether\paseo\events\RegisterSitemapGroupEvent;
use ether\paseo\Paseo;
use ether\paseo\records\SitemapRecord;
use yii\base\Component;
use yii\db\Exception;
use yii\helpers\FileHelper;

/**
 * Class Sitemap
 *
 * @author  Ether Creative
 * @package ether\paseo\services
 */
class Sitemap extends Component
{

	// Constants
	// =========================================================================

	/**
	 * @event RegisterSitemapGroupEvent - The event that is triggered when
	 *   registering sitemap groups.
	 *
	 * ```php
	 * use ether\paseo\events\RegisterSitemapGroupEvent;
	 * use ether\paseo\services\Sitemap;
	 * use yii\base\Event;
	 * Event::on(
	 *     Sitemap::class,
	 *     Sitemap::EVENT_REGISTER_SITEMAP_GROUPS,
	 *     function (RegisterSitemapGroupEvent $event) {
	 *         $event->groups['myGroup'] = [
	 *             'label' => 'My Group',
	 *             'rows' => [
	 *                 [
	 *                     'groupId'   => 100, // Can be a string or int, can't contain a period (.)
	 *                     'name'      => 'My Row',
	 *                     'criteria'  => [
	 *                         'type' => MyElementType::class,
	 *                         'id' => 100,
	 *                     ],
	 *                 ],
	 *             ],
	 *         ];
	 *     }
	 * );
	 * ```
	 */
	const EVENT_REGISTER_SITEMAP_GROUPS = 'paseo.registerSitemapGroups';

	// Public Methods
	// =========================================================================

	/**
	 * Returns an array of all the available sitemap groups
	 *
	 * @param bool $includeCustom
	 *
	 * @return array
	 * @throws SiteNotFoundException
	 */
	public function getSitemapGroups (bool $includeCustom = true)
	{
		$groups = [

			'sections' => [
				'label' => Craft::t('app', 'Sections'),
				'rows' => array_map(
					function (Section $section) {
						return [
							'groupId'   => $section->id,
							'name'      => $section->name,
							'criteria'  => [
								'type'      => Entry::class,
								'sectionId' => $section->id,
							],
						];
					},
					Craft::$app->getSections()->getAllSections()
				),
			],

			'categories' => [
				'label' => Craft::t('app', 'Categories'),
				'rows' => array_map(
					function (CategoryGroup $group) {
						return [
							'groupId'   => $group->id,
							'name'      => $group->name,
							'criteria'  => [
								'type'    => Category::class,
								'groupId' => $group->id,
							],
						];
					},
					Craft::$app->getCategories()->getAllGroups()
				),
			],

		];

		if (Paseo::$hasCommerce)
		{
			$groups['productTypes'] = [
				'label' => Craft::t('commerce', 'Product Types'),
				'rows' => array_map(
					function (ProductType $type) {
						return [
							'groupId'   => $type->id,
							'name'      => $type->name,
							'criteria'  => [
								'type'   => Product::class,
								'typeId' => $type->id,
							],
 						];
					},
					Commerce::getInstance()->getProductTypes()->getAllProductTypes()
				),
			];
		}

		$event = new RegisterSitemapGroupEvent([
			'groups' => $groups,
		]);
		$this->trigger(self::EVENT_REGISTER_SITEMAP_GROUPS, $event);

		if ($includeCustom)
		{
			$groups['custom'] = [
				'label' => Paseo::t('Custom URLs'),
				'rows'  => array_map(
					function (SitemapRecord $row) {
						return [
							'groupId' => $row->groupId,
							'name'    => $row->uri,
						];
					},
					SitemapRecord::find()->where([
						'group'  => 'custom',
						'siteId' => Craft::$app->getSites()->getPrimarySite()->id,
					])->orderBy('dateCreated')->all()
				),
			];
		}

		return $groups;
	}

	/**
	 * Gets all the rows for the sitemap and formats them for use
	 *
	 * @return array
	 * @throws SiteNotFoundException
	 */
	public function getSitemapRows ()
	{
		$groups = $this->getSitemapGroups();
		$sites  = Craft::$app->getSites()->getAllSiteIds();

		$rows = [];

		foreach ($groups as $handle => $group)
			foreach ($group['rows'] as $row)
				foreach ($sites as $id)
					$rows[$handle . '.' . $row['groupId'] . '.' . $id] = SitemapRecord::withDefaults();

		/** @var SitemapRecord $row */
		foreach (SitemapRecord::find()->all() as $row)
			$rows[$row->group . '.' . $row->groupId . '.' . $row->siteId] = $row;

		return $rows;
	}

	/**
	 * Saves the sitemap rows
	 *
	 * @param array $rows
	 *
	 * @throws Exception
	 */
	public function saveSitemapRows (array $rows)
	{
		$transaction = Craft::$app->getDb()->beginTransaction();

		foreach ($rows as $key => $row)
		{
			list($group, $groupId, $siteId) = explode('.', $key);

			if ($group === 'custom' && empty($row['uri']))
				continue;

			$record = new SitemapRecord();

			if ($row['id'] ?? false)
				$record = SitemapRecord::findOne($row['id']);

			$record->group     = $group;
			$record->groupId   = $groupId;
			$record->siteId    = $siteId;
			$record->frequency = $row['frequency'];
			$record->priority  = $row['priority'];
			$record->enabled   = $row['enabled'] === '1';
			$record->uri       = $row['uri'] ?? null;

			$record->save();
		}

		$transaction->commit();
	}

	/**
	 * Deletes sitemap rows by the given IDs
	 *
	 * @param array $ids
	 */
	public function deleteSitemapRowsByIds (array $ids)
	{
		SitemapRecord::deleteAll([
			'AND',
			['in', 'id', $ids],
		]);
	}

	// Generation
	// =========================================================================

	public function generateSitemapIndex ($sitemaps)
	{
		$sitemaps = array_map(function ($map) {
			return <<<XML
<sitemap>
	<loc>{$map['url']}</loc>
	<lastmod>{$map['lastmod']}</lastmod>
</sitemap>
XML;
		}, $sitemaps);

		$sitemaps = implode(PHP_EOL, $sitemaps);

		$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
	{$sitemaps}
</sitemapindex>
XML;

		file_put_contents(
			Craft::getAlias('@paseo/sitemaps/sitemap.xml'),
			$xml
		);
	}

	public function generateSitemapForGroup ($group)
	{
		// TODO: Generate all sitemap files for the given group and return an
		//  array of the generated file names and last modified dates.
		//  https://www.sitemaps.org/protocol.html
		//  [['url'=>'full_url','lastmod'=>'2004-10-01T18:23:17+00:00']]
	}

	public function generateSitemapForCustom ()
	{
		// TODO: Generate the sitemap file(s) for custom URLs and return an
		//  array of the generate file names and last modified dates.
		//  https://www.sitemaps.org/protocol.html
		//  [['url'=>'full_url','lastmod'=>'2004-10-01T18:23:17+00:00']]
	}

}
