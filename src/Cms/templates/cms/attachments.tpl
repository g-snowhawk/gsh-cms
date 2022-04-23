<section id="file-uploader">
  <h2>添付ファイル</h2>
  <p class="caution with-thumbnail">サムネールを右クリックすると添付ファイルの説明を追加できます。</p>
  {% for unit in custom.file %}
    <div class="file-set">
      <label class="selected">
        <span class="thumbnail">
          <img src="{% if unit.mime == 'application/pdf' %}{{ config.global.assets_path }}style/icon_pdf.svg{% else %}{{ unit.data }}{% endif %}" alt="{{ unit.alternate }}" title="{{ unit.title }}" draggable="false">
          {% if unit.mime == 'application/pdf' %}
            <span class="filename">{{ unit.alternate }}</span>
          {% endif %}
        </span>
        <input type="file" name="file[id_{{ unit.id }}]" accept="image/jpeg,.jpg,image/png,.png,image/gif,.gif,application/pdf,.pdf" class="image-selector">
      </label>
      <a href="#delete" class="mark"></a>
      <div class="popup">
        <b>代替テキスト</b><input type="text" name="alternate[id_{{ unit.id }}]" value="{{ unit.alternate }}">
        <b>説明文</b><textarea name="note[id_{{ unit.id }}]">{{ unit.note }}</textarea>
        {% if unit.mime == 'application/pdf' and apps.availableConvert() == true %}
          <select name="option1[id_{{ unit.id }}]">
            <option value="none"{% if unit.option1 == 'none' %} selected{% endif %}>サムネールなし</option>
            <option value="0"{% if unit.option1 == '0' %} selected{% endif %}>表紙のみ</option>
            <!--option value="all"{% if unit.option1 == 'all' %} selected{% endif %}>全ページ</option-->
          </select>
        {% else %}
          <!-- Automatic thumbnail creation is not supported -->
          <!-- ImageMagick needs to be installed on the server -->
        {% endif %}
      </div>
    </div>
  {% endfor %}
  <div class="file-set" id="attachment-origin" data-preset="tmsAttachmentsSetEventListener" data-after-added="tmsAttachmentsCountThumbnails">
    <label>
      <span class="thumbnail"></span>
      <input type="file" name="file[]" accept="image/jpeg,.jpg,image/png,.png,image/gif,.gif,application/pdf,.pdf" class="image-selector">
    </label>
  </div>
</section>
