<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\events;

use yii\base\Event;

/**
 * Class RegisterSitemapGroupEvent
 *
 * @author  Ether Creative
 * @package ether\paseo\events
 */
class RegisterSitemapGroupEvent extends Event
{

	// Properties
	// =========================================================================

	/**
	 * @var array - The groups available to the sitemap
	 */
	public $groups = [];

}
