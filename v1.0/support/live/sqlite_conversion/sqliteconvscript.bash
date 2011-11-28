#!/bin/bash
# 
# MySQL to sqlite3 script for Eduroam iPhone app
# Created by: Ashley Browning
# Date Created: 05/04/2011
# Version: 1.00



#Get the dump of the database
#mysqldump -u bash2 --password=BADgerSHO3z --compact --compatible=ansi --default-character-set=binary --extended-insert=false 3yp > dump.sql

#Execute the creation script for the sqlite tables
cat ./createdb | sqlite3 -echo eduroamtemp.sqlite > eduroamtemp.err

#PHP file to read the mysql dump and output INSERT statements tailored for sqlite
#php mysqltosqlite3.php dump dataonly
php ashmysqltosqlite3.php dump

#Load in the data output from the php file into sqlite3
cat ./dump.lsq3 | sqlite3 -echo eduroamtemp.sqlite > eduroamtemp.err

mv eduroamtemp.sqlite eduroam.sqlite
mv eduroamtemp.err eduroam.err

#Remove the previous versions
#rm eduroamtemp.sqlite
#rm eduroamtemp.err