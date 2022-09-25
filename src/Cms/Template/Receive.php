<?php
/**
 * This file is part of G.Snowhawk Application.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Cms\Template;

use Gsnowhawk\Common\Http;
use Gsnowhawk\Common\Lang;

/**
 * Template management request receive class.
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
        if (parent::save()) {
            $this->session->param('messages', Lang::translate('SUCCESS_SAVED'));
            Http::redirect(
                $this->app->systemURI().'?mode=cms.template.response'
            );
        }
        $this->view->bind('err', $this->app->err);
        $this->edit();
    }

    public function revokeDraft()
    {
        if (parent::revokeDraft()) {
            $this->session->param('messages', Lang::translate('SUCCESS_SAVED'));
            Http::redirect(
                $this->app->systemURI().'?mode=cms.template.response'
            );
        }
        $this->view->bind('err', $this->app->err);
        $this->edit();
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
            $this->app->systemURI().'?mode=cms.template.response'
        );
    }

    public function rebuildStyleSheets()
    {
        $sitekey = $this->siteID;
        if (false === $styles = $this->db->select('identifier,sourcecode', 'template', 'WHERE sitekey = ? AND kind = ? AND active = ?', [$sitekey, 6, 1])) {
            trigger_error($this->db->error());

            return false;
        }

        if (empty($styles)) {
            return true;
        }

        foreach ($styles as $style) {
            $path = $this->templatePath($style['identifier'], $sitekey);
            $this->view->clearCache($path);
            $sourcecode = $this->view->render($style['sourcecode'], true, true);
            try {
                file_put_contents($path, $sourcecode);
            } catch (\ErrorException $e) {
                trigger_error($e->getMessage());

                return false;
            }
        }

        return true;
    }

    public function export()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?'.'>'.PHP_EOL;
        $xml .= '<templates>'.PHP_EOL;
        $templates = $this->db->select('*', 'template', 'WHERE sitekey = ? AND active = ? ORDER BY kind,id', [$this->siteID, 1]);
        foreach ($templates as $template) {
            $attr = ($template['kind'] === '1') ? ' root="1"' : '';
            $xml .= sprintf('  <template%s>'.PHP_EOL, $attr);
            $xml .= sprintf('    <id>%s</id>'.PHP_EOL, htmlspecialchars($template['identifier']));
            $xml .= sprintf('    <title>%s</title>'.PHP_EOL, htmlspecialchars($template['title']));
            $xml .= sprintf('    <sourcecode><![CDATA[%s]]></sourcecode>'.PHP_EOL, preg_replace("/(\r\n|\r|\n)/", PHP_EOL, $template['sourcecode']));
            $xml .= sprintf('    <kind>%d</kind>'.PHP_EOL, htmlspecialchars($template['kind']));
            $xml .= sprintf('    <path>%s</path>'.PHP_EOL, htmlspecialchars($template['path']));
            $xml .= sprintf('    <active>%d</active>'.PHP_EOL, htmlspecialchars($template['active']));
            $xml .= '  </template>'.PHP_EOL;
        }
        $xml .= '</templates>';

        $filename = basename(parent::DEFAULT_TEMPLATES_XML_PATH);
        $len = strlen($xml);

        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-length: {$len}");
        header('Content-type: text/xml; charset=utf-8');
        echo $xml;
        exit;
    }

    public function import()
    {
        $json = ['status' => 0];

        $templates_xml = $this->request->files('template_xml');

        $this->db->begin();
        if (false === parent::updateDefaultTemplate($templates_xml['tmp_name'])) {
            $json = ['status' => 1, 'message' => 'System Error'];
            trigger_error($this->db->error());
        } else {
            $json['message'] = Lang::translate('SUCCESS_IMPORT');
            $this->db->commit();
        }

        if ($json['status'] > 0) {
            $this->db->rollback();
        }

        header('Content-type: application/json; charset=utf-8');
        echo json_encode($json);
        exit;
    }
}
