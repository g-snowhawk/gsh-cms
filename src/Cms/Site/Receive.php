<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Cms\Site;

use Gsnowhawk\Common\File;
use Gsnowhawk\Common\Http;
use Gsnowhawk\Common\Lang;

/**
 * Site management request receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receive extends Response
{
    /**
     * Select site.
     */
    public function select()
    {
        self::change();
        Http::redirect($this->app->systemURI());
    }

    /**
     * Change current site.
     */
    public function change()
    {
        parent::changeSite();
    }

    /**
     * Save the data.
     */
    public function save()
    {
        $message = 'SUCCESS_SAVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], 'cms.site.response'];

        if (!parent::save()) {
            $message = 'FAILED_SAVE';
            $status = 1;
            $options = [
                [[$this->view, 'bind'], ['err', $this->app->err]],
            ];
            $response = [[$this, 'edit'], null];
        }

        $this->postReceived(Lang::translate($message), $status, $response, $options);
    }

    /**
     * Remove the data.
     */
    public function remove()
    {
        if (parent::remove()) {
            $this->session->param('messages', Lang::translate('SUCCESS_REMOVED'));
            Http::redirect($this->app->systemURI().'?mode=cms.site.response');
        }
        $this->request->param('convert_request_method', 'get');
        $this->edit();
    }

    public function cleanupSiteData()
    {
        $sitekey = $this->request->param('id');
        if (empty($sitekey)) {
            return false;
        }

        // Cleanup upload directory
        $entries = $this->db->select('id,identifier', 'entry', 'WHERE sitekey = ?', [$sitekey]);
        if (!empty($entries)) {
            $origin = [];
            $copies = [];
            foreach ($entries as $unit) {
                if ($unit['id'] !== $unit['identifier']) {
                    $copies[] = $unit['id'];
                } else {
                    $origin[] = $unit['id'];
                }
            }

            $site = $this->db->get('openpath,uploaddir', 'site', 'id = ?', [$sitekey]);
            $dir = rtrim($site['openpath'], '/') . '/' . trim($site['uploaddir'], '/');
            if (is_dir($dir)) {
                foreach (scandir($dir) as $item) {
                    $path = "$dir/$item";
                    if (!preg_match('/^[0-9]+$/', $item) || !is_dir($path)) {
                        continue;
                    }

                    if (File::isEmpty($path)) {
                        @rmdir($path);
                    } elseif (!in_array($item, $copies) && !in_array($item, $origin)) {
                        File::rmdir($path, true);
                    } elseif (in_array($item, $origin)) {
                        $sections = $this->db->select('id', 'section', 'WHERE sitekey = ? and entrykey = ?', [$sitekey, $item]);
                        if (!empty($sections)) {
                            $section = array_column($sections, 'id');
                            foreach (scandir($path) as $item_s) {
                                $path_s = "$path/$item_s";
                                if (!preg_match('/^[0-9]+$/', $item_s) || !is_dir($path_s)) {
                                    continue;
                                }

                                if (File::isEmpty($path_s)) {
                                    @rmdir($path_s);
                                } elseif (!in_array($item_s, $section)) {
                                    File::rmdir($path_s, true);
                                }
                            }
                        }
                        if (File::isEmpty($path)) {
                            @rmdir($path);
                        }
                    }
                }
            }
        }

        $this->session->param('messages', Lang::translate('SUCCESS_CLEANUP_SITE'));
        Http::redirect($this->app->systemURI().'?mode=cms.site.response:edit&id='.urlencode($sitekey));
    }
}
