<?php
/**
 * Paseo for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\paseo\migrations;

use craft\db\Migration;
use craft\db\Table;
use ether\paseo\records\SitemapRecord;

/**
 * Class Install
 *
 * @author  Ether Creative
 * @package ether\paseo\migrations
 */
class Install extends Migration
{

	public function safeUp ()
	{

		// Sitemap
		// ---------------------------------------------------------------------

		$this->createTable(
			SitemapRecord::tableName(),
			[
				'id' => $this->primaryKey(),

				'siteId'      => $this->integer()->notNull(),
				'elementId'   => $this->integer()->null(), // for per-element overrides
				'groupId'     => $this->string()->null(),
				'group'       => $this->string()->null(),
				'uri'         => $this->string(255)->null(),
				'frequency'   => $this->enum('frequency', [
					'always',
					'hourly',
					'daily',
					'weekly',
					'monthly',
					'yearly',
					'never',
				])->notNull(),
				'priority'    => $this->float(1)->notNull(),
				'enabled'     => $this->boolean()->notNull()->defaultValue(true),

				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid'         => $this->uid()->notNull(),
			]
		);

		$this->addForeignKey(
			null,
			SitemapRecord::tableName(),
			'siteId',
			Table::SITES,
			'id',
			'CASCADE'
		);

		$this->addForeignKey(
			null,
			SitemapRecord::tableName(),
			'elementId',
			Table::ELEMENTS,
			'id',
			'CASCADE'
		);

	}

	public function safeDown ()
	{
		$this->dropTableIfExists(SitemapRecord::tableName());
	}

}
