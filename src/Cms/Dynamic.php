<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Cms;

use Gsnowhawk\Common\Environment;
use Gsnowhawk\Common\Http;
use Gsnowhawk\Common;
use Gsnowhawk\ViewException;

/**
 * Show dynamic page class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Dynamic extends Section
{
    private $uri;
    private $build_type;
    public $advanced_template;

    /**
     * Object Constructer.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->uri = Http::getURI();
        $this->view->bind('isfrontend', 1);
        $this->view->bind('withoutGlobalNav', true);
        $this->appendHtmlClass(['frontend']);
    }

    /**
     * Default view.
     */
    public function defaultView()
    {
        if (empty($this->session->param('current_site'))) {
            $choice = $this->currentSiteFromURI($this->uri);
            if (empty($choice)) {
                throw new ViewException($this->uri . ' is not found', 404);
            }
            $this->request->post('choice', $choice);
            $this->changeSite();
        }

        if ($this->siteProperty('type') !== 'dynamic') {
            trigger_error('Site type mismatch', E_USER_ERROR);
        }

        // Switch request with parameters or static URI
        $id = (isset($_SERVER['REDIRECT_URL'])) ? $this->findEntryFromURI()
                                                : $this->findEntryFromParameter();

        $this->session->param('build_type', $this->build_type);

        $current_page = $this->request->get('p');
        if (!empty($current_page)) {
            $this->request->param('current_page', $current_page);
        }

        $runlevel = $this->siteProperty('runlevel');
        $this->session->clear('authenticationFrequency');
        if ($runlevel !== 'normal') {
            $this->session->param('authenticationFrequency', 'everytime');
            if ($runlevel !== 'maintenance' || (($_COOKIE['wherefrom'] ?? null) !== 'console')) {
                $this->build_type = $runlevel;
            }
        }

        $plugins = $this->app->execPlugin('beforeBuild', $this->uri, $id);

        switch ($this->build_type) {
            case 'category':
                $source = $this->buildArchive($id, true);
                break;
            case 'entry':
                $source = $this->build($id, true);
                break;
            case 'maintenance':
                $source = $this->view->render('cms/announce.tpl', false);
                break;
            case 'stealth':
                $this->notFound();
                break;
            default:
                $plugins = $this->app->execPlugin('buildDynamicContents', $this->uri);
                foreach ($plugins as $plugin) {
                    if (is_null($plugin)) {
                        continue;
                    }
                    $source = $plugin;
                    break 2;
                }

                $this->notFound();
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1');
        header('X-Content-Type-Options: nosniff');
        echo $source;
        exit(0);
    }

    /**
     * Advanced view
     *
     * @param int $id
     * @param string $template
     *
     * @return string
     */
    public function advancedView($id, $template = null)
    {
        if (!is_null($template)) {
            $this->advanced_template = $template;
        }

        return $this->build($id);
    }

    /**
     * Login failed view
     *
     * @return void
     */
    public function failed()
    {
        if (is_null($this->session->param('uname'))) {
            $this->session->param('uname', 'guest');
        }

        $path1 = parse_url($this->site_data['url'] ?? '', PHP_URL_PATH);
        $path2 = parse_url(Environment::server('request_uri'), PHP_URL_PATH);
        if (rtrim($path1, '/') === rtrim($path2, '/')) {
            $this->session->clear('direct_uri');
        }

        $this->view->bind(
            'form',
            [
                'action' => Environment::server('REQUEST_URI'),
                'method' => 'post',
                'enctype' => 'application/x-www-form-urlencoded',
            ]
        );

        $this->defaultView();
    }

    /**
     * Convert from static URL to system path
     *
     * @return string
     */
    private function urlToPath()
    {
        return '/' . ltrim(str_replace($this->siteProperty('url'), '', $this->uri), '/');
    }

    /**
     * Find entry number from static URL
     *
     * @return int|false
     */
    private function findEntryFromURI()
    {
        $url = preg_replace('/\?.*$/', '', $this->urlToPath());
        $basename = basename($url);
        if (empty($basename)) {
            $basename = '/';
        }

        $pattern = '/^(.+)\.([0-9]+)('.preg_quote($this->site_data['defaultextension'], '/').')$/';
        if (preg_match($pattern, $basename, $match)) {
            $basename = $match[1].$match[3];
            if ($basename === $this->site_data['defaultpage']) {
                $basename = basename(dirname($url));
            }
            $this->request->param('current_page', $match[2]);
        }

        if (false !== $data = $this->db->select('id', 'entry', 'WHERE sitekey = ? AND filepath = ? AND active = ?', [$this->siteID, $basename, '1'])) {
            $this->build_type = 'entry';
            if (count($data) > 1) {
                foreach ($data as $unit) {
                    if ($url === $this->getEntryPath($unit['id'], 1)) {
                        return $unit['id'];
                    }
                }
            } elseif (count($data) === 1) {
                return $data[0]['id'];
            }
        }
        if (false !== $data = $this->db->select('id', 'category', 'WHERE sitekey = ? AND path = ? AND template IS NOT NULL', [$this->siteID, $basename])) {
            $this->build_type = 'category';
            if (count($data) > 1) {
                $url = rtrim($url, '/');
                foreach ($data as $unit) {
                    if ($url === $this->getCategoryPath($unit['id'], 2)) {
                        return $unit['id'];
                    }
                }
            } elseif (count($data) === 1) {
                return $data[0]['id'];
            }
        }
        $this->build_type = null;

        return false;
    }

    /**
     * Find entry number from URL parameters
     *
     * @return int|false
     */
    private function findEntryFromParameter()
    {
        $query_string = Environment::server('query_string');
        if (empty($query_string)) {
            return self::findEntryFromURI();
        }

        $table = ($this->request->param('t') === 'ca') ? 'category' : 'entry';
        $id = $this->request->param('u');
        if ($table === 'category') {
            $id = $this->db->get('id', $table, 'sitekey = ? AND id = ? AND template IS NOT NULL', [$this->siteID, $id]);
        } else {
            $id = $this->db->get('id', $table, 'sitekey = ? AND identifier = ? AND active = ?', [$this->siteID, $id, 1]);
        }
        $this->build_type = (empty($id)) ? null : $table;

        return $id;
    }

    private function notFound(): void
    {
        $this->session->clear('direct_uri');
        header_remove('Set-Cookie');
        http_response_code(404);
        Common::pageNotFound($this->app);
    }
}
