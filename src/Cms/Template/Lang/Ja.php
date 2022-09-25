<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Cms\Template\Lang;

/**
 * Japanese Languages for Gsnowhawk.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Ja extends \Gsnowhawk\Common\Lang
{
    protected $KIND_OF_TEMPLATE = [
        '0' => 'プレビュー専用',
        '1' => 'Top Page',
        '2' => 'エントリ',
        '3' => 'アーカイブ',
        '4' => 'Feed',
        '5' => '共通',
        '6' => 'スタイルシート',
    ];

    const SUCCESS_IMPORT = 'テンプレートのインポートが完了しました';
}
