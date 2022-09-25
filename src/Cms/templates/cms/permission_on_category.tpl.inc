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
    {% set filters = session.current_site ~ "." ~ current_category ~ "." %}
    <tr>
      <th>カテゴリ</th>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.category.create == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.category.create]" {% if post.perm[filters ~ 'cms.category.create']  == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.category.create]" {% if post.perm[filters ~ 'cms.category.create']  == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.category.read   == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.category.read]"   {% if post.perm[filters ~ 'cms.category.read']    == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.category.read]"   {% if post.perm[filters ~ 'cms.category.read']    == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.category.update == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.category.update]" {% if post.perm[filters ~ 'cms.category.update']  == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.category.update]" {% if post.perm[filters ~ 'cms.category.update']  == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.category.delete == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.category.delete]" {% if post.perm[filters ~ 'cms.category.delete']  == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.category.delete]" {% if post.perm[filters ~ 'cms.category.delete']  == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td>-</td>
      <td>{% if apps.userinfo.admin == 1 or priv.category.upfate == 1 %}<label><input type="checkbox" value="1" name="perm[{{ filters }}cms.category.disinherit]"{% if post.perm[filters ~ 'cms.category.disinherit'] == 1 %} checked{% endif %}><small>継承しない</small></label>{% else %}-{% endif %}</td>
    </tr>
    <tr>
      <th>エントリ</th>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.entry.create  == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.entry.create]" {% if post.perm[filters ~ 'cms.entry.create']  == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.entry.create]" {% if post.perm[filters ~ 'cms.entry.create']  == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.entry.read    == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.entry.read]"   {% if post.perm[filters ~ 'cms.entry.read']    == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.entry.read]"   {% if post.perm[filters ~ 'cms.entry.read']    == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.entry.update  == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.entry.update]" {% if post.perm[filters ~ 'cms.entry.update']  == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.entry.update]" {% if post.perm[filters ~ 'cms.entry.update']  == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.entry.delete  == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.entry.delete]" {% if post.perm[filters ~ 'cms.entry.delete']  == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.entry.delete]" {% if post.perm[filters ~ 'cms.entry.delete']  == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.entry.publish == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.entry.publish]"{% if post.perm[filters ~ 'cms.entry.publish'] == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.entry.publish]"{% if post.perm[filters ~ 'cms.entry.publish'] == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td>-</td>
    </tr>
    <tr>
      <th>セクション</th>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.section.create  == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.section.create]" {% if post.perm[filters ~ 'cms.section.create']  == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.section.create]" {% if post.perm[filters ~ 'cms.section.create']  == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.section.read    == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.section.read]"   {% if post.perm[filters ~ 'cms.section.read']    == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.section.read]"   {% if post.perm[filters ~ 'cms.section.read']    == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.section.update  == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.section.update]" {% if post.perm[filters ~ 'cms.section.update']  == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.section.update]" {% if post.perm[filters ~ 'cms.section.update']  == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.section.delete  == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.section.delete]" {% if post.perm[filters ~ 'cms.section.delete']  == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.section.delete]" {% if post.perm[filters ~ 'cms.section.delete']  == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td class="toggle">{% if apps.userinfo.admin == 1 or priv.section.publish == 1 %}<label><input type="radio" value="1" name="perm[{{ filters }}cms.section.publish]"{% if post.perm[filters ~ 'cms.section.publish'] == 1 %} checked{% endif %}><span>可</span></label>&nbsp;<label><input type="radio" value="0" name="perm[{{ filters }}cms.section.publish]"{% if post.perm[filters ~ 'cms.section.publish'] == "0" %} checked{% endif %}><span>否</span></label>{% else %}-{% endif %}</td>
      <td>-</td>
    </tr>
  </tbody>
</table>
