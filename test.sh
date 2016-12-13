#! /bin/bash
cat test.txt | while read LINE
do
/usr/bin/php gen_vhost.php $LINE

done
