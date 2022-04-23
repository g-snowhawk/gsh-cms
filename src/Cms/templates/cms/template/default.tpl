{% extends "master.tpl" %}

{% block head %}
  <script src="{{ config.global.assets_path }}script/cms/import-templates.js"></script>
{% endblock %}

{% block main %}
  <input type="hidden" name="mode" value="cms.template.receive:remove">
  <div class="explorer-list">
    <h1 class="headline">テンプレート一覧</h1>
    <div class="explorer-body">
      {% set prev = '' %}
      {% for unit in templates %}
        {% if loop.first %}
          <table>
            <thead>
              <tr>
                <!--td>タイプ</td-->
                <td>テンプレート名</td>
                <td>作成日</td>
                <td>更新日</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
              </tr>
            </thead>
            <tbody>
        {% endif %}
        {% if unit.kind != prev %}
          <tr class="separator">
            <td colspan="6">{{ kinds[unit.kind] }}</td>
          </tr>
        {% endif %}
        {% if unit.status == 'draft' %}
          {% set tips = '下書き有り' %}
        {% elseif unit.status == 'global' %}
          {% set tips = '使用可' %}
        {% elseif unit.status == 'release' %}
          {% set tips = '使用可' %}
        {% else %}
          {% set tips = '使用不可' %}
        {% endif %}
        <tr class="{{ unit.status }}">
          <!--td>{{ kinds[unit.kind] }}</td-->
          <td class="spacer link with-icon"><a href="?mode=cms.template.response:edit&id={{ unit.id|url_encode }}" data-tips="{{ tips }}">{{ unit.title }}</a></td>
          <td class="date">{{ unit.create_date|date('Y年n月j日 H:i') }}</td>
          <td class="date">{{ unit.modify_date|date('Y年n月j日 H:i') }}</td>
          {% if apps.hasPermission('cms.template.update') %}
            <td class="button"><a href="?mode=cms.template.response:edit&id={{ unit.id|url_encode }}">編集</a></td>
          {% else %}
            <td class="button">&nbsp;</td>
          {% endif %}
          {% if apps.hasPermission('cms.template.create') %}
            <td class="button"><a href="?mode=cms.template.response:edit&cp={{ unit.id|url_encode }}">複製</a></td>
          {% else %}
            <td class="button">&nbsp;</td>
          {% endif %}
          {% if apps.hasPermission('cms.template.delete') %}
            <td class="button reddy">{% if unit.kind != 1 %}<label><input type="radio" name="delete" value="{{ unit.id }}">削除</label>{% else %}&nbsp;{% endif %}</td>
          {% else %}
            <td class="button reddy">&nbsp;</td>
          {% endif %}
        </tr>
        {% set prev = unit.kind %}
        {% if loop.last %}
            </tbody>
          </table>
        {% endif %}
      {% else %}
        <p class="notice" colspan="4">テンプレートの登録がありません</p>
      {% endfor %}
    </div>
    <div class="footer-controls">
      <nav class="links">
        {% if apps.hasPermission('cms.template.create') %}
          <a href="?mode=cms.template.response:edit"><mark>+</mark>新規テンプレート</a>
        {% endif %}
        {% if apps.hasPermission('root') %}
          <label class="auto-uploader">インポート<input type="file" name="template_xml" accept=".xml,application/xml" data-mode="cms.template.receive:import" data-confirm="インポートを開始します。よろしいですか？"></label>
          {% if templates|length > 0 %}
            <a href="?mode=cms.template.receive:export" class="post-request">エクスポート</a>
          {% endif %}
        {% else %}
          <span>&nbsp;</span>
        {% endif %}
      </nav>
      <nav class="pagination">
        {% include 'pagination.tpl' %}
      </nav>
    </div>
  </div>
{% endblock %}
