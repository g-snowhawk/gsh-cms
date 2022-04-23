{% if header.id == 'cms-site-default' %}
  <script src="{{ config.global.assets_path }}script/cms/site.js" charset="utf-8"></script>
{% elseif header.id == 'cms-entry-default' %}
  <script src="{{ config.global.assets_path }}script/cms/category.js" charset="utf-8"></script>
{% elseif header.id == 'cms-entry-edit' or header.id == 'cms-section-edit' %}
  <script src="{{ config.global.assets_path }}script/cms/entry.js" charset="utf-8"></script>
  <script src="{{ config.global.assets_path }}script/cms/attachments.js" charset="utf-8"></script>
{% endif %}
{% if session.current_site is defined %}
  <script src="{{ config.global.assets_path }}script/cms/opensite.js" charset="utf-8"></script>
{% endif %}
