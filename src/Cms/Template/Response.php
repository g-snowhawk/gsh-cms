<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Cms\Template;

use Gsnowhawk\Common\Lang;

/**
 * Template management request response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Gsnowhawk\Cms\Template
{
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
            ['title' => 'テンプレート管理', 'id' => 'template', 'class' => 'template']
        );
    }

    /**
     * Default view.
     */
    public function defaultView()
    {
        $this->checkPermission('cms.template.read');

        $templates = $this->db->select(
            'id ,title, kind, create_date, modify_date',
            'template',
            'WHERE sitekey = ? AND revision = ? ORDER BY kind, create_date',
            [$this->siteID, 0]
        );

        $dir = @realpath($this->app->cnf('application:cms_global_templates'));
        foreach ($templates as &$unit) {
            $unit['status'] = self::status($unit['id'], $unit['modify_date'], $dir);
        }
        unset($unit);

        $sort = [
            'draft' => 0,
            'release' => 1,
            'global' => 2,
            'stop' => 3,
        ];

        usort($templates, function ($a, $b) use ($sort) {
            $as = $a['kind'] . $sort[$a['status']] . $a['create_date'];
            $bs = $b['kind'] . $sort[$b['status']] . $b['create_date'];

            return strcmp($as, $bs);
        });

        $this->view->bind('templates', $templates);
        $this->view->bind('kinds', $this->kind_of_template);

        $this->setHtmlId('cms-template-default');

        $globals = $this->view->param();
        $form = $globals['form'] ?? [];
        $form['confirm'] = Lang::translate('CONFIRM_DELETE_DATA');
        $this->view->bind('form', $form);

        $this->view->render('cms/template/default.tpl');
    }

    /**
     * Show edit form.
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $this->checkRecords('template', 'id = ? AND sitekey = ?', [$id, $this->siteID]);

        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('cms.template.'.$check);

        if ($this->request->method === 'post') {
            $post = $this->request->POST();
        } else {
            $param_key = 'id';
            $columns = ['id','title','sourcecode','kind','path','create_date','modify_date'];
            if (!empty($this->request->param('cp'))) {
                $param_key = 'cp';
                $columns = ['title','sourcecode','kind'];
            }
            $post = $this->db->get(
                implode(',', $columns),
                'template',
                'id = ? AND sitekey = ?',
                [$this->request->param($param_key), $this->siteID]
            );
            if (!empty($this->request->param('cp'))) {
                $post['title'] .= ' (Copied)';
            }
        }
        $post['publish'] = 'draft';

        if (!empty($post['id'])) {
            $dir = @realpath($this->app->cnf('application:cms_global_templates'));
            $context = ['usable' => false, 'use' => null];
            $status = self::status($post['id'], $post['modify_date'], $dir, $context);
            $this->view->bind('status', $status);
            $this->view->bind('context', $context);
        }

        $this->view->bind('post', $post);
        $this->view->bind('kinds', $this->kind_of_template);

        $globals = $this->view->param();
        $form = $globals['form'] ?? [];
        $form['confirm'] = Lang::translate('CONFIRM_SAVE_DATA');
        $this->view->bind('form', $form);

        $this->setHtmlId('cms-template-edit');
        $this->view->render('cms/template/edit.tpl');
    }
}
