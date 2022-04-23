{% if session.current_site is defined %}
  {% set priv = perms.site %}
  {% set prefix = 'site_' %}
  {% set current_category = '' %}
  <section class="permission" id="site-permission">
    <h2><a href="#permission-editor-site" class="accordion-switcher">サイト別権限設定</a></h2>
    <div id="permission-editor-site" class="accordion">
      <p>この権限は選択中のサイト【{{ apps.site_data.title }}】に適用されます</p>
      {% include 'cms/permission.tpl.inc' %}
    </div>
  </section>
  {# if session.current_category != apps.site_root #}
    {% set priv = perms.category %}
    {% set prefix = 'category_' %}
    {% set current_category = session.current_category %}
    <section class="permission" id="category-permission">
      <h2><a href="#permission-editor-category" class="accordion-switcher">カテゴリー別権限設定</a></h2>
      <div id="permission-editor-category" class="accordion">
        <p>この権限は選択中のカテゴリー【{{ apps.category_data.title }}】に適用されます</p>
        {% include 'cms/permission_on_category.tpl.inc' %}
      </div>
    </section>
  {# endif #}
{% endif %}
