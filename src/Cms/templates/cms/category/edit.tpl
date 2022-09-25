{% extends "master.tpl" %}

{% block main %}
  <input type="hidden" name="mode" value="cms.category.receive:save">
  <input type="hidden" name="id" value="{{ post.id }}">
  <p id="backlink"><a href="?mode=cms.entry.response">一覧に戻る</a></p>
  <div class="wrapper">
    <h1>カテゴリー編集</h1>
    {% if err.vl_title == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_title == 1 %} invalid{% endif %}">
      <label for="title">和名</label>
      <input type="text" name="title" id="title" value="{{ post.title }}">
    </div>
    {% if err.vl_path == 99 %}
      <div class="error" id="err-path-99">
        <i>同名のカテゴリが存在します</i>
      </div>
    {% endif %}
    <div class="fieldset">
      <label for="path">英名</label>
      <input type="text" name="path" id="path" value="{{ post.path }}">
    </div>
    <div class="fieldset">
      <label for="tags">キーワード</label>
      <input type="text" name="tags" id="tags" value="{{ post.tags }}">
    </div>
    {% if err.vl_description == 2 %}
      <div class="error">
        <i>HTMLタグを含むことはできません</i>
      </div>
    {% endif %}
    <div class="fieldset">
      <label for="description">要約</label>
      <textarea name="description" id="description">{{ post.description }}</textarea>
    </div>

    <div class="fieldset">
      <label for="parent">親カテゴリ</label>
      <div class="input select-box">
        <div class="select-text"></div>
        <div class="select-menu" id="category-selector">
          {% import "cms/category/pulldown.tpl" as pulldown %}
          {{ pulldown.recursion(null, 'radio', 'parent') }}
        </div>
      </div>
    </div>

    {% if err.vl_template == 1 %}
      <div class="error">
        <i>選択してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_template == 1 %} invalid{% endif %}">
      <label for="template">テンプレート</label>
      <select name="template" id="template">
      {% for unit in templates %}
        {% if loop.first %}
          <option value="">選択してください</option>
        {% endif %}
        <option value="{{ unit.id }}"{% if post.template == unit.id %} selected{% endif %}>{{ unit.title }}</option>
      {% else %}
        <option value="">テンプレートが登録されていません</option>
      {% endfor %}
      </select>
    </div>

    <div class="fieldset">
      <label for="default_template">初期テンプレート</label>
      <select name="default_template" id="default_template">
      {% for unit in default_templates %}
        {% if loop.first %}
          <option value="">選択してください</option>
        {% endif %}
        <option value="{{ unit.id }}"{% if post.default_template == unit.id %} selected{% endif %}>{{ unit.title }}</option>
      {% else %}
        <option value="">テンプレートが登録されていません</option>
      {% endfor %}
      </select>
    </div>

    {% if apps.hasPermission('site.create_priv') %}
      <div class="fieldset">
        <div class="legend">継承</div>
        <div class="input">
          <label><input type="radio" name="inheritance" value="1"{% if post.inheritance == 1 %} checked{% endif %}>テンプレートのみ</label><br>
          <label><input type="radio" name="inheritance" value="2"{% if post.inheritance == 2 %} checked{% endif %}>初期テンプレートのみ</label><br>
          <label><input type="radio" name="inheritance" value="4"{% if post.inheritance == 4 %} checked{% endif %}>テンプレート／初期テンプレート</label><br>
        </div>
      </div>
    {% else %}
      <input type="hidden" name="inheritance" value="{{ post.inheritance }}">
    {% endif %}

    <div class="fieldset">
      <label for="filepath">ファイル名</label>
      <input type="text" name="filepath" id="filepath" value="{{ post.filepath }}">
    </div>

    <div class="fieldset">
      <label for="cst_mixlist">Site Menu</label>
      <select name="cst_mixlist" id="cst_mixlist">
        <option value="">Off</option>
        <option value="on"{% if post.cst_mixlist == 'on' %} selected{% endif %}>On</option>
      </select>
    </div>

    {% if apps.hasPermission('site.create_priv') %}
      <div class="fieldset">
        <label for="archive_format">年月別アーカイブ</label>
        <input type="text" name="archive_format" id="archive_format" value="{{ post.archive_format }}">
        <span class="unit">{{ site.defaultextension }}</span>
      </div>
    {% else %}
      <input type="hidden" name="archive_format" value="{{ post.archive_format }}">
    {% endif %}

    {% include 'edit_form_metadata.tpl' %}

    <div class="form-footer">
      <div class="separate-block">
        <span>&nbsp;</span>
        <span>
          <a href="?mode=cms.entry.response" class="button" id="cancel-button">キャンセル</a>
          <input type="submit" name="s1_submit" value="保存">
        </span>
      </div>
    </div>
  </div>
{% endblock %}
