<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\controllers;

use craft\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Class SettingsController
 *
 * @author  Ether Creative
 * @package ether\paseo\controllers
 */
class SettingsController extends Controller
{

	/**
	 * @return Response
	 * @throws ForbiddenHttpException
	 */
	public function actionIndex ()
	{
		$this->requireAdmin();

		return $this->renderTemplate('paseo/_settings/index');
	}

}
