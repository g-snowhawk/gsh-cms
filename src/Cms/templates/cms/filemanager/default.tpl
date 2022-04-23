{% extends "master.tpl" %}

{% block head %}
  <script src="{{ config.global.assets_path }}script/cms/explorer.js"></script>
  <script src="{{ config.global.assets_path }}script/cms/fileuploader.js"></script>
{% endblock %}

{% block main %}
  <input type="hidden" name="mode" value="cms.filemanager.receive:remove">
  <input type="hidden" name="ondrop_mode" value="cms.filemanager.receive:move">
  <input type="hidden" name="rename_mode" value="cms.filemanager.receive:rename">
  <div class="explorer">
    <div class="explorer-sidebar resizable" data-minwidth="120">
      <div class="tree">
        <h1 class="headline">フォルダ一覧</h1>
        <nav>
          <ul>
            {% if apps.isAdmin() or apps.hasPermission('cms.file.noroot') != '1' %}
              <li><a href="?mode=cms.filemanager.receive:set-directory&amp;path=" class="drop-target{% if session.current_dir is empty %} current{% endif %}" data-drop-path="">/</a>
            {% endif %}
            {% import "cms/filemanager/tree.tpl" as tree %}
            {{ tree.recursion(null, null) }}
            {% if apps.isAdmin() or apps.hasPermission('cms.file.noroot') != '1' %}
              </li>
            {% endif %}
          </ul>
        </nav>
      </div>
    </div>
    <div class="explorer-mainframe">
      <div class="explorer-list">
        <h2 class="headline">ファイル一覧
          {% for dir in cwd %}
            {% set path = path is defined ? [path, dir]|join('/') : dir %}
            {% if loop.first %}
              <span class="breadcrumbs">
            {% endif %}
            <a href="?mode=cms.filemanager.receive:set-directory&amp;path={{ path|url_encode }}">{{ dir }}</a>
            {% if loop.last %}
              </span>
            {% endif %}
          {% endfor %}
        </h2>
        <div class="explorer-body">
          <table>
            <thead>
              <tr>
                <td>ファイル名</td>
                <td>URL</td>
                <td>サイズ</td>
                <td>更新日</td>
                <td>&nbsp;</td>
              </tr>
            </thead>
            <tbody>
            {% for unit in files %}
              <tr class="{{ unit.kind }}">
                {% if unit.kind == 'folder' %}
                  <td class="link spacer with-icon"><a href="?mode=cms.filemanager.receive:set-directory&amp;path={{ unit.path|url_encode }}" class="renamable">{{ unit.name }}</a></td>
                {% else %}
                  <td class="link spacer with-icon"><span class="renamable">{{ unit.name }}</span></td>
                {% endif %}
                <td class="url">
                  {% if unit.url is not empty %}
                    <a href="{{ unit.url }}" target="tms_filemanager">{{ unit.url }}</a>
                  {% endif %}
                </td>
                <td class="date">{{ unit.size }}</td>
                <td class="date">{{ unit.modify_date|date('Y年n月j日 H:i') }}</td>
                <td class="button reddy"><label><input type="radio" name="delete" value="{{ unit.kind }}:{{ unit.name }}">削除</label></td>
              </tr>
            {% else %}
              <tr>
                <td class="nowrap empty" colspan="4">ファイルがありません</td>
                <td></td>
              </tr>
            {% endfor %}
            </tbody>
          </table>
        </div>
        <div class="footer-controls">
          <div id="file-selector" data-error-message="%d個のファイルアップロードに失敗しました" data-directory-message="%d個のディレクトリをスキップしました">
            <label class="droparea">
              <input type="file" name="file" id="file" multiple>
              <span>ここにファイルをドロップします<br><small>またはクリックしてファイルを選択します</small></span>
            </label>
          </div>
          <nav class="links">
            <a href="?mode=cms.filemanager.response:add-folder" class="subform-opener"><i class="mark">+</i>新規フォルダ</a>
            <a href="?mode=cms.filemanager.receive:save-file" class="file-uploader"><i class="mark">+</i>新規ファイル</a>
          </nav>
          <nav class="pagination">
            {% include 'pagination.tpl' %}
          </nav>
        </div>
      </div>
    </div>
  </div>
{% endblock %}
