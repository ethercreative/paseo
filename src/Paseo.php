<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use ether\paseo\models\Settings;
use ether\paseo\services\Sitemap;
use yii\base\Event;

/**
 * Class Paseo
 *
 * @author  Ether Creative
 * @package ether\paseo
 *
 * @property Sitemap        $sitemap
 * @property services\Paseo $paseo
 */
class Paseo extends Plugin
{

	// Consts / Properties
	// =========================================================================

	const EDITION_LITE = 'lite';
	const EDITION_PRO  = 'pro';

	public static $hasCommerce = false;

	public $hasCpSection  = true;
	public $hasCpSettings = true;

	// Craft
	// =========================================================================

	public function init ()
	{
		parent::init();

		$this->setComponents([
			'sitemap' => Sitemap::class,
			'paseo'   => services\Paseo::class,
		]);

		Craft::setAlias(
			'@paseo',
			Craft::getAlias('@storage/paseo')
		);

		/** @noinspection PhpUndefinedNamespaceInspection */
		/** @noinspection PhpUndefinedClassInspection */
		self::$hasCommerce = class_exists(\craft\commerce\Plugin::class);

		// Events
		// ---------------------------------------------------------------------

		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_CP_URL_RULES,
			[$this, 'onRegisterCpUrlRules']
		);

		Event::on(
			UrlManager::class,
			UrlManager::EVENT_REGISTER_SITE_URL_RULES,
			[$this, 'onRegisterSiteUrlRules']
		);

		Event::on(
			UserPermissions::class,
			UserPermissions::EVENT_REGISTER_PERMISSIONS,
			[$this, 'onRegisterPermissions']
		);

		// Injections
		// ---------------------------------------------------------------------

		$request = Craft::$app->getRequest();

		if ($request->getIsConsoleRequest())
			return;

		$segments = $request->getSegments();

		if (
			$request->getIsCpRequest() &&
			$request->getIsGet() &&
			strpos($request->getFullPath(), 'settings/sites') &&
			end($segments) === 'sites' &&
			$this->getSettings()->sitemapEnabled
		) {
			$this->paseo->injectNotice(
				'tip',
				'#content',
				"**Sitemaps**  \nEach site group gets its own sitemap. The first site in each group is treated as the primary site while all other sites are treated as alternate versions (useful for other languages)."
			);
		}

	}

	public static function editions (): array
	{
		return [
			self::EDITION_LITE,
			self::EDITION_PRO,
		];
	}

	public function getCpNavItem ()
	{
		$item = parent::getCpNavItem();
		$user = Craft::$app->getUser();

		$subNav = [
			'dashboard' => [
				'label' => Paseo::t('Dashboard'),
				'url'   => 'paseo',
			],
		];

		if ($user->checkPermission('paseo.accessAnalytics'))
		{
			$subNav['analytics'] = [
				'label' => Paseo::t('Analytics'),
				'url'   => 'paseo/analytics',
			];
		}

		if ($user->checkPermission('paseo.accessRedirects'))
		{
			$subNav['redirects'] = [
				'label' => Paseo::t('Redirects'),
				'url'   => 'paseo/redirects',
			];
		}

		if ($user->checkPermission('paseo.accessSitemap'))
		{
			$subNav['sitemap'] = [
				'label' => Paseo::t('Sitemap'),
				'url'   => 'paseo/sitemap',
			];
		}

		if ($user->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges)
		{
			$subNav['settings'] = [
				'label' => Craft::t('app', 'Settings'),
				'url'   => 'paseo/settings',
			];
		}

		$item['subnav'] = $subNav;

		return $item;
	}

	// Craft: Settings
	// -------------------------------------------------------------------------

	protected function createSettingsModel ()
	{
		return new Settings();
	}

	public function getSettingsResponse ()
	{
		Craft::$app->controller->redirect(
			UrlHelper::cpUrl('paseo/settings')
		);
	}

	/**
	 * @return bool|Settings|null
	 */
	public function getSettings ()
	{
		return parent::getSettings();
	}

	// Events
	// =========================================================================

	public function onRegisterSiteUrlRules (RegisterUrlRulesEvent $event)
	{
		$settings = $this->getSettings();

		if ($settings->sitemapEnabled)
			$event->rules['sitemap<file:.*>.xml'] = 'paseo/sitemap/serve';
	}

	public function onRegisterCpUrlRules (RegisterUrlRulesEvent $event)
	{
		// Dashboard
		$event->rules['paseo'] = 'paseo/dashboard/index';

		// Analytics
		$event->rules['paseo/analytics'] = 'paseo/analytics/index';

		// Redirects
		$event->rules['paseo/redirects'] = 'paseo/redirects/index';

		// Sitemap
		$event->rules['paseo/sitemap'] = 'paseo/sitemap/index';
		$event->rules['paseo/sitemap/test'] = 'paseo/sitemap/test';

		// Settings
		$event->rules['paseo/settings'] = 'paseo/settings/index';
		$event->rules['paseo/settings/sitemap'] = 'paseo/settings/sitemap';
	}

	public function onRegisterPermissions (RegisterUserPermissionsEvent $event)
	{
		$event->permissions['Paseo'] = [
			'paseo.accessAnalytics' => ['label' => Paseo::t('Access Analytics')],
			'paseo.accessRedirects' => ['label' => Paseo::t('Access Redirects')],
			'paseo.accessSitemap'   => ['label' => Paseo::t('Access Sitemap')],
		];
	}

	// Helpers
	// =========================================================================

	public static function t (string $message, array $params = []): string
	{
		return Craft::t('paseo', $message, $params);
	}

	public static function i ()
	{
		return self::getInstance();
	}

}
