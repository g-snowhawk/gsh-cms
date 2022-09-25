<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Cms\Entry;

use ErrorException;
use Gsnowhawk\Cms\Category;
use Gsnowhawk\Common\Environment;
use Gsnowhawk\Common\Http;
use Gsnowhawk\Common\Lang;
use Gsnowhawk\Common\Text;

/**
 * Entry management data receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receive extends Response
{
    /*
     * Using common accessor methods
     */
    use \Gsnowhawk\Accessor;

    /**
     * Save the data.
     */
    public function save()
    {
        $redirect_type = 'redirect';
        $redirect_mode = (!empty($this->request->param('redirect_mode')))
            ? $this->request->param('redirect_mode')
            : 'cms.entry.response';

        if ($referer = $this->request->param('script_referer')) {
            $redirect_mode = $referer;
            $redirect_type = 'referer';
        }

        $message = 'SUCCESS_SAVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], [$redirect_mode, $redirect_type]];

        if (!parent::save()) {
            $message = 'FAILED_SAVE';
            $status = 1;
            $options = [
                [[$this->view, 'bind'], ['err', $this->app->err]],
            ];
            $response = [[$this, 'edit'], null];
        }

        $this->postReceived(Lang::translate($message), $status, $response, $options);
    }

    public function revokeDraft($type = 'entry')
    {
        $redirect_type = 'redirect';
        $redirect_mode = (!empty($this->request->param('redirect_mode')))
            ? $this->request->param('redirect_mode')
            : 'cms.entry.response';

        if ($referer = $this->request->param('script_referer')) {
            $redirect_mode = $referer;
            $redirect_type = 'referer';
        }

        $message = 'SUCCESS_SAVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], [$redirect_mode, $redirect_type]];

        if (!parent::revokeDraft($type)) {
            $message = 'FAILED_SAVE';
            $status = 1;
            $options = [
                [[$this->view, 'bind'], ['err', $this->app->err]],
            ];
            $response = [[$this, 'edit'], null];
        }

        $this->postReceived(Lang::translate($message), $status, $response, $options);
    }

    /**
     * Remove data.
     */
    public function remove()
    {
        $redirect_mode = (!empty($this->request->param('redirect_mode')))
            ? $this->request->param('redirect_mode')
            : 'cms.entry.response';

        $message = 'SUCCESS_REMOVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], $redirect_mode];

        list($type, $id) = explode(':', $this->request->post('delete'));
        $result = ($type === 'category') ? Category::remove() : parent::remove();

        if (!$result) {
            $message = 'FAILED_REMOVE';
            $status = 1;
        }

        $this->postReceived(Lang::translate($message), $status, $response, $options);
    }

    public function createRelay()
    {
        $entrykey = $this->request->post('eid');
        $relkey = $this->request->post('relay');
        $result = parent::createRelation($entrykey, $relkey);

        if (strtolower(Environment::server('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest') {
            Http::nocache();
            header('Content-type: application/json; charset=utf-8');
            $json = ['callback' => $this->request->param('callback'), 'data' => $result];
            echo json_encode($json);
            exit;
        }

        if ($result) {
            $this->session->param('messages', Lang::translate('SUCCESS_SAVED'));
        }
        Http::redirect(
            $this->app->systemURI().'?mode=cms.entry.response:edit'
        );
    }

    public function removeRelay()
    {
        $entrykey = $this->request->post('eid');
        $relkey = $this->request->post('removeRelay');
        $result = $this->db->delete('relation', 'entrykey=? AND relkey=?', [$entrykey, $relkey]);

        if (strtolower(Environment::server('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest') {
            Http::nocache();
            header('Content-type: application/json; charset=utf-8');
            $json = ['callback' => $this->request->param('callback'), 'data' => $result];
            echo json_encode($json);
            exit;
        }

        if ($result) {
            $this->session->param('messages', Lang::translate('SUCCESS_REMOVED'));
        }
        Http::redirect(
            $this->app->systemURI().'?mode=cms.entry.response:edit'
        );
    }

    /**
     * Save the category data.
     */
    public function saveCategory()
    {
        $message = 'SUCCESS_SAVED';
        $status = 0;
        $callback = '\Gsnowhawk\Common\Http::redirect';
        $args = [$this->app->systemURI().'?mode=cms.entry.response'];
        if (!Category::save()) {
            $message = 'FAILED_SAVE';
            $status = 1;
            $callback = [
                [[$this, 'init'], []],
                [[$this, 'defaultView'], []]
            ];
            $args = [];
        }
        $this->postReceived(Lang::translate($message), $status, $callback, $args);
    }

    public function reassemble()
    {
        $key = $this->request->param('id');
        $type = $this->request->param('type');
        $json = ['status' => 1];
        if ($type === 'entry') {
            if (false !== $this->createEntryFile($key)) {
                $json = ['status' => 0];
            }
        } elseif ($type === 'category') {
            if (false !== Category::reassembly($key, true)) {
                $json = ['status' => 0];
            }
        }

        Http::nocache();
        header('Content-type: application/json; charset=utf-8');
        echo json_encode($json);
        exit;
    }

    /**
     * Reassemble the site.
     */
    public function reassembly()
    {
        $message = 'SUCCESS_REASSEMBLY';
        $status = 0;
        $callback = [[$this, 'redirect'], 'cms.entry.response:reassembly'];
        $args = [];

        if ($this->isAjax) {
            $disable_functions = Text::explode(',', ini_get('disable_functions'));
            if (!in_array('exec', $disable_functions)) {
                $query = ['polling_id' => $this->request->param('polling_id')];
                $command = $this->nohup() . ' ' . $this->phpCLI()
                    . ' -d memory_limit=-1'
                    . ' -d include_path="'.ini_get('include_path') . '"'
                    . ' ' . Environment::server('script_filename')
                    . ' --phpsessid=' . escapeshellarg(session_id())
                    . ' --mode=' . escapeshellarg('cms.entry.receive:ajaxReassembly')
                    . ' --params=' . escapeshellarg(http_build_query($query))
                    . ' > /dev/null &';
                exec($command, $output, $return);
                $status = [
                    'status' => $return,
                ];

                if ($return !== 0) {
                    $message = 'FAILED_REASSEMBLY';
                } else {
                    $status['polling_id'] = $this->request->param('polling_id');
                    $status['polling_address'] = $this->app->systemURI().'?mode=cms.entry.response:pollingReassembly';
                }
            }
        } else {
            if (false === Category::reassembly()) {
                $message = 'FAILED_REASSEMBLY';
                $status = 1;
                $callback = [
                    [[$this, 'reassembly'], []]
                ];
                $args = [];
            } else {
                $this->app->logger->log("Reassembly the site `{$this->siteID}'", 101);
            }
        }

        $this->postReceived(Lang::translate($message), $status, $callback, $args);
    }

    /**
     * Set current category.
     *
     * return void
     */
    public function setCategory($id = null)
    {
        if (false === parent::setCategory($this->request->param('id'))) {
            trigger_error('Irrigal operation', E_USER_WARNING);
        }

        Http::redirect($this->app->systemURI().'?mode=cms.entry.response');
    }

    public function ajaxUploadImage()
    {
        $response = ['status' => 1];
        if (false !== $this->saveFiles('-1', null, $error)) {
            if (false !== $list = $this->imageList('-1')) {
                $response['status'] = 0;
                $response['list'] = $list;
            } else {
                $response['message'] = 'Database Error';
            }
        } else {
            $response['message'] = $error;
        }
        header('Content-type: application/json; charset=utf-8');
        echo json_encode($response);
        exit;
    }

    public function ajaxDeleteImage()
    {
        $id = $this->request->param('id');

        $subdir = 'stock';
        $upload_dir = $this->fileUploadDir($subdir);
        $file = $this->db->get('data', 'custom', 'id = ?', [$id]);
        $path = $upload_dir.'/'.basename($file);

        $response = ['status' => 1];

        clearstatcache(true, $upload_dir);
        if (true !== is_writable($upload_dir)) {
            $response['message'] = 'Permission denied';
        } else {
            $this->db->begin();
            if (false !== $this->db->delete('custom', 'id = ?', [$id])) {
                $response['id'] = $id;
                $response['status'] = 0;
                try {
                    if (file_exists($path)) {
                        unlink($path);
                    }
                    $this->db->commit();
                } catch (ErrorException $e) {
                    $response['message'] = 'System Error';
                    trigger_error($e->getMessage());
                }
            } else {
                $response['message'] = 'Database Error';
                trigger_error($this->db->error());
            }

            if ($response['status'] !== 0) {
                $this->db->rollback();
            }
        }

        header('Content-type: application/json; charset=utf-8');
        echo json_encode($response);
        exit;
    }

    public function ajaxReassembly()
    {
        $this->startPolling();

        $message = 'SUCCESS_REASSEMBLY';
        if (false === Category::reassembly()) {
            $message = 'FAILED_REASSEMBLY';
        }

        $this->endPolling();
        $this->echoPolling(['message' => Lang::translate($message)]);
    }

    public function trash()
    {
        $identifier = $this->request->param('identifier');
        $entrykey = $this->db->get('id', 'entry', 'identifier = ? AND active = ?', [$identifier, 1]);

        $options = [
            'statement' => 'id = ? OR identifier = ?',
            'replaces' => [$identifier, $identifier],
        ];
        $status = (
            (empty($entrykey) || $this->toPrivate($entrykey))
            && false !== $this->db->delete('entry', 'identifier = ? AND identifier != id', [$identifier])
            && parent::intoTrash('entry', $identifier, $options)
        ) ? 0 : 1;

        $message = $status > 0
            ? 'Failed into the trash'
            : 'Success into the trash';
        $response = [
            'status' => $status,
            'message' => $message,
        ];
        header('Content-type: application/json; charset=utf-8');
        echo json_encode($response);
        exit;
    }

    public function rewindTrashItem()
    {
        $identifier = $this->request->param('identifier');

        $options = [
            'statement' => 'id = ? OR identifier = ?',
            'replaces' => [$identifier, $identifier],
        ];
        $status = (
            parent::intoTrash('entry', $identifier, $options, '0')
        ) ? 0 : 1;

        $message = $status > 0
            ? 'Failed put out the trash'
            : 'Success put out the trash';
        $response = [
            'status' => $status,
            'message' => $message,
        ];
        header('Content-type: application/json; charset=utf-8');
        echo json_encode($response);
        exit;
    }

    public function emptyTrash()
    {
        $sql = file_get_contents(__DIR__ . '/trash.sql');

        $items = $this->db->getAll($sql, ['user_id' => $this->uid, 'site_id' => $this->siteID, 'revision' => 0]);
        if (false === $items) {
            parent::trash();
        }

        $this->db->begin();
        $error = false;
        foreach ($items as $item) {
            $id = $item['id'];
            if ($item['kind'] === 'category') {
                $path = $this->getCategoryPath($id, 1);
                if (false === $this->db->delete('category', "id = ? AND trash = '1'", [$id])) {
                    $error = true;
                    break;
                }
                continue;
            }
            if (
                false === $this->db->delete(parent::SECTION_TABLE, 'entrykey = ?', [$id])
                || false === $this->db->delete(parent::ENTRY_TABLE, "identifier = ? AND trash = '1' ORDER BY id DESC", [$id])
            ) {
                $error = true;
                break;
            }
        }

        if ($error) {
            trigger_error($this->db->error());
            $this->db->rollback();
        } else {
            $this->db->commit();
        }

        Http::redirect(
            $this->app->systemURI().'?mode=cms.entry.response:trash'
        );
    }

    public function getReassembleList()
    {
        $json = ['status' => 1];

        $entries = $this->db->select(
            'id',
            'entry',
            'WHERE sitekey = ? AND active = ? AND trash <> ?',
            [$this->siteID, '1', '1']
        );

        if (false !== $entries) {
            $categories = $this->db->select(
                'id',
                'category',
                "WHERE sitekey = ? AND (template <> '' OR template IS NOT NULL) AND trash <> ?",
                [$this->siteID, '1']
            );
            if (false !== $categories) {
                $json = [
                    'status' => 0,
                    'entries' => [],
                ];
                foreach ($entries as $entry) {
                    $json['entries'][] = [
                        'id' => $entry['id'],
                        'type' => 'entry',
                    ];
                }
                foreach ($categories as $category) {
                    $json['entries'][] = [
                        'id' => $category['id'],
                        'type' => 'category',
                    ];
                }
            }
        } else {
            $json['message'] = $this->db->error();
        }

        Http::nocache();
        header('Content-type: application/json; charset=utf-8');
        echo json_encode($json);
        exit;
    }

    public function cleanupUnusedData()
    {
        $json = ['status' => 0];
        $sitekey = $this->siteID;
        $upload_dir = $this->fileUploadDir();

        // Custom fields
        $types = ['section','entry','category'];
        foreach ($types as $type) {
            $sql = "DELETE dest FROM table::custom dest
                      LEFT JOIN `table::{$type}` src ON dest.relkey = src.id
                     WHERE dest.sitekey = ? AND dest.kind = ? AND src.id IS NULL";
            if (false === $this->db->exec($sql, [$sitekey, $type])) {
                trigger_error($this->db->error());
            }
        }

        // Files
        $openpath = $this->site_data['openpath'];
        $up = str_replace($openpath, '', $upload_dir);
        $fetch = $this->db->select(
            'data',
            'custom',
            'WHERE sitekey = ? AND data LIKE ?',
            [$sitekey, "{$up}/%"]
        );
        $paths = [];
        foreach ($fetch as $unit) {
            $paths[($openpath . $unit['data'])] = '';
        }
        unset($fetch);
        $this->cleanFiles($upload_dir, $paths);

        Http::nocache();
        header('Content-type: application/json; charset=utf-8');
        echo json_encode($json);
        exit;
    }

    private function cleanFiles($directory, $paths)
    {
        $list = scandir($directory);
        foreach ($list as $path) {
            if ($path === '.' || $path === '..') {
                continue;
            }

            $path = "$directory/$path";

            // Keep PDF thumbnails
            $origin = null;
            if (preg_match('/^(.+\.pdf)-[0-9]+' . preg_quote(parent::THUMBNAIL_EXTENSION, '/') . '$/', $path, $match)) {
                $origin = $match[1];
            }

            if (is_dir($path)) {
                $this->cleanFiles($path, $paths);
            } elseif (is_file($path) && !isset($paths[$path]) && !is_null($origin) && !isset($paths[$origin])) {
                @unlink($path);
            }
        }
        $list = array_diff(scandir($directory), ['.','..']);
        if (count($list) === 0) {
            rmdir($directory);
        }
    }
}
