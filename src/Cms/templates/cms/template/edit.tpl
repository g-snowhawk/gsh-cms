{% extends "master.tpl" %}

{% block main %}
  <p id="backlink"><a href="?mode=cms.template.response">一覧に戻る</a></p>
  <div class="wrapper">
    <h1>テンプレート編集</h1>
    {% if err.vl_title == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_title == 1 %} invalid{% endif %}">
      <label for="title">テンプレート名</label>
      <input type="text" name="title" id="title" value="{{ post.title }}" placeholder="分かりやすい名前をつけてください">
    </div>
    {% if err.vl_sourcecode == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_sourcecode == 1 %} invalid{% endif %}">
      <label for="sourcecode">テンプレート本体</label>
      <textarea name="sourcecode" id="sourcecode" wrap="off">{{ post.sourcecode }}</textarea>
    </div>
    {% if post.kind != 1 %}
      {% if err.vl_path == 1 %}
        <div class="error">
          <i>入力してください</i>
        </div>
      {% endif %}
      <div class="fieldset{% if err.vl_path == 1 %} invalid{% endif %}">
        <label for="path">ファイル名</label>
        <input type="text" name="path" id="path" value="{{ post.path }}">
      </div>
      <div class="fieldset">
        <label for="kind">タイプ</label>
        <select name="kind" id="kind">
        {% for key, value in kinds %}
          {% if key != '1' %}
            <option value="{{ key }}"{% if post.kind == key %} selected{% endif %}>{{ value }}</option>
          {% endif %}
        {% endfor %}
        </select>
      </div>
    {% else %}
      <input type="hidden" name="path" value="homepage">
      <input type="hidden" name="kind" value="1">
    {% endif %}
    <div class="fieldset">
      <div class="legend">保存オプション</div>
      <div class="input">
        <label><input type="radio" name="publish" value="draft"{% if post.publish == 'draft' %} checked{% endif %}>保存のみ</label>
        <label{% if status == 'draft' %} class="strong"{% endif %}><input type="radio" name="publish" value="release"{% if post.publish == 'release' %} checked{% endif %}>書き出す</label>
        {% if context.usable %}
          <label><input type="radio" name="publish" value="global"{% if post.publish == 'global' %} checked{% endif %}>初期テンプレートを使用</label>
        {% endif %}
      </div>
    </div>
    <p class="note">
      {% if context.usable %}
        {% if context.use == 'local' %}
          編集済みテンプレートを使用しています。
        {% elseif context.use == 'global' %}
          初期テンプレートを使用しています。
        {% else %}
          使用できる状態ではありません。書き出しをしてください。
        {% endif %}
      {% endif %}
      {% if status == 'draft' %}
        <b>※下書き保存されています。</b>
      {% endif %}
    </p>

    {% include 'edit_form_metadata.tpl' %}

    <div class="form-footer">
      <a href="?mode=cms.template.response" class="button">キャンセル</a>
      <input type="submit" name="s1_submit" value="登録">
      <input type="hidden" name="mode" value="cms.template.receive:save">
      <input type="hidden" name="id" value="{{ post.id }}">
      {% if status == 'draft' %}
      <hr>
      <button type="submit" name="swap_mode" value="cms.template.receive:revoke-draft" data-confirmation="下書きを取り消します%0Aこの操作は取り消せません。よろしいですか？" data-cancel-confirm="yes">下書きを破棄</button>
      {% endif %}
    </div>
  </div>
{% endblock %}
