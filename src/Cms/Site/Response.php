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

use Gsnowhawk\Common\Environment as Env;
use Gsnowhawk\Common\Http;
use Gsnowhawk\Common\Lang;

/**
 * Site management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Gsnowhawk\Cms\Site
{
    public const SITELIST_PAGE_KEY = 'sitelist_page';

    private $rows_per_page = 10;

    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        $this->view->bind(
            'header',
            ['title' => 'サイト管理', 'id' => 'site', 'class' => 'site']
        );
    }

    /**
     * Default view.
     */
    public function defaultView()
    {
        $this->checkPermission('cms.exec');

        if (is_null($this->request->param('mode'))
         && !is_null($this->session->param('current_site'))
        ) {
            Http::redirect($this->app->systemURI().'?mode=cms.entry.response');
        }

        if (!empty($this->request->param('p'))) {
            $this->session->param(self::SITELIST_PAGE_KEY, $this->request->param('p'));
        }

        $options = [];
        $sql = 'SELECT id, url, title, description, runlevel, type FROM table::site';
        if ($this->userinfo['admin'] !== 1) {
            $where = [];

            $owners = (array) parent::siteOwners($this->db, $this->uid);
            if (count($owners) > 0) {
                $where[] = 'userkey IN ('.implode(',', array_fill(0, count($owners), '?')).')';
                $options = array_merge($options, $owners);
            }

            $filtered = (array)parent::filteredSite($this->db, $this->uid);
            if (count($filtered) > 0) {
                $where[] = 'id IN ('.implode(',', array_fill(0, count($filtered), '?')).')';
                $options = array_merge($options, $filtered);
            }

            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
        }

        $this->app->execPlugin('cmsSiteSelectOption', [&$sql, &$options]);

        $post = [];
        $order = $_COOKIE['sort_order_sitelist'] ?? 'DESC';
        $sql .= ' ORDER by create_date ' . $order;
        $post['sort_order_sitelist'] = $order;

        // Pagenation
        $current_page = (int)$this->session->param(self::SITELIST_PAGE_KEY) ?: 1;
        $rows_per_page = intval($_COOKIE['rows_per_page_sitelist'] ?? $this->rows_per_page);
        $total_count = $this->db->recordCount($sql, $options);
        $offset_list = $rows_per_page * ($current_page - 1);
        $pager = clone $this->pager;
        $pager->init($total_count, $rows_per_page);
        $pager->setCurrentPage($current_page);
        $pager->setLinkFormat($this->app->systemURI().'?mode='.parent::DEFAULT_MODE.'&p=%d');
        $this->view->bind('pager', $pager);
        $sql .= " LIMIT $offset_list,$rows_per_page";
        $post['rows_per_page_sitelist'] = $rows_per_page;

        $this->view->bind('post', $post);

        $sites = $this->db->getAll($sql, $options);
        $this->view->bind('sites', $sites);

        $this->setHtmlId('cms-site-default');
        $this->view->render('cms/site/default.tpl');
    }

    /**
     * Show edit form.
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $this->checkRecords('site', 'id = ?', [$id]);

        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('cms.site.'.$check, $this->request->param('id'));

        if ($this->request->method === 'post'
            && $this->request->param('convert_request_method') !== 'get'
        ) {
            $post = $this->request->POST();
        } elseif (empty($id)) {
            // Default values
            $post = [
                     'defaultpage' => 'index.htm',
                'defaultextension' => '.htm',
                        'styledir' => 'style',
                       'uploaddir' => 'upload',
                         'maskdir' => '0755',
                        'maskfile' => '0644',
                        'maskexec' => '0755',
                     'maxrevision' => 0,
            ];
            $port = Env::server('server_port');
            $post['url'] = 'http'
                 . (($port === '443' || Env::server('https') === 'on') ? 's' : '')
                 . '://'
                 . Env::server('http_host') . '/';

            if (!empty($style = $this->app->cnf('application:cms_site_default_style'))) {
                $post['style'] = $style;
            }
        } else {
            $post = $this->db->selectSingle('*', 'site', 'WHERE id = ?', [$id]);
        }
        $this->view->bind('post', $post);

        // Site owner candidate
        $this->view->bind('owners', $this->siteOwnerCandidates());

        $globals = $this->view->param();
        $form = $globals['form'] ?? [];
        $form['confirm'] = Lang::translate('CONFIRM_SAVE_DATA');
        $this->view->bind('form', $form);

        $form['confirm'] = Lang::translate('CONFIRM_DELETE_DATA');
        $this->view->bind('subform', $form);

        $this->view->bind('err', $this->app->err);

        $this->app->execPlugin('beforeRendering', __FUNCTION__);

        $this->setHtmlId('cms-site-edit');
        $this->view->render('cms/site/edit.tpl');
    }

    /**
     * Site owner candidate.
     *
     * @return mixed
     */
    private function siteOwnerCandidates()
    {
        $children = $this->childUsers($this->uid, 'id');
        $options = [$this->uid];
        foreach ($children as $unit) {
            $options[] = $unit['id'];
        }
        $sql = 'SELECT id,company,fullname
                  FROM table::user
                 WHERE id IN ('.implode(',', array_fill(0, count($options), '?')).')';

        return $this->db->getAll($sql, $options);
    }
}
