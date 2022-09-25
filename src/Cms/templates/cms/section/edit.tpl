{% extends "master.tpl" %}

{% block main %}
  <input type="hidden" name="mode" value="cms.section.receive:save">
  <input type="hidden" name="eid" value="{{ post.eid }}">
  <input type="hidden" name="prn" value="{{ post.prn }}">
  <input type="hidden" name="id" value="{{ post.id }}">
  <input type="hidden" name="level" value="{{ post.level }}">
  <p id="backlink">
    <a href="?mode=cms.entry.response:edit&amp;id={{ post.eid }}">エントリに戻る</a>
    {% if post.prn is not empty %}
      /&nbsp;<a href="?mode=cms.section.response:edit&amp;id={{ post.prn }}">ひとつ上に戻る</a>
    {% endif %}
  </p>
  <div class="wrapper">
    <h1>セクション編集</h1>
    {% if err.vl_title == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_title == 1 %} invalid{% endif %}">
      <label for="title">タイトル</label>
      <input type="text" name="title" id="title" value="{{ post.title }}">
    </div>
    {% if err.vl_body == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_body == 1 %} invalid{% endif %}">
      <label for="body">本文</label>
      <textarea name="body" id="body">{{ post.body }}</textarea>
    </div>
    <nav class="insert">
      <a href="#body" data-insert="link">リンク挿入</a>
      <a href="#body" data-insert="image" data-upload="cms.entry.receive:ajaxUploadImage" data-delete="cms.entry.receive:ajaxDeleteImage" data-list="cms.entry.response:ajaxImageList" data-confirm="この画像を削除します。よろしいですか？">画像挿入</a>
    </nav>

    <div class="fieldset">
      <label for="cst_sectionclass">クラス</label>
      <select name="cst_sectionclass" id="cst_sectionclass">
        <option value="">ノーマル</option>
        <option value="table-like"{% if post.cst_sectionclass == 'table-like' %} selected{% endif %}>表組みタイプ</option>
        <option value="flex-row-reverse"{% if post.cst_sectionclass == 'flex-row-reverse' %} selected{% endif %}>横並びタイプ（画像左）</option>
        <option value="flex-row"{% if post.cst_sectionclass == 'flex-row' %} selected{% endif %}>横並びタイプ（画像右）</option>
      </select>
    </div>

    <div class="fieldset">
      <div class="legend">公開オプション</div>
      <div class="input">
        <label><input type="radio" name="publish" value="draft"{% if post.publish == 'draft' %} checked{% endif %}>保存のみ</label>
        <label><input type="radio" name="publish" value="release"{% if post.publish == 'release' %} checked{% endif %}>公開</label>
        <label><input type="radio" name="publish" value="private"{% if post.publish == 'private' %} checked{% endif %}>非公開</label>
      </div>
    </div>

    {% include 'cms/attachments.tpl' %}

    {% if post.id is not empty and post.level < 6 %}
      <section id="section-list" class="relational-list">
        <h2>子セクション</h2>
        <nav>
          {% import "cms/section/tree.tpl" as tree %}
          {{ tree.recursion(post.eid, post.id) }}
        </nav>
        <p class="create function-key"><a href="?mode=cms.section.response:edit&amp;eid={{ post.eid }}&amp;prn={{ post.id }}"><i>+</i>子セクション追加</a></p>
      </section>
    {% endif %}

    {% include 'edit_form_metadata.tpl' %}

    <div class="form-footer">
      <div class="flexbox">
        {% if revisions is defined %}
          {% for revision in revisions %}
            {% if loop.first %}
              <button type="submit" name="swap_mode" value="cms.section.receive:revoke-draft" data-confirmation="下書きを取り消します%0Aこの操作は取り消せません。よろしいですか？" data-cancel-confirm="yes">下書きを破棄</button>
            {% endif %}
            <input type="hidden" name="version" value="{{ revision }}">
          {% endfor %}
        {% endif %}
        {% set backMode = post.prn is empty ? 'cms.entry.response:edit' : 'cms.section.response:edit' %}
        <hr>
        <a href="?mode={{ backMode }}&amp;id={{ post.eid }}" class="button" id="cancel-button">キャンセル</a>
        <input type="submit" name="s1_submit" value="保存">
      </div>
    </div>
  </div>
{% endblock %}
