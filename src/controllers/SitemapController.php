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
 * Class SitemapController
 *
 * @author  Ether Creative
 * @package ether\paseo\controllers
 */
class SitemapController extends Controller
{

	/**
	 * @return Response
	 * @throws ForbiddenHttpException
	 */
	public function actionIndex ()
	{
		$this->requirePermission('paseo.accessSitemap');

		return $this->renderTemplate('paseo/_sitemap/index');
	}

}
