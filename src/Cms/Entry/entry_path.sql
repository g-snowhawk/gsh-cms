SELECT filepath, category,
       (SELECT openpath FROM table::site WHERE id = e.sitekey) AS openpath,
       (SELECT path FROM table::category WHERE id = e.category) AS categorypath
  FROM table::entry AS e
 WHERE id = ?
