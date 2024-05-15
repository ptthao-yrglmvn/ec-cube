<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Codeception\Util\Fixtures;
use Doctrine\ORM\EntityManager;
use Eccube\Entity\Plugin;
use Eccube\Repository\PluginRepository;
use Page\Admin\PluginManagePage;
use Page\Admin\PluginSearchPage;
use Page\Admin\PluginStoreInstallPage;

class PluginAutomationCest
{
    private $store;

    public function _before(AcceptanceTester $I)
    {
        $I->loginAsAdmin();
    }

    public function _after(AcceptanceTester $I)
    {
        $I->waitForJS("return $.active == 0;", 120);

        # Disable maintenance
        $config = Fixtures::get('config');
        $maintenance_file_path= $config->get('eccube_content_maintenance_file_path');
        if (file_exists($maintenance_file_path)) {
            unlink($maintenance_file_path);
        }
    }

    public function test_setting_authenKey(AcceptanceTester $I)
    {
        $store = Store_Plugin::start($I);
        $this->retryOnException($I, function() use ($store) {
            $store->setting_key();
        });
    }

    public function test_link_authenKey(AcceptanceTester $I)
    {
        $this->retryOnException($I, function() use ($I) {
            PluginSearchPage::go($I);
            $I->waitForText('プラグインを探すオーナーズストア');
            $I->dontSee('オーナーズストアとの通信に失敗しました。時間を置いてもう一度お試しください。', '.alert-danger');
            $I->dontSee('オーナーズストアの認証に失敗しました。認証キーの設定を確認してください。', '.alert-danger');
            $I->dontSee('オーナーズストアとの通信に失敗しました。時間を置いてもう一度お試しください。', '.alert-danger');
        });
    }

    public function test_search_by_name(AcceptanceTester $I)
    {
        $store = Store_Plugin::start($I);
        $this->retryOnException($I, function() use ($store) {
            $store->search_by_name();
        });
    }

    public function test_install(AcceptanceTester $I)
    {
        $store = Store_Plugin::start($I);
        $this->retryOnException($I, function() use ($store) {
            $store->install();
        });
    }

    public function test_enable(AcceptanceTester $I)
    {
        $store = Store_Plugin::start($I);
        $this->retryOnException($I, function() use ($store) {
            $store->enable();
        });
    }

    public function test_disable(AcceptanceTester $I)
    {
        $store = Store_Plugin::start($I);
        $this->retryOnException($I, function() use ($store) {
            $store->disable();
        });
    }

    public function test_remove(AcceptanceTester $I)
    {
        $store = Store_Plugin::start($I);
        $this->retryOnException($I, function() use ($store) {
            $store->uninstall();
        });
    }

    public function test_directoryIsRemoved(AcceptanceTester $I)
    {
        $store = Store_Plugin::start($I);
        $this->retryOnException($I, function() use ($store) {
            $store->checkDirectoryIsRemoved();
        });
    }

    protected function retryOnException(AcceptanceTester $I, callable $func, $maxAttempts = 3)
    {
        $attempts = 0;
        while (true) {
            try {
                $I->loginAsAdmin();
                return $func();
            } catch (\Exception $e) {
                $attempts++;
                if ($attempts >= $maxAttempts) {
                    throw new \Exception("Max retries reached for test: " . $e->getMessage(), 0, $e);
                }
                $I->resetCookie(getenv('ECCUBE_COOKIE_NAME'));
                $I->deleteSessionSnapshot('login');
            }
        }
    }
}


class Store_Plugin
{
    /** @var AcceptanceTester */
    protected $I;

    /** @var PluginManagePage */
    protected $ManagePage;

    /** @var EccubeConfig */
    protected $config;

    /** @var \Doctrine\DBAL\Connection */
    protected $conn;

    /** @var Plugin */
    protected $Plugin;

    /** @var EntityManager */
    protected $em;

    /** @var PluginRepository */
    protected $pluginRepository;

    /** @var string */
    protected $authenticationKey;

    /** @var object */
    protected $plugin;

    public function __construct(AcceptanceTester $I, $authenticationKey, $plugin)
    {
        $this->I = $I;
        $this->plugin = $plugin;
        $this->authenticationKey = $authenticationKey;
        $this->em = Fixtures::get('entityManager');
        $this->config = Fixtures::get('config');
        $this->conn = $this->em->getConnection();
        $this->pluginRepository = $this->em->getRepository(Plugin::class);
    }

    public static function start(AcceptanceTester $I)
    {
        $authenticationKey = getenv('AUTHENTICATION_KEY');
        $pluginId = getenv('PLUGIN_ID');

        $plugin = $I->getPluginByApi($authenticationKey, $pluginId);
        $result = new self($I, $authenticationKey, $plugin);

        return $result;
    }

    public function setting_key()
    {
        $this->I->wantTo('Authentication key setting');

        $this->I->amOnPage('/'.$this->config['eccube_admin_route'].'/store/plugin/authentication_setting');

        $this->I->expect('認証キーの入力を行います。');
        $this->I->fillField(['id' => 'admin_authentication_authentication_key'], $this->authenticationKey);

        $this->I->expect('認証キーの登録ボタンをクリックします。');
        $this->I->click(['css' => '.btn-ec-conversion']);
        $this->I->waitForText('保存しました');

        return $this;
    }

    public function search_by_name()
    {
        PluginSearchPage::go($this->I);
        $this->I->fillField(['id' => 'search_plugin_keyword'], $this->plugin['name']);
        $this->I->click('検索');
        $this->I->see($this->plugin['name'], '#plugin-list');

        return $this;
    }

    public function install()
    {
        PluginManagePage::go($this->I);
        $this->I->click(['xpath' => '//p[contains(text(),"'.$this->plugin['code'].'")]/ancestor::tr/td/a[contains(text(),"インストール")]']);
        
        PluginStoreInstallPage::at($this->I)->インストール();

        $plugin = $this->pluginRepository->findByCode($this->plugin['code']);
        $this->I->assertFalse($plugin->isInitialized(), '初期化されていない');
        $this->I->assertFalse($plugin->isEnabled(), '有効化されていない');
        $this->I->assertDirectoryExists($this->config['plugin_realdir'].'/'.$this->plugin['code']);

        return $this;
    }

    public function enable()
    {
        $plugin = $this->pluginRepository->findByCode($this->plugin['code']);

        PluginManagePage::go($this->I)->ストアプラグイン_有効化($this->plugin['code']);

        $this->em->refresh($plugin);
        $this->I->assertTrue($plugin->isInitialized(), '初期化されている');
        $this->I->assertTrue($plugin->isEnabled(), '有効化されている');

        return $this;
    }

    public function disable()
    {
        $plugin = $this->pluginRepository->findByCode($this->plugin['code']);
        PluginManagePage::go($this->I)->ストアプラグイン_無効化($this->plugin['code']);

        $this->em->refresh($plugin);
        $this->I->assertTrue($plugin->isInitialized(), '初期化されている');
        $this->I->assertFalse($plugin->isEnabled(), '無効化されている');

        return $this;
    }

    public function uninstall()
    {
        $plugin = $this->pluginRepository->findByCode($this->plugin['code']);
        PluginManagePage::go($this->I)->ストアプラグイン_削除($this->plugin['code']);

        $this->em->refresh($plugin);
        $plugin = $this->pluginRepository->findByCode($this->plugin['code']);
        $this->I->assertNull($plugin, '削除されている');

        return $this;
    }

    public function checkDirectoryIsRemoved()
    {
        $this->I->assertDirectoryDoesNotExist($this->config['plugin_realdir'].'/'.$this->plugin['code']);

        return $this;
    }
}