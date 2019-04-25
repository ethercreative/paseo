<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\controllers;

use Craft;
use craft\web\assets\vue\VueAsset;
use craft\web\Controller;
use ether\paseo\Paseo;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
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
	 * @throws InvalidConfigException
	 */
	public function actionIndex ()
	{
		$this->requirePermission('paseo.accessSitemap');

		$groups = Paseo::i()->sitemap->getSitemapGroups();
		$rows   = Paseo::i()->sitemap->getSitemapRows();
		$sites  = Craft::$app->getSites()->getAllSites();

		Craft::$app->getView()->registerAssetBundle(VueAsset::class);

		return $this->renderTemplate(
			'paseo/_sitemap/index',
			compact('groups', 'sites', 'rows')
		);
	}

	/**
	 * @throws ForbiddenHttpException
	 * @throws BadRequestHttpException
	 */
	public function actionSaveRows ()
	{
		$this->requirePermission('paseo.accessSitemap');

		$rows = Craft::$app->getRequest()->getRequiredBodyParam('row');
		Paseo::i()->sitemap->saveSitemapRows($rows);

		$this->redirectToPostedUrl();
	}

}
