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
use craft\base\Field;
use craft\commerce\elements\Product;
use craft\commerce\models\ProductType;
use craft\commerce\Plugin as Commerce;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\MatrixBlock;
use craft\errors\SiteNotFoundException;
use craft\fields\Assets;
use craft\fields\Matrix;
use craft\helpers\FileHelper;
use craft\helpers\Html;
use craft\helpers\Json;
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
use yii\base\InvalidConfigException;
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
	 *                     'groupId'   => 100, // Can be a string or int, can't contain a period (.)
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

	// Properties
	// =========================================================================

	private $_indexableFiles = [];

	private static $_INDEXABLE_FILE_TYPES = [
		'swf',
		'pdf',
		'ps',
		'dwf',
		'kml',
		'kmz',
		'gpx',
		'hwp',
		'htm',
		'html',
		'xls',
		'xlsx',
		'ppt',
		'pptx',
		'doc',
		'docx',
		'odp',
		'ods',
		'odt',
		'rtf',
		'svg',
		'tex',
		'txt',
		'text',
		'md',
		'bas',
		'c',
		'cc',
		'cpp',
		'cxx',
		'h',
		'hpp',
		'cs',
		'java',
		'pl',
		'py',
		'wml',
		'wap',
		'xml',
	];

	private static $_INDEXABLE_FILE_KINDS = [
		'excel',
		'illustrator',
		'powerpoint',
		'pdf',
		'xml',
		'text',
		'word',
	];

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
		$settings = Paseo::i()->getSettings();

		$urls = array_map(function (Element $element) use (
			$config, $sites, $elementsBySite, &$lastMod, $settings
		) {
			if ($element->getUrl() === null)
				return null;

			$altUrls = [];

			/** @var Site $site */
			foreach ($sites as $site)
			{
				// TODO: Check config for this site to ensure it's enabled in
				//  sitemap settings

				$el = $this->_find($elementsBySite[$site->id], [
					'id' => $element->id,
				]);

				if (empty($el))
					continue;

				/** @var Element $el */
				$el = reset($el);

				if ($el->getUrl() === null)
					continue;

				$altUrls[] = <<<XML
<xhtml:link rel="alternate" hreflang="$site->language" href="{$el->getUrl()}" />
XML;
			}

			$altUrls = implode(PHP_EOL, $altUrls);

			if ($settings->sitemapIncludeMedia)
				$media = $this->_mediaUrls($element);
			else
				$media = '';

			if ($lastMod === null || $lastMod < $element->dateUpdated)
				$lastMod = $element->dateUpdated;

			return <<<XML
<url>
	<loc>{$this->_url($element->getUrl())}</loc>
	<lastmod>{$element->dateUpdated->format(DateTime::W3C)}</lastmod>
	<changefreq>{$config->frequency}</changefreq>
	<priority>{$config->priority}</priority>
	{$altUrls}
	{$media}
</url>
XML;
		}, $elementsBySite[$primarySite->id]);

		$urls = implode(PHP_EOL, $urls);

		$attributes = [
			'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"',
			'xmlns:xhtml="http://www.w3.org/1999/xhtml"',
		];

		if ($settings->sitemapIncludeMedia)
		{
			$attributes[] = 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
			$attributes[] = 'xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"';
		}

		$attributes = implode(' ', $attributes);

		$indexableFiles = implode(PHP_EOL, $this->_indexableFiles);

		return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset {$attributes}>
	{$urls}
	{$indexableFiles}
</urlset>
XML;
	}

	/**
	 * @param Element $element
	 *
	 * @return string
	 * @throws \yii\base\Exception
	 */
	private function _mediaUrls (Element $element)
	{
		$assets = $this->_getFieldsFromElement($element, [
			Assets::class,
		]);

		// TODO: Add support for other block based fields (i.e. SuperTable, Neo)
		$blocks = $this->_getFieldsFromElement($element, [
			Matrix::class,
		]);
		
		foreach ($blocks as $field)
			foreach ($element->{$field->handle}->all() as $block)
				$assets = array_merge(
					$assets,
					$this->_getFieldsFromBlock($block, [
						Assets::class,
					])
				);

		$urls = [];

		foreach ($assets as $assetField)
			foreach ($element->{$assetField->handle}->all() as $asset)
				$urls[] = $this->_assetFieldToSitemapUrls($asset);

		return implode(PHP_EOL, array_filter($urls));
	}

	/**
	 * Gets all the fields on the element that match the types given
	 *
	 * @param Element $element
	 * @param array   $fieldTypes
	 *
	 * @return Field[]
	 */
	private function _getFieldsFromElement (Element $element, array $fieldTypes)
	{
		static $fieldsCache = [];

		$fields = [];
		$fieldLayout = $element->getFieldLayout();
		$key = $fieldLayout->uid . Json::encode($fieldTypes);

		if (array_key_exists($key, $fieldsCache))
			return $fieldsCache[$key];

		$allFields = $fieldLayout->getFields();

		foreach ($allFields as $field)
			if (in_array(get_class($field), $fieldTypes))
				$fields[] = $field;

		return $fieldsCache[$key] = $fields;
	}

	/**
	 * @param MatrixBlock $block
	 * @param array       $fieldTypes
	 *
	 * @return array
	 */
	private function _getFieldsFromBlock ($block, array $fieldTypes)
	{
		static $fieldsCache = [];

		$fields = [];

		try {
			$type = $block->getType();
			$key = $type->uid . Json::encode($fieldTypes);

			if (array_key_exists($key, $fieldsCache))
				return $fieldsCache[$key];

			$allFields = $type->getFields();
		}
		catch (InvalidConfigException $e) {
			return [];
		}

		foreach ($allFields as $field)
			if (in_array(get_class($field), $fieldTypes))
				$fields[] = $field;

		return $fieldsCache[$key] = $fields;
	}

	/**
	 * @param Asset $asset
	 *
	 * @return string|null
	 * @throws \yii\base\Exception
	 */
	private function _assetFieldToSitemapUrls (Asset $asset)
	{
		if (!$asset->enabledForSite || !$asset->getUrl())
			return null;

		switch ($asset->kind)
		{
			case 'image':
				return <<<XML
<image:image>
	<image:loc>{$this->_url($asset->getUrl())}</image:loc>
	<image:title>{$asset->title}</image:title>
</image:image>
XML;
			case 'video':
				return <<<XML
<video:video>
	<video:content_loc>{$this->_url($asset->getUrl())}</video:content_loc>
	<video:title>{$asset->title}</video:title>
</video:video>
XML;
		}

		if (
			in_array($asset->mimeType, self::$_INDEXABLE_FILE_TYPES) ||
			in_array($asset->kind, self::$_INDEXABLE_FILE_KINDS)
		) {
			// TODO: Allow setting of changefreq / priority in sitemap settings
			$this->_indexableFiles[] = <<<XML
<url>
	<loc>{$this->_url($asset->getUrl())}</loc>
	<lastmod>{$asset->dateUpdated->format(DateTime::W3C)}</lastmod>
</url>
XML;
		}

		return null;
	}

	/**
	 * @param string $url
	 *
	 * @return string
	 * @throws \yii\base\Exception
	 */
	private function _url (string $url)
	{
		if (!UrlHelper::isAbsoluteUrl($url))
			$url = UrlHelper::siteUrl($url);

		return Html::encode($url);
	}

}
