<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016-2017 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Cms\Lang;

/**
 * Japanese Languages for Gsnowhawk.
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 */
class Ja extends \Gsnowhawk\Common\Lang
{
    const APP_NAME = 'CMS';

    protected $APPLICATION_NAME = self::APP_NAME;
    protected $APPLICATION_LABEL = self::APP_NAME;
    protected $APP_DETAIL    = self::APP_NAME.'機能を提供します。';
    protected $SUCCESS_SETUP = self::APP_NAME.'機能の追加に成功しました。';
    protected $FAILED_SETUP  = self::APP_NAME.'機能の追加に失敗しました。';

    protected $CONFIRM_REASSEMBLY = 'サイトを再構築します。この処理は時間のかかることがあります';
    protected $SUCCESS_REASSEMBLY = '再構築を完了しました';
    protected $FAILED_REASSEMBLY = '再構築に失敗しました';

    const SUCCESS_CLEANUP_SITE = 'サイトの最適化が完了しました';
}
