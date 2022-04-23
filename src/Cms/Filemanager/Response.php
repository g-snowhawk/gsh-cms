<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2018 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Cms\Filemanager;

use Gsnowhawk\Common\File;
use Gsnowhawk\Common\Http;

/**
 * Entry management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Gsnowhawk\Filemanager
{
    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind('header', [
            'title' => 'ファイル管理',
            'id' => 'filemanager',
            'class' => 'filemanager'
        ]);

        $sid = $this->session->param('current_site');

        if (empty($sid)) {
            trigger_error('No select a site', E_USER_ERROR);
        }

        $site = $this->db->get('openpath,uploaddir,url', 'site', 'id = ?', [$sid]);
        $this->session->param('filemanager_filter1', $sid);

        $path = File::realpath(implode(DIRECTORY_SEPARATOR, [
            $site['uploaddir'],
            self::USER_DIRECTORY_NAME
        ]));

        parent::setRootDirectory(File::realpath(implode(DIRECTORY_SEPARATOR, [
            $site['openpath'],
            $path
        ])));

        parent::setBaseUrl(Http::realuri(implode('/', [
            $site['url'],
            $path
        ])));
    }

    /**
     * Default view.
     */
    public function defaultView()
    {
        parent::explorer('cms-');
    }

    public function addFolder()
    {
        parent::addFolder();
    }

    public function addFile()
    {
        parent::addFile();
    }

    public function childDirectories($directory, $parent)
    {
        return parent::fileList($directory, $parent, 'directory');
    }

    public function childFiles($directory, $parent)
    {
        return parent::fileList($directory, $parent, 'file');
    }

    public function download($path)
    {
        parent::download($path);
    }

    protected function extraPermission($key, $filter1 = 0, $filter2 = 0, ...$args)
    {
        $sitekey = $this->session->param('current_site');
        if (!empty($sitekey) && $key !== 'system' && strpos($key, 'noroot') === false) {
            $owner = $this->db->get('userkey', 'site', 'id = ?', [$sitekey]);
            if ($owner === $this->uid) {
                return true;
            }
        }

        return;
    }
}
