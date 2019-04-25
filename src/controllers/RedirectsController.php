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
 * Class RedirectsController
 *
 * @author  Ether Creative
 * @package ether\paseo\controllers
 */
class RedirectsController extends Controller
{

	/**
	 * @return Response
	 * @throws ForbiddenHttpException
	 */
	public function actionIndex ()
	{
		$this->requirePermission('paseo.accessRedirects');

		return $this->renderTemplate('paseo/_redirects/index');
	}

}
