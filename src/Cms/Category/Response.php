<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Cms\Category;

use Gsnowhawk\PermitException;
use Gsnowhawk\Common\Lang;

/**
 * Category management response class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends \Gsnowhawk\Cms\Category
{
    /**
     * Object Constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array(parent::class.'::__construct', $params);

        if (empty($this->siteID)) {
            trigger_error('No select a site', E_USER_ERROR);
        }

        $this->view->bind(
            'header',
            ['title' => Lang::translate('PAGE_TITLE'), 'id' => 'category', 'class' => 'category']
        );
    }

    /**
     * Show edit form.
     */
    public function edit()
    {
        if ($this->isAjax) {
            return $this->editSubform();
        }

        $id = $this->request->param('id');
        $this->checkRecords('category', 'id = ? AND sitekey = ?', [$id, $this->siteID]);

        $check = (empty($id)) ? 'create' : 'update';
        if ($check === 'update') {
            $parent = $this->db->nsmGetParent(
                'id',
                '(SELECT * FROM table::category WHERE sitekey = :site_id)',
                '(SELECT * FROM table::category WHERE id = :category_id)',
                ['site_id' => $this->siteID, 'category_id' => $id]
            );
            if ($this->session->param('current_category') !== intval($parent)) {
                throw new PermitException(Lang::translate('ILLEGAL_OPERATION'), 403, E_ERROR, __FILE__, __LINE__);
            }
        }

        $this->checkPermission('cms.category.'.$check);

        if ($this->request->method === 'post') {
            $post = $this->request->POST();
        } else {
            $fetch = $this->db->select(
                'id, title, tags, description, path, filepath, archive_format, template, default_template, inheritance, author_date, create_date, modify_date',
                'category',
                'WHERE id = ?',
                [$id]
            );
            if (count((array) $fetch) > 0) {
                $post = $fetch[0];
            }
        }

        // Custom fields
        $customs = $this->db->select(
            'name, data',
            'custom',
            'WHERE sitekey = ? AND relkey = ? AND kind = ?',
            [$this->siteID, $id, 'category']
        );
        foreach ((array) $customs as $unit) {
            $post[$unit['name']] = $unit['data'];
        }

        if (isset($post)) {
            $this->view->bind('post', $post);
        }

        //
        $templates = $this->db->select('id,title', 'template', 'WHERE sitekey = ? AND kind= ? AND revision = ?', [$this->siteID, 3, 0]);
        $this->view->bind('templates', $templates);

        //
        $templates = $this->db->select('id,title', 'template', 'WHERE sitekey = ? AND (kind = ? OR kind = ?) AND revision = ?', [$this->siteID, 2, 0, 0]);
        $this->view->bind('default_templates', $templates);

        $globals = $this->view->param();
        $form = $globals['form'] ?? [];
        $form['confirm'] = Lang::translate('CONFIRM_SAVE_DATA');
        $this->view->bind('form', $form);

        $this->setHtmlId('cms-category-edit');
        $this->view->render('cms/category/edit.tpl');
    }

    public function editSubform()
    {
        $response = $this->view->render('cms/category/subform.tpl', true);
        if ($this->request->method === 'post'
            && $this->request->post('request_type') !== 'response-subform'
        ) {
            return $response;
        }
        $json = [
            'status' => 200,
            'response' => $response,
        ];
        header('Content-type: text/plain; charset=utf-8');
        echo json_encode($json);
        exit;
    }
}
