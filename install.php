<?php
/*
 
 The Cloud Server is composed of three parts:
---------------------------------------------
 
  1. The "cloud.php" script which is the web page that the 7802 Terminals connect with.
  2. "index.html" which is the web page that human users can access collected data with.
  3. A set of three tables ("proj_terminals", "proj_program", "proj_data") stored in the MySQL database of your choice.
 
 Refer to the User's Manual for more details on the content and function of these scripts and tables.
 
The Cloud Server is installed by:
---------------------------------
 
      -Copy the included PHP and INC files to a folder on your web hosting server (use your usual 
      FTP client for this). These can be copied to the main folder of your website, or to a subfolder.
      
	  -Create an empty database or identify a previously existing one to contain the required tables.
	  
      -Edit the "varstermrouter.inc" file. 
      Notice that "install.php" and "cloud.php" use the file "varstermrouter.inc". 
      This file needs to be edited to contain the correct connection and password data for your database.
      You may want to put it in a restricted access folder as a security measure (don't forget to change 
      the "includes" in the other files if you do).  
      
      -Build the necessary MySQL database tables. 
      Either run this installer by pointing your web browser to
      http://www.myWebsite/myConnectionHostFolder/install.php
      or use the included DDL (database definition log) file to build the tables manually using the 
      software tools provided by your web hosting provider.
  */

  
  
/*
 * This file needs to be edited and variables need to be set for access to your MySQL database server
 */
include("varstermrouter.inc");




/*
 Some hosting services will not allow us to drop (delete) or create databases here and you will
 have to remove these lines from the script and create the database manually using your hosting
 service tools (cpanel, etc).

 If the database does not already exist and you would like the installer to try to create it,
 use the following lines. Just remove the // at
 the beginning of the following 2 lines.
 */

//echo "DROP DATABASE IF EXISTS $database;";
//echo "CREATE DATABASE $database;";


$link = mysql_connect($host, $user, $password);
mysql_select_db($database);

//
// This removes the old tables if they exist
//
// Some hosting services will not allow us to drop (delete) tables here and you will have to remove
// these lines from the script and drop the tables manually using your hosting service tools (cpanel, etc).
$query = "DROP TABLE IF EXISTS `proj_terminals`;";
$result_clienttable = mysql_query($query) or die ("ERROR:Could not execute delete proj_terminals");
$query = "DROP TABLE IF EXISTS `proj_program`;";
$result_clienttable = mysql_query($query) or die ("ERROR:Could not execute delete proj_program");
$query = "DROP TABLE IF EXISTS `proj_trnsx`;";
$result_clienttable = mysql_query($query) or die ("ERROR:Could not execute delete proj_trnsx");
$query = "DROP TABLE IF EXISTS `proj_data`;";
$result_clienttable = mysql_query($query) or die ("ERROR:Could not execute delete proj_data");
$query = "DROP TABLE IF EXISTS `messages`;";
$result_clienttable = mysql_query($query) or die ("ERROR:Could not execute delete Messages");


// This creates the tables in the database
$query = "CREATE TABLE IF NOT EXISTS `proj_data` ("
."  `autoinc` bigint(20) NOT NULL AUTO_INCREMENT,"
."  `user` varchar(50) NOT NULL,"
."  `password` varchar(255) NOT NULL,"
."  `serverid` varchar(50) NOT NULL,"
."  `termid` varchar(24) NOT NULL,"
."  `progpointer` int(11) NOT NULL,"
."  `data` varchar(1024) NOT NULL,"
."  PRIMARY KEY (`autoinc`)"
.") ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=33 ;";
$result_clienttable = mysql_query($query) or die ("ERROR:Could not execute create proj_data");


$query = "CREATE TABLE IF NOT EXISTS `proj_trnsx` ("
."  `autoinc` bigint(20) NOT NULL AUTO_INCREMENT,"
."  `user` varchar(50) NOT NULL,"
."  `password` varchar(255) NOT NULL,"
."  `serverid` varchar(50) NOT NULL,"
."  `termid` varchar(24) NOT NULL,"
."  `progpointer` int(11) NOT NULL,"
."  `data` varchar(1024) NOT NULL,"
."  PRIMARY KEY (`autoinc`)"
.") ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=33 ;";
$result_clienttable = mysql_query($query) or die ("ERROR:Could not execute create proj_trnsx");


$query = "CREATE TABLE IF NOT EXISTS `proj_terminals` ("
."  `termid` varchar(24) NOT NULL,"
."  `progpointer` int(11) NOT NULL,"
."  PRIMARY KEY (`termid`)"
.") ENGINE=MyISAM DEFAULT CHARSET=latin1;";
$result_clienttable = mysql_query($query) or die ("ERROR:Could not execute create proj_terminals");


$query = "CREATE TABLE IF NOT EXISTS `proj_program` ("
."  `progpointer` int(11) NOT NULL,"
."  `on_yes` int(11) NOT NULL,"
."  `on_no` int(11) NOT NULL,"
."  `cmd` varchar(255) NOT NULL,"
."  PRIMARY KEY (`progpointer`)"
.") ENGINE=MyISAM DEFAULT CHARSET=latin1;";
$result_clienttable = mysql_query($query) or die ("ERROR:Could not execute create proj_program");


$query = "INSERT INTO `proj_program` (`progpointer`, `on_yes`, `on_no`, `cmd`) VALUES"
."(0, 1, 0, 'signin'),"
."(1, 2, 0, '@C0@1,1,0,Scan Item:@2,1,1,'),"
."(2, 1, 1, '@C0@1,1,1,Enter Quantity: ');";
$result_clienttable = mysql_query($query) or die ("ERROR:Could not execute add program data to create proj_program");


$query = "CREATE TABLE IF NOT EXISTS `messages` ("
."  `user` varchar(50) NOT NULL,"
."  `password` varchar(255) NOT NULL,"
."  `serverid` varchar(50) NOT NULL,"
."  `termid` varchar(50) NOT NULL,"
."  `data` varchar(64000) DEFAULT NULL,"
."  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,"
."  KEY `user` (`user`),"
."  KEY `termid` (`termid`),"
."  KEY `serverid` (`serverid`),"
."  KEY `password` (`password`)"
.") ENGINE=InnoDB DEFAULT CHARSET=latin1;";
$result_clienttable = mysql_query($query) or die ("ERROR:Could not execute create Messages");


echo "Installation successful!";