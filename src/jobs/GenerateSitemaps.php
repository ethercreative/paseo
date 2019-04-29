<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\jobs;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\helpers\FileHelper;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;
use ether\paseo\Paseo;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\queue\Queue;

/**
 * Class GenerateSitemaps
 *
 * @author  Ether Creative
 * @package ether\paseo\jobs
 */
class GenerateSitemaps extends BaseJob
{

	// Properties
	// =========================================================================

	private $_currentStep = 0;
	private $_totalSteps  = 0;

	// Methods
	// =========================================================================

	protected function defaultDescription ()
	{
		return Paseo::t('Generating sitemaps');
	}

	/**
	 * @param Queue|QueueInterface $queue The queue the job belongs to
	 *
	 * @throws SiteNotFoundException
	 * @throws ErrorException
	 * @throws Exception
	 */
	public function execute ($queue)
	{
		if (!Paseo::i()->getSettings()->sitemapEnabled)
			return;

		$s = Paseo::i()->sitemap;
		$groups = $s->getSitemapGroups(false);

		$this->_totalSteps = count($groups) + 3;

		$sitemaps = [];

		$this->_incrementStep($queue);
		$sitemapsStorage = Craft::getAlias('@paseo/sitemaps');
		FileHelper::createDirectory($sitemapsStorage);
		FileHelper::clearDirectory($sitemapsStorage);

		foreach ($groups as $handle => $group)
		{
			$this->_incrementStep($queue);

			$sitemaps = $this->_mergeSitemaps(
				$sitemaps,
				$s->generateSitemapForGroup($handle, $group)
			);
		}

		$this->_incrementStep($queue);
		$sitemaps = $this->_mergeSitemaps(
			$sitemaps,
			$s->generateSitemapForCustom()
		);

		$this->_incrementStep($queue);
		$s->generateSitemapIndex($sitemaps);
	}

	// Helpers
	// =========================================================================

	private function _incrementStep ($queue)
	{
		$this->_currentStep++;
		$this->setProgress($queue, $this->_currentStep / $this->_totalSteps);
	}

	private function _mergeSitemaps ($a, $b)
	{
		foreach ($b as $siteGroupId => $sitemaps)
		{
			if (!array_key_exists($siteGroupId, $a))
			{
				$a[$siteGroupId] = $sitemaps;
				continue;
			}

			$a[$siteGroupId] = array_merge(
				$a[$siteGroupId],
				$sitemaps
			);
		}

		return $a;
	}

}
