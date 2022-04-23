{% extends "subform.tpl" %}

{% block main %}
  <article class="wrapper">
    <h1>新規フォルダ</h1>
    {% if err.vl_path == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset">
      <label for="path">フォルダ名</label>
      <input type="text" name="path" id="path" value="{{ post.path }}" required>
      <div class="extension">
        <span>半角英数字、ハイフン、アンダースコアが使用できます</span>
      </div>
    </div>
    <div class="form-footer">
      <input type="submit" name="s1_submit" value="保存">
      <input type="hidden" name="mode" value="cms.filemanager.receive:save-folder">
    </div>
  </article>
{% endblock %}
