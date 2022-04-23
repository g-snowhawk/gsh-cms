{% macro recursion(i) %}
  {% import _self as self %}
  {% set category = apps.childCategories(i) %}
  {% for item in category %}
    {% if loop.first %}
      <ul>
    {% endif %}
    <li><a href="?mode=cms.entry.receive:set-category&amp;id={{ item.id }}"{% if item.id == session.current_category %} class="current"{% endif %}>{{ item.title }}{%if item.cnt > 0 %} <span class="entry-count sup">[{{ item.cnt }}]</span>{% endif %}</a>
    {% if item.id is not null %}
      {{ self.recursion(item.id) }}
    {% endif %}
    </li>
    {% if loop.last %}
      </ul>
    {% endif %}
  {% endfor %}
{% endmacro %}
