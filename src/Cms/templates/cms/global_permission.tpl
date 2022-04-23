<tr>
  <th>ファイル管理</th>
  <td>-</td>
  {% if apps.userinfo.admin == 1 or priv.user.grant == 1 %}
    <td>{% if apps.userinfo.admin == 1 or priv.file.create == 1 %}<input type="checkbox" value="1" name="perm[cms.file.create]"{% if post.perm['cms.file.create'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
    <td>{% if apps.userinfo.admin == 1 or priv.file.read   == 1 %}<input type="checkbox" value="1" name="perm[cms.file.read]"  {% if post.perm['cms.file.read']   == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
    <td>{% if apps.userinfo.admin == 1 or priv.file.update == 1 %}<input type="checkbox" value="1" name="perm[cms.file.update]"{% if post.perm['cms.file.update'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
    <td>{% if apps.userinfo.admin == 1 or priv.file.delete == 1 %}<input type="checkbox" value="1" name="perm[cms.file.delete]"{% if post.perm['cms.file.delete'] == 1 %} checked{% endif %}>{% else %}-{% endif %}</td>
  {% else %}
    <td>-</td>
    <td>-</td>
    <td>-</td>
    <td>-</td>
  {% endif %}
  <td>{% if apps.userinfo.admin == 1 %}<label><input type="checkbox" value="1" name="perm[cms.file.noroot]"{% if post.perm['cms.file.noroot'] == 1 %} checked{% endif %}><small>最上位拒否</small></label>{% else %}-{% endif %}</td>
</tr>
