{% extends "master.tpl" %}

{% block main %}
  <input type="hidden" name="mode" value="cms.site.receive:save">
  <input type="hidden" name="id" value="{{ post.id }}">
  <p id="backlink"><a href="?mode=cms.site.response">一覧に戻る</a></p>
  <div class="wrapper">
    <h1>サイトデータ編集</h1>
    {% if err.vl_title == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_title == 1 %} invalid{% endif %}">
      <label for="title">サイト名</label>
      <input type="text" name="title" id="title" value="{{ post.title }}">
    </div>
    {% if err.vl_url == 1 %}
      <div class="error">
        <i>入力してください</i>
      </div>
    {% endif %}
    <div class="fieldset{% if err.vl_url == 1 %} invalid{% endif %}">
      <label for="url">URL</label>
      <input type="text" name="url" id="url" value="{{ post.url }}">
    </div>
    <div class="fieldset">
      <label for="defaultpage">デフォルトページ</label>
      <input type="text" name="defaultpage" id="defaultpage" maxlength="32" value="{{ post.defaultpage }}">
    </div>
    <div class="fieldset">
      <label for="defaultextension">デフォルト拡張子</label>
      <input type="text" name="defaultextension" id="defaultextension" maxlength="5" value="{{ post.defaultextension }}">
    </div>
    <div class="fieldset">
      <label for="styledir">Styleディレクトリ</label>
      <input type="text" name="styledir" id="styledir" maxlength="32" value="{{ post.styledir }}">
    </div>
    <div class="fieldset">
      <label for="uploaddir">UpLoadディレクトリ</label>
      <input type="text" name="uploaddir" id="uploaddir" maxlength="32" value="{{ post.uploaddir }}">
    </div>
    <div class="fieldset">
      <label for="lang">言語</label>
      <select name="lang" id="lang">
        <option value="ja">日本語</option>
      </select>
    </div>
    <div class="fieldset">
      <label for="encoding">文字コード</label>
      <select name="encoding" id="encoding">
        <option value="UTF-8">UTF-8</option>
      </select>
    </div>
    <div class="fieldset">
      <label for="maxrevision">バージョン保存数</label>
      <input type="text" name="maxrevision" id="maxrevision" value="{{ post.maxrevision }}">
    </div>
    {% if apps.hasPermission('cms.site.create') %}
      {% if err.vl_openpath == 2 %}
        <div class="error">
          <i>書き込み権限がありません</i>
        </div>
      {% endif %}
      <div class="fieldset">
        <label for="openpath">公開ディレクトリ</label>
        <input type="text" name="openpath" id="openpath" value="{{ post.openpath }}">
      </div>
      <div class="fieldset">
        <label for="userkey">公開形式</label>
        <select name="type" id="type">
          <option value="static"{% if post.type == "static" %} selected{% endif %}>静的ページ</option>
          <option value="dynamic"{% if post.type == "dynamic" %} selected{% endif %}>動的ページ</option>
        </select>
      </div>
    {% endif %}
    {% if apps.isAdmin == 1 %}
      <div class="fieldset">
        <label for="userkey">所有者</label>
        <select name="userkey" id="userkey">
          <option value="">所有者を選択してください</option>
          {% for owner in owners %}
          <option value="{{ owner.id }}"{% if owner.id == post.userkey %} selected{% endif %}>{{ owner.fullname }}{% if owner.company is not empty %}&nbsp;({{ owner.company }}){% endif %}</option>
          {% endfor %}
        </select>
      </div>
    {% endif %}

    {% if apps.hasPermission('system') %}
      <div class="fieldset">
        <div class="legend">サイトルート</div>
        <div class="input">
          <input type="hidden" name="noroot" value="0">
          <label><input type="checkbox" name="noroot" id="noroot" value="1"{% if post.noroot == '1' %} checked{% endif %}>表示しない</label>
        </div>
      </div>
    {% endif %}

    {% if post.type == 'dynamic' %}
      <div class="fieldset">
        <label for="runlevel">公開モード</label>
        <select name="runlevel" id="runlevel">
          <option value="normal"{% if post.runlevel == 'normal' %} selected{% endif %}>通常稼動</option>
          <option value="maintenance"{% if post.runlevel == 'maintenance' %} selected{% endif %}>メンテナンス</option>
          <option value="emergency"{% if post.runlevel == 'emergency' %} selected{% endif %}>停止</option>
          <option value="stealth"{% if post.runlevel == 'stealth' %} selected{% endif %}>非表示</option>
        </select>
      </div>
      <div class="fieldset">
        <label for="announce">アナウンス</label>
        <textarea name="announce" id="announce" placeholder="例）&lt;p&gt;ただ今、メンテナンス中です。&lt;/p&gt;">{{ post.announce }}</textarea>
      </div>
    {% endif %}

    {% include 'edit_form_metadata.tpl' %}

    <div class="form-footer">
      <input type="submit" name="s1_submit" value="登録">
      {% if apps.hasPermission('system') and post.id is not empty %}
        <button type="submit" name="swap_mode" value="cms.site.receive:cleanup-site-data" data-confirmation="不要なデータを削除します。%0Aこの操作は取り消せません。よろしいですか？" data-cancel-confirm="yes">最適化</button>
      {% endif %}
    </div>
  </div>
{% endblock %}

{% block subform %}
  {% if apps.hasPermission('site.delete') and post.id is not empty %}
    <form id="TMS-subform" action="{{ subform.action }}" method="{{ subform.method }}" enctype="{{ subform.enctype }}"{% if form.confirm %} data-remove-confirm="{{ subform.confirm|url_encode }}"{% endif %}>
      <input type="hidden" name="stub" value="{{ stub }}">
      <input type="hidden" name="id" value="{{ post.id }}">
      <input type="hidden" name="mode" value="cms.site.receive:remove">
      <p class="ta-c">
        <input type="checkbox" name="removing" value="1">
        このサイトを削除します<br>
        カテゴリ、エントリ、その他関連するすべてのデータが削除されます<br>
        <b>この操作は取り消しできません！</b>
      </p>
      {% if err.vl_authorize == 1 %}
      <div class="error ta-c">
        <i>認証に失敗しました</i>
      </div>
      {% endif %}
      <div class="form-footer">
        <input type="submit" name="s1_submit" value="削除">
      </div>
      <script src="/common/script/cms/remove_site.js"></script>
    </form>
  {% endif %}
{% endblock %}
