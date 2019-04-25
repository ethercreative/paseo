<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\controllers;

use craft\web\Controller;
use ether\paseo\Paseo;
use yii\base\Action;
use yii\web\ForbiddenHttpException;

/**
 * Class SettingsController
 *
 * @author  Ether Creative
 * @package ether\paseo\controllers
 */
class SettingsController extends Controller
{

	/**
	 * @param Action $action
	 *
	 * @return bool
	 * @throws ForbiddenHttpException
	 */
	public function beforeAction ($action)
	{
		$this->requireAdmin();

		return parent::beforeAction($action);
	}

	public function actionIndex ()
	{
		return $this->renderTemplate('paseo/_settings/index');
	}

	public function actionSitemap ()
	{
		return $this->renderTemplate(
			'paseo/_settings/sitemap',
			['settings' => Paseo::i()->getSettings()]
		);
	}

}
