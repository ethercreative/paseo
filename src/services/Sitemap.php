<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\services;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin as Commerce;
use craft\elements\Category;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\models\CategoryGroup;
use craft\models\Section;
use craft\models\Site;
use DateTime;
use ether\paseo\events\RegisterSitemapGroupEvent;
use ether\paseo\Paseo;
use ether\paseo\records\SitemapRecord;
use yii\base\Component;
use yii\base\ErrorException;
use yii\db\Exception;

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
	 *                     'groupId'   => 100, // Can be a string or int, can't
	 *                     contain a period (.)
	 *                     'name'      => 'My Row',
     *                     'type'      => MyElementType::class,
	 *                     'criteria'  => [
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
							'type'      => Entry::class,
							'criteria'  => [
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
							'type'    => Category::class,
							'criteria'  => [
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
							'type'   => Product::class,
							'criteria'  => [
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

	/**
	 * @param $sitemapData
	 *
	 * @throws ErrorException
	 * @throws \yii\base\Exception
	 */
	public function generateSitemapIndex ($sitemapData)
	{
		$siteGroups = Craft::$app->getSites()->getAllGroups();

		foreach ($siteGroups as $group)
		{
			if (!array_key_exists($group->id, $sitemapData) || empty($sitemapData[$group->id]))
				continue;

			$sitemaps = array_map(
				function ($map) {
					return <<<XML
<sitemap>
	<loc>{$map['url']}</loc>
	<lastmod>{$map['lastmod']}</lastmod>
</sitemap>
XML;
				},
				$sitemapData[$group->id]
			);

			$sitemaps = implode(PHP_EOL, $sitemaps);

			$xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
	{$sitemaps}
</sitemapindex>
XML;

			FileHelper::createDirectory(
				Craft::getAlias('@paseo/sitemaps/' . $group->id)
			);
			FileHelper::writeToFile(
				Craft::getAlias(
					'@paseo/sitemaps/' . $group->id . '/sitemap.xml'
				),
				$xml
			);
		}
	}

	/**
	 * @param $handle
	 * @param $group
	 *
	 * @return array
	 * @throws \yii\base\Exception
	 * @throws ErrorException
	 */
	public function generateSitemapForGroup ($handle, $group)
	{
		$sitemaps = [];
		$settings = Paseo::i()->getSettings();
		$siteGroups = Craft::$app->getSites()->getAllGroups();
		$meta = SitemapRecord::findAll([
			'group' => $handle,
		]);

		foreach ($group['rows'] as $row)
		{
			foreach ($siteGroups as $siteGroup)
			{
				if (empty($sitemaps[$siteGroup->id]))
					$sitemaps[$siteGroup->id] = [];

				/** @var DateTime $lastMod */
				$lastMod = null;

				$sites = $siteGroup->getSites();
				$primarySite = $sites[0];

				$config = $this->_find($meta, [
					'groupId'   => $row['groupId'],
					'siteId'    => $primarySite->id,
					'elementId' => null,
				]);

				if (empty($config))
					continue;

				$config = reset($config);

				if (!$config->enabled)
					continue;

				/** @var ElementInterface $cls */
				$cls = $row['type'];

				$totalResults = $cls::find()
                    ->where($row['criteria'])
					->siteId($primarySite->id)
                    ->count();

				$totalPages = ceil($totalResults / $settings->sitemapPaginationLimit);
				$p = 0;

				while ($p++ < $totalPages)
				{
					$elementsBySite = [];

					foreach ($sites as $site)
					{
						$elementsBySite[$site->id] = $cls::find()
							->where($row['criteria'])
							->siteId($site->id)
							->limit($settings->sitemapPaginationLimit)
							->orderBy('dateCreated')
							->offset($settings->sitemapPaginationLimit * ($p - 1))
							->all();
					}

					$xml = $this->_buildSitemapForElements(
						$config,
						$sites,
						$elementsBySite,
						$lastMod
					);
					$file = 'sitemap-' . $handle . '-' . $p . '.xml';

					FileHelper::createDirectory(
						Craft::getAlias('@paseo/sitemaps/' . $siteGroup->id)
					);
					FileHelper::writeToFile(
						Craft::getAlias('@paseo/sitemaps/' . $siteGroup->id . '/' . $file),
						$xml
					);

					$sitemaps[$siteGroup->id][] = [
						'url' => UrlHelper::siteUrl(
							$file,
							null,
							null,
							$primarySite->id
						),
						'lastmod' => $lastMod ? $lastMod->format(DateTime::W3C) : null,
					];
				}
			}
		}

		return $sitemaps;
	}

	public function generateSitemapForCustom ()
	{
		// TODO: Generate the sitemap file(s) for custom URLs and return an
		//  array of the generate file names and last modified dates.
		//  https://www.sitemaps.org/protocol.html
		//  [$siteGroup->id => [['url'=>'full_url','lastmod'=>'2004-10-01T18:23:17+00:00']]]

		return [];
	}

	// Helpers
	// =========================================================================

	/**
	 * @param array $data
	 * @param array $query
	 *
	 * @return array
	 */
	private function _find (array $data, array $query)
	{
		return array_filter($data, function ($row) use ($query) {
			foreach ($query as $column => $value)
				if ($row->$column != $value)
					return false;

			return true;
		});
	}

	/**
	 * @param SitemapRecord $config
	 * @param               $sites
	 * @param               $elementsBySite
	 * @param               $lastMod
	 *
	 * @return string
	 */
	private function _buildSitemapForElements (
		SitemapRecord $config, $sites, $elementsBySite, &$lastMod
	) {
		// TODO: Get config overrides for individual elements
		$primarySite = array_shift($sites);

		$urls = array_map(function (Element $element) use ($config, $sites, $elementsBySite, &$lastMod) {
			$altUrls = [];

			/** @var Site $site */
			foreach ($sites as $site)
			{
				$el = $this->_find($elementsBySite[$site->id], [
					'id' => $element->id,
				]);

				if (empty($el))
					continue;

				$el = reset($el);

				$altUrls[] = <<<XML
<xhtml:link rel="alternate" hreflang="$site->language" href="{$el->getUrl()}" />
XML;
			}

			$altUrls = implode(PHP_EOL, $altUrls);

			if ($lastMod === null || $lastMod < $element->dateUpdated)
				$lastMod = $element->dateUpdated;

			// TODO: Include media / indexable files if required

			return <<<XML
<url>
	<loc>{$element->getUrl()}</loc>
	<lastmod>{$element->dateUpdated->format(DateTime::W3C)}</lastmod>
	<changefreq>{$config->frequency}</changefreq>
	<priority>{$config->priority}</priority>
	{$altUrls}
</url>
XML;
		}, $elementsBySite[$primarySite->id]);

		$urls = implode(PHP_EOL, $urls);

		return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
	{$urls}
</urlset>
XML;
	}

}
