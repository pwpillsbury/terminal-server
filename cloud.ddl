
--If the database already exists but you want to create it
--again, use the following lines. Just remove the -- at
--the beginning of the following 2 lines.

--DROP DATABASE IF EXISTS TermRouter;
--CREATE DATABASE TermServer;

USE TermServer;


DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `user` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `serverid` varchar(50) NOT NULL,
  `termid` varchar(50) NOT NULL,
  `data` varchar(64000) DEFAULT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `user` (`user`),
  KEY `termid` (`termid`),
  KEY `serverid` (`serverid`),
  KEY `password` (`password`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `proj_data`;
CREATE TABLE IF NOT EXISTS `proj_data` (
  `autoinc` bigint(20) NOT NULL AUTO_INCREMENT,
  `user` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `serverid` varchar(50) NOT NULL,
  `termid` varchar(24) NOT NULL,
  `progpointer` int(11) NOT NULL,
  `data` varchar(1024) NOT NULL,
  PRIMARY KEY (`autoinc`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=33 ;


DROP TABLE IF EXISTS `proj_trnsx`;
CREATE TABLE IF NOT EXISTS `proj_trnsx` (
  `autoinc` bigint(20) NOT NULL AUTO_INCREMENT,
  `user` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `serverid` varchar(50) NOT NULL,
  `termid` varchar(24) NOT NULL,
  `progpointer` int(11) NOT NULL,
  `data` varchar(1024) NOT NULL,
  PRIMARY KEY (`autoinc`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=33 ;


DROP TABLE IF EXISTS `proj_terminals`;
CREATE TABLE IF NOT EXISTS `proj_terminals` (
  `termid` varchar(24) NOT NULL,
  `progpointer` int(11) NOT NULL,
  PRIMARY KEY (`termid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


DROP TABLE IF EXISTS `proj_program`;
CREATE TABLE IF NOT EXISTS `proj_program` (
  `progpointer` int(11) NOT NULL,
  `on_yes` int(11) NOT NULL,
  `on_no` int(11) NOT NULL,
  `cmd` varchar(255) NOT NULL,
  PRIMARY KEY (`progpointer`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
--
-- Dumping data for table `proj_program`
--
INSERT INTO `proj_program` (`progpointer`, `on_yes`, `on_no`, `cmd`) VALUES
(0, 1, 0, 'signin'),
(1, 2, 0, '@C0@1,1,0,Scan Item:@2,1,1,'),
(2, 1, 1, '@C0@1,1,1,Enter Quantity: ');
