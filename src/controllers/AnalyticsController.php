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
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Class AnalyticsController
 *
 * @author  Ether Creative
 * @package ether\paseo\controllers
 */
class AnalyticsController extends Controller
{

	/**
	 * @return Response
	 * @throws ForbiddenHttpException
	 */
	public function actionIndex ()
	{
		$this->requirePermission('paseo.accessAnalytics');

		if (Paseo::i()->is(Paseo::EDITION_PRO, '<'))
			return $this->renderTemplate('paseo/_pro', [
				'title' => Paseo::t('Analytics'),
				'selectedSubnavItem' => 'analytics',
			]);

		return $this->renderTemplate('paseo/_analytics/index');
	}

}
