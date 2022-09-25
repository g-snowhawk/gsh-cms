SELECT children.id, children.title, NULL AS parent, NULL AS status, NULL AS filepath,
       NULL AS release_date, NULL AS close_date,
       children.create_date, children.modify_date, children.author_date,
       'category' AS kind
  FROM (SELECT * FROM table::category WHERE id = :category_id) parent
       LEFT OUTER JOIN (SELECT c1.*
                          FROM (SELECT * FROM table::category WHERE trash = '0' AND sitekey = :site_id AND reserved = '0') c1
                               LEFT JOIN (SELECT priv,filter2 FROM table::permission WHERE userkey = :user_id AND application = 'cms' AND class = 'category' AND type = 'read') p1
                                      ON c1.id = p1.filter2
                                   WHERE p1.priv = '1' OR p1.priv IS NULL) children
                    ON children.lft > parent.lft
                   AND children.lft < parent.rgt
 WHERE NOT EXISTS (SELECT *
                     FROM (SELECT c2.*
                             FROM (SELECT * FROM table::category WHERE trash = '0' AND sitekey = :site_id) c2
                                  LEFT JOIN (SELECT priv,filter2 FROM table::permission WHERE userkey = :user_id AND application = 'cms' AND class = 'category' AND type = 'read') p2
                                         ON c2.id = p2.filter2
                                      WHERE p2.priv = '1' OR p2.priv IS NULL) midparent
                    WHERE midparent.lft BETWEEN parent.lft AND parent.rgt
                      AND children.lft BETWEEN midparent.lft AND midparent.rgt
                      AND midparent.id NOT IN (children.id, parent.id)
                  )
   AND children.id IS NOT NULL
 UNION
SELECT id, title, category AS parent, status, filepath, release_date, close_date,
       create_date, modify_date, author_date, 'entry' AS kind
  FROM table::entry
 WHERE trash = '0'
   AND sitekey = :site_id
   AND category = :category_id
   AND revision = :revision
 ORDER BY kind{{ sort_option }}
