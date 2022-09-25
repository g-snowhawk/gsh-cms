SELECT c.id, c.title, 'category' AS kind
  FROM (SELECT * FROM table::category WHERE trash = '1' AND sitekey = :site_id) c
  LEFT JOIN (SELECT priv,filter2 FROM table::permission WHERE userkey = :user_id AND application = 'cms' AND class = 'category' AND `type` = 'write') p
    ON c.id = p.filter2
 UNION
SELECT id, title, 'entry' AS kind
  FROM table::entry
 WHERE trash = '1'
   AND sitekey = :site_id
   AND revision = :revision
 ORDER BY kind, title
