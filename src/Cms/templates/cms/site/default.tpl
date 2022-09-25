{% extends "master.tpl" %}

{% block main %}
  <div class="wrapper">
    <h1>{{ siteLabel|default('サイト') }}一覧</h1>
    <div class="header-control">
      <hr>
      <select name="sort_order_sitelist" class="setcookie-and-reload"{% if pager.records <= 1 %} disabled{% endif %}>
        <option value="DESC"{% if post.sort_order_sitelist == 'DESC' %} selected{% endif %}>新しい順</option>
        <option value="ASC"{% if post.sort_order_sitelist == 'ASC' %} selected{% endif %}>古い順</option>
      </select>
    </div>
    {% for unit in sites %}
      <section{% if session.current_site == unit.id %} class="current"{% endif %}>
        <h2>{{ unit.title }}</h2>
        <p>{{ unit.url }}
        <div class="item-footer">
          {% if unit.type == 'dynamic' %}
            <p class="runlevel {{ unit.runlevel }}">
            {%- if unit.runlevel == 'maintenance' %}メンテナンス
            {% elseif unit.runlevel == 'emergency' %}停止
            {% elseif unit.runlevel == 'stealth' %}非表示
            {% else %}通常稼動{% endif -%}
            </p>
          {% else %}
            <div class="dummy"></div>
          {% endif %}
          <p class="controls">
            {% if apps.hasPermission('cms.site.update',unit.id) and unit.update != '0' %}
              <a href="?mode=cms.site.response:edit&id={{ unit.id }}">編集</a>
            {% endif %}
            <label>選択<input type="radio" name="choice" value="{{ unit.id }}"{% if current_site == unit.id %} checked{% endif %}></label>
          </p>
        </div>
    </section>
    {% else %}
      <section class="nodata">
        <h2>No data...</h2>
        <p>{{ siteLabel|default('サイト') }}が登録されていません
      </section>
    {% endfor %}
    <div class="footer-controls">
      {% if apps.hasPermission('cms.site.create') %}
        <p class="create function-key"><a href="?mode=cms.site.response:edit"><i>+</i>新規{{ siteLabel|default('サイト') }}</a></p>
      {% endif %}
      <nav class="pagination">
        {% include 'pagination.tpl' with {'columnCount':'9'} %}
      </nav>
      {% set rows = [5,10,15,20,50] %}
      {% if pager.records > rows[0] %}
      {% for row in rows %}
        {% if loop.first %}
        <select name="rows_per_page_sitelist" class="setcookie-and-reload">
        {% endif %}
        <option value="{{ row }}"{% if post.rows_per_page_sitelist == row %} selected{% endif %}>{{ row }}件表示</option>
        {% if loop.last %}
        </select>
        {% endif %}
      {% endfor %}
      {% endif %}
    </div>
  </div>
  <input type="hidden" name="mode" value="cms.site.receive:select">
{% endblock %}
