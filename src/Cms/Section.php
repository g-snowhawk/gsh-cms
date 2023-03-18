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

use Gsnowhawk\Common\Text;

/**
 * Entry management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Section extends Entry
{
    /**
     * Object Constructer.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array(parent::class.'::__construct', $params);
    }

    /**
     * Save the data.
     */
    protected function save()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('cms.section.'.$check);

        $this->app->execPlugin('beforeSave');

        $table = 'section';
        $skip = [
            'id', 'sitekey', 'entrykey',
            'level', 'identifire', 'revision', 'active',
            'create_date', 'modify_date', 'lft', 'rgt',
        ];

        $entrykey = $this->request->param('eid');
        $parent_id = $this->request->param('prn');

        $post = $this->request->POST();

        $valid = [];
        $valid[] = ['vl_title', 'title', 'empty'];
        //$valid[] = ['vl_body', 'body', 'empty'];

        if (!$this->validate($valid)) {
            return false;
        }
        $this->db->begin();

        $fields = $this->db->getFields($this->db->TABLE($table));
        $save = ['revision' => '0'];
        $raw = [];
        foreach ($fields as $field) {
            if (in_array($field, $skip)) {
                continue;
            }
            if (isset($post[$field])) {
                if (!empty($post[$field] && in_array($field, $this->date_columns))) {
                    $save[$field] = date('Y-m-d H:i', Text::strtotime($post[$field]));
                    continue;
                }
                $save[$field] = (empty($post[$field])) ? null : $post[$field];
            }
        }

        if (empty($save['author_date'])) {
            $save['author_date'] = date('Y-m-d H:i');
        }

        $result = 0;
        if (empty($post['id'])) {
            $raw['create_date'] = 'CURRENT_TIMESTAMP';
            $save['sitekey'] = $this->siteID;
            $save['entrykey'] = $entrykey;

            if (empty($parent_id)) {
                $statement = 'entrykey = ? AND revision = ?';
                $options = [$entrykey, 0];
                $parent_rgt = (int) $this->db->max('rgt', 'section', $statement, $options) + 1;
                $save['lft'] = $parent_rgt;
                $save['rgt'] = $parent_rgt + 1;
            } else {
                $parent_rgt = $this->db->get('rgt', 'section', 'id = ?', [$parent_id]);

                $save['lft'] = $parent_rgt;
                $save['rgt'] = $parent_rgt + 1;

                $update_parent = $this->db->prepare($this->db->nsmBeforeInsertChildSQL('section'));
                if (false === $update_parent->execute(['parent_rgt' => $parent_rgt, 'offset' => 2])) {
                    trigger_error($this->db->error());

                    return false;
                }
            }

            if (!empty($parent_id)) {
                $level = $this->db->get('level', 'section', 'id = ?', [$parent_id]);
                $save['level'] = $level + 1;
                if ($save['level'] > 6) {
                    return false;
                }
                $this->request->param('level', $save['level']);
            }

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
            $result = $this->db->update($table, $save, 'id = ?', [$post['id']], $raw);
        }
        if ($result !== false) {
            $modified = ($result > 0) ? $this->db->modified($table, 'id = ?', [$post['id']]) : true;

            // Save upload files
            $file_count = $this->saveFiles($entrykey, $post['id']);

            // Save custom fields
            $customs = [];
            foreach ($post as $key => $value) {
                if (strpos($key, 'cst_') === 0) {
                    $customs[$key] = $value;
                }
            }
            $custom_count = $this->saveCustomField('section', $post['id'], $customs);

            if ($modified !== false && $file_count !== false && $custom_count !== false) {
                $others = $file_count + $custom_count;
                if ($others > 0) {
                    $this->db->modified(self::ENTRY_TABLE, 'id = ?', [$post['id']]);
                }

                $result += $others;

                if ($this->request->param('publish') === 'release') {
                    $status = $this->db->get('status', 'section', 'id = ?', [$post['id']]);
                    $copy = ($result > 0 || $status !== 'release');
                    if (false === $this->releaseSection($post, $copy)) {
                        $result = false;
                    }

                    if ($this->siteProperty('type') === 'static') {
                        // Rebuild entry file
                        $entrykey = $this->db->get('id', 'entry', 'identifier = ? AND active = 1', [$entrykey]);
                        if (!empty($entrykey)) {
                            $this->createEntryFile($entrykey);
                            $this->buildArchives($entrykey);
                        }
                    }
                } elseif ($this->request->param('publish') === 'private') {
                    if (false === $this->toPrivate($post)) {
                        $result = false;
                    }
                } else {
                    if ($result === 0) {
                        $this->app->err['vl_nochange'] = 1;
                        $result = false;
                    } else {
                        $this->db->update($table, ['status' => $this->request->param('publish')], 'id = ?', [$post['id']], $raw);
                    }
                }
            } else {
                $result = false;
            }

            $this->app->execPlugin('saved');

            if ($result !== false) {
                return $this->db->commit();
            }
        } else {
            trigger_error($this->db->error());
        }
        $this->db->rollback();

        return false;
    }

    /**
     * a Public entry to Private.
     *
     * @param array $post
     *
     * @return bool
     */
    protected function toPrivate($post): bool
    {
        $this->checkPermission('cms.section.publish');

        $return_value = true;

        $sectionkey = $post['id'];
        $entrykey = $this->db->get('entrykey', 'section', 'id = ?', [$sectionkey]);
        $active_sectionkey = $this->db->get('id', 'section', 'identifier = ? AND active = ?', [$sectionkey, 1]);

        if (false === $ret = $this->db->update('section', ['active' => '0'], 'identifier = ?', [$sectionkey])) {
            return false;
        }
        if ($ret > 0) {
            $this->db->update('section', ['status' => $this->request->param('publish')], 'id = ?', [$id], $raw);
        }

        if ($this->siteProperty('type') === 'static') {
            if (false === $this->createEntryFile($entrykey)
                || false === $this->buildArchives($entrykey)
            ) {
                $return_value = false;
            }
        }

        return $return_value;
    }

    /**
     * Remove data.
     *
     * @return bool
     */
    protected function remove()
    {
        $this->checkPermission('cms.section.delete');

        $sectionkey = $this->request->param('remove');
        $entrykey = $this->getEntryKey($sectionkey);
        $sitekey = $this->siteID;

        // Remove attachment files
        $directories = $this->db->select('id', 'section', 'WHERE identifier = ?', [$sectionkey]);
        foreach ($directories as $directory) {
            $this->removeFiles($entrykey, $directory['id']);
        }

        $this->db->begin();

        $unit = $this->db->get('lft, rgt', 'section', 'id = ?', [$sectionkey]);

        $rebuild = $this->app->cnf('application:cms_build_at_remove_section');
        if (false !== $this->db->delete('section', 'entrykey = ? AND revision = ? AND lft BETWEEN ? AND ?', [$entrykey, '0', $unit['lft'], $unit['rgt']])
            && ($rebuild !== 'immediately' || false !== $this->cleanupRevisions('section', $sitekey, $entrykey))
            && false !== $this->db->nsmCleanup('section', 'WHERE sitekey = ? and entrykey = ? and revision = ?', [$sitekey, $entrykey, '0'])
            && false !== $this->db->delete('section', 'identifier = ?', [$sectionkey])
        ) {
            // Rebuild entry file
            if ($rebuild === 'immediately' && !empty($entrykey)) {
                $this->createEntryFile($entrykey);
                $this->buildArchives($entrykey);
            }

            return $this->db->commit();
        }
        trigger_error($this->db->error());
        $this->db->rollback();

        return false;
    }

    /**
     * Parent section.
     *
     * @param int $sectionkey
     *
     * @return int
     */
    public function getParentSection($sectionkey)
    {
        if (empty($sectionkey)) {
            return $this->request->param('prn');
        }
        $entrykey = $this->getEntryKey($sectionkey);

        $parent = '(SELECT * FROM table::section WHERE entrykey = :entry_id AND revision = 0)';
        $children = '(SELECT * FROM table::section WHERE id = :section_id)';

        return $this->db->nsmGetParent('parent.id', $parent, $children, ['entry_id' => $entrykey, 'section_id' => $sectionkey]);
    }

    /**
     * Entry of the section.
     *
     * @param int $sectionkey
     *
     * @return mixed
     */
    public function getEntryKey($sectionkey)
    {
        if (empty($sectionkey)) {
            return $this->request->param('eid');
        }

        return $this->db->get('entrykey', 'section', 'id = ?', [$sectionkey]);
    }
}
