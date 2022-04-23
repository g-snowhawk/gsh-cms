{% macro recursion(eid, i) %}
  {% import _self as self %}
  {% set sections = apps.childSections(eid,i,['id','title','status']) %}
  {% for item in sections %}
    {% if item.status == 'release' %}
      {% set tips = '公開中' %}
    {% elseif item.status == 'private' %}
      {% set tips = '非公開' %}
    {% elseif item.new == 1 %}
      {% set tips = '未公開' %}
    {% else %}
      {% set tips = '下書き有り' %}
    {% endif %}
    {% if loop.first %}
      <ul>
    {% endif %}
    <li>
      <div class="line with-icon{{ item.status is defined ? ' ' ~ item.status : '' }}{{ item.new is defined ? ' new' : '' }}" data-tips="{{ tips }}">
        <a href="?mode=cms.section.response:edit&amp;id={{ item.id }}"{% if item.id == session.current_category %} class="current"{% endif %}>{{ item.title }}</a>
        <label><input type="radio" name="remove" value="{{ item.id }}">削除</label>
      </div>
    {% if item.id is not null %}
      {{ self.recursion(eid,item.id) }}
    {% endif %}
    </li>
    {% if loop.last %}
      </ul>
    {% endif %}
  {% endfor %}
{% endmacro %}
