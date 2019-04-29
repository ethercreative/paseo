<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\services;

use Craft;
use craft\base\Component;
use craft\helpers\Json;
use craft\web\View;
use ether\paseo\web\assets\PaseoAsset;
use Exception;
use yii\helpers\Markdown;

/**
 * Class Paseo
 *
 * @author  Ether Creative
 * @package ether\paseo\services
 */
class Paseo extends Component
{

	/**
	 * @param $type
	 * @param $parentSelector
	 * @param $message
	 */
	public function injectNotice ($type, $parentSelector, $message)
	{
		$message = Markdown::process(\ether\paseo\Paseo::t($message));
		$message = Json::encode($message);

		$js = <<<JS
!function () {
	const parent = document.querySelector('$parentSelector')
		, notice = document.createElement('div');
	
	notice.classList.add('paseo-$type');
	notice.innerHTML = {$message};
	
	parent.insertBefore(notice, parent.firstElementChild);
}();
JS;

		try {
			Craft::$app->getView()->registerAssetBundle(PaseoAsset::class);
			Craft::$app->getView()->registerJs($js, View::POS_END);
		} catch (Exception $e) {}
	}

}
