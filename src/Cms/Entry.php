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

use ErrorException;
use Exception;
use Imagick;
use Gsnowhawk\Common\File;
use Gsnowhawk\Common\Text;

/**
 * Entry management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Entry extends Category
{
    public const ENTRY_TABLE = 'entry';
    public const SECTION_TABLE = 'section';
    public const PREVIEW_FILES_DIR = 'preview';

    /**
     * Date format columns
     *
     * @var array
     */
    protected $date_columns = ['release_date', 'close_date', 'author_date'];
    protected $bit_columns = ['acl'];
    protected $date_columns_format = 'Y/m/d H:i';

    /**
     * Object Constructer.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array('parent::__construct', $params);
    }

    /**
     * Save the data.
     */
    protected function save()
    {
        $entrykey = $this->request->param('id');
        $check = (empty($entrykey)) ? 'create' : 'update';
        $this->checkPermission('cms.entry.'.$check);

        $this->app->execPlugin('beforeSave');

        $skip = [
            'id', 'sitekey', 'userkey',
            'path', 'identifier', 'revision',
            'status',
            'create_date', 'modify_date',
        ];

        $post = $this->request->POST();
        $release_date = $post['release_date'] ?? null;

        $valid = [];
        $valid[] = ['vl_title', 'title', 'empty'];
        //$valid[] = ['vl_body', 'body', 'empty'];
        $valid[] = ['vl_category', 'category', 'empty'];
        $valid[] = ['vl_filepath', 'filepath', 'empty'];
        $valid[] = ['vl_template', 'template', 'empty'];
        $valid[] = ['vl_description', 'description', 'disallowtags', 2];
        $valid[] = ['vl_release_period', 'release_date', 'datetime_format', 1, 'allowempty'];
        $valid[] = ['vl_release_period', 'close_date', 'datetime_format', 2, 'allowempty'];
        $valid[] = ['vl_release_period', 'close_date', 'gt_datetime', 3, $release_date, 'allowempty'];
        $valid[] = ['vl_author_date', 'author_date', 'datetime_format', 1, 'allowempty'];

        if (!$this->validate($valid)) {
            return false;
        }

        // Check exists
        $statement = 'where sitekey = ? and category = ? and filepath = ?';
        $options = [$this->siteID, $post['category'], $post['filepath']];
        if (!empty($post['id'])) {
            $statement .= ' and identifier <> ?';
            $options[] = $post['id'];
        }
        $entries = $this->db->select('identifier', 'entry', $statement, $options);
        if (!empty($entries)) {
            $exists = array_column($entries, 'identifier');
            if (empty($post['id']) || in_array($post['id'], $exists)) {
                $this->app->err['vl_filepath'] = 99;

                return false;
            }
        }

        $this->db->begin();

        $fields = $this->db->getFields(self::ENTRY_TABLE, true);
        $save = ['revision' => '0'];
        $raw = [];
        foreach ($fields as $unit) {
            $field = $unit['Field'];
            $null = ($unit['Null'] === 'YES');
            if (in_array($field, $skip)) {
                continue;
            }
            if (isset($post[$field])) {
                if (!empty($post[$field] && in_array($field, $this->date_columns))) {
                    $save[$field] = date('Y-m-d H:i', Text::strtotime($post[$field]));
                    continue;
                }

                if (!empty($post[$field] && in_array($field, $this->bit_columns))) {
                    $bit = 0;
                    foreach ($post[$field] as $value) {
                        $bit = $bit|intval($value);
                    }
                    $save[$field] = $bit;
                    continue;
                }

                $save[$field] = ($post[$field] === '' && $null) ? null : $post[$field];
            }
        }

        // NULL is not empty to foreign key.
        if (empty($save['template'])) {
            $save['template'] = null;
        }

        if (empty($save['author_date'])) {
            $save['author_date'] = date('Y-m-d H:i');
        }

        $result = 0;
        $origin = null;
        if (empty($post['id'])) {
            $raw['create_date'] = 'CURRENT_TIMESTAMP';
            $save['sitekey'] = $this->siteID;
            $save['userkey'] = $this->uid;
            $save['category'] = $save['category'] ?? $this->categoryID;
            if (false !== $result = $this->db->insert(self::ENTRY_TABLE, $save, $raw)) {
                $entrykey = $this->db->lastInsertId(null, 'id');
                $this->db->update(
                    self::ENTRY_TABLE,
                    ['identifier' => $entrykey],
                    'id = ? AND sitekey = ?',
                    [$entrykey, $this->siteID]
                );
                $post['id'] = $entrykey;
            }
        } else {
            $statement = 'id = ? AND sitekey = ?';
            $options = [$post['id'], $this->siteID];
            $origin = $this->db->get('id,category,path,filepath', self::ENTRY_TABLE, $statement, $options);
            $origin['release_path'] = $this->getEntryPath($origin['id']);
            $result = $this->db->update(self::ENTRY_TABLE, $save, $statement, $options, $raw);
        }
        if ($result !== false) {
            $modified = ($result > 0) ? $this->db->modified(self::ENTRY_TABLE, 'id = ?', [$post['id']]) : true;

            // Save upload files
            $file_count = $this->saveFiles($post['id']);

            // Save custom fields
            $customs = [];
            foreach ($post as $key => $value) {
                if (strpos($key, 'cst_') === 0) {
                    $customs[$key] = $value;
                }
            }

            // Upload custom images
            foreach ($this->request->files() as $key => $value) {
                if (strpos($key, 'cst_') === 0) {
                    $customs[$key] = $value;
                }
            }

            $custom_count = $this->saveCustomField('entry', $post['id'], $customs);

            $relation = $this->createRelation($this->request->param('eid'), $post['id']);

            if ($modified !== false && $file_count !== false && $custom_count !== false) {
                $others = $file_count + $custom_count;
                $plugin_result = $this->app->execPlugin('afterSave', $post);
                foreach ($plugin_result as $plugin_count) {
                    if (false === $plugin_count) {
                        $result = false;
                        break;
                    }
                    $others += (int)$plugin_count;
                }

                if ($others > 0) {
                    $this->db->modified(self::ENTRY_TABLE, 'id = ?', [$post['id']]);
                }

                $result += $others;

                if ($this->request->param('publish') === 'release') {
                    $status = $this->db->get('status', self::ENTRY_TABLE, 'id = ?', [$post['id']]);
                    $copy = ($result > 0 || $status !== 'release');
                    if (false === $this->release($post, $copy, $origin)) {
                        $result = false;
                    }
                } elseif ($this->request->param('publish') === 'private') {
                    $entrykey = $this->db->get('id', self::ENTRY_TABLE, 'identifier = ? AND active = 1', [$post['id']]);
                    if (false === $this->toPrivate($entrykey)) {
                        $result = false;
                    }
                } else {
                    if ($result === 0) {
                        $this->app->err['vl_nochange'] = 1;
                        $result = false;
                    } else {
                        $result = $this->db->update(self::ENTRY_TABLE, ['status' => $this->request->param('publish')], 'id = ?', [$entrykey], $raw);
                    }
                }
            } else {
                $result = false;
            }
            if ($result !== false) {
                $this->app->logger->log("Save the entry `{$entrykey}'", 101);

                $commit = $this->db->commit();

                $plugin_result = $this->app->execPlugin('completeSave', $post);
                foreach ($plugin_result as $result) {
                    if (false === $result) {
                        return false;
                    }
                }

                return $commit;
            }
        } else {
            trigger_error($this->db->error());
        }
        $this->db->rollback();

        return false;
    }

    protected function revokeDraft($type = 'entry')
    {
        $this->checkPermission("cms.{$type}.update");
        $origin = $this->request->param('id');
        $revision = $this->request->param('version');

        $target = $this->db->get('*', $type, 'identifier = ? AND revision = ?', [$origin, $revision]);
        if (empty($target)) {
            return false;
        }

        $skips = [
            'id',
            'sitekey',
            'userkey',
            'identifier',
            'revision',
            'active',
            'create_date',
        ];
        $save = [];
        foreach ($target as $key => $value) {
            if (in_array($key, $skips)) {
                continue;
            }
            $save[$key] = $value;
        }
        if ($target['active'] === 1) {
            $save['status'] = 'release';
        } elseif (empty($target['status'])) {
            $save['status'] = null;
        }

        $this->db->begin();
        $return_value = true;

        // Custom fields
        $return_value = $this->revertCustomFields($origin, $target['id'], $type);

        // Exec plugins
        if ($return_value) {
            $plugin_result = $this->app->execPlugin('revokeDraftFromEntry', $origin, $target['id'], $type);
            foreach ($plugin_result as $result) {
                if (false === $result) {
                    $return_value = false;
                    break;
                }
            }
        }

        if ($return_value) {
            $return_value = $this->db->update($type, $save, 'id = ?', [$origin]);
        }

        if ($return_value !== false) {
            $this->app->logger->log("Revoke the draft from {$type} `{$origin}'", 101);
            if (false !== $this->db->commit()) {
                return true;
            }
            $return_value = false;
        }

        $this->db->rollback();

        return false;
    }

    /**
     * Save upload files.
     *
     * @param int $entrykey
     * @param int $sectionkey
     *
     * @return mixed
     */
    protected function saveFiles($entrykey, $sectionkey = null, &$error = null, $input_name = 'file')
    {
        $kind = (empty($sectionkey)) ? 'entry' : 'section';
        $count = 0;

        $subdir = ($entrykey < 0) ? 'stock' : $entrykey;
        if ($entrykey === self::PREVIEW_FILES_DIR) {
            $subdir = $entrykey . '/' . self::previewTemporaryDir();
            $entrykey = 0;
        }

        $upload_dir = $this->fileUploadDir($subdir, $sectionkey);
        clearstatcache(true, $upload_dir);
        if (true !== is_writable($upload_dir)) {
            $error = 'Permission denied';

            return false;
        }

        $delete = $this->request->param('delete');
        $note = $this->request->param('note');
        $option1 = $this->request->param('option1');
        $alternate = $this->request->param('alternate');

        $sort = 0;
        if (isset($_FILES[$input_name])) {
            if (is_array($_FILES[$input_name]['name'])) {
                foreach ($_FILES[$input_name]['name'] as $key => $name) {
                    $save_data = [
                        'sitekey' => $this->siteID,
                        'relkey' => (empty($sectionkey)) ? $entrykey : $sectionkey,
                        'kind' => $kind,
                    ];

                    // Convert encoding multibyte characters
                    if (mb_strlen($name) !== mb_strwidth($name)) {
                        $name = Text::convert($name);
                    }

                    $file_name = urldecode(pathinfo(basename(urlencode($name)), PATHINFO_FILENAME));
                    $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                    $alt_name = $file_name;
                    if ($file_name !== urlencode($file_name)) {
                        $file_name = md5($file_name);
                    }

                    $save_data['name'] = (!empty($alt_name)) ? $input_name.'.'.$alt_name : null;

                    $upload_path = File::realpath("$upload_dir/$file_name.$file_extension");

                    $id = (strpos($key, 'id_') === 0) ? str_replace('id_', '', $key) : null;
                    $save_data['sort'] = $sort++;
                    $save_data['mime'] = $_FILES[$input_name]['type'][$key];

                    if (isset($note[$key])) {
                        $save_data['note'] = (empty($note[$key])) ? null : $note[$key];
                    }
                    if (isset($option1[$key])) {
                        $save_data['option1'] = (empty($option1[$key]) && $option1[$key] !== '0') ? null : $option1[$key];
                    }
                    if (isset($alternate[$key])) {
                        $save_data['alternate'] = (empty($alternate[$key]) && $alternate[$key] !== '0') ? null : $alternate[$key];
                    }

                    if (false === $ret = self::moveUploadedFile(
                        $upload_path,
                        $save_data,
                        [
                            'name' => $_FILES[$input_name]['name'][$key],
                            'tmp_name' => $_FILES[$input_name]['tmp_name'][$key],
                            'error' => $_FILES[$input_name]['error'][$key],
                        ],
                        $error,
                        $id,
                        $delete[$key] ?? null
                    )) {
                        return false;
                    }

                    $count += $ret;
                }
            } else {
                $name = $_FILES[$input_name]['name'];

                // Convert encoding multibyte characters
                if (mb_strlen($name) !== mb_strwidth($name)) {
                    $name = Text::convert($name);
                }

                $file_name = urldecode(pathinfo(basename(urlencode($name)), PATHINFO_FILENAME));
                $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                $alt_name = $file_name;
                if ($file_name !== urlencode($file_name)) {
                    $file_name = md5($file_name);
                }

                $save_data = [
                    'sitekey' => $this->siteID,
                    'relkey' => (empty($sectionkey)) ? $entrykey : $sectionkey,
                    'kind' => $kind,
                ];

                $save_data['name'] = $input_name;
                $save_data['mime'] = $_FILES[$input_name]['type'];

                if (isset($note[$input_name])) {
                    $save_data['note'] = (empty($note[$input_name])) ? null : $note[$input_name];
                }
                if (isset($option1[$input_name])) {
                    $save_data['option1'] = (empty($option1[$input_name]) && $option1[$input_name] !== '0') ? null : $option1[$input_name];
                }
                if (isset($alternate[$input_name])) {
                    $save_data['alternate'] = (empty($alternate[$input_name]) && $alternate[$input_name] !== '0') ? null : $alternate[$input_name];
                }

                $upload_path = File::realpath("$upload_dir/$file_name.$file_extension");

                $id = $this->db->get(
                    'id',
                    'custom',
                    'sitekey = ? AND kind = ? AND relkey = ? AND name = ?',
                    [
                        $save_data['sitekey'],
                        $save_data['kind'],
                        $save_data['relkey'],
                        $save_data['name']
                    ]
                );

                if (false === $ret = self::moveUploadedFile(
                    $upload_path,
                    $save_data,
                    [
                        'name' => $name,
                        'tmp_name' => $_FILES[$input_name]['tmp_name'],
                        'error' => $_FILES[$input_name]['error'],
                    ],
                    $error,
                    $id,
                    (($delete[$input_name] ?? null) === '1' ? $id : null)
                )) {
                    return false;
                }

                $count += $ret;
            }
        }

        $this->removeEmptyDir($upload_dir);

        return $count;
    }

    /**
     * Remove upload files.
     *
     * @param int $entrykey
     * @param int $sectionkey
     *
     * @return bool
     */
    protected function removeFiles($entrykey, $sectionkey = null)
    {
        if (empty($entrykey)) {
            trigger_error('Unexpected error', E_USER_ERROR);
        }
        $upload_dir = $this->fileUploadDir($entrykey, $sectionkey);

        if ($entrykey === self::PREVIEW_FILES_DIR) {
            $this->session->clear('preview_attachments');
            if (File::rmdir("{$upload_dir}/" . self::previewTemporaryDir(), true)) {
                return $this->removeEmptyDir($upload_dir);
            }
        }

        return File::rmdir($upload_dir, true);
    }

    /**
     * Release the entry.
     *
     * @param array $post
     * @param bool  $copy
     * @param array $origin
     *
     * @return bool
     */
    protected function release($post, $copy, $origin = null)
    {
        $this->checkPermission('cms.entry.publish');

        $return_value = true;

        $sid = $this->siteID;
        $entrykey = $post['id'];

        $latest_version = $this->db->max('revision', self::ENTRY_TABLE, 'sitekey = ? AND identifier = ?', [$sid, $entrykey]);
        $new_version = (int) $latest_version + 1;

        $upload_dir = $this->fileUploadDir();

        if ($copy || $latest_version === '0') {
            $this->db->update(self::ENTRY_TABLE, ['active' => '0'], 'identifier = ?', [$entrykey]);

            $fields = $this->db->getFields(self::ENTRY_TABLE);
            $cols = [];
            foreach ($fields as $field) {
                switch ($field) {
                    case 'active':
                        $cols[] = "'1' AS active";
                        break;
                    case 'id':
                    case 'status':
                        $cols[] = "NULL AS `$field`";
                        break;
                    case 'revision':
                        $cols[] = $this->db->quote($new_version).' AS revision';
                        break;
                    default:
                        $cols[] = $field;
                        break;
                }
            }
            if (false === $this->db->copyRecord($cols, self::ENTRY_TABLE, '', 'id = ?', [$entrykey])) {
                return false;
            }
            $new_entrykey = $this->db->lastInsertId(null, 'id');
            $raw = null;
            $this->db->update(self::ENTRY_TABLE, ['status' => $this->request->param('publish')], 'id = ?', [$entrykey], $raw);

            // Remove older version
            $save_count = $this->db->get('maxrevision', 'site', 'id = ?', [$this->siteID]);
            $limit = $new_version - (int) $save_count;

            if (false !== $deletes = $this->db->select('id', self::ENTRY_TABLE, 'WHERE sitekey = ? AND identifier = ? AND revision > ? AND revision < ?', [$sid, $entrykey, 0, $limit])) {
                $plugin_result = $this->app->execPlugin('beforeRemoveOldEntries', $deletes);
                foreach ($plugin_result as $plugin_count) {
                    if (false === $plugin_count) {
                        return false;
                    }
                }

                foreach ($deletes as $delete) {
                    $this->removeFiles($delete['id']);
                    // Custom fields
                    if (false === $this->db->delete('custom', 'sitekey = ? AND kind = ? AND relkey = ?', [$sid, 'entry', $delete['id']])) {
                        trigger_error($this->db->error());

                        return false;
                    }
                }
            }

            if (false === $this->db->delete(self::ENTRY_TABLE, "sitekey = ? AND identifier = ? AND revision > '0' AND revision < ?", [$sid, $entrykey, $limit])) {
                trigger_error($this->db->error());

                return false;
            }
        } else {
            $save = ['active' => '1'];
            foreach ($this->date_columns as $x_date) {
                if (isset($post[$x_date])) {
                    // TODO: which use empty or is_null
                    $save[$x_date] = (empty($post[$x_date]))
                        ? null
                        : date('Y-m-d H:i', Text::strtotime($post[$x_date]));
                }
            }
            $this->db->update(self::ENTRY_TABLE, $save, 'identifier = ? ORDER BY revision DESC LIMIT 1', [$entrykey]);
            $new_entrykey = $this->db->get('id', self::ENTRY_TABLE, 'identifier = ? AND active = 1', [$entrykey]);
        }

        // Release sections
        if (false === $this->cleanupRevisions('section', $sid, $entrykey)) {
            trigger_error($this->db->error());
        }
        if (false !== $sections = $this->db->select('id, entrykey AS eid', self::SECTION_TABLE, 'WHERE entrykey = ? AND revision = ? AND status = ?', [$entrykey, '0', 'draft'])) {
            foreach ($sections as $section) {
                if (false === $this->releaseSection($section, true)) {
                    return false;
                }
            }
        }

        $release_path = $this->getEntryPath($entrykey);
        $release_dir = dirname($release_path);

        // Remove older files
        if ($files = $this->db->select('filepath', self::ENTRY_TABLE, 'WHERE sitekey = ? AND identifier = ?', [$sid, $entrykey])) {
            foreach ((array) $files as $file) {
                $remove_file = $release_dir.'/'.$file['filepath'];
                if (file_exists($remove_file) && is_file($remove_file)) {
                    @unlink($remove_file);
                }
            }
        }
        if (isset($origin['release_path']) && $release_path !== $origin['release_path']) {
            $remove_file = $origin['release_path'];
            if (file_exists($remove_file) && is_file($remove_file)) {
                @unlink($remove_file);
            }
        }

        $this->copyAttachments($new_entrykey, 'entry', $upload_dir);
        $this->copyCustomFields($new_entrykey);

        $plugin_result = $this->app->execPlugin('afterReleaseEntry', $new_entrykey);
        foreach ($plugin_result as $plugin_count) {
            if (false === $plugin_count) {
                return false;
            }
        }

        if ($this->siteProperty('type') === 'static') {
            try {
                $source = $this->build($new_entrykey, ($this->session->param('ispreview') === 1));
                if (!empty($source)) {
                    // Check release path
                    if (!file_exists($release_dir) && !File::mkdir($release_dir)) {
                        return false;
                    }
                    file_put_contents($release_path, $source);
                    $this->app->logger->log("Create entry file `$release_path'", 101);
                } elseif (file_exists($release_path)) {
                    @unlink($release_path);
                    $this->app->logger->log("Remove entry file `$release_path'", 101);
                }
                $this->buildArchives($new_entrykey);
                $this->buildFeeds();
                $this->removeEmptyDir($release_dir);
            } catch (Exception $e) {
                trigger_error($e->getMessage());

                return false;
            }
        }

        return $return_value;
    }

    /**
     * Release the Section.
     *
     * @param array $post
     * @param bool  $copy
     *
     * @return bool
     */
    protected function releaseSection($post, $copy)
    {
        $this->checkPermission('cms.entry.publish');

        $return_value = true;

        $sid = $this->siteID;
        $entrykey = $post['eid'];
        $sectionkey = $post['id'];

        $latest_version = $this->db->max('revision', self::SECTION_TABLE, 'sitekey = ? AND identifier = ?', [$sid, $sectionkey]);
        $new_version = (int) $latest_version + 1;

        $upload_dir = $this->fileUploadDir();

        if ($copy || $latest_version === '0') {
            $this->db->update(self::SECTION_TABLE, ['active' => '0'], 'identifier = ?', [$sectionkey]);

            $fields = $this->db->getFields(self::SECTION_TABLE);
            $cols = [];
            foreach ($fields as $field) {
                switch ($field) {
                    case 'active':
                        $cols[] = "'1' AS active";
                        break;
                    case 'id':
                    case 'status':
                        $cols[] = "NULL AS `$field`";
                        break;
                    case 'revision':
                        $cols[] = $this->db->quote($new_version).' AS revision';
                        break;
                    default:
                        $cols[] = $field;
                        break;
                }
            }
            if (false === $this->db->copyRecord($cols, self::SECTION_TABLE, '', 'id = ?', [$sectionkey])) {
                return false;
            }
            $new_sectionkey = $this->db->lastInsertId(null, 'id');
            $raw = null;
            $this->db->update(self::SECTION_TABLE, ['status' => $this->request->param('publish')], 'id = ?', [$sectionkey], $raw);

            // Remove older version
            $save_count = $this->db->get('maxrevision', 'site', 'id = ?', [$this->siteID]);
            $limit = $new_version - (int) $save_count;

            if (false !== ($deletes = $this->db->select('id, entrykey', self::SECTION_TABLE, 'WHERE sitekey = ? AND identifier = ? AND revision > ? AND revision < ?', [$sid, $sectionkey, 0, $limit]))) {
                foreach ($deletes as $delete) {
                    $this->removeFiles($delete['entrykey'], $delete['id']);
                    // Custom fields
                    if (false === $this->db->delete('custom', 'sitekey = ? AND kind = ? AND relkey = ?', [$sid, 'section', $delete['id']])) {
                        trigger_error($this->db->error());

                        return false;
                    }
                }
            }

            if (false === ($cnt = $this->db->delete(self::SECTION_TABLE, 'sitekey = ? AND identifier = ? AND revision > ? AND revision < ?', [$sid, $sectionkey, 0, $limit]))) {
                trigger_error($this->db->error());

                return false;
            }
            $sectionkey = $new_sectionkey;
        } else {
            $save = ['active' => '1'];
            foreach ($this->date_columns as $x_date) {
                if (isset($post[$x_date])) {
                    $save[$x_date] = date('Y-m-d H:i', Text::strtotime($post[$x_date]));
                }
            }
            $this->db->update(self::SECTION_TABLE, $save, 'identifier = ? ORDER BY revision DESC LIMIT 1', [$sectionkey]);
            $sectionkey = $this->db->get('id', self::SECTION_TABLE, 'identifier = ? AND active = 1', [$sectionkey]);
        }

        $this->copyAttachments($sectionkey, 'section', rtrim($upload_dir, '/') . "/$entrykey");
        $this->copyCustomFields($entrykey, $sectionkey);

        return $return_value;
    }

    private function revertCustomFields($origin, $revert, $kind = 'entry')
    {
        $sitekey = $this->siteID;

        $entrykey = ($kind === 'entry') ? $origin : $this->db->get('entrykey', 'section', 'id = ?', [$origin]);
        if (empty($entrykey)) {
            return false;
        }
        $sectionkey = ($kind !== 'entry') ? $origin : '';
        $dest = rtrim($this->site_data['path']
            . $this->site_data['uploaddir']
            . "/{$entrykey}/{$sectionkey}", '/');

        // Rewind
        $items = $this->db->select(
            '*',
            'custom',
            'WHERE sitekey = ? AND relkey = ? AND kind = ?',
            [$sitekey, $revert, $kind]
        );

        if (!empty($items)) {
            // Delete origin
            if (false === ($deleted = $this->db->delete(
                'custom',
                'sitekey = ? AND relkey = ? AND kind = ?',
                [$sitekey, $origin, $kind]
            ))) {
                return false;
            }

            foreach ($items as $item) {
                $item['relkey'] = $origin;
                if (strpos($item['name'], 'file.') === 0) {
                    $path = explode('/', $item['data']);
                    $basename = array_pop($path);
                    $item['data'] = "{$dest}/{$basename}";
                }
                unset($item['id']);

                if (false === $this->db->insert('custom', $item)) {
                    return false;
                }
            }
        }

        // Files
        $dest = $this->site_data['openpath'] . $dest;
        $src = dirname($dest) . "/$revert";

        if (is_dir($src)) {
            if (is_dir($dest)) {
                $backup = "$dest.bak";
                if (false === @rename($dest, $backup)) {
                    return false;
                }
            }

            $result = File::copydir($src, $dest, true);

            if ($result && $kind === 'entry' && isset($backup)) {
                if (false !== ($fetch = $this->db->select('id', 'section', 'WHERE sitekey = ? AND entrykey = ?', [$sitekey, $origin]))) {
                    foreach ($fetch as $i => $unit) {
                        if (($i === array_key_first($fetch)) && !is_dir($dest)) {
                            @mkdir($dest, 0777, true);
                        }
                        $id = $unit['id'];
                        $s = "{$backup}/{$id}";
                        if (is_dir($s) && false === File::copydir($s, "$dest/{$id}", true)) {
                            $result = false;
                            break;
                        }
                    }
                } else {
                    $result = false;
                }
            }

            // Cleanup or rewind to old files
            if (isset($backup)) {
                if ($result) {
                    if (false === File::rmdir($backup, true)) {
                        return false;
                    }
                } else {
                    @rename($backup, $dest);

                    return false;
                }
            }
        }

        return true;
    }

    private function copyCustomFields($entrykey, $sectionkey = null)
    {
        $sitekey = $this->siteID;
        $kind = (empty($sectionkey)) ? 'entry' : 'section';
        $relkey = ($kind === 'section') ? $sectionkey : $entrykey;
        $identifier = $this->db->get('identifier', $kind, 'id = ?', [$relkey]);
        $dest = rtrim($this->site_data['path']
            . $this->site_data['uploaddir']
            . "/{$entrykey}/{$sectionkey}", '/');

        $keys = array_column((array)$this->db->select(
            'id',
            'custom',
            'WHERE sitekey = ? AND relkey = ? AND kind = ?',
            [$sitekey, $relkey, $kind]
        ), 'id');

        $fields = $this->db->select(
            '*',
            'custom',
            'WHERE sitekey = ? AND relkey = ? AND kind = ?',
            [$sitekey, $identifier, $kind]
        );

        foreach ((array)$fields as $field) {
            unset($field['id']);
            if (strpos($field['name'], 'file.') === 0) {
                $path = explode('/', $field['data']);
                $basename = array_pop($path);
                $field['data'] = "{$dest}/{$basename}";
            }
            $field['relkey'] = $relkey;

            $id = array_shift($keys);

            if (empty($id)) {
                if (false === $this->db->insert('custom', $field)) {
                    return false;
                }
            } else {
                if (false === $this->db->update('custom', $field, 'id = ?', [$id])) {
                    return false;
                }
            }
        }

        // Cleanup old fields
        if (!empty($keys)) {
            $this->db->delete(
                'custom',
                sprintf('id IN(%s)', array_fill(0, count($keys), '?')),
                $keys
            );
        }
        if (false === $this->cleanupCustomFields($kind, $sitekey)) {
            return false;
        }

        return true;
    }

    /**
     * a Public entry to Private.
     *
     * @param array $entrykey
     *
     * @return bool
     */
    protected function toPrivate($entrykey): bool
    {
        $this->checkPermission('cms.entry.publish');

        $return_value = true;

        $origin = $this->db->get('identifier', self::ENTRY_TABLE, 'id = ?', [$entrykey]);
        if (false === $ret = $this->db->update(self::ENTRY_TABLE, ['active' => '0'], 'identifier = ?', [$origin])) {
            return false;
        }
        if ($ret > 0) {
            if (false === $this->db->update(self::ENTRY_TABLE, ['status' => $this->request->param('publish')], 'id = ?', [$origin])) {
                return false;
            }
        }

        if ($this->siteProperty('type') === 'static') {
            // Rebuild Archives
            if (false === $this->buildArchives($entrykey)) {
                return false;
            }

            $release_path = $this->getEntryPath($entrykey);
            if (file_exists($release_path)) {
                if (false === @unlink($release_path)) {
                    return false;
                }
            }

            $directory = dirname($release_path);
            if (is_dir($directory) && count(glob("$directory/*")) === 0) {
                rmdir($directory);
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
        $this->checkPermission('cms.entry.delete');

        list($kind, $entrykey) = explode(':', $this->request->param('delete'));

        if ($kind === 'category') {
            return parent::remove();
        }

        $this->db->begin();

        if (false === $this->db->update(self::ENTRY_TABLE, ['active' => '0'], 'identifier = ?', [$entrykey])) {
            return false;
        }

        // Remove Public files
        $entrykeys = $this->db->select('id', self::ENTRY_TABLE, 'WHERE identifier = ?', [$entrykey]);
        foreach ($entrykeys as $unit) {
            $this->toPrivate($unit['id']);
            $this->removeFiles($unit['id']);
        }

        $result = true;
        $plugin_result = $this->app->execPlugin('beforeRemove', $entrykey);
        foreach ($plugin_result as $plugin_count) {
            if (false === $plugin_count) {
                $result = false;
                break;
            }
        }

        $category = $this->db->get('category', self::ENTRY_TABLE, 'identifier = ?', [$entrykey]);
        $this->request->param('category', $category);
        if ($result !== false && false !== $this->db->delete(self::SECTION_TABLE, 'entrykey = ?', [$entrykey])) {
            if (false !== $this->db->delete(self::ENTRY_TABLE, 'identifier = ?', [$entrykey])) {
                $this->app->logger->log("Remove the entry `{$entrykey}'", 101);

                $commit = $this->db->commit();

                $plugin_result = $this->app->execPlugin('completeRemove', $this->request->param());
                foreach ($plugin_result as $result) {
                    if (false === $result) {
                        return false;
                    }
                }

                return $commit;
            }
        }
        trigger_error($this->db->error());
        $this->db->rollback();

        return false;
    }

    /**
     * Build entry source.
     *
     * @param int  $entrykey
     * @param bool $preview
     *
     * @return mixed
     */
    protected function build($entrykey, $preview = false, $force_db = false)
    {
        $build_type = $this->session->param('build_type');
        $this->session->param('build_type', 'entry');

        $entry = [];
        if ($preview === true) {
            $entry = $this->request->param();
            if (!isset($entry['identifier'])) {
                $entry['identifier'] = (isset($entry['id'])) ? $entry['id'] : $entrykey;
            }
            if (!is_null($entry['identifier'])) {
                $entry['identifier'] = intval($entry['identifier']);
            }
        }
        if ((bool) $force_db === false && isset($entry['template'])) {
            $entry['category'] = $this->categoryID;
            $tid = $entry['template'];
        } else {
            if (!empty($entrykey)) {
                $statement = 'WHERE id = ?';
                $options = [$entrykey];

                $is_frontend = false;
                if ($this->site_data['type'] === 'dynamic' && $this->view->param('isfrontend') === 1) {
                    $statement .= ' AND (`acl` = 0 OR `acl`&?)';
                    $options[] = $this->userinfo['priv'];
                    $is_frontend = true;
                }

                $data = $this->db->select('*', self::ENTRY_TABLE, $statement, $options);
                if (false === $data) {
                    return false;
                }
                if (count($data) > 0) {
                    if ((bool) $force_db) {
                        $entry = [];
                    }
                    $saved = array_shift($data);
                    $tid = $saved['template'];
                    foreach ($saved as $key => $value) {
                        if (!isset($entry[$key])) {
                            $entry[$key] = $saved[$key];
                        }
                    }
                } elseif ($is_frontend) {
                    trigger_error('Request is not found', E_USER_ERROR);
                }
            }
        }

        if (property_exists($this, 'advanced_template') && !empty($this->advanced_template)) {
            $tid = $this->advanced_template;
        }

        if (empty($tid)) {
            return '';
        }

        // Custom fields
        $customs = $this->db->select(
            'name, data',
            'custom',
            'WHERE sitekey = ? AND relkey = ? AND kind = ? AND name LIKE ?',
            [$this->siteID, $entry['id'], 'entry', 'cst_%']
        );
        foreach ((array) $customs as $unit) {
            if (!isset($entry[$unit['name']])) {
                $entry[$unit['name']] = $unit['data'];
            }
        }

        // Category data
        $category = $this->categoryData($entry['category']);
        $this->view->bind('category', $category);
        $sub_class = trim(str_replace(['/','.'], ['-','_'], preg_replace('/\.html?$/', '', $category['path'])), '-_');

        $current_build_category_origin = $this->session->param('current_build_category');
        $this->session->param('current_build_category', $entry['category']);

        $apps = new Response($this->app);
        $this->view->bind('apps', $apps);

        $template = $this->db->get(
            'sourcecode,kind,path',
            'template',
            'identifier = ?',
            [$tid]
        );

        if (!is_null($template['kind'])) {
            $template['kind'] = intval($template['kind']);
        }

        if ($preview === true
            && $template['kind'] === 0
            && $category['path'] === '/'
            && $this->defaultPage($entry['category']) === $entry['filepath']
        ) {
            $template = $this->db->get(
                'id,sourcecode,kind,path',
                'template',
                'kind = ?',
                ['1']
            );
            $tid = $template['id'];
        }

        $entry['url'] = $this->getEntryPath($entrykey, 1);
        $this->session->param('preview_data', $entry);

        // The entry use dummy template
        if ($preview === true && $template['kind'] === 0) {
            $categorykey = $this->findArchive($entry['category']);

            return $this->buildArchive($categorykey, $preview);
        }

        $this->view->bind('current', $entry);

        $this->bindSiteData($entry['url']);

        if (empty($template['sourcecode']) || ($template['kind'] === 0 && $preview === false)) {
            return '';
        }

        $pagenumber = $this->request->param('current_page');
        if (empty($pagenumber)) {
            $pagenumber = 1;
        }
        $this->view->bind('page_number', $pagenumber);

        $html_class = [str_replace('_', '-', $template['path'])];
        if (!empty($sub_class)) {
            $html_class[] = $sub_class;
        }
        $this->appendHtmlClass($html_class);
        $html_id = $this->pathToID($entry['url']);
        if (empty($html_id)) {
            $html_id = $html_class;
        }
        $this->setHtmlId($html_id);

        $this->view->bind('get', $this->request->get());

        $this->view->bind('build_type', $this->session->param('build_type'));

        $path = $this->templatePath($tid);
        $this->setPathToView(dirname($path));

        $params = ($this->session->param('ispreview') === 1 || $this->siteProperty('type') !== 'dynamic');
        $source = $this->view->render(basename($path), $params);

        $this->session->param('current_build_category', $current_build_category_origin);
        $this->session->param('build_type', $build_type);

        if ($this->session->param('ispreview') === 1) {
            $js = PHP_EOL.'<script src="script/cms/preview.js"></script>';
            if (preg_match("/<\/(body|html)>/i", $source, $match)) {
                $source = preg_replace('/'.preg_quote($match[0], '/').'/', $js.$match[0], $source);
            } else {
                $source .= $js;
            }
        }

        return $source;
    }

    /**
     * Create HTML file.
     *
     * @param int $entrykey
     *
     * @return bool
     */
    protected function createEntryFile($entrykey)
    {
        $release_path = $this->getEntryPath($entrykey);
        if (empty($release_path)) {
            return false;
        }

        // Check release path
        $dir = dirname($release_path);
        if (!file_exists($dir) && !File::mkdir($dir)) {
            return false;
        }

        // Remove older files
        if ($files = $this->db->select('filepath', self::ENTRY_TABLE, 'WHERE identifier = ?', [$entrykey])) {
            foreach ((array) $files as $file) {
                $remove_file = $dir.'/'.$file['filepath'];
                if (file_exists($remove_file)) {
                    @unlink($remove_file);
                }
            }
        }

        $source = $this->build($entrykey, ($this->session->param('ispreview') === 1));
        if (empty($source)) {
            if (file_exists($release_path)) {
                @unlink($release_path);
            }

            return true;
        }

        return file_put_contents($release_path, $source);
    }

    /*
     * Image List
     *
     * @param int $entrykey
     * @return mixed
     */
    protected function imageList($entrykey)
    {
        $list = $this->db->select(
            'id, data, mime',
            'custom',
            'WHERE sitekey = ? AND relkey = ?',
            [$this->siteID, $entrykey]
        );

        return $list;
    }

    /**
     * Remove image files for preview.
     */
    public function removePreviewImages()
    {
        $this->db->delete(
            'custom',
            'sitekey = ? AND relkey = ? AND kind = ? AND name LIKE ?',
            [$this->siteID, 0, 'entry', 'file.%']
        );
        $this->removeFiles(self::PREVIEW_FILES_DIR);
    }

    public function createRelation($entrykey, $relkey)
    {
        if (is_null($entrykey)
            || $this->db->exists('relation', 'entrykey = ? AND relkey = ?', [$entrykey, $relkey])
        ) {
            return true;
        }

        return $this->db->insert('relation', ['entrykey' => $entrykey, 'relkey' => $relkey]);
    }

    /**
     * Relational entries
     *
     * @param int   $entrykey
     * @param array $columns
     *
     * @return array
     */
    public function relations($entrykey, array $columns = null)
    {
        $cols = [];
        if (is_null($columns)) {
            $cols[] = 'rel.*';
        } else {
            foreach ($columns as $column) {
                $cols[] = "rel.`$column`";
            }
        }
        $columns = implode(',', $cols);

        $sql = "SELECT $columns
                  FROM table::entry rel
                  JOIN (SELECT r.relkey
                          FROM table::relation r
                          JOIN (SELECT id FROM table::entry WHERE id = ?) e
                            ON r.entrykey = e.id) rev
                    ON rel.id = rev.relkey";

        return $this->db->getAll($sql, [$entrykey]);
    }

    /**
     * Recommend entries
     *
     * @param int   $entrykey
     * @param array $columns
     *
     * @return array
     */
    public function recommends($entrykey, array $columns = null)
    {
        $cols = [];
        if (is_null($columns)) {
            $cols[] = 'rel.*';
        } else {
            foreach ($columns as $column) {
                $cols[] = "rel.`$column`";
            }
        }
        $columns = implode(',', $cols);

        $sql = "SELECT $columns
                  FROM (SELECT * FROM table::entry WHERE active = 1) rel
                  JOIN (SELECT r.relkey
                          FROM table::relation r
                          JOIN (SELECT identifier FROM table::entry WHERE identifier = ? AND active = 1) e
                            ON r.entrykey = e.identifier) rev
                    ON rel.identifier = rev.relkey";

        return $this->db->getAll($sql, [$entrykey]);
    }

    public static function clearPDFThumbnail($pdf)
    {
        $result = true;
        $thumbnails = glob("$pdf*".parent::THUMBNAIL_EXTENSION);
        foreach ($thumbnails as $thumbnail) {
            try {
                unlink($thumbnail);
            } catch (ErrorException $e) {
                $result = false;
            }
        }

        return $result;
    }

    public function createPDFThumbnail($command, $id, $pdf, $pages = null, $density = 144, $quarity = 90)
    {
        $result = true;
        $start = 0;
        $end = 0;

        // cleanup old files
        self::clearPDFThumbnail($pdf);

        if (strtolower($pages ?? '') === 'none') {
            return $result;
        }

        //
        if (!file_exists($pdf)) {
            $pdf = preg_replace('/^(.+)\/'.preg_quote(self::PREVIEW_FILES_DIR, '/').'\/[a-f0-9]{32}\/([^\/]+)$/', "$1/$id/$2", $pdf);
            if (!file_exists($pdf)) {
                return $result;
            }
        }

        if ($command === 'imagick') {
            $convert = new Imagick();
            $convert->setResolution($density, $density);
            $convert->readImage($pdf);
            $convert->setCompressionQuality($quarity);
            if ($pages === strtolower('all')) {
                $end = $convert->getImageScene();
            } elseif (preg_match('/^(\d+)-(\d+)?$/', $pages, $range)) {
                $start = (int)$range[1];
                $end = (isset($range[2]) && !empty($range[2])) ? (int)$range[2] : $convert->getImageScene();
            } elseif (preg_match('/^(\d+)$/', $pages, $range)) {
                $start = $end = (int)$pages;
            }
            for ($i = $start; $i <= $end; $i++) {
                $convert->setIteratorIndex($i);
                $result = $convert->writeImage("$pdf-$i".parent::THUMBNAIL_EXTENSION);
            }
            $convert->destroy();
        } elseif (!empty($command)) {
            $page = '[0]';
            if ($pages === strtolower('all')) {
                $page = '';
            } elseif (preg_match('/^(\d+)-(\d+)?$/', $pages, $range)) {
                $page = $pages;
            } elseif (preg_match('/^(\d+)$/', $pages, $range)) {
                $page = "[$pages]";
            }
            exec("$command -density $density -quality $quarity $pdf$page $pdf".parent::THUMBNAIL_EXTENSION, $output, $status);
            $result = ($status === 0);
        }

        return $result;
    }

    protected function entryData($entrykey, $column = '*', $raw = false)
    {
        $statement = 'sitekey = ?';
        $options = [$this->siteID];

        $statement .= ($raw !== false) ? ' AND identifier = id AND identifier = ?' : ' AND id = ?';
        $options[] = $entrykey;

        $fetch = $this->db->select($column, self::ENTRY_TABLE, "WHERE $statement", $options);
        if (!empty($fetch)) {
            $data = array_shift($fetch);
            $data['url'] = $this->getEntryPath($entrykey, 1);
            $data['html_id'] = $this->pathToID($data['url']);

            return $data;
        }
    }

    /**
     * Copy attachment files
     *
     * @param int $somekey
     * @param string $table
     * @param string $upload_dir
     *
     * @return bool
     */
    private function copyAttachments($somekey, $table, $upload_dir)
    {
        $copytype = 'hard';

        $identifier = $this->db->get('identifier', $table, 'id = ?', [$somekey]);
        $src = rtrim($upload_dir, '/') . "/$identifier";
        if (!is_dir($src)) {
            return false;
        }
        $dest = "$upload_dir/$somekey";
        if (is_dir($dest)) {
            File::rmdir($dest, true);
        }

        if ($table === self::SECTION_TABLE) {
            return File::copy($src, $dest, true, $copytype);
        }

        mkdir($dest, 0777, true);
        $files = scandir($src);
        foreach ($files as $file) {
            $path = "$src/$file";
            if (is_file($path)) {
                if (false === File::copy($path, "$dest/$file", false, $copytype)) {
                    return false;
                }
            }
        }
        $this->removeEmptyDir($dest);
    }

    protected function findArchive($categorykey)
    {
        do {
            $category_template = $this->db->get('template', 'category', 'id = ?', [$categorykey]);
            if (!empty($category_template)) {
                break;
            }
            $filepath = pathinfo($this->getCategoryPath($categorykey), PATHINFO_BASENAME);
            if ($this->db->exists('entry', 'category = ? AND filepath = ?', [$categorykey, $filepath])) {
                break;
            }

            $categorykey = $this->parentCategory($categorykey, 'id');
        } while (!empty($categorykey));

        return $categorykey;
    }

    public function entriesList(int $limit)
    {
        $sql = file_get_contents(__DIR__ . '/Entry/default.sql');

        // Sort order
        $sort_option = '';
        if ($this->session->param('cms_entry_list_order')) {
            $sort_option = ','.$this->session->param('cms_entry_list_order');
        } elseif ($this->app->cnf('application:cms_entry_list_order')) {
            $sort_option = ','.$this->app->cnf('application:cms_entry_list_order');
        } else {
            $sort_option = ", CASE WHEN status IS NULL THEN 0 WHEN status = 'draft' THEN 1 ELSE 2 END ASC, `create_date` DESC";
        }
        $sql = str_ireplace('{{ sort_option }}', $sort_option, $sql);

        $replaces = [
            'user_id' => $this->uid,
            'site_id' => $this->siteID,
            'category_id' => $this->categoryID,
            'revision' => 0
        ];

        $total = $this->db->recordCount($sql, $replaces);
        $this->pager->init($total, $limit);
        $current_page = (int)$this->request->param('p');
        if (empty($current_page)) {
            $current_page = 1;
        }
        $this->pager->setCurrentPage($current_page);

        $offset = $limit * ($current_page - 1);

        if (!empty($limit)) {
            $sql .= " LIMIT {$offset},{$limit}";
        }

        $items = $this->db->getAll($sql, $replaces);
        foreach ($items as &$item) {
            if ($item['kind'] === 'category') {
                $item['empty'] = $this->isEmpty($item['id']);
            } elseif ($item['kind'] === 'entry') {
                if (!$this->db->exists('entry', 'identifier = ? AND identifier <> id', [$item['id']])) {
                    $item['new'] = 1;
                }
            }
        }
        unset($item);

        return $items;
    }

    public function trashItems(int $limit)
    {
        $sql = file_get_contents(__DIR__ . '/Entry/trash.sql');

        // Sort order
        $sort_option = '';
        if ($this->session->param('cms_entry_list_order')) {
            $sort_option = ','.$this->session->param('cms_entry_list_order');
        } elseif ($this->app->cnf('application:cms_entry_list_order')) {
            $sort_option = ','.$this->app->cnf('application:cms_entry_list_order');
        }
        $sql = str_ireplace('{{ sort_option }}', $sort_option, $sql);

        $replaces = ['user_id' => $this->uid, 'site_id' => $this->siteID, 'revision' => 0];

        $total = $this->db->recordCount($sql, $replaces);
        $this->pager->init($total, $limit);
        $current_page = (int)$this->request->param('p');
        if (empty($current_page)) {
            $current_page = 1;
        }
        $this->pager->setCurrentPage($current_page);

        $offset = $limit * ($current_page - 1);

        if (!empty($limit)) {
            $sql .= " LIMIT {$offset},{$limit}";
        }

        return $this->db->getAll($sql, $replaces);
    }

    private function setPreviewAttachments($data)
    {
        $preview_attachments = $this->session->param('preview_attachments');
        if (is_null($preview_attachments)) {
            $preview_attachments = [];
        }

        if (empty($data['name'])) {
            if (false === $data = $this->db->get(
                'id, data AS path, mime, alternate, note, option1',
                'custom',
                'id = ?',
                [$data['id']]
            )) {
                return;
            }
        } elseif (!isset($data['path'])) {
            $data['path'] = $data['data'];
        }

        $preview_attachments[] = $data;
        $this->session->param('preview_attachments', $preview_attachments);
    }

    private function moveUploadedFile(
        $upload_path,
        $save_data,
        $context,
        &$error,
        $id = null,
        $delete = null
    ) {
        $upload_dir = dirname($upload_path);

        switch ($context['error']) {
        case UPLOAD_ERR_NO_FILE:
            if (is_null($delete) || empty($id)) {
                if (!empty($id) && $this->session->param('ispreview') === 1) {
                    $save_data['id'] = $id;
                    self::setPreviewAttachments($save_data);
                }

                $old = $this->db->get('mime,alternate,data,note,option1,sort', 'custom', 'sitekey = ? AND id = ?', [$this->siteID, $id]);
                if (!empty($old)) {
                    $update_flag = false;
                    $save = [];
                    if ($old['sort'] !== $save_data['sort']) {
                        $update_flag = true;
                        $save['sort'] = $save_data['sort'];
                    }
                    if ($old['note'] !== $save_data['note']) {
                        $update_flag = true;
                        $save['note'] = $save_data['note'];
                    }
                    if ($old['alternate'] !== ($save_data['alternate'] ?? null)) {
                        $update_flag = true;
                        $save['alternate'] = $save_data['alternate'] ?? null;
                    }
                    if ($old['option1'] !== ($save_data['option1'] ?? null)) {
                        $update_flag = true;
                        $save['option1'] = $save_data['option1'] ?? null;
                    }
                    if (!empty($save_data['mime']) && $old['mime'] !== ($save_data['mime'] ?? null)) {
                        $update_flag = true;
                        $save['mime'] = $save_data['mime'];
                    }

                    if (($save['mime'] ?? $old['mime']) === 'application/pdf') {
                        $file_path = $upload_dir.'/'.basename($old['data']);
                        if (($save['option1'] ?? null) === 'none') {
                            self::clearPDFThumbnail($file_path);
                        } else {
                            self::createPDFThumbnail($this->command_convert, $id, $file_path, $save['option1'] ?? $old['option1']);
                        }
                    }

                    if ($update_flag && $this->session->param('ispreview') !== 1) {
                        if (false === $ret = $this->db->update('custom', $save, 'sitekey = ? AND id = ?', [$this->siteID, $id], [])) {
                            trigger_error($this->db->error());

                            return false;
                        }

                        return $ret;
                    }
                }

                return 0;
            }
            // no break
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $error = 'File size is too large';

            return false;
        default:
            $error = 'File upload failed';

            return false;
        }

        $old = $this->db->get('mime,alternate,data,note,option1', 'custom', 'sitekey = ? AND id = ?', [$this->siteID, $id]);
        $old_path = (false !== $old) ? $upload_dir.'/'.basename($old['data']) : null;

        if (!is_null($delete)) {
            if ($this->session->param('ispreview') !== 1) {
                if (!empty($old_path)) {
                    if (is_file($old_path)) {
                        unlink($old_path);
                        $ret = 1;
                    }

                    self::clearPDFThumbnail($old_path);

                    if (!empty($old_path)) {
                        $directory = dirname($old_path);
                        if (count(glob("$directory/*")) === 0) {
                            rmdir($directory);
                        }
                    }
                }

                if (false === $ret = $this->db->delete('custom', 'sitekey = ? AND id = ?', [$this->siteID, $delete])) {
                    trigger_error($this->db->error());

                    return false;
                }

                return $ret;
            }
        }

        if (empty($context['name'])) {
            if ($this->session->param('ispreview') === 1) {
                return 0;
            }

            if (false === $ret = $this->db->update('custom', $save_data, 'sitekey = ? AND id = ?', [$this->siteID, $id], [])) {
                trigger_error($this->db->error());

                return false;
            }

            if (!isset($save_data['option1'])) {
                $save_data['option1'] = null;
            }
            if (!isset($old['option1'])) {
                $old['option1'] = null;
            }
            if ($save_data['option1'] !== $old['option1'] && $old['mime'] === 'application/pdf') {
                $upload_path = $upload_dir.'/'.basename($old['data']);
                self::createPDFThumbnail($this->command_convert, $id, $upload_path, $save_data['option1']);
            }

            return $ret;
        }

        $docroot = preg_replace(
            '/' . preg_quote(rtrim($this->site_data['path'], '/'), '/') . '$/',
            '',
            rtrim($this->site_data['openpath'], '/')
        );
        $save_data['data'] = str_replace($docroot, '', $upload_path);

        $ret = 0;
        if ($this->session->param('ispreview') === 1) {
            if (strpos($save_data['name'], 'file.') === 0) {
                self::setPreviewAttachments($save_data);
            } else {
                $this->request->param($save_data['name'], $save_data['data']);
            }
            $ret = 1;
        } else {
            if (!empty($id)) {
                if (false === $ret = $this->db->update('custom', $save_data, 'sitekey = ? AND id = ?', [$this->siteID, $id], [])) {
                    trigger_error($this->db->error());

                    return false;
                }
                //$save_data['id'] = $id;
            } else {
                if (false === $ret = $this->db->insert('custom', $save_data, [])) {
                    trigger_error($this->db->error());

                    return false;
                }
            }
        }
        $count = $ret;

        $diff = 1;
        if (file_exists($upload_path) && (string)file_get_contents($upload_path) === (string) file_get_contents($context['tmp_name'])) {
            $diff = 0;
        } elseif ($old_path !== $upload_path) {
            if (!empty($old_path) && file_exists($old_path) && is_file($old_path)) {
                self::clearPDFThumbnail($old_path);
                unlink($old_path);
            }
        }

        $directory = dirname($upload_path);
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
        if (move_uploaded_file($context['tmp_name'], $upload_path)) {

            // Create PDF thumbnail
            $option1 = $save_data['option1'] ?? null;
            if (strtolower($save_data['mime']) === 'application/pdf' && (!empty($option1) || $option1 === '0')) {
                self::createPDFThumbnail($this->command_convert, $id, $upload_path, $option1);
            }

            $count += $diff;
        } else {
            trigger_error("File upload Failure `$upload_path'");

            return false;
        }

        return $count;
    }

    protected function previewTemporaryDir()
    {
        return md5($this->session->param('ticket'));
    }
}
