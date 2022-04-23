<!DOCTYPE html>
<html lang="ja" class="announce">
  <meta charset="UTF-8">
  <title>Under Construction</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
  <meta name="format-detection" content="telephone=no">
  <link rel="stylesheet" href="/common/style/cms/default.css">
  {% set styles = apps.styleSheets() %}
  {% for style in styles %}
    <link rel="stylesheet" href="{{ site.path }}{{ style }}">
  {% endfor %}
  <div id="container">
    <div id="announce">{{ site.announce|raw }}</div>
  </div>
