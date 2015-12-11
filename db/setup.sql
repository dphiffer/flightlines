# ************************************************************
# Sequel Pro SQL dump
# Version 4499
#
# http://www.sequelpro.com/
# https://github.com/sequelpro/sequelpro
#
# Host: localhost (MySQL 5.6.27)
# Database: flight_lines
# Generation Time: 2015-12-11 05:51:15 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table image
# ------------------------------------------------------------

DROP TABLE IF EXISTS `image`;

CREATE TABLE `image` (
  `image_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `viewer_id` int(11) DEFAULT NULL,
  `video_id` varchar(255) DEFAULT NULL,
  `location_id` varchar(255) DEFAULT NULL,
  `video_date` varchar(8) DEFAULT NULL,
  `video_num` varchar(8) DEFAULT NULL,
  `image_time` int(11) DEFAULT NULL,
  `image_delta` int(11) DEFAULT NULL,
  `image_color` varchar(7) DEFAULT NULL,
  `image_type` varchar(255) DEFAULT 'render',
  `image_created` datetime DEFAULT NULL,
  PRIMARY KEY (`image_id`),
  KEY `viewer_index` (`viewer_id`,`image_type`),
  KEY `video_time_index` (`video_id`,`image_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table location
# ------------------------------------------------------------

DROP TABLE IF EXISTS `location`;

CREATE TABLE `location` (
  `location_id` varchar(255) NOT NULL DEFAULT '',
  `location_title` varchar(255) DEFAULT '',
  `location_lat` float DEFAULT NULL,
  `location_lng` float DEFAULT NULL,
  PRIMARY KEY (`location_id`),
  KEY `lat_lng_index` (`location_lat`,`location_lng`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

LOCK TABLES `location` WRITE;
/*!40000 ALTER TABLE `location` DISABLE KEYS */;

INSERT INTO `location` (`location_id`, `location_title`, `location_lat`, `location_lng`)
VALUES
	('1381-myrtle','Myrtle Ave / Bushwick / NYC',40.6987,-73.9231),
	('flux-factory','Flux Factory / LIC / Queens',40.7526,-73.9372),
	('jcal','JCAL / Jamaica / Queens',40.7039,-73.7982);

/*!40000 ALTER TABLE `location` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table session
# ------------------------------------------------------------

DROP TABLE IF EXISTS `session`;

CREATE TABLE `session` (
  `session_id` varchar(32) NOT NULL DEFAULT '',
  `session_time` int(11) DEFAULT NULL,
  `session_data` text,
  PRIMARY KEY (`session_id`),
  KEY `time_index` (`session_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table video
# ------------------------------------------------------------

DROP TABLE IF EXISTS `video`;

CREATE TABLE `video` (
  `video_id` varchar(255) NOT NULL DEFAULT '',
  `location_id` varchar(255) DEFAULT '',
  `video_date` varchar(8) DEFAULT NULL,
  `video_num` varchar(8) DEFAULT NULL,
  `video_created` datetime DEFAULT NULL,
  `video_status` varchar(255) DEFAULT 'pending',
  PRIMARY KEY (`video_id`),
  KEY `location_date_num_index` (`location_id`,`video_date`,`video_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table viewer
# ------------------------------------------------------------

DROP TABLE IF EXISTS `viewer`;

CREATE TABLE `viewer` (
  `viewer_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `viewer_ip` varchar(255) DEFAULT NULL,
  `viewer_ua` varchar(255) DEFAULT NULL,
  `viewer_created` datetime DEFAULT NULL,
  `viewer_updated` datetime DEFAULT NULL,
  `viewer_status` varchar(255) DEFAULT 'active',
  PRIMARY KEY (`viewer_id`),
  KEY `ip_index` (`viewer_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
