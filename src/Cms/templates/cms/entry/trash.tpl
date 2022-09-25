{% extends "master.tpl" %}

{% block head %}
  <script src="{{ config.global.assets_path }}script/trash.js"></script>
{% endblock %}

{% block main %}
  <input type="hidden" name="mode" value="cms.entry.receive:empty-trash">
  <div class="explorer">
    <div class="explorer-sidebar resizable" data-minwidth="120">
      <div class="tree">
        <h1 class="headline">カテゴリ一覧...</h1>
        <nav>
          <ul>
            <li><a href="{{ referer is defined ? referer : '?mode=cms.entry.response' }}">ページ一覧</a></li>
            <li><a class="current with-icon trash">ごみ箱</a></li>
          </ul>
        </nav>
      </div>
    </div>
    <div class="explorer-mainframe">
      <div class="explorer-list">
        <h2 class="headline">ごみ箱</h2>
        <div class="explorer-body">
          {% set items = apps.trashItems(50) %}
          {% for unit in items %}
            {% if loop.first %}
              <table>
                <thead>
                  <tr>
                    <td>タイトル</td>
                    <td>&nbsp;</td>
                  </tr>
                </thead>
                <tbody>
            {% endif %}
            <tr class="{{ unit.kind == 'category' ? 'folder' : 'file' }}{% if unit.status is not empty %} {{ unit.status }}{% endif %}">
              {% if unit.kind == 'category' %}
                <td class="link with-icon spacer"><a>{{ unit.title }}</a></td>
              {% else %}
                <td class="link with-icon spacer"><a>{{ unit.title }}</a></td>
              {% endif %}
              {% if unit.kind == 'category' and apps.hasPermission('cms.category.delete', null, session.current_category) %}
                <td class="button"><label><input type="checkbox" name="rewind[]" value="cms:{{ unit.kind }}:{{ unit.id }}" class="invisible">戻す</label></td>
              {% elseif unit.kind == 'entry' and apps.hasPermission('cms.entry.delete', null, unit.parent) %}
                <td class="button"><label><input type="checkbox" name="rewind[]" value="cms:{{ unit.kind }}:{{ unit.id }}" class="invisible">戻す</label></td>
              {% else %}
                <td class="button">&nbsp;</td>
              {% endif %}
            </tr>
            {% if loop.last %}
                </tbody>
              </table>
            {% endif %}
          {% else %}
            <p class="notice">ごみ箱は空です</p>
          {% endfor %}
        </div>
        <div class="footer-controls">
          <nav class="links">
            {% if items|length > 0 %}
            <a href="?mode=cms.entry.receive:empty-trash" data-confirm="ごみ箱を空にします。よろしいですか？%0Aこの操作は取り消せません。"><i class="mark">&middot;</i>ごみ箱を空にする</a>
            {% else %}
            <span>&nbsp;</span>
            {% endif %}
          </nav>
          <nav class="pagination">
            {% include 'pagination.tpl' %}
          </nav>
        </div>
      </div>
    </div>
  </div>
{% endblock %}
