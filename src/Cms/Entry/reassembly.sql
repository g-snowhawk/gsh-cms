SELECT *
  FROM (SELECT id, title, path, filepath, template,
               (SELECT COUNT(*)
                  FROM table::entry
                 WHERE category = cat.id
                   AND revision = 0
                 GROUP BY category
               ) AS cnt
          FROM table::category cat
         WHERE sitekey = ?
           AND template IS NOT NULL
       ) sub
