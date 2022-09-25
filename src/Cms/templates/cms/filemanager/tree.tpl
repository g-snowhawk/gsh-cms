{% macro recursion(directory, parentDir) %}
  {% import _self as self %}
  {% set folders = apps.childDirectories(directory, parentDir) %}
  {% for folder in folders %}
    {% if loop.first %}
      <ul>
    {% endif %}
    <li><a href="?mode=cms.filemanager.receive:set-directory&amp;path={{ folder.path|url_encode }}" class="drop-target{% if folder.path == session.current_dir %} current{% endif %}" data-drop-path="{{ folder.path|url_encode }}">・{{ folder.name }}</a>
    {% if folder.name is not null %}
      {{ self.recursion(folder.name, folder.parent) }}
    {% endif %}
    </li>
    {% if loop.last %}
      </ul>
    {% endif %}
  {% endfor %}
{% endmacro %}
