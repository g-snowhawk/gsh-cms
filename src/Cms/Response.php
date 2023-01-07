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

use Gsnowhawk\Common\Environment;
use Gsnowhawk\Common\Text;

/**
 * Site management class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Response extends Section
{
    /**
     * Object constructor.
     */
    public function __construct()
    {
        $params = func_get_args();
        call_user_func_array(parent::class.'::__construct', $params);
    }

    public function getCategory($id, $columns = '*', $offset_level = null)
    {
        if (!is_null($offset_level)) {
            $categories = $this->categories($id, '*');

            if ($offset_level < 0) {
                $offset_level = abs($offset_level) - 1;
                $categories = array_reverse($categories);
            }

            $id = $categories[$offset_level]['id'];
        }

        return $this->categoryData($id, $columns);
    }

    public function filterCategories($filter, $column = '*', array $targets = null)
    {
        if (empty($filter)) {
            return;
        }

        if (empty($targets)) {
            $targets = ['id', 'path', 'title', 'tags'];
        }

        $statement = 'sitekey = ?';
        $options = [$this->siteID];

        $filters = Text::explode(',', $filter);

        $statics = [];
        $wildcards = [];
        foreach ($filters as $keyword) {
            if (strpos($keyword, '*') !== false) {
                $wildcargs[] = strtr($keyword, '*', '%');
            } else {
                $statics[] = $keyword;
            }
        }
        unset($filters);

        $conditions = [];
        $replaces = [];
        if (count($statics) > 1) {
            $placeholder = implode(',', array_fill(0, count($statics), '?'));
            foreach ($targets as $target) {
                if ($target === 'tags') {
                    foreach ($statics as $keyword) {
                        $conditions[] = "FIND_IN_SET(?, $target)";
                        $replaces[] = $keyword;
                    }
                    continue;
                }
                $conditions[] = "$target IN ($placeholder)";
                $replaces = array_merge($replaces, $statics);
            }
        } else {
            $statics = array_shift($statics);
            foreach ($targets as $target) {
                $conditions[] = ($target === 'tags') ? "FIND_IN_SET(?, $target)" : "$target = ?";
                $replaces[] = $statics;
            }
        }
        foreach ($wildcards as $wildcard) {
            foreach ($targets as $target) {
                $conditions[] = "$target LIKE ?";
                $replaces[] = $wildcard;
            }
        }
        if (count($conditions) > 0) {
            $statement .= ' AND (' . implode(' OR ', $conditions) . ')';
            $options = array_merge($options, $replaces);
        }

        $fetch = (array)$this->db->select('id', 'category', "WHERE $statement", $options);
        $list = [];
        foreach ($fetch as $unit) {
            $list[] = $this->categoryData($unit['id'], $column);
        }

        return $list;
    }

    public function filterCategory($filter, $column = '*')
    {
        if (empty($filter)) {
            return;
        }

        $statement = 'sitekey = ?';
        $options = [$this->siteID];

        $statement .= ' AND (id = ? OR title = ? OR path = ?)';
        $options = array_merge($options, array_fill(0, 3, $filter));

        $id = $this->db->get('id', 'category', $statement, $options);

        return $this->categoryData($id, $column);
    }

    public function filterEntry($filter, $column = '*')
    {
        if (empty($filter)) {
            return;
        }

        $statement = 'sitekey = ? AND active = ?';
        $options = [$this->siteID, 1];

        $statement .= ' AND (identifier = ? OR title = ? OR path = ? OR filepath = ?)';
        $options = array_merge($options, array_fill(0, 4, $filter));

        $id = $this->db->get('id', 'entry', $statement, $options);

        return $this->entryData($id, $column);
    }

    public function server($key)
    {
        $key = strtoupper($key);
        $allows = [
            'HTTP_HOST','HTTP_REFERER','HTTP_USER_AGENT','HTTPS',
            'QUERY_STRING',
            'REMOTE_ADDR','REMOTE_HOST','REMOTE_PORT','REMOTE_USER',
            'REQUEST_METHOD','REQUEST_TIME','REQUEST_TIME_FLOAT','REQUEST_URI',
            'SERVER_NAME','SERVER_PROTOCOL'
        ];

        return (in_array($key, $allows)) ? Environment::server($key) : null;
    }

    public function callByPlugin(\Gsnowhawk\Plugin $plugin, $function, ...$params)
    {
        call_user_func_array([$this, $function], $params);
    }
}
