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

namespace Helper;

use Codeception\Util\Fixtures;
use Eccube\Common\Constant;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class Acceptance extends \Codeception\Module
{
    private $plugin = null;

    public function _initialize()
    {
        $this->clearDownloadDir();
    }

    private function clearDownloadDir()
    {
        $downloadDir = dirname(__DIR__).'/_downloads/';
        if (file_exists($downloadDir)) {
            $files = scandir($downloadDir);
            $files = array_filter($files, function ($fileName) use ($downloadDir) {
                return is_file($downloadDir.$fileName) && (strpos($fileName, '.') != 0);
            });
            foreach ($files as $f) {
                unlink($downloadDir.$f);
            }
        }
    }

    public function getBaseUrl()
    {
        return $this->getModule('WebDriver')->_getUrl();
    }

    public function getPluginByApi($authenticationKey, $pluginId)
    {
        if ($this->plugin !== null) {
            return $this->plugin;
        }

        $config = Fixtures::get('config');
        $url = $config->get('eccube_package_api_url').'/plugins/purchased';

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => array(
                    'X-ECCUBE-KEY: '.$authenticationKey,
                    'X-ECCUBE-VERSION: '.Constant::VERSION,
                ),
            )
        ));
            
        $result = json_decode(file_get_contents($url, false, $context), true);
        $this->plugin = array_reduce($result, function ($carry, $item) use ($pluginId) {
            if ($item['id'] == $pluginId) {
                $carry = $item;
            }
            return $carry;
        }, []);

        return $this->plugin;
    }
}
