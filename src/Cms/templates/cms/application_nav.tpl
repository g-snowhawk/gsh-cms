{% if session.current_site is defined %}
  <li id="cms-page"><a href="?mode=cms.entry.response">ページ管理</a></li>
  {% if site is not defined %}
    {% set site = apps.dataFromDb('id,type', 'site', 'id = ?', [session.current_site]) %}
  {% endif %}
  {% if site.type == 'static' %}
    <li id="cms-reassembly"><a href="?mode=cms.entry.response:reassembly">再構築</a></li>
  {% endif %}
  {% if apps.hasPermission('cms.file.read', permission.filter1) %}
    <li id="cms-file"><a href="?mode=cms.filemanager.response">ファイル管理</a></li>
  {% endif %}
  {% if apps.hasPermission('cms.template.read') %}
    <li id="cms-template"><a href="?mode=cms.template.response">テンプレート</a></li>
  {% endif %}
{% endif %}
<li id="cms-site"><a href="?mode=cms.site.response">サイト管理</a></li>
