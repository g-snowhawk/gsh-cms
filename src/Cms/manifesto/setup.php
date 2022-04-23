<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 *
 * @see \Gsnowhawk\PackageSetup
 *
 * @license https://www.plus-5.com/licenses/mit-license  MIT License
 * @author  Taka Goto <www.plus-5.com>
 * @copyright 2016-2017 PlusFive (https://www.plus-5.com)
 */

namespace Gsnowhawk\Cms;

use Gsnowhawk\Common\Lang;
use Gsnowhawk\App;
use Gsnowhawk\PackageSetup;

/**
 * Application install class.
 */
final class setup extends PackageSetup
{
    /**
     * Current version number.
     */
    public const VERSION = '1.0.0';

    /**
     * Object constructor.
     *
     * @param Gsnowhawk\App $app
     * @param string  $installed_version
     */
    public function __construct(App $app, $installed_version)
    {
        $this->app = $app;
        $this->installed_version = $installed_version;
    }

    /**
     * Namespace of this package.
     *
     * @return string
     */
    public static function getNameSpace()
    {
        return __NAMESPACE__;
    }

    /**
     * Description of this package.
     *
     * @see Gsnowhawk\Common\Lang::translate()
     *
     * @return string
     */
    public static function getDescription()
    {
        return Lang::translate('APP_DETAIL', __CLASS__);
    }

    /**
     * Execute install/update package.
     *
     * @param array &$configuration
     *
     * @return bool
     */
    public function update(&$configuration)
    {
        $result = false;
        if (false !== $this->updateDatabase(__DIR__)
         && false !== $this->updateConfiguration($configuration)
        ) {
            $result = true;
        }

        $key = ($result) ? 'SUCCESS_SETUP' : 'FAILED_SETUP';
        $this->message = Lang::translate($key);

        return $result;
    }

    /**
     * Update configuration.
     *
     * @param array &$configuration
     *
     * @return bool
     */
    private function updateConfiguration(&$configuration)
    {
        $class = __NAMESPACE__;

        if (!isset($configuration['application'])) {
            $configuration['application'] = [];
        }
        $configuration['application']['default_mode'] = $class::DEFAULT_MODE;
        $configuration['application'][';cms_global_templates'] = '';

        return true;
    }
}
