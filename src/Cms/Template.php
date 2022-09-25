<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Cms;

use Gsnowhawk\Common\Lang;

/**
 * Template management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Template extends \Gsnowhawk\Cms\Site
{
    /*
     * Using common accessor methods
     */
    use \Gsnowhawk\Accessor;

    /**
     * kind of Template.
     *
     * @var array
     */
    private $kind_of_template = [];

    /**
     * Object constructor.
     */
    public function __construct()
    {
        call_user_func_array('parent::__construct', func_get_args());

        $this->kind_of_template = Lang::translate('KIND_OF_TEMPLATE');
    }

    /**
     * Save the data.
     */
    protected function save()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('cms.template.'.$check);

        $table = 'template';
        $skip = ['id', 'sitekey', 'create_date', 'modify_date'];

        $post = $this->request->POST();

        $valid = [];
        $valid[] = ['vl_title', 'title', 'empty'];
        $valid[] = ['vl_sourcecode', 'sourcecode', 'empty'];

        if (!$this->validate($valid)) {
            return false;
        }
        $this->db->begin();

        $fields = $this->db->getFields($this->db->TABLE($table));
        $save = [];
        $raw = [];
        foreach ($fields as $field) {
            if (in_array($field, $skip)) {
                continue;
            }
            if (isset($post[$field])) {
                $save[$field] = $post[$field];
            }
        }

        if (empty($post['id'])) {
            $raw = ['create_date' => 'CURRENT_TIMESTAMP'];
            $save['sitekey'] = $this->siteID;
            if (false !== $result = $this->db->insert($table, $save, $raw)) {
                $post['id'] = $this->db->lastInsertId(null, 'id');
                $this->db->update(
                    $table,
                    ['identifier' => $post['id']],
                    'id = ?',
                    [$post['id']]
                );
            }
        } else {
            $old_path = $this->templatePath($id, $this->siteID);
            $result = $this->db->update($table, $save, 'id = ?', [$post['id']], $raw);
        }

        if ($result !== false) {
            $modified = ($result > 0) ? $this->db->modified($table, 'id = ?', [$post['id']]) : true;
            if ($modified) {
                if ($this->request->param('publish') === 'draft') {
                    if ($result === 0) {
                        $this->app->err['vl_nochange'] = 1;
                        $result = false;
                    }
                } else {
                    if (!empty($old_path) && file_exists($old_path)) {
                        unlink($old_path);
                    }
                    if ($this->request->param('publish') === 'release') {
                        if (false === ($copy = ($result > 0))) {
                            $a = $this->db->get('modify_date', 'template', 'id = ?', [$post['id']]);
                            $b = $this->db->get('modify_date', 'template', 'identifier = ? AND active = ? AND modify_date < ?', [$post['id'], '1', $a]);
                            if (!empty($b)) {
                                $copy = true;
                            }
                        }
                        if (false === $this->buildTemplate($post, $copy)) {
                            $result = false;
                        }
                    } elseif ($this->request->param('publish') === 'global') {
                        $result = $this->db->delete('template', 'identifier = ? AND identifier <> id', [$post['id']]);
                    }
                }
            } else {
                $result = false;
            }
            if ($result !== false) {
                return $this->db->commit();
            }
        }

        $error = $this->db->error();
        if (!empty($error)) {
            trigger_error($error);
        }
        $this->db->rollback();

        return false;
    }

    protected function revokeDraft()
    {
        $id = $this->request->param('id');
        $check = 'update';
        $this->checkPermission('cms.template.'.$check);

        $save = $this->db->get(
            'sourcecode, modify_date',
            'template',
            'identifier = ? AND active = ?',
            [$id, 1]
        );

        if (empty($save)) {
            $dir = @realpath($this->app->cnf('application:cms_global_templates'));
            $origin = $this->templatePath($id, $this->siteID, false);
            $save = [
                'sourcecode' => file_get_contents($origin),
            ];
        }

        $this->db->begin();

        if (false === $this->db->update('template', $save, 'id = ?', [$id])) {
            $error = $this->db->error();
            if (!empty($error)) {
                trigger_error($error);
            }
            $this->db->rollback();

            return false;
        }

        return $this->db->commit();
    }

    /**
     * Remove template data and files.
     *
     * @return bool
     */
    protected function remove()
    {
        $this->checkPermission('cms.template.delete');

        $id = $this->request->param('delete');
        $identifier = $this->db->select('id', 'template', 'WHERE identifier = ?', [$id]);
        $files = [];
        foreach ($identifier as $unit) {
            $files[] = $this->templatePath($unit['id']);
        }
        if (false !== $this->db->delete('template', 'identifier = ?', [$id])) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            return true;
        }
        trigger_error($this->db->error());

        return false;
    }

    public function styleSheets()
    {
        $fetch = $this->db->select(
            'identifier,path',
            'template',
            'WHERE sitekey = ? AND kind = ? AND active = ?',
            [$this->siteID, 6, 1]
        );

        $style_sheets = [];
        foreach ((array)$fetch as $unit) {
            $style_sheets[] = trim($this->site_data['styledir']).'/'.$unit['path'].'.css';
        }

        return $style_sheets;
    }

    protected function status($id, $modify_date, $dir, &$context = null): string
    {
        $status = 'stop';
        $local_template = $this->templatePath($id);
        if (!empty($dir)) {
            $global_template = $dir . '/' . basename($local_template);
            if (is_file($global_template)) {
                $status = 'global';
                $context['usable'] = true;
                $context['use'] = 'global';
            }
        }
        if (is_file($local_template)) {
            $status = 'release';
            $context['use'] = 'local';
        }
        $active = $this->db->get(
            'id',
            'template',
            'identifier = ? AND active = ? AND modify_date < ?',
            [$id, '1', $modify_date]
        );
        if (!empty($active)) {
            $status = 'draft';
        } elseif ($status === 'global') {
            $sourcecode = preg_replace(
                '/(\r\n|\r|\n)/',
                PHP_EOL,
                rtrim(strval($this->db->get('sourcecode', 'template', 'id = ?', [$id])))
            );
            $global = preg_replace(
                '/(\r\n|\r|\n)/',
                PHP_EOL,
                rtrim(strval(@file_get_contents($global_template)))
            );
            if ($sourcecode !== $global) {
                $status = 'draft';
            }
        }

        return $status;
    }
}
