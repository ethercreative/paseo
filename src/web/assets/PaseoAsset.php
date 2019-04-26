<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\web\assets;

use craft\web\AssetBundle;

/**
 * Class PaseoAsset
 *
 * @author  Ether Creative
 * @package ether\paseo\web\assets
 */
class PaseoAsset extends AssetBundle
{

	public function init ()
	{
		$this->sourcePath = __DIR__;

		$this->css = [
			'css/paseo.css',
		];

		parent::init();
	}

}
