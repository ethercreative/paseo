<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\console\controllers;

use Craft;
use ether\paseo\jobs\GenerateSitemaps;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Class DefaultController
 *
 * @author  Ether Creative
 * @package ether\paseo\console\controllers
 */
class SitemapController extends Controller
{

	public function actionRegenerate ()
	{
		Craft::$app->getQueue()->push(new GenerateSitemaps());

		return ExitCode::OK;
	}

}
