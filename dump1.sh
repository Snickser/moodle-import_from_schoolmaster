#!/bin/bash

mysql shravanam -e "SELECT t.name fullname,t.name shortname,t.cat_id category
FROM dxg_training t WHERE t.status=1 limit 100;" > /tmp/training.csv

#LEFT JOIN dxg_training_cats c ON t.cat_id = c.cat_id limit 2;" > /tmp/training.csv

php -e decoder-html.php

rm /tmp/training.csv

#echo "fullname,shortname,category,summary,visible" > training2.csv
#cat training1.csv >> training2.csv
