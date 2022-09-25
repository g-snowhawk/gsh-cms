<table>
  <thead>
    <tr>
      <td>権限適用範囲</td>
      <td>作成</td>
      <td>読取</td>
      <td>更新</td>
      <td>削除</td>
      <td>公開</td>
      <td>その他</td>
    </tr>
  </thead>
  <tbody>
    {% set filters = session.current_site ~ ".." %}
    {% if prefix != 'category_' %}
      <tr>
        <th>サイト</th>
        <td>{% if apps.userinfo.admin == 1 or priv.site.create == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.site.create]"{% if post.perm[filters ~ 'cms.site.create'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
        <td>{% if apps.userinfo.admin == 1 or priv.site.read   == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.site.read]"  {% if post.perm[filters ~ 'cms.site.read']   == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
        <td>{% if apps.userinfo.admin == 1 or priv.site.update == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.site.update]"{% if post.perm[filters ~ 'cms.site.update'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
        <td>{% if apps.userinfo.admin == 1 or priv.site.delete == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.site.delete]"{% if post.perm[filters ~ 'cms.site.delete'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
        <td>-</td>
        <td>{% if apps.userinfo.admin == 1 %}<label><input type="checkbox" value="1" name="perm[{{ filters }}cms.site.noroot]"{% if post.perm[filters ~ 'cms.site.noroot'] == 1 %} checked{% endif %}><small>最上位拒否</small></label>{% else %}-{% endif %}</td>
      </tr>
      <tr>
        <th>テンプレート</th>
        <td>{% if apps.userinfo.admin == 1 or priv.template.create == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.template.create]"{% if post.perm[filters ~ 'cms.template.create'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
        <td>{% if apps.userinfo.admin == 1 or priv.template.read   == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.template.read]"  {% if post.perm[filters ~ 'cms.template.read']   == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
        <td>{% if apps.userinfo.admin == 1 or priv.template.update == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.template.update]"{% if post.perm[filters ~ 'cms.template.update'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
        <td>{% if apps.userinfo.admin == 1 or priv.template.delete == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.template.delete]"{% if post.perm[filters ~ 'cms.template.delete'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
        <td>-</td>
        <td>-</td>
      </tr>
      <tr>
        <th>ファイル</th>
        <td>{% if apps.userinfo.admin == 1 or priv.file.create == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.file.create]"{% if post.perm[filters ~ 'cms.file.create'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
        <td>{% if apps.userinfo.admin == 1 or priv.file.read   == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.file.read]"  {% if post.perm[filters ~ 'cms.file.read']   == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
        <td>{% if apps.userinfo.admin == 1 or priv.file.update == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.file.update]"{% if post.perm[filters ~ 'cms.file.update'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
        <td>{% if apps.userinfo.admin == 1 or priv.file.delete == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.file.delete]"{% if post.perm[filters ~ 'cms.file.delete'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
        <td>-</td>
        <td>{% if apps.userinfo.admin == 1 %}<label><input type="checkbox" value="1" name="perm[{{ filters }}cms.file.noroot]"{% if post.perm[filters ~ 'cms.file.noroot'] == 1 %} checked{% endif %}><small>最上位拒否</small></label>{% else %}-{% endif %}</td>
      </tr>
    {% endif %}
    {% set filters = session.current_site ~ "." ~ current_category ~ "." %}
    <tr>
      <th>カテゴリ</th>
      <td>{% if apps.userinfo.admin == 1 or priv.category.create == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.category.create]" {% if post.perm[filters ~ 'cms.category.create']  == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>{% if apps.userinfo.admin == 1 or priv.category.read   == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.category.read]"   {% if post.perm[filters ~ 'cms.category.read']    == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>{% if apps.userinfo.admin == 1 or priv.category.update == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.category.update]" {% if post.perm[filters ~ 'cms.category.update']  == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>{% if apps.userinfo.admin == 1 or priv.category.delete == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.category.delete]" {% if post.perm[filters ~ 'cms.category.delete']  == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>-</td>
      <td>{% if apps.userinfo.admin == 1 or priv.category.upfate == 1 %}<label><input type="checkbox" value="1" name="perm[{{ filters }}cms.category.disinherit]"{% if post.perm[filters ~ 'cms.category.disinherit'] == 1 %} checked{% endif %}><small>継承しない</small></label>{% else %}-{% endif %}</td>
    </tr>
    <tr>
      <th>エントリ</th>
      <td>{% if apps.userinfo.admin == 1 or priv.entry.create  == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.entry.create]" {% if post.perm[filters ~ 'cms.entry.create']  == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>{% if apps.userinfo.admin == 1 or priv.entry.read    == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.entry.read]"   {% if post.perm[filters ~ 'cms.entry.read']    == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>{% if apps.userinfo.admin == 1 or priv.entry.update  == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.entry.update]" {% if post.perm[filters ~ 'cms.entry.update']  == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>{% if apps.userinfo.admin == 1 or priv.entry.delete  == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.entry.delete]" {% if post.perm[filters ~ 'cms.entry.delete']  == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>{% if apps.userinfo.admin == 1 or priv.entry.publish == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.entry.publish]"{% if post.perm[filters ~ 'cms.entry.publish'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>-</td>
    </tr>
    <tr>
      <th>セクション</th>
      <td>{% if apps.userinfo.admin == 1 or priv.section.create  == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.section.create]" {% if post.perm[filters ~ 'cms.section.create']  == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>{% if apps.userinfo.admin == 1 or priv.section.read    == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.section.read]"   {% if post.perm[filters ~ 'cms.section.read']    == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>{% if apps.userinfo.admin == 1 or priv.section.update  == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.section.update]" {% if post.perm[filters ~ 'cms.section.update']  == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>{% if apps.userinfo.admin == 1 or priv.section.delete  == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.section.delete]" {% if post.perm[filters ~ 'cms.section.delete']  == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>{% if apps.userinfo.admin == 1 or priv.section.publish == 1 %}<input type="checkbox" value="1" name="perm[{{ filters }}cms.section.publish]"{% if post.perm[filters ~ 'cms.section.publish'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
      <td>-</td>
    </tr>
  </tbody>
</table>
