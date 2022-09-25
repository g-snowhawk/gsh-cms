{% if session.current_site is defined %}
  {% set url = apps.site_data.url %}
  {% if url is empty %}
    {% set url = apps.dataFromDb('url', 'site', 'id = ?', [session.current_site]) %}
  {% endif %}
  <li><a href="{{ url }}" target="_brank" class="open-the-site">サイトを開く</a></li>
{% endif %}
