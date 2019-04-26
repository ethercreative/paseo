<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\Controller;
use ether\paseo\Paseo;
use ether\paseo\web\assets\PaseoAsset;
use yii\base\InvalidConfigException;
use yii\db\Exception;
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
	 * @throws SiteNotFoundException
	 * @throws InvalidConfigException
	 */
	public function actionIndex ()
	{
		$this->requirePermission('paseo.accessSitemap');

		$enabled = Paseo::i()->getSettings()->sitemapEnabled;
		$groups  = Paseo::i()->sitemap->getSitemapGroups();
		$rows    = Paseo::i()->sitemap->getSitemapRows();
		$sites   = Craft::$app->getSites()->getAllSites();

		Craft::$app->getView()->registerAssetBundle(PaseoAsset::class);

		return $this->renderTemplate(
			'paseo/_sitemap/index',
			compact('groups', 'sites', 'rows', 'enabled')
		);
	}

	/**
	 * @throws ForbiddenHttpException
	 * @throws BadRequestHttpException
	 * @throws Exception
	 */
	public function actionSaveRows ()
	{
		$this->requirePermission('paseo.accessSitemap');
		$request = Craft::$app->getRequest();

		$rows   = $request->getRequiredBodyParam('row');
		$rowIds = $request->getBodyParam('paseoDeleteCustom', []);

		Paseo::i()->sitemap->saveSitemapRows($rows);
		Paseo::i()->sitemap->deleteSitemapRowsByIds($rowIds);

		$this->redirectToPostedUrl();
	}

}
