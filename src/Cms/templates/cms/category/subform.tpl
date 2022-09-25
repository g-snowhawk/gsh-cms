{% extends "subform.tpl" %}

{% block main %}
  <article class="wrapper">
    <h1>新規カテゴリー</h1>
    {% if err.vl_title == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_title == 1 %} invalid{% endif %}">
      <label for="title">タイトル</label>
      <input type="text" name="title" id="title" value="{{ post.title }}" required>
    </div>
    {% if err.vl_path == 99 %}
      <div class="error" id="err-path-99">
        <i>同名のカテゴリが存在します</i>
      </div>
    {% endif %}
    <div class="fieldset">
      <label for="path">フォルダ名</label>
      <input type="text" name="path" id="path" value="{{ post.path }}">
      <div class="extension">
        <span>半角英数字、ハイフン、アンダースコアが使用できます</span>
      </div>
    </div>
    <div class="form-footer">
      <input type="submit" name="s1_submit" value="保存">
      <input type="hidden" name="mode" value="cms.category.receive:save">
    </div>
  </article>
{% endblock %}
