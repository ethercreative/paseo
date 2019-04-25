<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\controllers;

use craft\web\Controller;

/**
 * Class DashboardController
 *
 * @author  Ether Creative
 * @package ether\paseo\controllers
 */
class DashboardController extends Controller
{

	public function actionIndex ()
	{
		return $this->renderTemplate('paseo/_dashboard/index');
	}

}
