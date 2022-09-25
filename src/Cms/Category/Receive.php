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

use Gsnowhawk\Common\File;
use Gsnowhawk\Common\Http;
use Gsnowhawk\Common\Lang;

/**
 * Category data receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receive extends Response
{
    /**
     * Save the data.
     */
    public function save()
    {
        $message = 'SUCCESS_SAVED';
        $status = 0;
        $options = [];
        $response = [[$this, 'redirect'], 'cms.entry.response'];

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

    /**
     * Remove data.
     */
    public function remove()
    {
        if (parent::remove()) {
            $this->session->param('messages', Lang::translate('SUCCESS_REMOVED'));
        }
        Http::redirect(
            $this->app->systemURI().'?mode=cms.entry.response'
        );
    }

    public function trash()
    {
        $identifier = $this->request->param('identifier');
        if (false === $this->isEmpty($identifier)) {
            $status = 1;
            $message = Lang::translate('NOT_EMPTY');
        } else {
            $this->db->begin();
            $path = $this->getCategoryPath($identifier, 1);
            $status = parent::intoTrash('category', $identifier) ? 0 : 1;

            // Remove physical path
            if ($status === 0) {
                if (file_exists($path)) {
                    if (false === File::rmdir($path, true)) {
                        trigger_error("{$path} doesn't remove. Please remove manually if you need.");
                    }
                }
            }

            if ($status > 0) {
                $this->db->rollback();
            } else {
                $this->db->commit();
            }

            $message = $status > 0
                ? 'Failed into the trash'
                : 'Success into the trash';
        }

        $response = [
            'status' => $status,
            'message' => $message,
        ];
        header('Content-type: text/plain; charset=utf-8');
        echo json_encode($response);
        exit;
    }

    public function rewindTrashItem()
    {
        $identifier = $this->request->param('identifier');
        $status = parent::intoTrash('category', $identifier, [], '0') ? 0 : 1;
        $message = $status > 0
            ? 'Failed put out the item'
            : 'Success put out the item';
        $response = [
            'status' => $status,
            'message' => $message,
        ];
        header('Content-type: text/plain; charset=utf-8');
        echo json_encode($response);
        exit;
    }
}
