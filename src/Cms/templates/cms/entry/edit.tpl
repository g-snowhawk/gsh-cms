{% extends "master.tpl" %}

{% block head %}
  <template id="popup-note">
    <div class="popup">
      <b>代替テキスト</b><input type="text" name="alternate[]">
      <b>説明文</b><textarea name="note[]"></textarea>
      {% if apps.availableConvert() == true %}
      <select name="option1[]">
        <option value="none">サムネールなし</option>
        <option value="0">表紙のみ</option>
        <!--option value="all">全ページ</option-->
      </select>
      {% endif %}
    </div>
  </template>
  <template id="choices-input">
    <div class="choices-input">
      <input type="text" name="form_choices[]" class="normal">
      <button type="button" class="choices-remove">Delete</button>
    </div>
  </template>
{% endblock %}

{% block main %}
  <input type="hidden" name="mode" value="cms.entry.receive:save">
  <input type="hidden" name="id" value="{{ post.id }}">
  <p id="backlink"><a href="?mode=cms.entry.response">一覧に戻る</a></p>
  <div class="wrapper">
    <h1>エントリ編集</h1>
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
      <label for="category">カテゴリ</label>
      <div class="input select-box">
        <div class="select-text"></div>
        <div class="select-menu" id="category-selector">
          {% import "cms/category/pulldown.tpl" as pulldown %}
          {{ pulldown.recursion(null, 'radio', 'category') }}
        </div>
      </div>
    </div>

    <div class="fieldset">
      <label for="tags">キーワード</label>
      <input type="text" name="tags" id="tags" value="{{ post.tags}}">
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
    {% if err.vl_filepath == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% elseif err.vl_filepath == 99 %}
      <div class="error" id="err-filepath-99">
        <i>同名のエントリが存在します</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_filepath == 1 %} invalid{% endif %}">
      <label for="filepath">ファイル名</label>
      <input type="text" name="filepath" id="filepath" value="{{ post.filepath }}">
    </div>
    {% if err.vl_template == 1 %}
      <div class="error">
        <i>選択してください</i>
      </div>
    {% endif %}
    {% for unit in templates %}
      {% if loop.first %}
        <div class="fieldset{% if err.vl_template == 1 %} invalid{% endif %}">
          <label for="template">テンプレート</label>
          <select name="template" id="template">
            <option value="">選択してください</option>
      {% endif %}
      <option value="{{ unit.id }}"{% if post.template == unit.id %} selected{% endif %}>{{ unit.title }}</option>
      {% if loop.last %}
          </select>
        </div>
      {% endif %}
    {% else %}
      <script>
        alert('テンプレートが登録されていません。\n先にエントリ用のテンプレートを追加してください');
      </script>
    {% endfor %}

    {% if err.vl_release_period > 0 %}
    <div class="error">
      {% if err.vl_release_period == 1 %}
      <i>開始日は0000/00/00 00:00形式で入力してください</i>
      {% elseif err.vl_release_period == 2 %}
      <i>終了日は0000/00/00 00:00形式で入力してください</i>
      {% elseif err.vl_release_period == 3 %}
      <i>終了日は開始日より後を入力してください</i>
      {% endif %}
    </div>
    {% endif %}
    <div class="fieldset{% if err.vl_release_period > 0 %} invalid{% endif %}">
      <div class="legend">公開オプション</div>
      <fieldset>
        <label title="変更内容はWEBサイトに反映されません"><input type="radio" name="publish" value="draft"{% if post.publish == 'draft' %} checked{% endif %}>下書き保存</label>
        <label title="WEBサイトに投稿を公開します"><input type="radio" name="publish" value="release"{% if post.publish == 'release' %} checked{% endif %}>公開</label>
        <label title="WEBサイトに公開中の投稿を非表示にします"><input type="radio" name="publish" value="private"{% if post.publish == 'private' %} checked{% endif %}>公開停止</label>
      </fieldset>
    </div>

    {% if site.type == 'dynamic' %}
      <div class="fieldset">
        <label for="release_date">公開期間</label>
        <div class="input">
          <input type="datetime-local" name="release_date" id="release_date" value="{{ post.release_date|date('Y-m-d\\TH:i') }}" placeholder="開始" class="datetime">&nbsp;〜&nbsp;<input type="datetime-local" name="close_date" id="close_date" value="{{ post.close_date|date('Y-m-d\\TH:i') }}" placeholder="終了" class="datetime">
        </div>
      </div>
    {% endif %}

    {% include 'cms/attachments.tpl' %}

    <section id="section-list" class="relational-list">
      <h2>セクション</h2>
      {% if post.id is not empty %}
        <nav>
          {% import "cms/section/tree.tpl" as tree %}
          {{ tree.recursion(post.id, null) }}
        </nav>
        <p class="create function-key"><a href="?mode=cms.section.response:edit&amp;eid={{ post.id }}" class="small-button"><i>+</i>セクション追加</a></p>
      {% else %}
        <p>※セクションを追加するにはエントリーを一度保存する必要があります</p>
      {% endif %}
    </section>

    {% include 'edit_form_metadata.tpl' %}

    <div class="form-footer">
      <div class="flexbox">
        <input type="submit" name="preview" value="プレビュー">
        {% if revisions is defined %}
          {% for revision in revisions %}
            {% if loop.first %}
              <button type="submit" name="swap_mode" value="cms.entry.receive:revoke-draft" data-confirmation="下書きを取り消します%0Aこの操作は取り消せません。よろしいですか？" data-cancel-confirm="yes">下書きを破棄</button>
            {% endif %}
            <input type="hidden" name="version" value="{{ revision }}">
          {% endfor %}
        {% endif %}
        <hr>
        <a href="?mode=cms.entry.response" class="button">キャンセル</a>
        <input type="submit" name="s1_submit" value="保存">
      </div>
    </div>
  </div>
{% endblock %}
