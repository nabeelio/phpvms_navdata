<?php

error_reporting(E_ALL & ~E_NOTICE);

$db = mysql_connect('localhost', 'navdata', 'navdata');
mysql_select_db('navdata');

define('NAV_NDB', 2);
define('NAV_VOR', 3);
define('NAV_DME', 4);
define('NAV_FIX', 5);