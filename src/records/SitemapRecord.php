<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\records;

use craft\db\ActiveRecord;

/**
 * Class SitemapRecord
 *
 * @author  Ether Creative
 * @package ether\paseo\records
 *
 * @property int $id
 * @property int $siteId
 * @property int|null $elementId
 * @property string|null $groupId
 * @property string|null $group
 * @property string|null $url
 * @property string $frequency
 * @property float $priority
 * @property bool $enabled
 */
class SitemapRecord extends ActiveRecord
{

	// Methods
	// =========================================================================

	public static function tableName (): string
	{
		return '{{%paseo_sitemap}}';
	}

	public static function withDefaults ()
	{
		$record = new self();

		$record->frequency = 'monthly';
		$record->priority = 0.5;
		$record->enabled = '1';

		return $record;
	}

}
