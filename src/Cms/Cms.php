<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk;

use Gsnowhawk\Cms\Site;
use Gsnowhawk\Cms\Site\Receive as SiteReceive;
use Gsnowhawk\Common\Http;
use Gsnowhawk\Common\Lang;
use Gsnowhawk\Common\Text;
use Gsnowhawk\PackageInterface;
use Gsnowhawk\View;
use Gsnowhawk\User;

/**
 * Site management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Cms extends User implements PackageInterface
{
    /*
     * Using common accessor methods
     */
    use Accessor;

    /**
     * Application default mode.
     */
    public const DEFAULT_MODE = 'cms.site.response';
    public const USER_EDIT_EXTENDS = '\\Gsnowhawk\\Cms\\Category';
    public const THUMBNAIL_EXTENSION = '.jpg';
    public const DEFAULT_TEMPLATES_XML_PATH = 'cms/default_templates.xml';

    protected $command_convert = null;

    /**
     * Object constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array(parent::class.'::__construct', $params);

        if (is_a($this->view, 'Gsnowhawk\\View')) {
            $this->view->addPath(self::templateDir());
        }

        if (class_exists('Imagick')) {
            $this->command_convert = 'imagick';
        } else {
            $convert = $this->app->cnf('external_command:convert');
            if (!empty($convert) && is_executable($convert)) {
                $disable_functions = Text::explode(',', ini_get('disable_functions'));
                if (!in_array('exec', $disable_functions)) {
                    exec('convert --version', $output, $status);
                    if ($status === 0) {
                        $this->command_convert = $convert;
                    }
                }
            }
        }
    }

    /**
     * Default Mode
     *
     * @final
     * @param Gsnowhawk\App $app
     *
     * @return string
     */
    final public static function getDefaultMode($app)
    {
        $mode = $app->cnf('application:default_mode');

        if ($mode === 'cms.site.response'
            && $current_application = $app->session->param('application_name')
        ) {
            $inst = new SiteReceive($app);
            if (($inst->userinfo['admin'] ?? null) !== '1') {
                $sites = Site::filteredSite($inst->db, $inst->userinfo['id'] ?? null);
                if (count($sites) === 1 && empty($app->session->param('current_site'))) {
                    $inst->request->post('choice', $sites[0]);
                    $inst->change();
                }
            }
            if (!empty($app->session->param('current_site'))) {
                $mode = 'cms.entry.response';
            }
        }

        return ($mode === 'cms.dynamic') ? $mode : self::DEFAULT_MODE;
    }

    /**
     * This package name
     *
     * @final
     *
     * @return string
     */
    final public static function packageName()
    {
        return strtolower(stripslashes(str_replace(__NAMESPACE__, '', __CLASS__)));
    }

    /**
     * Application name
     *
     * @final
     *
     * @return string
     */
    final public static function applicationName()
    {
        return Lang::translate('APPLICATION_NAME');
    }

    /**
     * Application label
     *
     * @final
     *
     * @return string
     */
    final public static function applicationLabel()
    {
        return Lang::translate('APPLICATION_LABEL');
    }

    /**
     * This package version
     *
     * @final
     *
     * @return string
     */
    final public static function version()
    {
        return System::getVersion(__CLASS__);
    }

    /**
     * This package version
     *
     * @final
     *
     * @return string|null
     */
    final public static function templateDir()
    {
        return __DIR__.'/'.View::TEMPLATE_DIR_NAME;
    }

    /**
     * Unload action
     *
     * Clear session data for package,
     * when unload application
     */
    public static function unload()
    {
        if (isset($_SESSION['current_site'])) {
            unset($_SESSION['current_site']);
        }
        if (isset($_SESSION['current_category'])) {
            unset($_SESSION['current_category']);
        }
    }

    /**
     * Clear application level permissions.
     *
     * @see Gsnowhawk\User::updatePermission()
     *
     * @param Gsnowhawk\Db $db
     * @param int    $userkey
     *
     * @return bool
     */
    public static function clearApplicationPermission(Db $db, $userkey)
    {
        $filter1 = [0];
        $filter2 = [0];
        if (isset($_SESSION['current_site'])) {
            $filter1[] = $_SESSION['current_site'];
        }
        if (isset($_SESSION['current_category'])) {
            $filter2[] = $_SESSION['current_category'];
        }
        $statement = 'userkey = ? AND application = ?'
            .' AND filter1 IN ('.implode(',', array_fill(0, count($filter1), '?')).')'
            .' AND filter2 IN ('.implode(',', array_fill(0, count($filter2), '?')).')';
        $options = array_merge([$userkey, self::packageName()], $filter1, $filter2);

        return $db->delete('permission', $statement, $options);
    }

    /**
     * Reference permission.
     *
     * @todo Better handling for inheritance
     *
     * @see Gsnowhawk\User::hasPermission()
     *
     * @param string $key
     * @param int    $filter1
     * @param int    $filter2
     *
     * @return bool
     */
    public function hasPermission($key, $filter1 = 0, $filter2 = 0)
    {
        if ($key === 'root') {
            return parent::hasPermission($key, $filter1, $filter2);
        }

        // Administrators have full control
        if ($this->isAdmin()) {
            if ($key === 'cms.site.create'
                && !$this->isRoot()
                && $this->app->cnf('application:cms_site_creator') === 'rootonly'
            ) {
                return false;
            }

            return true;
        }

        $exec = ($key === 'cms.exec') ? 1 : 0;
        $type = preg_match("/^cms\.(category|entry)\..+$/", $key, $match);
        $kind = $match[1] ?? null;

        if ($exec !== 1 && empty($filter1)) {
            $filter1 = $this->siteID;
        }

        $revoke_owner = [
            'system',
            'cms.site.create',
            'cms.site.remove',
            'cms.site.noroot',
        ];
        if (!in_array($key, $revoke_owner) && $this->isOwner($filter1)) {
            $priv = parent::getPrivilege($key, $filter1, $filter2);

            return ($priv !== '0');
        }

        if ($type === 1 && empty($filter2)) {
            $filter2 = $this->categoryID;
        }

        $permission = parent::hasPermission($key, $filter1, $filter2);
        if ($type === 1
            && false === $permission
            && false === $this->getPrivilege($key, $filter1, $filter2)
        ) {
            $_filter2 = $filter2;
            do {
                $_filter2 = $this->parentCategory($_filter2, $col = 'id');
                $raw = $this->getPrivilege($key, $filter1, $_filter2);
                if ($raw === '0') {
                    break;
                } elseif ($raw === '1') {
                    $permission = true;
                    if ($kind === 'category') {
                        $disinherit = $this->getPrivilege('cms.category.disinherit', $filter1, $_filter2);
                        if ($disinherit === '1') {
                            $permission = false;
                        }
                    }
                    break;
                }
            } while (!empty($_filter2));
        }

        // Not inheritance of parent permssion
        if ($permission && preg_match("/^cms\.category\.(create|delete)$/", $key)) {
            $parent = $this->parentCategory($filter2, 'id');
            $raw = $this->getPrivilege('cms.category.inherit', $filter1, $parent);
            if ($raw === '1') {
                $permission = false;
            }
        }

        return (bool) $permission;
    }

    /**
     * Release the template.
     *
     * @param array $post
     * @param bool  $copy
     * @param int   $sitekey
     *
     * @return bool
     */
    protected function buildTemplate($post, $copy, $sitekey = null)
    {
        $return_value = true;

        $id = $post['id'];
        $table = 'template';

        $latest_version = intval($this->db->max('revision', 'template', 'identifier = ?', [$id]));
        $new_version = $latest_version + 1;

        if ($copy || $latest_version === 0) {
            $this->db->update($table, ['active' => '0'], 'identifier = ?', [$id]);

            $fields = $this->db->getFields($table);
            $cols = [];
            foreach ($fields as $field) {
                switch ($field) {
                    case 'id':
                        $cols[] = 'NULL AS id';
                        break;
                    case 'revision':
                        $cols[] = $this->db->quote($new_version).' AS revision';
                        break;
                    case 'active':
                        $cols[] = "'1' AS active";
                        break;
                    default:
                        $cols[] = $field;
                        break;
                }
            }
            if (false === $this->db->copyRecord($cols, $table, '', 'id = ?', [$id])) {
                return false;
            }

            // Remove older version
            $save_count = $this->site_data['maxrevision'];
            $limit = $new_version - (int)$save_count;
            if (false === $this->db->delete($table, "identifier = ? AND revision > '0' AND revision < ?", [$id, $limit])) {
                trigger_error($this->db->error());

                return false;
            }
        } else {
            $this->db->update($table, ['active' => '1'], 'identifier = ? ORDER BY revision DESC LIMIT 1', [$id]);
        }

        $path = $this->templatePath($id, $sitekey);

        $this->view->clearCache($path);

        $sourcecode = (strval($post['kind']) === '6')
            ? $this->view->render($post['sourcecode'], true, true)
            : $post['sourcecode'];

        return file_put_contents($path, $sourcecode);
    }

    public function init()
    {
        parent::init();
        $config = $this->view->param('config');
        $config['application'] = ['guest' => 'allow'];
        $this->view->bind('config', $config);
    }

    /**
     * Find current site number from static URL
     *
     * @return int|false
     */
    public function currentSiteFromURI($uri)
    {
        $origin = $uri;
        while ($uri !== '.') {
            if (false !== $id = $this->db->get('id', 'site', 'url = ? OR url = ?', [$uri, "$uri/"])) {
                if ($origin === $uri && !preg_match('/\/$/', $uri)) {
                    Http::redirect("$uri/");
                }
                break;
            }
            $uri = dirname($uri);
        }

        return $id;
    }

    public static function extendedTemplatePath($uri, Common $app)
    {
        $origin = $uri;

        while ($uri !== '.' && !is_null($app->db)) {
            $sitekey = $app->db->get('id', 'site', 'url = ? OR url = ?', [$uri, "$uri/"]);
            if (!empty($sitekey)) {
                break;
            }
            $uri = dirname($uri);
        }

        if (empty($sitekey)) {
            return;
        }

        $app->session->param('current_site', $sitekey);

        return $app->app->cnf('global:data_dir')."/mirror/$sitekey/templates";
    }

    public function availableConvert()
    {
        return !empty($this->command_convert);
    }

    protected function pathToID($path)
    {
        return trim(str_replace(['/','.'], ['-','_'], preg_replace('/\.html?$/', '', $path)), '-_');
    }

    protected function nohup()
    {
        $command_path = $this->app->cnf('cms:path_nohup');

        return (empty($command_path)) ? 'nohup' : $command_path;
    }

    protected function phpCLI()
    {
        $command_path = $this->app->cnf('cms:php_cli');

        return (empty($command_path)) ? 'php' : $command_path;
    }

    protected function cleanupRevisions($type, $sitekey = null, $entrykey = null): bool
    {
        if (empty($sitekey)) {
            $sitekey = $this->siteID;
        }

        $sql = "DELETE src FROM `table::{$type}` src
                  LEFT JOIN (SELECT id FROM `table::{$type}` WHERE revision = ?) org
                    ON src.identifier = org.id
                 WHERE src.sitekey = ? AND org.id IS NULL";
        $options = ['0', $sitekey];

        if (!empty($entrykey)) {
            $sql .= ' AND src.entrykey = ?';
            $options[] = $entrykey;
        }

        if (false === $this->db->exec($sql, $options)) {
            trigger_error($this->db->error());

            return false;
        }

        return true;
    }

    protected function cleanupCustomFields($type, $sitekey = null): bool
    {
        if (empty($sitekey)) {
            $sitekey = $this->siteID;
        }

        $sql = "DELETE dest FROM table::custom dest
                  LEFT JOIN `table::{$type}` src ON dest.relkey = src.id
                 WHERE dest.sitekey = ? AND dest.kind = ? AND src.id IS NULL";

        if (false === $this->db->exec($sql, [$sitekey, $type])) {
            trigger_error($this->db->error());

            return false;
        }

        return true;
    }

    protected function removeEmptyDir($directory)
    {
        if (is_dir($directory)) {
            $list = array_diff(scandir($directory), ['.','..']);
            if (count($list) === 0) {
                return @rmdir($directory);
            }
        }

        return false;
    }

    protected function setPathToView($dir, $view = null): void
    {
        if (!is_array($dir)) {
            $dir = [$dir];
        }

        // Add user preference
        if (!empty($global = $this->cnf('application:cms_global_templates'))) {
            $dir[] = $global;
        }

        // Add current directory
        $dir[] = getcwd() . '/' . Common::DEFAULT_TEMPLATES_DIR_NAME;

        if (is_null($view)) {
            $view = $this->view;
        }
        $view->setPath($dir);
    }
}
