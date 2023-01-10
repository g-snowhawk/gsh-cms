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
use Gsnowhawk\Common\Environment;
use Gsnowhawk\Common\File;
use Gsnowhawk\Common\Http;
use Gsnowhawk\Common\Lang;
use Gsnowhawk\Common\Pagination;
use Gsnowhawk\Common\Text;
use Gsnowhawk\Common\Variable;
use Gsnowhawk\Db;

/**
 * Category management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 *
 * @uses Gsnowhawk\Accessor
 */
class Category extends Template
{
    /*
     * Using common accessor methods
     */
    use \Gsnowhawk\Accessor;

    /**
     * Current category properties.
     *
     * @var array
     */
    private $category_data;

    /**
     * entry list offset.
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * page separation.
     *
     * @var bool
     */
    private $page_separation = false;

    private $page_suffix_watcher;
    private $page_separation_watcher;

    private $file_name_format;

    /**
     * Pagination object
     *
     * @var Gsnowhawk\Common\Pagination
     */
    public $pager;

    /**
     * Object Constructer.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array(parent::class.'::__construct', $params);

        $this->category_data = $this->db->get('*', 'category', 'id = ?', [$this->categoryID]);
    }

    /**
     * Save the data.
     *
     * @return bool
     */
    protected function save()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('cms.category.'.$check);

        $table = 'category';
        $skip = ['id', 'sitekey', 'userkey', 'create_date', 'modify_date', 'lft', 'rgt'];

        $post = $this->request->post();

        $valid = [];
        $valid[] = ['vl_title', 'title', 'empty'];
        $valid[] = ['vl_description', 'description', 'disallowtags', 2];
        if (empty($post['id'])) {
            $valid[] = ['vl_path', 'path', 'empty'];
        } else {
            $old_path = $this->getCategoryPath($post['id'], 1);
        }

        if (!$this->validate($valid)) {
            return false;
        }

        // Check exists
        $children = $this->childCategories($this->categoryID, 'path');
        if (!empty($children)) {
            $exists = array_column($children, 'path');
            if (in_array($post['path'], $exists)) {
                if (empty($old_path) || basename($old_path) !== $post['path']) {
                    $this->app->err['vl_path'] = 99;

                    return false;
                }
            }
        }

        $this->db->begin();

        $fields = $this->db->getFields($table);
        $save = [];
        $raw = [];
        foreach ($fields as $field) {
            if (in_array($field, $skip)) {
                continue;
            }
            if (isset($post[$field])) {
                if ($field === 'author_date' && !empty($post[$field])) {
                    $save[$field] = date('Y-m-d H:i', Text::strtotime($post[$field]));
                    continue;
                }
                $save[$field] = (Variable::empty($post[$field])) ? null : $post[$field];
            }
        }

        // NULL is not empty to foreign key.
        if (empty($save['template'])) {
            $save['template'] = null;
        }

        if (empty($save['author_date'])) {
            $save['author_date'] = date('Y-m-d H:i');
        }

        $reassembly = false;
        if (empty($post['id'])) {
            $parent_rgt = $this->db->get('rgt', 'category', 'id = ?', [$this->categoryID]);

            $save['lft'] = $parent_rgt;
            $save['rgt'] = $parent_rgt + 1;

            $save['sitekey'] = $this->siteID;
            $save['userkey'] = $this->uid;

            // Inherit template from parent category
            $inherit = $this->db->get('inheritance,template,default_template', 'category', 'id = ?', [$this->categoryID]);
            $i = (int)$inherit['inheritance'];
            if ($i > 0) {
                if ($i === ($i & (1|4))) {
                    $save['template'] = $inherit['template'];
                }
                if ($i === ($i & (2|4))) {
                    $save['default_template'] = $inherit['default_template'];
                }
            }

            $raw['create_date'] = 'CURRENT_TIMESTAMP';

            $update_parent = $this->db->prepare(
                $this->db->nsmBeforeInsertChildSQL('category')
            );

            if (false !== $update_parent->execute(['parent_rgt' => $parent_rgt, 'offset' => 2])
                && false !== $result = $this->db->insert($table, $save, $raw)
            ) {
                $post['id'] = $this->db->lastInsertId(null, 'id');
            }
        } else {
            if (false !== $result = $this->moveCategory($post['id'], $post['parent'])) {
                $result = $this->db->update($table, $save, "id = ? AND reserved = '0'", [$post['id']], $raw);
            }
        }
        if ($result !== false) {
            $modified = ($result > 0) ? $this->db->modified($table, 'id = ?', [$post['id']]) : true;
            if ($modified) {
                // If there is a need to do something after saving
                $new_path = $this->getCategoryPath($post['id'], 1);
                if (isset($old_path) && $old_path !== $new_path) {
                    if (file_exists($old_path)) {
                        $result = rename($old_path, $new_path);
                    }
                }
                // ^ write here.
            } else {
                $result = false;
            }
            if ($result !== false) {
                $customs = [];
                foreach ((array)$post as $key => $value) {
                    if (strpos($key, 'cst_') === 0) {
                        $customs[$key] = $value;
                    }
                }
                if (false !== $this->saveCustomField('category', $post['id'], $customs)) {
                    if ($reassembly) {
                        self::reassembly($this->categoryID);
                    }
                    $this->app->logger->log("Save the category `{$post['id']}'", 201);

                    return $this->db->commit();
                }
            }
        }
        trigger_error($this->db->error());
        $this->db->rollback();

