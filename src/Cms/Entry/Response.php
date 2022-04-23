<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Cms\Entry;

use Gsnowhawk\Common\Environment;
use Gsnowhawk\Common\File;
use Gsnowhawk\Common\Http;
use Gsnowhawk\Common\Lang;
use Gsnowhawk\Common\Variable;
use Gsnowhawk\PermitException;

/**
 * Entry management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Gsnowhawk\Cms\Entry
{
    public const DEFAULT_MODE = 'cms.entry.response';
    public const DEFAULT_VIEW_ID = 'cms-entry-default';
    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);

        if (empty($this->siteID)) {
            trigger_error('No select a site', E_USER_ERROR);
        }

        $this->view->bind(
            'header',
            ['title' => 'エントリ管理', 'id' => 'entry', 'class' => 'entry']
        );
    }

    /**
     * Default view.
     */
    public function defaultView()
    {
        $this->checkPermission('cms.entry.read', $this->siteID, $this->site_data['rootcategory']);

        $plugins = $this->app->execPlugin('prepareDefaultView', self::DEFAULT_VIEW_ID);

        // Change category when current category is system reserved
        $reserved = $this->db->get('reserved', 'category', 'id=?', [$this->session->param('current_category')]);
        if ($reserved === '1') {
            if (!is_null($this->session->param('escape_current_category'))) {
                $this->setCategory($this->session->param('escape_current_category'));
                $this->session->clear('escape_current_category');
            } else {
                $this->setCategory();
            }
            $this->setCategory($this->session->param('current_category'));
        }

        $this->pager->setLinkFormat($this->app->systemURI().'?mode='.self::DEFAULT_MODE.'&p=%d');
        $this->view->bind('pager', $this->pager);

        $form = $this->view->param('form');
        $form['confirm'] = Lang::translate('CONFIRM_DELETE_DATA');
        $this->view->bind('form', $form);

        $this->view->bind('err', $this->app->err);

        $this->setHtmlId('entry-default');

        if ($this->isAjax) {
            return $this->view->render('cms/entry/default.tpl', true);
        }
        parent::defaultView(self::DEFAULT_VIEW_ID);
    }

    /**
     * Show edit form.
     */
    public function edit()
    {
        if (!is_null($this->request->param('rel'))) {
            $eid = $this->request->param('rel');
            $cid = $this->db->get('category', 'entry', 'id = ?', [$eid]);
            $this->setCategory($cid);
            $this->request->param('id', $eid);
            $this->request->param('rel', null, true);
        }

        $id = $this->request->param('id');
        $this->checkRecords('entry', 'id = ? AND sitekey = ?', [$id, $this->siteID]);

        $check = (empty($id)) ? 'create' : 'update';

        if (!is_null($this->request->param('ccp'))) {
            $cid = $this->db->get('id', 'category', 'path=?', [$this->request->param('ccp')]);
            $this->setCategory($cid);
        }

        if ($check === 'update') {
            $current = $this->session->param('current_category');
            if (is_int($current)) {
                $current = strval($current);
            }

            $parent = $this->db->get('category', 'entry', 'id = ?', [$id]);
            if (is_int($parent)) {
                $parent = strval($parent);
            }

            if ($current !== $parent) {
                throw new PermitException(Lang::translate('ILLEGAL_OPERATION'), 403, E_ERROR, __FILE__, __LINE__);
            }
        }

        $this->checkPermission('cms.entry.'.$check);

        if ($this->request->method === 'post') {
            $post = $this->request->POST();
        } else {
            $post = ['author_date' => ''];
            $fields = array_diff(
                $this->db->getFields('entry'),
                ['sitekey','userkey','category','path','identifier','revision','active','status']
            );
            $fetch = $this->db->get(
                implode(',', $fields),
                'entry',
                'id = ?',
                [$this->request->param('id')]
            );
            if (!empty($fetch)) {
                $post = $fetch;
                foreach ($this->date_columns as $x_date) {
                    if (!empty($post[$x_date])) {
                        $post[$x_date] = date($this->date_columns_format, strtotime($post[$x_date]));
                    }
                }
                foreach ($this->bit_columns as $x_bit) {
                    if (!empty($post[$x_bit])) {
                        $post[$x_bit] = Variable::decToBitArray($post[$x_bit] ?? 0);
                    }
                }
            } else {
                foreach ($fields as $key) {
                    if (!is_null($this->request->GET($key))) {
                        if (in_array($key, $this->date_columns)) {
                            $post[$key] = date($this->date_columns_format, strtotime($this->request->GET($key)));
                            continue;
                        }
                        $post[$key] = $this->request->GET($key);
                    }
                }
            }

            if (empty($post['category'])) {
                $post['category'] = $this->db->get('id', 'category', 'sitekey = ? AND id = ?', [$this->siteID, $this->session->param('current_category')]);
            }

            if (empty($post['template'])) {
                $post['template'] = $this->db->get('default_template', 'category', 'sitekey = ? AND id = ?', [$this->siteID, $this->session->param('current_category')]);
            }
        }
        if (empty($post['publish'])) {
            $post['publish'] = 'draft';
        }
        if (empty($post['filepath'])) {
            $post['filepath'] = 'doc'.date('ymdhis').$this->site_data['defaultextension'];
        }

        // Revisions
        if (!empty($post['id'])) {
            $revisions = $this->db->select(
                'revision',
                'entry',
                'WHERE identifier = ? AND revision > ? AND modify_date < ? ORDER BY revision DESC',
                [$post['id'], 0, $post['modify_date']]
            );
            if (!empty($revisions)) {
                $this->view->bind('revisions', array_column($revisions, 'revision'));
            }
        }

        // Custom fields
        $customs = $this->db->select(
            'name, data, note',
            'custom',
            'WHERE sitekey = ? AND relkey = ? AND kind = ? AND name LIKE ?',
            [$this->siteID, $id, 'entry', 'cst_%']
        );
        foreach ((array) $customs as $unit) {
            $post[$unit['name']] = $unit['data'];
        }

        // Convert datetime
        foreach (['release_date', 'close_date', 'author_date'] as $key) {
            if (isset($post[$key]) && !empty($post[$key])) {
                try {
                    $date = new \DateTime($post[$key]);
                    $post[$key] = $date->format('Y-m-d\TH:i');
                } catch (\Exception $e) {
                    //
                }
            }
        }

        $this->view->bind('post', $post);

        $category_reservation = (empty($post['category']))
            ? null : $this->db->get('reserved', 'category', 'sitekey = ? AND id = ?', [$this->siteID, $post['category']]);
        $this->view->bind('category_reservation', $category_reservation);

        // Files
        $custom = [];
        $data = $this->db->select('*', 'custom', 'WHERE kind = ? AND relkey != ? AND relkey = ? AND name LIKE ? ORDER BY `sort`', ['entry', 0, $id, 'file.%']);
        foreach ((array)$data as $unit) {
            $name = $unit['name'];
            if (strpos($name, 'file.') === 0) {
                $name = 'file';
            }
            unset($unit['name']);
            if (!isset($custom[$name])) {
                $custom[$name] = [];
            }
            if ($name === 'file') {
                $unit['title'] = basename($unit['data']);
            }
            $custom[$name][] = $unit;
        }
        $this->view->bind('custom', $custom);

        //
        $templates = $this->db->select(
            'id,title',
            'template',
            'WHERE sitekey = ? AND kind IN(0,2) AND revision = 0',
            [$this->siteID]
        );
        $this->view->bind('templates', $templates);

        // Revision
        $revision = $this->db->get('revision', 'entry', 'identifier = ? AND active = ?', [$id, 1]);
        $this->view->bind('revision', $revision);

        $globals = $this->view->param();
        $form = $globals['form'] ?? [];
        $form['confirm'] = Lang::translate('CONFIRM_SAVE_DATA');
        $form['enctype'] = 'multipart/form-data';
        $this->view->bind('form', $form);

        if (!is_null($this->request->param('eid'))) {
            $this->view->bind('relayentry', $this->request->param('eid'));
        }

        $this->setHtmlClass(['entry', 'entry-edit']);

        parent::defaultView('cms-entry-edit');
    }

    /**
     * Preview Entry.
     */
    public function preview()
    {
        $this->session->param('ispreview', 1);

        // Save temporary image files
        $this->removePreviewImages();
        $this->saveFiles(parent::PREVIEW_FILES_DIR);
        $files = $this->request->files();
        foreach ($files as $key => $value) {
            if (strpos($key, 'cst_') === 0) {
                $this->saveFiles(parent::PREVIEW_FILES_DIR, null, $error, $key);
            }
        }

        $source = $this->build($this->request->param('id'), true);

        // Clear session data for preview
        $this->session->clear('preview_attachments');
        $this->session->clear('preview_data');
        $this->session->clear('ispreview');

        // Security Headers
        Http::responseHeader('X-Frame-Options', 'SAMEORIGIN');
        Http::responseHeader('X-XSS-Protection', $this->app->cnf('application:cms_preview_xssp') ?? '1');
        Http::responseHeader('X-Content-Type-Options', 'nosniff');
        if (!empty($csp = $this->app->cnf('application:cms_preview_csp'))) {
            Http::responseHeader('Content-Security-Policy', $csp);
        }

        echo $source;
        exit;
    }

    public function beforeUnloadPreview()
    {
        $this->removePreviewImages();
    }

    /**
     * Reassemble the site.
     */
    public function reassembly()
    {
        // Can uses async
        //$enable_async = false;
        //$disable_functions = array_map('trim', explode(',', ini_get('disable_functions')));
        //if (!in_array('exec', $disable_functions)) {
        //    $enable_cli = exec(
        //        $this->nohup() . ' ' . $this->phpCLI() . ' --version',
        //        $response, $status
        //    );

        //    if ($status === 0) {
        //        $this->view->bind('runAsyncBy', uniqid('pol'));
        //        $this->view->bind('confirmReassembly', Lang::translate('CONFIRM_REASSEMBLY'));
        //        $enable_async = true;
        //    }
        //}

        //if (false === $enable_async) {
        //    $form = $this->view->param('form');
        //    $form['confirm'] = Lang::translate('CONFIRM_REASSEMBLY');
        //    $this->view->bind('form', $form);
        //}

        $this->view->bind('confirmReassembly', Lang::translate('CONFIRM_REASSEMBLY'));
        $this->view->bind('runAsyncBy', uniqid('pol'));

        $this->setHtmlId('cms-entry-reassembly');
        $this->view->render('cms/entry/reassembly.tpl');
    }

    public function ajaxImageList()
    {
        $response = [
            'status' => 0,
            'upload_max_filesize' => File::strTobytes(ini_get('upload_max_filesize')),
            'post_max_size' => File::strTobytes(ini_get('post_max_size')),
        ];
        if (false !== $list = $this->imageList('-1')) {
            $response['list'] = $list;
        } else {
            $response['status'] = 1;
            $response['message'] = 'Database Error';
        }

        header('Content-type: application/json; charset=utf-8');
        echo json_encode($response);
        exit;
    }

    public function pollingReassembly()
    {
        $polling_file = $this->echoPolling(['message' => Lang::translate('SUCCESS_REASSEMBLY')]);
    }

    public function trash()
    {
        $form = $this->view->param('form');
        $form['confirm'] = Lang::translate('CONFIRM_DELETE_DATA');
        $this->view->bind('form', $form);

        if ($cookie = Environment::cookie('script_referer')) {
            $this->view->bind('referer', $cookie);
        }

        $this->pager->setLinkFormat($this->app->systemURI().'?mode=cms.entry.response:trash&p=%d');
        $this->view->bind('pager', $this->pager);
        //$this->view->bind('err', $this->app->err);

        $this->setHtmlId('cms-trash');

        parent::defaultView('cms-entry-trash');
    }

    public function setCurrentCategory($category)
    {
        parent::setCategory($category);
    }
}
