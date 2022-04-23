<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Cms\Filemanager;

/**
 * Site management request receive class.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Receive extends Response
{
    public function setDirectory()
    {
        parent::setCurrentDirectory($this->request->param('path'), true);
    }

    public function rename()
    {
        parent::rename();
    }

    public function move()
    {
        parent::move();
    }

    public function remove()
    {
        parent::remove();
    }

    public function saveFolder()
    {
        parent::saveFolder();
    }

    public function saveFile()
    {
        parent::saveFile();
    }
}
