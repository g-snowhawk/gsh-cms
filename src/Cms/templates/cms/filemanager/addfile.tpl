{% extends "subform.tpl" %}

{% block main %}
  <article class="wrapper">
    <h1>ファイルアップロード</h1>
    {% if err.vl_file >= 1 %}
      <div class="error">
        {% if err.vl_file == 4 %}
          <i>ファイルを選択してください</i>
        {% elseif err.vl_file == 1 or err.vl_file == 2 %}
          <i>ファイルサイズが大き過ぎます</i>
        {% elseif err.vl_file == 9 %}
          <i>ExcelまたはCSVファイルを選択してください</i>
        {% else %}
          <i>ファイルアップロードに失敗しました</i>
        {% endif %}
      </div>
      <div class="error">
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_file == 1 %} invalid{% endif %}">
      <label for="title">ファイル</label>
      <input type="file" name="file" id="file" value="{{ post.file }}" accept="{% for key, format in formats %}{{ format.mime }},.{{ key }}{% if not loop.last %},{% endif %}{% endfor %}" required>
    </div>
    <div class="form-footer">
      <input type="submit" name="s1_submit" value="アップロード">
      <input type="hidden" name="mode" value="cms.filemanager.receive:save-file">
    </div>
  </article>
{% endblock %}
