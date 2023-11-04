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

use ErrorException;
use Gsnowhawk\Common\Environment;
use Gsnowhawk\Common\File;
use Gsnowhawk\Common\Lang;
use Gsnowhawk\Common\Text;
use Gsnowhawk\Common;
use Gsnowhawk\Db;
use Gsnowhawk\PermitException;
use Gsnowhawk\Security;
use Gsnowhawk\User;
use Gsnowhawk\View;
use Gsnowhawk\ViewException;

/**
 * Site management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Site extends \Gsnowhawk\Cms
{
    /*
     * Using common accessor methods
     */
    use \Gsnowhawk\Accessor;

    public const DEFAULT_UPLOAD_DIR = 'upload';

    /**
     * Current site ID.
     *
     * @var int
     */
    private $siteID;

    /**
     * Current site data.
     *
     * @var array
     */
    private $site_data;

    /**
     * Current category
     *
     * @var int
     */
    private $categoryID;

    /**
     * Root category
     * This property for template engine
     *
     * @var int
     */
    protected $site_root;

    /**
     * Object constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array(parent::class.'::__construct', $params);

        $this->siteID = $this->session->param('current_site');
        if (!empty($this->siteID)) {
            $this->site_data = $this->loadSiteData($this->siteID);
            self::setCategory($this->session->param('current_category'));
        }
    }

    protected function loadSiteData($id)
    {
        $site_data = $this->db->get('*', 'site', 'id = ?', [$id]);
        if (empty($site_data)) {
            return null;
        }

        $url = parse_url($site_data['url']);
        if (empty($url['path'])) {
            $url['path'] = '/';
        }
        $site_data['path'] = $url['path'];

        if (empty($site_data['uploaddir'])) {
            $site_data['uploaddir'] = self::DEFAULT_UPLOAD_DIR;
        }

        if (empty($site_data['openpath'])) {
            $site_data['openpath'] = $this->app->cnf('global:docroot') . $site_data['path'];
        }

        $site_data['uploaddir'] = trim($site_data['uploaddir'], '/');
        $site_data['styledir'] = trim($site_data['styledir'], '/');

        // Convert to URL
        $uri = parse_url($site_data['url']);
        $site_data['uploadurl'] = $uri['path'].$site_data['uploaddir'].'/';
        $site_data['styleurl'] = $uri['path'].$site_data['styledir'].'/';

        $site_data['owner'] = $this->ownerInfo($id);

        if (is_a($this->view, 'Gsnowhawk\\View')) {
            $this->view->bind('site', $site_data);
        }

        $site_data['rootcategory'] = self::rootCategory();
        $this->site_root = $site_data['rootcategory'];

        return $site_data;
    }

    /**
     * Inserted or Updated the site data.
     *
     * Direct call of this function is prohibited
     *
     * @return bool
     */
    protected function save()
    {
        $id = $this->request->param('id');
        $check = (empty($id)) ? 'create' : 'update';
        $this->checkPermission('site.'.$check, $id);

        $valid = [];
        $valid[] = ['vl_title', 'title', 'empty'];
        $valid[] = ['vl_url', 'url', 'empty'];
        if (false === $this->validate($valid)) {
            return false;
        }

        $table = 'site';
        $post = $this->request->post();

        // Check writable
        if (empty($post['openpath'])) {
            $url = parse_url($post['url']);
            if (empty($url['path'])) {
                $url['path'] = '/';
            }
            $post['openpath'] = $this->app->cnf('global:docroot').'/'.ltrim($url['path'], '/');
            $this->request->param('openpath', $post['openpath']);
        }
        if (false === File::mkdir($post['openpath'])) {
            $this->app->err['vl_openpath'] = 2;

            return false;
        }

        $skip = ['id', 'userkey', 'create_date', 'modify_date'];
        if (!$this->hasPermission('cms.site.create')) {
            $skip = array_merge($skip, ['openpath', 'maskdir', 'maskfile', 'maskexec']);
        }

        $save = $this->createSaveData($table, $post, $skip, 'implode');
        if (!empty($post['userkey']) && $this->isAdmin()) {
            $save['userkey'] = $post['userkey'];
        }
        $raw = null;

        $this->db->begin();

        $escape_data = $this->site_data;
        $escape_id = $this->siteID;
        if (empty($post['id'])) {
            if (!isset($save['userkey'])) {
                $save['userkey'] = $this->uid;
            }
            $raw = ['create_date' => 'CURRENT_TIMESTAMP'];
            if (false !== $result = $this->db->insert($table, $save, $raw)) {
                $post['id'] = $this->db->lastInsertId(null, 'id');

                $escape_current_site = $this->session->param('current_site');
                $this->siteID = $post['id'];
                $this->session->param('current_site', $this->siteID);
                $this->site_data = $this->loadSiteData($this->siteID);

                if (false === $this->createRootCategory($post['id'])) {
                    $result = false;
                }

                if (empty($escape_current_site)) {
                    $this->session->clear('current_site');
                } else {
                    $this->session->param('current_site', $escape_current_site);
                }
            }
        } else {
            if (false !== $result = $this->db->update($table, $save, 'id = ?', [$post['id']], $raw)) {
                $this->site_data = $this->loadSiteData($post['id']);
            }
        }
        if ($result !== false) {
            $modified = ($result > 0) ? $this->db->modified($table, 'id = ?', [$post['id']]) : true;
            if ($modified) {
                // If there is a need to do something after saving
                $plugin_results = $this->app->execPlugin('afterSaveCmsSite', $this->site_data);
                foreach ((array)$plugin_results as $plugin_result) {
                    if ($plugin_result === false || is_null($plugin_result)) {
                        $reault = false;
                    } elseif (is_int($plugin_result)) {
                        $result += $plugin_result;
                    }
                }
            } else {
                $result = false;
            }
            if ($result === 0) {
                $this->app->err['vl_nochange'] = 1;
            } elseif ($result !== false) {
                $result = $this->db->commit();
                $this->cleanupMirror();
            }
        } else {
            trigger_error($this->db->error());
        }
        $this->site_data = $escape_data;
        $this->siteID = $escape_id;

        if ($result === false) {
            $this->db->rollback();
        }

        return $result;
    }

    /**
     * Change current site.
     */
    protected function changeSite()
    {
        $id = $this->request->post('choice');
        $this->checkPermission('cms.site.read', $id);
        if ($this->siteID === $id) {
            return;
        }

        $this->session->param('current_site', $id);
        $this->session->clear('current_category');
        $this->app->logger->log("Selected site `{$id}'");
        $this->siteID = $id;
        $this->site_data = $this->loadSiteData($id);

        $this->setCategory();
    }

    /**
     * Reference Templates Directory.
     *
     * @param int $sitekey
     * @param int $kind
     *
     * @return string
     */
    public function templateDirBySite($sitekey = null, $kind = null)
    {
        $sitekey = (empty($sitekey)) ? $this->siteID : $sitekey;
        if (empty($sitekey)) {
            return;
        }

        if (strval($kind) === '6') {
            $path = implode(
                '/',
                array_filter([
                    rtrim($this->site_data['openpath'], '/'),
                    trim($this->site_data['styledir'], '/')
                ])
            );
        } else {
            $path = $this->app->cnf('global:data_dir')."/mirror/$sitekey/templates";
        }

        if (!is_dir($path)) {
            File::mkdir($path);
        }

        return $path;
    }

    /**
     * Reference Templates Path.
     *
     * @param int $id
     * @param int $sitekey
     *
     * @return string
     */
    public function templatePath($id, $sitekey = null, $raw = true)
    {
        $sitekey = (empty($sitekey)) ? $this->siteID : $sitekey;
        if (empty($sitekey)) {
            return;
        }

        $path = null;
        $unit = $this->db->get('path,kind', 'template', 'id = ?', [$id]);
        if (!empty($unit)) {
            $kind = strval($unit['kind']);
            $dir = $this->templateDirBySite($sitekey, $kind);
            $extension = ($kind === '6') ? 'css' : 'tpl';
            $path = "{$dir}/{$unit['path']}.{$extension}";

            if ($raw) {
                return $path;
            }

            $i = 0;
            while (!file_exists($path)) {
                if ($i === 0 && !empty($dir = $this->app->cnf('application:cms_global_templates'))) {
                    $path = $dir . DIRECTORY_SEPARATOR . basename($path);
                } else {
                    $path = getcwd() . DIRECTORY_SEPARATOR
                        . Common::DEFAULT_TEMPLATES_DIR_NAME . DIRECTORY_SEPARATOR
                        . basename($path);
                }
                if (++$i > 2) {
                    $path = null;
                    break;
                }
            }
        }

        if (empty($path)) {
            throw new ViewException('Template is not found', 404);
        }

        return $path;
    }

    /**
     * Create Root Category.
     *
     * @param int $sitekey
     *
     * @return bool
     */
    private function createRootCategory($sitekey)
    {
        if (false === $template = $this->createDefaultTemplate($sitekey)) {
            return false;
        }

        $previous_rgt = (int)$this->db->max('rgt', 'category');
        $save = [
            'sitekey' => $sitekey,
            'userkey' => User::getUserID($this->db),
            'template' => $template,
            'path' => '/',
            'title' => 'Site Root',
            'lft' => $previous_rgt + 1,
            'rgt' => $previous_rgt + 2,
        ];
        $raw = [
            'create_date' => 'CURRENT_TIMESTAMP',
            'modify_date' => 'CURRENT_TIMESTAMP',
        ];

        if (false === $this->db->insert('category', $save, $raw)) {
            return false;
        }

        $plugin_results = $this->app->execPlugin('createChildCategoriesForCmsSite', $this->site_data);
        foreach ((array)$plugin_results as $plugin_result) {
            if ($plugin_result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create Index Template.
     *
     * @param int $sitekey
     *
     * @return mixed
     */
    protected function createDefaultTemplate($sitekey)
    {
        $templates_dir = View::TEMPLATE_DIR_NAME;
        $default_templates_xml = $this->app->cnf('global:data_dir')."/$templates_dir/".parent::DEFAULT_TEMPLATES_XML_PATH;
        if (!file_exists($default_templates_xml)) {
            $default_templates_xml = realpath(__DIR__."/$templates_dir/".parent::DEFAULT_TEMPLATES_XML_PATH);
        }

        try {
            $xml_source = file_get_contents($default_templates_xml);
            if (false === $xml = simplexml_load_string($xml_source)) {
                throw new ErrorException('Failed to parse XML');
            }
        } catch (ErrorException $e) {
            $message = $e->getMessage();
            if (stripos($message, 'No such file or directory') !== false) {
                return true;
            }
            echo $message;

            return false;
        }

        $global_templates = rtrim($this->app->cnf('application:cms_global_templates') ?? '', '/');
        if (!empty($global_templates) && !is_dir($global_templates)) {
            if (false === @mkdir($global_templates, 0777, true)) {
                $global_templates = null;
            }
        }

        $build_templates = [];
        $root_template = null;
        foreach ($xml->template as $unit) {
            $save = [
                'sitekey' => $sitekey,
                'title' => $unit->title,
                'sourcecode' => $unit->sourcecode,
                'revision' => 0,
                'active' => $unit->active,
                'kind' => $unit->kind,
                'path' => $unit->path,
            ];
            $raw = [
                'create_date' => 'CURRENT_TIMESTAMP',
                'modify_date' => 'CURRENT_TIMESTAMP',
            ];
            if (false === $this->db->insert('template', $save, $raw)) {
                return false;
            }
            $id = $this->db->lastInsertId(null, 'id');
            if (false === $this->db->update('template', ['identifier' => $id], 'id = ?', [$id])) {
                return false;
            }

            // Set build template
            if ((string)$unit->active === '1') {
                $save['id'] = $id;
                $build_templates[] = $save;
                //if (false === $this->buildTemplate($save, true, $sitekey)) {
                //    return false;
                //}
            }

            if ((string)$unit->attributes()->root === '1') {
                $root_template = $id;
            }

            if (!empty($global_templates)) {
                $path = $this->templatePath($id, $sitekey);
                if (!preg_match('/\.css$/', $path)) {
                    $file = "$global_templates/" . basename($path);
                    if (!is_file($file)) {
                        file_put_contents($file, $unit->sourcecode);
                    }
                }
            }
        }

        // Build template files
        foreach ($build_templates as $template) {
            if (false === $this->buildTemplate($template, true, $sitekey)) {
                return false;
            }
        }

        return $root_template;
    }

    protected function updateDefaultTemplate($templates_xml)
    {
        try {
            $xml_source = file_get_contents($templates_xml);
            if (false === $xml = simplexml_load_string($xml_source)) {
                throw new ErrorException('Failed to parse XML');
            }
        } catch (ErrorException $e) {
            trigger_error($e->getMessage());

            return false;
        }

        $sitekey = $this->session->param('current_site');
        $root_template = null;
        foreach ($xml->template as $unit) {
            $save = [
                'sitekey' => $sitekey,
                'title' => $unit->title,
                'sourcecode' => $unit->sourcecode,
                'revision' => 0,
                'active' => $unit->active,
                'kind' => $unit->kind,
                'path' => $unit->path,
            ];
            $raw = [
                'create_date' => 'CURRENT_TIMESTAMP',
                'modify_date' => 'CURRENT_TIMESTAMP',
            ];
            if (false !== $this->db->insert('template', $save, $raw)) {
                $id = $this->db->lastInsertId(null, 'id');
                $save['id'] = $id;
                if (false === $this->db->update('template', ['identifier' => $id], 'id = ?', [$id])) {
                    return false;
                }
            } else {
                $raw = ['modify_date' => 'CURRENT_TIMESTAMP'];
                $where = 'sitekey = ? AND path = ? AND revision = ? AND id = identifier';
                $replaces = [$sitekey, $unit->path, 0];
                $update = [
                    'title' => $save['title'],
                    'sourcecode' => $save['sourcecode'],
                    'active' => $save['active'],
                    'kind' => $save['kind'],
                ];
                if (false === $this->db->update('template', $update, $where, $replaces, $raw)) {
                    trigger_error($this->db->error());

                    return false;
                }
                $save['id'] = $this->db->get('id', 'template', $where, $replaces);
            }

            // build template file
            if ((string)$unit->active === '1') {
                if (false === $this->buildTemplate($save, true, $sitekey)) {
                    return false;
                }
            }

            if ((string)$unit->attributes()->root === '1') {
                $root_template = $save['id'];
            }
        }

        return $root_template;
    }

    /**
     * pickup site owners.
     *
     * @param \Gsnowhawk\Db $db
     * @param int     $uid
     *
     * @return mixed
     */
    public static function siteOwners(Db $db, $uid)
    {
        $root = $db->nsmGetRoot('children.lft', $db->TABLE('user'));
        $parent_lft = $root[0]['lft'];
        $owners = [$uid];
        if (false !== $ret = $db->nsmGetParents($uid, 'parent.id', $db->TABLE('user'), null, $parent_lft)) {
            foreach ((array) $ret as $unit) {
                $owners[] = $unit['id'];
            }
        }

        return $owners;
    }

    /**
     * Site owner.
     *
     * @param int $sitekey
     *
     * @return bool
     */
    public function isOwner($sitekey)
    {
        $owner = $this->siteProperty('userkey');
        if (empty($owner)) {
            $owner = $this->db->get('userkey', 'site', 'id=?', [$sitekey]);
        }

        return $owner === $this->uid;
    }

    /**
     * Site owner detail
     *
     * @param int sitekey
     *
     * @return mixed
     */
    public function ownerInfo($sitekey)
    {
        $owner = $this->db->get('userkey', 'site', 'id = ?', [$sitekey]);
        $owner_info = $this->db->get(
            'id,email,company,division,fullname,fullname_rubi,url,zip,state,city,town,address1,address2,tel,fax',
            'user',
            'id = ?',
            [$owner]
        );

        // Aliases

        return $owner_info;
    }

    /**
     * filtered by permission.
     *
     * @param \Gsnowhawk\Db $db
     * @param int     $uid
     *
     * @return mixed
     */
    public static function filteredSite(Db $db, $userkey)
    {
        $filtered = [];
        if (false !== ($ret = $db->select(
            'filter1',
            'permission',
            'WHERE userkey = ? AND application = ? AND class = ? AND type = ? AND priv = ?',
            [$userkey, 'cms', 'site', 'read', 1]
        ))) {
            foreach ((array) $ret as $unit) {
                $filtered[] = $unit['filter1'];
            }
        }
        if (false !== ($owned = $db->select('id', 'site', 'WHERE userkey = ?', [$userkey]))) {
            foreach ($owned as $unit) {
                $filtered[] = $unit['id'];
            }
        }

        return array_values(array_unique($filtered));
    }

    /**
     * Remove the data.
     */
    protected function remove()
    {
        $auth = new Security('user', $this->db, $this->app->cnf('global:password_encrypt_algorithm'));
        if (false === $auth->authentication($this->userinfo['uname'], $this->request->param('passphrase'))) {
            $this->app->err['vl_authorize'] = 1;

            return false;
        }

        $this->db->begin();
        $sitekey = $this->request->param('id');
        $this->checkPermission('cms.site.delete', $sitekey);

        $site_data = $this->loadSiteData($sitekey);

        try {
            $results = $this->app->execPlugin('beforeRemoveCmsSite', $sitekey);
            foreach ((array)$results as $result) {
                if ($result === false) {
                    throw new ErrorException('Some error in exec plugins');
                }
            }
        } catch (ErrorException $e) {
            return false;
        }

        if (false === $this->db->delete('site', 'id = ?', [$sitekey])) {
            return false;
        }

        $remove_dirs = [];
        if ($site_data['path'] === '/') {
            $remove_dirs[] = implode(
                DIRECTORY_SEPARATOR,
                array_filter([
                    $site_data['openpath'],
                    $site_data['uploaddir']
                ])
            );
            $remove_dirs[] = $this->templateDirBySite($sitekey, '6');
        } else {
            $remove_dirs[] = $site_data['openpath'];
        }
        foreach ($remove_dirs as $remove_dir) {
            try {
                File::rmdirs($remove_dir, true);
            } catch (ErrorException $e) {
                if (count(glob("$remove_dir/*")) > 0) {
                    return false;
                }
            }
        }

        try {
            File::rmdirs($this->app->cnf('global:data_dir').'/mirror/'.$sitekey, true);
            $results = $this->app->execPlugin('afterRemoveCmsSite', $sitekey);
            foreach ((array)$results as $result) {
                if ($result === false) {
                    throw new ErrorException('Some error in exec plugins');
                }
            }
        } catch (ErrorException $e) {
            return false;
        }

        if ($this->session->param('current_site') === $sitekey) {
            $this->session->clear('current_site');
        }

        if (false === $this->db->commit()) {
            return false;
        }
        $this->cleanupMirror();

        return true;
    }

    public function commonTemplate($title)
    {
        $statement = 'sitekey = ? AND kind = ? AND title = ?';
        $statement .= ($this->session->param('ispreview') === 1) ? ' AND revision = 0' : ' AND active = 1';
        $source = $this->db->get('sourcecode', 'template', $statement, [$this->siteID, 5, $title]);
        if (empty($source)) {
            $source = '<!-- '.htmlspecialchars($title).' is not found -->';
        }

        return $source;
    }

    public function siteProperty($key = null)
    {
        if (is_null($key)) {
            return $this->site_data;
        }

        return (isset($this->site_data[$key])) ? $this->site_data[$key] : null;
    }

    /**
     * Checking permission.
     *
     * @see Gsnowhawk\User::checkPermission()
     *
     * @param string $type
     * @param int    $filter1
     * @param int    $filter2
     */
    protected function checkPermission($type, $filter1 = 0, $filter2 = 0)
    {
        $options = array_values(parent::parsePermissionKey($type));
        if ($this->session->param('uname') === 'guest'
            && in_array(array_pop($options), ['read','exec'])
        ) {
            return true;
        }

        if ($type === 'cms.site.create'
            && !$this->isRoot()
            && $this->app->cnf('application:cms_site_creator') === 'rootonly'
        ) {
            return false;
        }

        try {
            parent::checkPermission($type, $filter1, $filter2);

            return true;
        } catch (PermitException $e) {
            try {
                parent::checkPermission($type, $filter1);

                return true;
            } catch (PermitException $e) {
                trigger_error("$type : $filter1 : $filter2");
            }
            $trace = debug_backtrace();
            $file = $trace[0]['file'] ?? null;
            $line = $trace[0]['line'] ?? 0;
            throw new PermitException(Lang::translate('PERMISSION_DENIED'), 403, E_ERROR, $file, $line);
        }
    }

    protected function fileUploadDir($entrykey = null, $sectionkey = null)
    {
        $path = implode(
            DIRECTORY_SEPARATOR,
            array_filter([
                rtrim($this->site_data['openpath'], '/'),
                rtrim($this->site_data['uploaddir'], '/'),
                $entrykey,
                $sectionkey
            ])
        );

        if (!file_exists($path)) {
            try {
                mkdir($path, 0777, true);
            } catch (ErrorException $e) {
                // Nop
            }
        }

        return $path;
    }

    public function hideSiteRoot(): bool
    {
        if ($this->isAdmin()) {
            return false;
        }
        if (($this->site_data['noroot'] ?? '0') === '1') {
            return true;
        }

        return $this->hasPermission('cms.site.noroot');
    }

    /**
     * Build feeds.
     *
     * @return bool
     */
    public function buildFeeds()
    {
        if ($this->session->param('ispreview') === 1) {
            return true;
        }

        // Escape current type of building
        $build_type = $this->session->param('build_type');
        $this->session->param('build_type', 'feed');

        $templates = $this->db->select('*', 'template', 'WHERE sitekey = ? AND kind = ? AND active = ?', [$this->siteID, 4, 1]);

        foreach ($templates as $template) {
            $apps = new Category\Response($this->app);
            $view = $this->app->createView();
            $view->bind('apps', $apps);
            $view->bind('build_type', $this->session->param('build_type'));

            $view->bind('current', $template);

            $template['url'] = '/' . $template['path'];

            $view->bind('site', $this->site_data);

            $html_class = str_replace('_', '-', $template['path']);
            $html_id = $this->pathToID($template['url']);
            if (empty($html_id)) {
                $html_id = $html_class;
            }
            $this->setHtmlId($html_id, $view);

            $sub_class = $this->pathToID($template['path']);
            $html_class .= " $sub_class";
            $this->appendHtmlClass([$html_class, $sub_class], $view);

            $path = $this->templatePath($template['identifier']);
            $this->setPathToView(dirname($path), $view);
            $source = $view->render(basename($path), 1);

            unset($view);

            $output = $this->site_data['openpath'].'/'.$template['path'];

            try {
                file_put_contents($output, $source);
            } catch (ErrorException $e) {
                trigger_error($e->getMessage());

                return false;
            }
        }

        // Rewind current type of building
        $this->session->param('build_type', $build_type);

        return true;
    }

    private function cleanupMirror(): void
    {
        $fetch = $this->db->select('id', 'site');
        $sites = array_column($fetch, 'id');
        $mirror = $this->app->cnf('global:data_dir') . '/mirror';
        $dirs = scandir($mirror);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            if (!in_array($dir, $sites)) {
                $path = "{$mirror}/{$dir}";
                if (is_dir($path)) {
                    File::rmdir($path, true);
                }
            }
        }
    }

    public function rootOrigin($columns = 'children.id')
    {
        if (false === ($root = $this->db->nsmGetRoot(
            $columns,
            '(SELECT * FROM table::category WHERE sitekey = :site_id)',
            null,
            ['site_id' => $this->siteID]
        ))) {
            return;
        }

        return $root[0] ?? null;
    }

    /**
     * Site Root category.
     *
     * @param string $columns
     *
     * @return int
     */
    public function rootCategory($columns = 'children.id')
    {
        $siteID = $this->siteID;
        if (empty($siteID)) {
            return;
        }

        $root = $this->rootOrigin($columns);
        $categoryID = $root['id'] ?? null;

        if ($columns === 'children.id') {
            return (int)$categoryID;
        }

        return $root;
    }

    /**
     * Set Current Category.
     *
     * @param int $id
     */
    protected function setCategory($id = null)
    {
        $previous = $this->session->param('current_category');
        if (is_null($id)) {
            $id = $this->site_data['rootcategory'];
        }

        if (false !== $this->hideSiteRoot() && $id === $this->site_data['rootcategory']) {
            if (empty($this->site_data['first_category'])) {
                if (false === ($chroot = $this->db->nsmGetChildren(
                    'children.id',
                    '(SELECT * FROM table::category WHERE id = :category_id)',
                    '(SELECT * FROM table::category WHERE sitekey = :site_id)',
                    "(SELECT c.* FROM (SELECT * FROM table::category WHERE sitekey = :site_id AND reserved = '0' AND trash <> '1') c LEFT JOIN (SELECT * FROM table::permission WHERE userkey = :user_id AND application = 'cms' AND class = 'category' AND type = 'read') p ON c.id = p.filter2 WHERE p.priv != '0' OR p.priv IS NULL)",
                    'AND children.id IS NOT NULL ORDER BY children.`priority`',
                    ['site_id' => $this->siteID, 'category_id' => $id, 'user_id' => $this->uid]
                ))) {
                    throw new PermitException(Lang::translate('PERMISSION_DENIED'), 403, E_ERROR, __FILE__, __LINE__);
                }
                $this->site_data['first_category'] = $chroot[0]['id'];
            }
            $id = $this->site_data['first_category'];
        }

        if (empty($id)) {
            return false;
        }

        $this->checkPermission('cms.category.read', $this->siteID, $id);
        $this->categoryID = intval($id);
        $this->session->param('current_category', $this->categoryID);
        if ($previous !== $this->categoryID) {
            self::__construct();
        }

        return $this->categoryID;
    }

    /**
     * Parent of the category
     *
     * @param int    $id
     * @param string $col
     *
     * @return array|false
     */
    public function parentCategory($id, $col = '*')
    {
        $tmp = Text::explode(',', $col);
        $columns = [];
        foreach ($tmp as $column) {
            $columns[] = 'parent.'.$column;
        }
        $columns = implode(',', $columns);

        return $this->db->nsmGetParent(
            $columns,
            '(SELECT * FROM table::category WHERE sitekey = :site_id)',
            '(SELECT * FROM table::category WHERE id = :category_id)',
            ['site_id' => $this->siteID, 'category_id' => $id]
        );
    }
}
