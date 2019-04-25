<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\models;

use craft\base\Model;

/**
 * Class Settings
 *
 * @author  Ether Creative
 * @package ether\paseo\models
 */
class Settings extends Model
{

	// Sitemap
	// =========================================================================

	/**
	 * @var bool - Will generate and serve sitemap files when enabled.
	 */
	public $sitemapEnabled = true;

	/**
	 * @var int - How many top-level elements each sitemap can show per-page.
	 *   Reduce this number if your server is struggling to generate the sitemaps.
	 */
	public $sitemapPaginationLimit = 1000;

	/**
	 * @var bool - Will include images and videos found in asset fields.
	 */
	public $sitemapIncludeMedia = true;

	/**
	 * @var bool - Will include files found in asset fields that can be
	 *   indexed by Google.
	 * @see https://support.google.com/webmasters/answer/35287?hl=en
	 */
	public $sitemapIncludeIndexableFiles = true;

	// Validation
	// =========================================================================

	public function rules ()
	{
		return [
			[
				['sitemapPaginationLimit'],
				'required',
			],

			// Sitemap
			['sitemapPaginationLimit', 'number', 'min' => 100],
		];
	}

}