        return false;
    }

    /**
     * Remove category data.
     *
     * @return bool
     */
    protected function remove()
    {
        $this->checkPermission('cms.category.delete');

        list($kind, $id) = explode(':', $this->request->param('delete'));

        $this->db->begin();

        if (false === $this->isEmpty($id)) {
            $this->session->param('messages', Lang::translate('NOT_EMPTY'));

            return false;
        }

        $sitekey = $this->db->quote($this->siteID);
        $path = $this->getCategoryPath($id, 1);
        $parent = $this->parentCategory($id, 'id, title');

        if (false !== $this->db->delete('category', "id = ? AND sitekey = ? AND reserved = '0'", [$id, $this->siteID])
            && false !== $this->db->nsmCleanup('category')
        ) {
            if (!file_exists($path) || File::rmdirs($path, true)) {
                if ($this->siteProperty('type') === 'static') {
                    self::reassembly($parent);
                }
                $this->app->logger->log("Remove the category `{$id}'", 201);

                return $this->db->commit();
            }
        } else {
            trigger_error($this->db->error());
        }
        $this->db->rollback();

        return false;
    }

    /**
     * Category is empty or not.
     *
     * @param int $id
     *
     * @return bool
     */
    public function isEmpty($id)
    {
        $record_count = 0;

        $children = '(SELECT * FROM table::category WHERE sitekey = ?)';
        $parent = '(SELECT * FROM table::category WHERE id = ?)';
        if (false === $categories = $this->db->nsmGetCount($parent, $children, [$id, $this->siteID, $this->siteID])) {
            return false;
        }
        if (false === $entries = $this->db->count('entry', 'category = ?', [$id])) {
            return false;
        }

        return ((int) $categories + (int) $entries) === 0;
    }

    /**
     * Categories of Entry.
     *
     * @param int    $category
     * @param string $columns
     * @param string $rootkey
     *
     * @return mixed
     */
    public function categories($category, $columns = '*', $rootkey = 'rootcategory')
    {
        $tmp = Text::explode(',', $columns);

        $cols = [];
        foreach ($tmp as $col) {
            $quot = ($col === '*') ? '' : '`';
            $cols[] = "middle.$quot$col$quot";
        }

        $columns = implode(',', $cols);
        $table = "(SELECT * FROM table::category WHERE sitekey = :site_id AND reserved = '0')";
        $root = $this->site_data[$rootkey];

        return $this->db->nsmGetNodePath($columns, $table, null, null, ['site_id' => $this->siteID, $root, $category]);
    }

    /**
     * Public Category Path.
     *
     * @param int $id
     * @param int $type
     *
     * @return string
     */
    public function getCategoryPath($id, $type = 0)
    {
        $paths = $this->categories($id, 'path');
        $path = array_column($paths, 'path');
        $path = array_filter($path, function ($var) {
            return $var !== '' && $var !== '/';
        });

        $file_name = $this->defaultPage($id);
        if ($type > 3 && $file_name === $this->site_data['defaultpage']) {
            $file_name = '';
        }

        switch ($type) {
        case 5: // relative URL
            $cbc = $this->session->param('current_build_category');
            if (!empty($cbc)) {
                $build_cwd = trim($this->getCategoryPath($cbc, 3), '/');
                $cwd = explode('/', $build_cwd);
                $cwd = array_filter($cwd, function ($var) {
                    return $var !== '' && $var !== '/';
                });
                $diff = count($path) - count($cwd);
                $static = '.';
                if ($diff > 0) {
                    $category_path = implode('/', $path);
                    $static = ltrim(str_replace($build_cwd, '', $category_path), '/');
                } elseif ($diff < 0) {
                    $static = rtrim(str_repeat('../', abs($diff)), '/');
                }

                return $static . '/' . $file_name;
            }
            // no break
        case 4: // physical URL
            $path[] = $file_name;

            return $this->site_data['path'] . implode('/', $path);
        case 3: // backward compatible
            return preg_replace('/^\/+/', '/', File::realpath('/'.implode('/', $path).'/'));
        case 2: // backward compatible
            return File::realpath('/'.implode('/', $path));
        case 1: // only directory
            break;
        case 0: // physical path
            $path[] = $file_name;
            break;
        }

        return File::realpath($this->site_data['openpath'] . '/' . implode('/', $path));
    }

    /**
     * Public Path.
     *
     * @param int $id
     * @param int $type
     *
     * @return string
     */
    public function getEntryPath($id, $type = 0)
    {
        if (empty($id)) {
            return;
        }

        $sql = file_get_contents(__DIR__ . '/Entry/entry_path.sql');
        if (false === ($unit = $this->db->getAll($sql, [$id], Db::GET_RETURN_HASH))) {
            return;
        }

        if ($type > 0 && $unit['filepath'] === $this->site_data['defaultpage']) {
            $unit['filepath'] = '';
        }

        $category_path = trim($this->getCategoryPath($unit['category'], 2), '/');

        switch ($type) {
        case 4: // relative URL
            $build_cwd = rtrim($this->getCategoryPath($this->session->param('current_build_category'), 4), '/');
            $category_path = rtrim($this->getCategoryPath($unit['category'], 4), '/');
            $cwd = explode('/', $build_cwd);
            $ret = explode('/', $category_path);
            $diff = count($ret) - count($cwd);
            $static = '.';
            if ($diff > 0) {
                $static = ltrim(str_replace($build_cwd, '', $category_path), '/');
            } elseif ($diff < 0) {
                $static = rtrim(str_repeat('../', abs($diff)), '/');
            }
            $category_path = '';
            break;
        case 3: // URL
            $static = rtrim($this->site_data['url'], '/') . '/';
            break;
        case 2: // absolute URL
            $static = rtrim($this->site_data['path'], '/') . '/';
            break;
        case 1: // backward compatible
            $static = '/';
            break;
        case 0: // physical path
            $static = rtrim($this->site_data['openpath'], '/') . '/';
            break;
        }


        return $static . $category_path . '/' . $unit['filepath'];
    }

    /**
     * Parent of the category
     *
     * @param int    $id
     * @param string $col
     *
     * @return array|false
     */
    //public function parentCategory($id, $col = '*')
    //{
    //    $tmp = Text::explode(',', $col);
    //    $columns = [];
    //    foreach ($tmp as $column) {
    //        $columns[] = 'parent.'.$column;
    //    }
    //    $columns = implode(',', $columns);

    //    return $this->db->nsmGetParent(
    //        $columns,
    //        '(SELECT * FROM table::category WHERE sitekey = :site_id)',
    //        '(SELECT * FROM table::category WHERE id = :category_id)',
    //        ['site_id' => $this->siteID, 'category_id' => $id]
    //    );
    //}

    /**
     * Children of the category.
     *
     * @param int $id
     *
     * @return array|bool
     */
    public function childCategories($id, $col = '*', $depth = 0, $order_by = 'children.priority')
    {
        $tmp = Text::explode(',', $col);
        $columns = [];
        foreach ($tmp as $column) {
            $columns[] = 'children.'.$column;
        }
        $columns = implode(',', $columns);
        $columns .= ",(SELECT COUNT(*) FROM table::entry WHERE sitekey = :site_id AND category = children.id AND revision = 0 AND trash <> '1' GROUP BY category) AS cnt";
        $columns .= ",(SELECT COUNT(*) FROM table::entry WHERE sitekey = :site_id AND category = children.id AND active = 1 AND trash <> '1' GROUP BY category) AS active_cnt";

        if (is_null($id)) {
            if ($this->hideSiteRoot() === false) {
                return [ self::rootCategory($columns) ];
            }
            $id = $this->site_data['rootcategory'];
        }

        $parent = '(SELECT * FROM table::category WHERE id = :category_id)';
        $midparent = '(SELECT * FROM table::category WHERE sitekey = :site_id)';
        $children = $this->categoryListSQL();

        $sort = " ORDER BY {$order_by}";
        $list = $this->db->nsmGetChildren($columns, $parent, $midparent, $children, "AND children.id IS NOT NULL{$sort}", ['site_id' => $this->siteID, 'category_id' => $id]);

        if (false === $list) {
            trigger_error($this->db->error());

            return false;
        }

        foreach ($list as &$unit) {
            if (!isset($unit['id'])) {
                continue;
            }
            // Custom fields
            $customs = $this->db->select(
                'name, data',
                'custom',
                'WHERE sitekey = ? AND relkey = ? AND kind = ?',
                [$this->siteID, $unit['id'], 'category']
            );
            foreach ((array)$customs as $custom) {
                if (!array_key_exists($custom['name'], $unit)) {
                    $unit[$custom['name']] = $custom['data'];
                }
            }

            $range = $this->db->get('lft,rgt', 'category', 'id = ?', [$unit['id']]);
            $count = $this->db->count('category', 'sitekey = ? AND lft > ? AND rgt < ?', [$this->siteID, $range['lft'], $range['rgt']]);
            $unit['child_count'] = $count;
            if (empty($unit['template'])) {
                $defaultpage = $this->defaultPage($unit['id']);
                if (!$this->db->exists(
                    'entry',
                    'sitekey = ? AND category = ? AND filepath = ? AND active = ?',
                    [$this->siteID, $unit['id'], $defaultpage, '1']
                )) {
                    continue;
                }
            }
            $unit['url'] = $this->getCategoryPath($unit['id'], 3);
            $unit['relative'] = $this->getCategoryPath($unit['id'], 5);
        }
        unset($unit);

        return $list;
    }

    /**
     * SQL for list category.
     *
     * @return string
     */
    public function categoryListSQL()
    {
        $sql = "(SELECT * FROM table::category WHERE sitekey = ? AND reserved = '0' AND trash <> '1')";
        $options = [$this->siteID];
        $permission = "SELECT * FROM table::permission WHERE userkey = ? AND application = 'cms' AND class = 'category' AND type = 'read'";
        $sql = "(SELECT c.* FROM $sql c LEFT JOIN ($permission) p ON c.id = p.filter2 WHERE p.priv != '0' OR p.priv IS NULL)";
        $options[] = $this->uid;

        return $this->db->build($sql, $options);
    }

    public function mixListOfCategory($category_id, $categories = null, $sort = 'DESC')
    {
        if (!is_array($categories)) {
            $categories = [];
        }
        $entries = $this->entries('', 0, 0, 0, 0, null, $sort, $category_id);

        $items = [];
        foreach (array_merge($categories, $entries) as $unit) {
            $key = date('YmdHis', strtotime($unit['author_date']));
            while (isset($items[$key])) {
                ++$key;
            }
            $items[$key] = $unit;
        }
        if (strtoupper($sort) === 'DESC') {
            krsort($items);
        } elseif (strtoupper($sort) === 'ASC') {
            ksort($items);
        }

        return $items;
    }

    /**
     * Build single archive page.
     *
     * @param int $category_id
     * @param bool $preview
     *
     * @return bool
     */
    public function buildArchive($category_id, $preview = false)
    {
        // If entry exists for default page
        if (method_exists($this, 'build')) {
            $release_path = $this->getCategoryPath($category_id);
            $release_file = basename($release_path);
            $eid = $this->db->get('id', 'entry', 'category = ? AND filepath = ? AND active = 1', [$category_id, $release_file]);
            if ($category_id !== $this->site_data['rootcategory'] && !empty($eid)) {
                $source = $this->build($eid, $preview, true);

                return $source;
            }
        }

        $build_type = $this->session->param('build_type');
        $this->session->param('build_type', 'archive');

        $category = $this->categoryData($category_id, 'id,title,path,filepath,template,archive_format');
        $this->view->bind('current', $category);

        $defaultpage = $this->defaultPage($category['id']);
        $file_name = pathinfo($defaultpage, PATHINFO_FILENAME);
        $file_extension = pathinfo($defaultpage, PATHINFO_EXTENSION);

        $current_build_category_origin = $this->session->param('current_build_category');
        $this->session->param('current_build_category', $category['id']);

        $apps = new Response($this->app);
        $apps->pager = new Pagination();
        $suffix_separator = '.';
        $apps->pager->setSuffix($suffix_separator);
        $apps->pager->setLinkFormat(sprintf('%s%s%%s.%s', $file_name, $suffix_separator, $file_extension));

        $page = $this->request->param('current_page');
        if (empty($page)) {
            $page = 1;
        }
        $apps->pager->setCurrentPage($page);

        $this->view->bind('apps', $apps);
        $this->view->bind('build_type', $this->session->param('build_type'));

        $this->bindSiteData("{$category['url']}/");

        $template = $this->db->get('path', 'template', 'id = ?', [$category['template']]);

        $html_class = str_replace('_', '-', $template);
        $html_id = $this->pathToID($category['url']);
        if (empty($html_id)) {
            $html_id = $html_class;
        }
        $this->setHtmlId($html_id);

        $sub_class = $this->pathToID($category['path']);
        if ($this->request->param('in_reassemble') === '1') {
            $this->setHtmlClass([$html_class, $sub_class]);
        } else {
            $this->appendHtmlClass([$html_class, $sub_class]);
        }

        $path = $this->templatePath($category['template']);
        $this->setPathToView(dirname($path));
        $source = $this->view->render(basename($path), $preview);

        $this->session->param('current_build_category', $current_build_category_origin);

        $this->session->param('build_type', $build_type);

        $this->page_suffix_watcher = $apps->pager->suffix($page);
        $this->page_separation_watcher = $apps->page_separation;

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
     * Build archive pages.
     *
     * @param int $entry_id
     *
     * @return bool
     */
    public function buildArchives($entry_id)
    {
        $build_type = $this->session->param('build_type');
        $this->session->param('build_type', 'archive');

        $category_id = $this->db->get('category', 'entry', 'id = ?', [$entry_id]);
        $release_path = $this->getCategoryPath($category_id);
        $release_file = basename($release_path);
        $release_dir = dirname($release_path);
        $original_file = pathinfo($release_path, PATHINFO_FILENAME);
        $file_extension = pathinfo($release_path, PATHINFO_EXTENSION);

        $categories = $this->categories($category_id, 'id, title, path, filepath, template, archive_format');
        $categories = array_reverse($categories);
        foreach ($categories as $category) {
            $original_release_dir = $release_dir;
            $release_dir = dirname($release_dir);
            $url = $this->getCategoryPath($category['id'], 2);
            $dir = File::realpath($this->site_data['openpath'].'/'.$url);

            if (!empty($category['archive_format'])) {
                $format = preg_replace('/%[a-z]/i', '*', $category['archive_format']);
                foreach ((array)glob("$dir/$format.$file_extension") as $remove_file) {
                    unlink($remove_file);
                }
            }

            $arr_archives_name = [];
            if (!empty($category['archive_format'])) {
                $fetch = $this->getArchivesLink($category['id']);
                foreach ((array)$fetch as $unit) {
                    $arr_archives_name[] = $unit['format'];
                }
            }
            array_unshift($arr_archives_name, $original_file);

            foreach ($arr_archives_name as $file_name) {

                // if find same name in entries
                if (strval($category['id']) !== strval($this->site_data['rootcategory'])
                    && method_exists($this, 'build')
                ) {
                    $eid = $this->db->get('id', 'entry', 'category = ? AND filepath = ? AND active = 1', [$category['id'], $release_file]);
                    if (!empty($eid)) {
                        if ($eid === $entry_id) {
                            continue;
                        }
                        $source = $this->build($eid, false, true);
                        $path = sprintf('%s/%s.%s', $original_release_dir, $file_name, $file_extension);
                        if (empty($source)) {
                            if (file_exists($path)) {
                                @unlink($path);
                                $this->app->logger->log("Remove archive file `$path'", 201);
                            }
                        } else {
                            file_put_contents($path, $source);
                            $this->app->logger->log("Create archive file `$path'", 201);
                        }
                        continue;
                    }
                }

                if (empty($category['template'])) {
                    continue;
                }

                foreach ((array)glob("$dir/$file_name*.$file_extension") as $remove_file) {
                    unlink($remove_file);
                }

                if ($file_name !== $original_file) {
                    $this->setArchiveMonthOfTheYear($file_name, $category['archive_format']);
                }

                $page = 1;
                do {
                    $this->request->param('current_page', $page);
                    $source = $this->buildArchive($category['id'], true);
                    $path = sprintf(
                        '%s/%s%s.%s',
                        $dir,
                        $file_name,
                        $this->page_suffix_watcher,
                        $file_extension
                    );

                    try {
                        file_put_contents($path, $source);
                    } catch (ErrorException $e) {
                        trigger_error($e->getMessage());

                        return false;
                    }
                    ++$page;
                } while ($this->page_separation_watcher);
                $this->request->param('amy', null, true);
            }
        }

        $this->session->param('build_type', $build_type);

        return true;
    }

    /**
     * Recent Entries.
     *
     * @param int   $row
     * @param int   $pagenation
     * @param array $filter_category
     *
     * @return array
     */
    public function recent($row = 0, $pagenation = 0, $filter_category = null)
    {
        $this->page_separation = (int) $pagenation !== 0;

        $date_option = '';
        $date_option .= ' AND (release_date <= CURRENT_TIMESTAMP OR release_date IS NULL)';
        $date_option .= ' AND (close_date > CURRENT_TIMESTAMP OR close_date IS NULL)';

        $statement = "sitekey = ? AND active = ?$date_option ORDER BY author_date DESC";
        $options = [$this->siteID, 1];
        $total = $this->db->count('entry', $statement, $options);

        if ($this->page_separation) {
            if (is_null($this->pager)) {
                $this->pager = new Pagination();
            }
            if (!$this->pager->isInited()) {
                $this->pager->init($total, $row);
            }
        }

        if ($this->offset + $row >= $total) {
            $this->page_separation = false;
        }

        $statement = "WHERE $statement";
        if ($row > 0) {
            $offset = ($this->offset > 0) ? $this->offset.',' : '';
            $statement .= " LIMIT $offset$row";
        }
        $this->offset += $row;

        $replaced = false;
        $index = 0;
        if ($this->session->param('ispreview') === 1) {
            $preview_data = $this->session->param('preview_data');
            $author_date = strtotime($preview_data['author_date'] ?? date('Y-m-d H:is'));
        }
        $list = (array)$this->db->select('*', 'entry', $statement, $options);
        foreach ($list as $i => &$unit) {
            if (isset($preview_data)) {
                if ($unit['identifier'] === $preview_data['identifier']) {
                    $unit = $preview_data;
                    $replaced = true;
                } elseif ($replaced === false) {
                    if (strtotime($unit['author_date']) > $author_date) {
                        $index = $i + 1;
                    }
                }
            } else {
                $unit['url'] = $this->getEntryPath($unit['id'], 2);
                $unit['relative'] = $this->getEntryPath($unit['id'], 4);
                $unit['html_id'] = $this->pathToID($unit['url']);
            }

            // Custom fields
            $customs = $this->db->select(
                'name, data',
                'custom',
                'WHERE sitekey = ? AND relkey = ? AND kind = ?',
                [$this->siteID, $unit['identifier'], 'entry']
            );
            foreach ((array)$customs as $custom) {
                if (!array_key_exists($custom['name'], $unit)) {
                    $unit[$custom['name']] = $custom['data'];
                }
            }
        }
        unset($unit);

        if ($replaced === false
            && isset($preview_data)
            && in_array($preview_data['category'], $categories)
        ) {
            array_splice($list, $index, 0, $preview_data);
            if (count($list) > $row) {
                array_pop($list);
            }
        }

        return $list;
    }

    public function entry($filter = '')
    {
        $statement = 'sitekey = ? AND active = ? AND (id = ? OR path = ? OR filepath = ? OR title = ?)';
        $options = [$this->siteID, 1, $filter, $filter, $filter, $filter];
        $unit = $this->db->get('*', 'entry', $statement, $options);

        if (empty($unit)) {
            return;
        }

        // Custom fields
        $customs = $this->db->select(
            'name, data',
            'custom',
            'WHERE sitekey = ? AND relkey = ? AND kind = ?',
            [$this->siteID, $unit['identifier'], 'entry']
        );
        foreach ((array)$customs as $custom) {
            if (!array_key_exists($custom['name'], $unit)) {
                $unit[$custom['name']] = $custom['data'];
            }
        }

        return $unit;
    }

    /**
     * list entries
     *
     * @param string $filter
     * @param int    $recursive
     * @param int    $row
     * @param int    $offset
     * @param int    $pagenation
     *
     * @return array
     */
    public function entries($filter = '', $recursive = 0, $row = 0, $offset = 0, $pagenation = 0, $current_page = null, $sort = 'ASC', $chroot = null)
    {
        if (is_null($current_page)) {
            $current_page = $this->request->param('current_page');
        }

        $this->offset = (!is_null($current_page)) ? ($current_page - 1) * $row : $offset;

        $statement = 'sitekey = ? AND active = ?';
        $options = [$this->siteID, 1];

        if ($this->site_data['type'] === 'dynamic' && $this->view->param('isfrontend') === 1) {
            $statement .= ' AND (`acl` = 0 OR `acl`&?)';
            $options[] = $this->userinfo['priv'];
        }

        if (is_null($chroot)) {
            $chroot = $this->session->param('current_build_category');
        } elseif ($chroot === '0' || $chroot === 0) {
            $chroot = self::rootCategory();
        }

        $stat = 'WHERE sitekey = ?';
        $opt = [$this->siteID];

        if (!empty($filter)) {
            $range = $this->db->get('lft,rgt', 'category', 'id=?', [$chroot]);
            $stat .= ' AND (lft >= ? AND rgt <= ?)';
            $opt[] = $range['lft'];
            $opt[] = $range['rgt'];
            $filters = Text::explode(',', $filter);
            $placeholder = implode(',', array_fill(0, count($filters), '?'));
            $stat .= " AND (id IN ($placeholder) OR path IN($placeholder) OR title IN($placeholder))";
            $opt = array_merge($opt, $filters, $filters, $filters);
        } else {
            $stat .= ' AND id = ?';
            $opt[] = $chroot;
        }

        $ids = $this->db->select('id', 'category', $stat, $opt);
        if (!empty($filter) && empty($ids)) {
            return;
        }
        $categories = array_column((array)$ids, 'id');

        if ((bool)$recursive) {
            $child_categories = [];
            foreach ($categories as $category) {
                $parent = '(SELECT * FROM table::category WHERE id = :category_id)';
                $children = 'table::category';
                if (false !== $children = $this->db->nsmGetDecendants('children.id', $parent, $children, ['category_id' => $category])) {
                    foreach ($children as $unit) {
                        $child_categories[] = $unit['id'];
                    }
                } else {
                    trigger_error($this->db->error());
                }
            }
            $categories = array_merge($categories, $child_categories);
        }

        if (count($categories) === 1) {
            $statement .= ' AND category = ?';
            $options[] = $categories[0];
        } elseif (count($categories) > 1) {
            $categories = array_values(array_unique($categories));
            $statement .= ' AND category IN('.implode(',', array_fill(0, count($categories), '?')).')';
            $options = array_merge($options, $categories);
        }

        if (!empty($this->request->param('aby'))) {
            $year = (int)$this->request->param('aby');
            $statement .= " AND author_date >= '$year-01-01 00:00:00' AND author_date <= '$year-12-31 23:59:59'";
        }

        if (!empty($this->request->param('amy'))) {
            $date = explode('-', $this->request->param('amy'));
            $year = array_shift($date);
            $start_month = '01';
            $end_month = '12';
            $start_day = '01';
            $end_day = '31';
            if (!empty($date)) {
                $start_month = array_shift($date);
                $end_month = $start_month;
                $end_day = date('t', strtotime("$year-$start_month"));
                if (!empty($date)) {
                    $start_day = array_shift($date);
                    $end_day = $start_day;
                }
            }
            $statement .= " AND author_date >= '$year-$start_month-$start_day 00:00:00' AND author_date <= '$year-$end_month-$end_day 23:59:59'";
        }

        // TODO: These statements are toggle by argument
        //$statement .= " AND body IS NOT NULL AND body != ''";
        $statement .= ' AND (release_date <= CURRENT_TIMESTAMP OR release_date IS NULL)';
        $statement .= ' AND (close_date > CURRENT_TIMESTAMP OR close_date IS NULL)';

        $statement .= " ORDER BY author_date {$sort}, modify_date {$sort}";

        $this->page_separation = (int) $pagenation !== 0;
        //$total = $this->db->count('entry', $statement, $options);
        $total = $this->db->recordCount("SELECT * FROM table::entry WHERE {$statement}", $options);
        if ($this->page_separation) {
            if (is_null($this->pager)) {
                $this->pager = new Pagination();
            }
            if (!$this->pager->isInited()) {
                $this->pager->init($total, $row);
                if (!is_null($current_page)) {
                    $this->pager->setCurrentPage($current_page);
                }
            } elseif ($this->pager->total() !== $total) {
                $this->pager->reset($total);
            }
        }
        if ($this->offset + $row >= $total) {
            $this->page_separation = false;
        }
        if ($row > 0) {
            $offset = ($this->offset > 0) ? $this->offset.',' : '';
            $statement .= " LIMIT $offset$row";
        }
        $this->offset += $row;

        $replaced = false;
        $index = 0;
        if ($this->session->param('ispreview') === 1) {
            $preview_data = $this->session->param('preview_data');
            $author_date = strtotime($preview_data['author_date'] ?? date('Y-m-d H:i:s'));
        }
        $list = (array)$this->db->select('*', 'entry', "WHERE $statement", $options);
        foreach ($list as $i => &$unit) {
            if (isset($preview_data)) {
                if ($unit['identifier'] === $preview_data['identifier']) {
                    $unit = $preview_data;
                    $replaced = true;
                } elseif ($replaced === false) {
                    if (strtotime($unit['author_date']) > $author_date) {
                        $index = $i + 1;
                    }
                }
            } else {
                $unit['url'] = $this->getEntryPath($unit['id'], 2);
                $unit['relative'] = $this->getEntryPath($unit['id'], 4);
                $unit['html_id'] = $this->pathToID($unit['url']);
            }

            // Custom fields
            $customs = $this->db->select(
                'name, data',
                'custom',
                'WHERE sitekey = ? AND relkey = ? AND kind = ?',
                [$this->siteID, $unit['identifier'], 'entry']
            );
            foreach ((array)$customs as $custom) {
                if (!array_key_exists($custom['name'], $unit)) {
                    $unit[$custom['name']] = $custom['data'];
                }
            }
        }
        unset($unit);

        if ($replaced === false
            && isset($preview_data)
            && in_array($preview_data['category'], $categories)
        ) {
            array_splice($list, $index, 0, [$preview_data]);
            if ($row > 0 && count($list) > $row) {
                array_pop($list);
            }
        }

        return (empty($list)) ? null : $list;
    }

    /**
     * Children of the section.
     *
     * @param int   $entrykey
     * @param int   $sectionkey
     * @param array $columns
     *
     * @return array
     */
    public function sections($entrykey, $sectionkey, array $columns = null, $sort = 'ASC')
    {
        $cols = ['children.id AS relkey'];
        if (is_null($columns)) {
            $cols[] = 'children.*';
        } else {
            foreach ($columns as $column) {
                $cols[] = "children.`$column`";
            }
        }
        $columns = implode(',', $cols);

        $statement = $this->filterPreview();
        $order = " ORDER BY children.author_date $sort";

        if (is_null($sectionkey)) {
            $table = "(SELECT * FROM table::section WHERE entrykey = :entry_id$statement ORDER BY author_date)";
            $list = (array)$this->db->nsmGetRoot($columns, $table, null, ['entry_id' => $entrykey], $order);
        } else {
            $parent = '(SELECT * FROM table::section WHERE id = :section_id)';
            $children = "(SELECT * FROM table::section WHERE entrykey = :entry_id$statement)";
            $list = (array)$this->db->nsmGetChildren($columns, $parent, $children, $children, " AND children.id IS NOT NULL$order", ['entry_id' => $entrykey, 'section_id' => $sectionkey]);
        }

        // custom data
        foreach ($list as &$data) {
            $customs = (array)$this->db->select(
                'name, data',
                'custom',
                'WHERE sitekey = ? AND relkey = ? AND kind = ?',
                [$this->siteID, $data['relkey'], 'section']
            );
            foreach ($customs as $unit) {
                $data[$unit['name']] = $unit['data'];
            }
            unset($data['relkey']);
        }
        unset($data);

        return $list;
    }

    /**
     * Single attachment file.
     *
     * @param int    $entrykey
     * @param string $kind
     * @param string $filter
     *
     * @return mixed
     */
    public function attachment($entrykey, $kind = 'entry', $filter = '')
    {
        $ret = $this->attachments($entrykey, $kind, $filter, 1);
        if (!empty($ret)) {
            return array_shift($ret);
        }
    }

    /**
     * Attachment files.
     *
     * @param int    $entrykey
     * @param string $kind
     * @param string $filter
     * @param int    $limit
     *
     * @return mixed
     */
    public function attachments($entrykey, $kind = 'entry', $filter = '', $limit = null)
    {
        if ($this->session->param('ispreview') === 1) {
            $list = $this->session->param('preview_attachments');
        } else {
            if (!is_null($limit) && preg_match('/^[0-9]+(\s*,\s*[0-9]+)?$/', $limit)) {
                $limit = " LIMIT $limit";
            }
            $relkey_condition = '= ?';
            $statement = "WHERE sitekey = ? AND kind = ? AND name LIKE ? AND relkey $relkey_condition";
            $options = [$this->siteID, $kind, 'file.%', $entrykey];
            $list = $this->db->select(
                'id, name, mime, alternate, data AS path, note',
                'custom',
                "$statement ORDER BY `sort`$limit",
                $options
            );
        }

        if (empty($list)) {
            return;
        }

        $upload_dir = File::realpath('/'.$this->site_data['uploaddir']);
        $dir = $this->site_data['openpath'];
        foreach ($list as &$data) {
            if ($this->session->param('ispreview') === 1) {
                $deletekey = 'id_'.($data['id'] ?? '');
                if (isset($delete[$deletekey])) {
                    $data = null;
                    continue;
                }
            }

            // Thumbnail
            $pathinfo = pathinfo($data['path']);
            $thumbname = "{$pathinfo['dirname']}/{$pathinfo['basename']}";
            $thumbnails = glob("$dir{$thumbname}-*".parent::THUMBNAIL_EXTENSION);
            $path = "{$pathinfo['dirname']}/";
            foreach ($thumbnails as $thumbnail) {
                if (!isset($data['thumbnail'])) {
                    $data['thumbnail'] = [];
                }
                $data['thumbnail'][] = $path.basename($thumbnail);
            }

            if (empty($data['name'])) {
                $data['name'] = $pathinfo['filename'];
            } else {
                $data['name'] = preg_replace('/^file\./', '', $data['name']);
            }
        }
        unset($data);

        return (empty($list)) ? null : array_filter($list);
    }

    /**
     * Children of the section.
     *
     * @param int   $eid
     * @param int   $id
     * @param array $columns
     *
     * @return array
     */
    public function childSections($eid, $id, array $columns = null, $order_by = ' ORDER BY children.author_date')
    {
        $cols = [];
        if (is_null($columns)) {
            $cols[] = 'children.*';
        } else {
            foreach ($columns as $column) {
                $cols[] = "children.`$column`";
            }
        }
        $columns = implode(',', $cols);

        if (is_null($id)) {
            $table = '(SELECT * FROM table::section WHERE entrykey = :entry_id AND revision = 0 ORDER BY author_date)';
            $list = $this->db->nsmGetRoot($columns, $table, null, ['entry_id' => $eid], $order_by);
        } else {
            $parent = '(SELECT * FROM table::section WHERE id = :section_id)';
            $children = '(SELECT * FROM table::section WHERE entrykey = :entry_id AND revision = 0)';
            $list = $this->db->nsmGetChildren($columns, $parent, $children, $children, " AND children.id IS NOT NULL{$order_by}", ['entry_id' => $eid, 'section_id' => $id]);
        }

        foreach ($list as &$item) {
            if (!$this->db->exists('section', 'identifier = ? AND identifier <> id', [$item['id']])) {
                $item['new'] = 1;
            }
        }
        unset($item);

        return $list;
    }

    /**
     * List of categories
     *
     * @param string $filter
     * @param string $wildcard
     * @param string $columns
     *
     * @return array
     */
    public function categoriesList($filter = '', $wildcard = '', $columns = 'id')
    {
        $statement = 'WHERE sitekey = ?';
        $options = [$this->siteID];

        if (!empty($filter)) {
            $filters = Text::explode(',', $filter);
            $placeholder = implode(',', array_fill(0, count($filters), '?'));
            $where = "id IN($placeholder) OR path IN($placeholder) OR title IN($placeholder)";
        }

        if (!empty($wildcard)) {
            $filters = [str_replace('*', '%', $wildcard)];
            $where = 'path LIKE ? OR filepath LIKE ? OR title LIKE ?';
        }
        $statement = "WHERE sitekey = ? AND ($where)";
        $options = array_merge([$this->siteID], $filters, $filters, $filters);
        $ids = $this->db->select($columns, 'category', $statement, $options);
        if ($ids === false) {
            trigger_error($this->db->error());
        }

        $list = [];
        foreach ($ids as $i) {
            if ($columns !== 'id') {
                $list[] = $i;
                continue;
            }
            if (false === $data = $this->childCategories($i['id'])) {
                continue;
            }
            foreach ($data as $n => $unit) {
                if (!empty($unit['template'])) {
                    $unit['url'] = $this->getCategoryPath($unit['id'], 3);
                }
                $data[$n] = $unit;
            }
            $list = array_merge($list, $data);
        }

        return $list;
    }

    /**
     * Detail of the category
     *
     * @param int    $id
     * @param string $columns
     *
     * @return array
     */
    public function categoryData($id, $columns = '*')
    {
        $data = [];
        $fetch = $this->db->select($columns, 'category', 'WHERE id = ?', [$id]);
        if (count((array) $fetch) > 0) {
            $data = $fetch[0];
        }

        // Custom fields
        $customs = $this->db->select(
            'name, data',
            'custom',
            'WHERE sitekey = ? AND relkey = ? AND kind = ?',
            [$this->siteID, $id, 'category']
        );
        foreach ((array) $customs as $unit) {
            $data[$unit['name']] = $unit['data'];
        }

        $data['url'] = $this->getCategoryPath($id, 3);
        $data['html_id'] = $this->pathToID($data['url']);

        return $data;
    }

    /**
     *  in the category
     *
     * @param int    $id
     * @param string $path
     *
     * @return bool
     */
    public function inCategory($id, ...$patterns)
    {
        $method = 'getCategoryPath';
        $path = call_user_func_array([$this, $method], [$id, 2]);
        foreach ($patterns as $pattern) {
            $regex = str_replace(['\\*','\\?'], ['[^\/]*','.?'], preg_replace('/\\*\\*(\\\\\/)?/', '.+', preg_quote($pattern, '/')));
            if (preg_match("/^$regex$/i", $path)) {
                return true;
            }
        }

        return false;
    }
    public function _inCategory($id, $path, $type = 'category')
    {
        $method = ($type === 'category') ? 'getCategoryPath' : 'getEntryPath';
        $fullpath = call_user_func_array([$this, $method], [$id, 2]);
        if (strpos($path, '/*') !== false) {
            $path = preg_quote(str_replace('/*', '', $path), '/').'(\/[^\/]+)?';

            return preg_match("/^$path$/i", $fullpath);
        }

        return strpos($fullpath, $path) === 0;
    }

    /**
     *  not in the category
     *
     * @param int    $id
     * @param string $path
     *
     * @return bool
     */
    public function notInCategory($id, ...$patterns)
    {
        return !call_user_func_array([$this,'inCategory'], func_get_args());
    }


    /**
     * Save the custom data
     *
     * @param string $kind
     * @param int    $id
     * @param array  $data
     *
     * @return int
     */
    public function saveCustomField($kind, $id, $data)
    {
        $total = 0;
        if (empty($data)) {
            return $total;
        }

        foreach ($data as $key => $value) {
            if (empty($value)) {
                if (false === $count = $this->db->delete('custom', 'sitekey = ? AND relkey = ? AND kind = ? AND name = ?', [$this->siteID, $id, $kind, $key])) {
                    trigger_error($this->db->error());

                    return false;
                }
                $total += $count;
                continue;
            }

            // Save upload files
            if (is_array($value) && method_exists($this, 'saveFiles')) {
                if (false === $count = $this->saveFiles($id, null, $error, $key)) {
                    return false;
                }
                $total += $count;
                continue;
            }

            $unit = [
                'sitekey' => $this->siteID,
                'relkey' => $id,
                'kind' => $kind,
                'name' => $key,
                'mime' => 'text/plain',
                'data' => $value,
            ];

            if (false === $count = $this->db->updateOrInsert('custom', $unit, ['sitekey', 'relkey', 'kind', 'name'])) {
                trigger_error($this->db->error());

                return false;
            }

            $total += $count;
        }

        return $total;
    }

    /**
     * List of bread crumbs
     *
     * @param int $id
     * @param bool $all
     *
     * @return array
     */
    public function breadcrumbs($id, $all = false)
    {
        $build_type = $this->session->param('build_type');

        if ($build_type === 'entry') {
            $entry_path = $this->getEntryPath($id);
            $dir = dirname($entry_path);
            $basename = basename($entry_path);
            $entry = $this->db->get('id,category,filepath AS path,title', 'entry', 'id = ?', [$id]);
            $categorykey = $entry['category'];
        } elseif ($build_type === 'feed') {
            $feed = $this->db->get('id,path,title', 'template', 'id = ?', [$id]);

            return [$feed];
        } else {
            $categorykey = $id;
        }

        $defaultpage = $this->defaultPage($categorykey);

        $categories = $this->categories($categorykey, 'id, path, title, template');
        $total = count($categories);
        foreach ($categories as $n => $category) {
            if (empty($category['template'])) {
                $statement = 'sitekey = ? AND category = ? AND filepath = ?'.$this->filterPreview();
                $replaces = [$this->siteID, $category['id'], $defaultpage];
                if (!$this->db->exists('entry', $statement, $replaces)) {
                    if ((bool)$all === false) {
                        $categories[$n] = null;
                    }
                    continue;
                }
            }

            // absolute url
            $category['url'] = $this->getCategoryPath($category['id'], 4);

            // relative url
            $repeat = $total - $n - 1;
            $category['relative'] = ($repeat > 0) ? str_repeat('../', $repeat) : './';
            $filename = $this->defaultPage($category['id']);
            if ($filename !== $this->site_data['defaultpage']) {
                $category['relative'] .= $filename;
            }

            $categories[$n] = $category;
        }

        if ($build_type === 'entry' && strpos($basename, $defaultpage) === 0) {
            array_pop($categories);
        }

        // Unset site root
        array_shift($categories);
        if ($build_type === 'entry') {
            $categories[] = $entry;
        }

        return array_values(array_filter($categories));
    }

    /**
     * Filter of data for preview
     *
     * @return string
     */
    protected function filterPreview()
    {
        return ((int)$this->session->param('ispreview') === 1) ? ' AND revision = 0' : ' AND active = 1';
    }

    /**
     * Reassemble the category.
     */
    protected function reassembly()
    {
        if ($this->siteProperty('type') !== 'static') {
            return true;
        }

        // Clear template cache
        if (false === $this->view->clearAllCaches()) {
            return false;
        }

        $all = false;
        try {
            $category_id = func_get_arg(0);
        } catch (ErrorException $e) {
            $category_id = self::rootCategory();
            $all = true;
        }

        try {
            $single = func_get_arg(1);
        } catch (ErrorException $e) {
            $single = false;
        }

        if (false === $single) {
            $range = $this->db->get('lft,rgt', 'category', 'id = ?', [$category_id]);
            $categories = $this->db->select('id,template,path,archive_format', 'category', 'WHERE lft >= ? AND rgt <= ?', [$range['lft'], $range['rgt']]);
        } else {
            $categories = [
                $this->db->get('id,template,path,archive_format', 'category', 'id = ?', [$category_id]),
            ];
        }

        foreach ($categories as $category) {
            if (empty($category['template'])) {
                continue;
            }

            $defaultpage = $this->defaultPage($category['id']);
            $original_file = pathinfo($defaultpage, PATHINFO_FILENAME);
            $file_extension = pathinfo($defaultpage, PATHINFO_EXTENSION);

            $url = $this->getCategoryPath($category['id'], 2);
            $dir = File::realpath($this->site_data['openpath'].'/'.$url);
            $directory_exists = file_exists($dir);

            $arr_archives_name = [];
            if (!empty($category['archive_format'])) {
                $format = preg_replace('/%[a-z]/i', '*', $category['archive_format']);
                foreach ((array)glob("$dir/$format.$file_extension") as $remove_file) {
                    unlink($remove_file);
                }

                $fetch = $this->getArchivesLink($category['id']);
                foreach ((array)$fetch as $unit) {
                    $arr_archives_name[] = $unit['format'];
                }
            }
            array_unshift($arr_archives_name, $original_file);

            foreach ($arr_archives_name as $file_name) {
                foreach (glob("$dir/$file_name*.$file_extension") as $remove_file) {
                    unlink($remove_file);
                }

                if ($file_name !== $original_file) {
                    $this->setArchiveMonthOfTheYear($file_name, $category['archive_format']);
                }

                $page = 1;
                do {
                    $this->request->param('current_page', $page);
                    $source = $this->buildArchive($category['id'], true);
                    $path = sprintf(
                        '%s/%s%s.%s',
                        $dir,
                        $file_name,
                        $this->page_suffix_watcher,
                        $file_extension
                    );

                    if (!$directory_exists) {
                        try {
                            mkdir($dir, 0777, true);
                            $directory_exists = true;
                        } catch (ErrorException $e) {
                            trigger_error($e->getMessage());

                            return false;
                        }
                    }

                    if (false === file_put_contents($path, $source)) {
                        trigger_error("Can't assembled $path");

                        return false;
                    }
                    ++$page;
                } while ($this->page_separation_watcher);
            }
        }

        $this->buildFeeds();

        if (false !== $single) {
            return true;
        }

        if (method_exists($this, 'createEntryFile')) {
            $filter = [$this->siteID];
            $placeholder = '';
            if ($all === false) {
                $categories = array_column($categories, 'id');
                $placeholder = ' AND category IN('.implode(',', array_fill(0, count($categories), '?')).')';
                $filter = array_merge($filter, $categories);
            }
            $entries = (array) $this->db->select(
                'id',
                'entry',
                "WHERE sitekey = ? AND active = 1$placeholder",
                $filter
            );
            foreach ($entries as $entry) {
                $id = $entry['id'];
                if (false === $this->createEntryFile($id)) {
                    trigger_error('Failure assemble '.$id);
                    continue;
                }
            }
        }

        return true;
    }

    public function archivesByYear($category_id = null, $archive_format = '%Y')
    {
        $options = [$archive_format, 1];
        $filter = (empty($category_id)) ? '' : ' AND category = ?';
        $url = $this->site_data['path'];
        if ($filter !== '') {
            $options[] = $category_id;
            $url = $this->getCategoryPath($category_id, 2);
        }
        $sql = $this->db->build(
            "SELECT DATE_FORMAT(author_date, '%Y') AS year,
                    DATE_FORMAT(author_date, ?) AS `format`
               FROM table::entry
              WHERE active = ? $filter
              GROUP BY year
              ORDER BY year DESC",
            $options
        );

        if (false !== $this->db->query($sql)) {
            $list = (array)$this->db->fetchAll();
            $file_extension = $this->site_data['defaultextension'];
            foreach ($list as &$unit) {
                $unit['url'] = rtrim($url, '/') . "/{$unit['format']}.$file_extension";
            }
            unset($unit);

            return $list;
        }
        trigger_error($this->db->error());
    }

    protected function moveCategory($self, $new_parent)
    {
        $old_parent = $this->parentCategory($self, 'id');
        if ($old_parent !== $new_parent && $self !== $new_parent) {
            $lftrgt = $this->db->select('lft,rgt', 'category', 'WHERE id = ?', [$self]);
            $lft = (int)$lftrgt[0]['lft'];
            $rgt = (int)$lftrgt[0]['rgt'];
            $offset = $rgt - $lft + 1;

            $update_parent = $this->db->prepare(
                $this->db->nsmBeforeInsertChildSQL('category')
            );

            $parent_rgt = $this->db->get('rgt', 'category', 'id = ?', [$new_parent]);
            if (false === $update_parent->execute(['parent_rgt' => $parent_rgt, 'offset' => $offset])) {
                return false;
            }

            $lftrgt = $this->db->select('lft,rgt', 'category', 'WHERE id = ?', [$self]);
            $lft = (int)$lftrgt[0]['lft'];
            $rgt = (int)$lftrgt[0]['rgt'];

            $update_self = $this->db->prepare(
                'UPDATE table::category
                    SET lft = lft + :offset,
                        rgt = rgt + :offset
                  WHERE lft >= :lft AND rgt <= :rgt'
            );
            $offset = $parent_rgt - $lft;
            if (false === $update_self->execute(['offset' => $offset, 'lft' => $lft, 'rgt' => $rgt])) {
                return false;
            }

            if (false === $this->db->nsmCleanup('category')) {
                return false;
            }
        }

        return true;
    }

    protected function bindSiteData($path)
    {
        $site = $this->site_data;
        if (isset($site['path'])) {
            $path = preg_replace('/^'.preg_quote($site['path'], '/').'/', '', $path);
        }
        $dir = dirname("$path.");
        if ($dir !== '' && $dir !== '.' && $dir !== '/') {
            $dirs = explode('/', $dir);
            $depth = array_fill(0, count($dirs), '..');
            $site['relative'] = implode('/', $depth).'/';
        }
        $site['current'] = '';

        if ($this->session->param('ispreview') === 1) {
            $content_uri = (parse_url(rtrim($site['url'], '/').'/'.$path))['path'];
            $system_uri = (parse_url('//' . Environment::server('server_name') . Environment::server('request_uri')))['path'];
            $content_dir = (pathinfo("$content_uri."))['dirname'];
            $system_dir = (pathinfo("$system_uri."))['dirname'];

            $content_count = count(explode('/', ltrim($content_dir, '/')));
            $system_count = count(explode('/', ltrim($system_dir, '/')));

            $site['current'] = '';
            for ($i = 0; $i < $system_count; $i++) {
                $site['current'] .= '../';
            }
            $site['current'] .= ltrim($content_dir, '/') . '/';

            $diff = $content_count - $system_count;
            $depth = array_fill(0, $content_count - $diff, '..');
            $site['relative'] = implode('/', $depth).'/';
        }

        $this->view->bind('site', $site);
    }

    private function setArchiveMonthOfTheYear($file_name, $archive_format)
    {
        $year = date('Y');
        if (preg_match_all('/%[a-z]/i', $archive_format, $params)) {
            $pattern = preg_replace('/%[a-z]/i', '(.+)', $archive_format);
            preg_match("/$pattern/", $file_name, $matches);
            foreach ($params[0] as $i => $value) {
                if ($value === '%Y' || $value === '%y') {
                    $year = $matches[$i + 1];
                } else {
                    $month = $matches[$i + 1];
                }
            }
        }

        $date_format = 'Y-m';
        if (empty($month)) {
            $date_format = 'Y';
            $month = date('m');
        }
        $this->request->param('amy', date($date_format, strtotime(implode('-', array_filter([$year, $month])))));
    }

    /**
     * Interface for View class
     *
     * @param int $category_id
     *
     * @return array
     */
    public function archivesLink($category_id, $sort = 'ASC')
    {
        $file_extension = $this->site_data['defaultextension'];
        $url = $this->getCategoryPath($category_id, 2);
        $fetch = (array)$this->getArchivesLInk($category_id, $sort);
        foreach ($fetch as &$unit) {
            $unit['url'] = "$url/{$unit['format']}.$file_extension";
        }
        unset($unit);

        return $fetch;
    }

    private function getArchivesLink($category_id, $sort = 'ASC')
    {
        $archive_format = $this->db->get('archive_format', 'category', 'id = ?', [$category_id]);
        if (empty($archive_format)) {
            return;
        }

        $order = '';
        if (!empty($sort) && in_array(strtoupper($sort), ['ASC','DESC'])) {
            $order = " ORDER BY author_date $sort";
        }

        return $this->db->getAll(
            'SELECT DATE_FORMAT(author_date, ?) AS format, MIN(author_date) AS author_date
               FROM table::entry
              WHERE sitekey = ? AND category = ? AND active = ?
              GROUP BY format',
            [$archive_format, $this->siteID, $category_id, 1]
        );
    }

    public function defaultPage($category_id)
    {
        $filepath = $this->db->get(
            'filepath',
            'category',
            'id = ? AND sitekey = ?',
            [$category_id, $this->siteID]
        );

        return (!empty($filepath)) ? $filepath : $this->site_data['defaultpage'];
    }
}
