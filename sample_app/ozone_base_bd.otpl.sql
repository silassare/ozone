/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `oz_administrators`
--

DROP TABLE IF EXISTS `oz_administrators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oz_administrators` (
  `user_id` bigint(20) unsigned NOT NULL,
  `admin_regdate` bigint(20) unsigned NOT NULL,
  `admin_valid` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oz_authenticator`
--

DROP TABLE IF EXISTS `oz_authenticator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oz_authenticator` (
  `auth_label` varchar(60) NOT NULL,
  `auth_for` varchar(255) NOT NULL,
  `auth_code` varchar(32) NOT NULL,
  `auth_token` char(32) NOT NULL,
  `auth_try_max` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `auth_try_count` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `auth_expire` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`auth_label`,`auth_for`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oz_authenticator`
--

LOCK TABLES `oz_authenticator` WRITE;
/*!40000 ALTER TABLE `oz_authenticator` DISABLE KEYS */;
/*!40000 ALTER TABLE `oz_authenticator` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oz_clients`
--

DROP TABLE IF EXISTS `oz_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oz_clients` (
  `client_clid` varchar(35) NOT NULL,
  `client_userid` bigint(20) DEFAULT NULL,
  `client_url` varchar(255) NOT NULL,
  `client_valid` tinyint(1) NOT NULL,
  `client_pkey` text,
  `client_about` text,
  `client_geoloc` text,
  PRIMARY KEY (`client_clid`),
  UNIQUE KEY `client_clid` (`client_clid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oz_clients`
--

LOCK TABLES `oz_clients` WRITE;
/*!40000 ALTER TABLE `oz_clients` DISABLE KEYS */;
INSERT INTO `oz_clients` VALUES ('678928D0-95OIF6BF-067FGB58-F5T2EH42',NULL,'http://web.khamelia.com',1,NULL,'khamelia web app.',NULL);
/*!40000 ALTER TABLE `oz_clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oz_clients_users`
--

DROP TABLE IF EXISTS `oz_clients_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oz_clients_users` (
  `client_clid` char(35) NOT NULL,
  `client_userid` bigint(20) NOT NULL,
  `client_sid` char(32) NOT NULL,
  `client_token` char(32) NOT NULL,
  `client_last_check` bigint(20) NOT NULL,
  PRIMARY KEY (`client_clid`,`client_userid`),
  UNIQUE KEY `client_apikey` (`client_clid`,`client_userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oz_countries`
--

DROP TABLE IF EXISTS `oz_countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oz_countries` (
  `country_id` int(11) NOT NULL AUTO_INCREMENT,
  `country_cc2` char(2) NOT NULL,
  `country_code` varchar(6) NOT NULL,
  `country_name` varchar(60) NOT NULL,
  `country_name_fr` varchar(60) NOT NULL,
  `country_ok` tinyint(1) NOT NULL,
  PRIMARY KEY (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oz_files`
--

DROP TABLE IF EXISTS `oz_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oz_files` (
  `file_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'id de celui qui a envoyer le fichier',
  `file_key` char(32) NOT NULL COMMENT 'clef du fichier',
  `file_clone` bigint(20) DEFAULT '0' COMMENT 'id du fichier original dont ceci est une copie',
  `file_size` double NOT NULL,
  `file_type` varchar(60) DEFAULT NULL COMMENT 'type mime du fichier',
  `file_name` varchar(100) DEFAULT NULL COMMENT 'nom du fichier sur le poste client',
  `file_label` text COMMENT 'un petit text descriptif du fichier',
  `file_path` varchar(255) NOT NULL COMMENT 'lien vers le fichier original',
  `file_thumb` varchar(255) DEFAULT NULL COMMENT 'lien vers un thumbnails si possible(image, video...)',
  `file_upload_time` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`file_id`),
  KEY `oz_files_ibfk_2` (`user_id`),
  CONSTRAINT `oz_files_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `oz_users` (`user_id`) ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oz_sessions`
--

DROP TABLE IF EXISTS `oz_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oz_sessions` (
  `sess_sid` varchar(32) NOT NULL,
  `sess_data` text,
  `sess_expire` bigint(20) NOT NULL,
  PRIMARY KEY (`sess_sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `oz_users`
--

DROP TABLE IF EXISTS `oz_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oz_users` (
  `user_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_phone` varchar(30) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `user_pass` varchar(255) NOT NULL,
  `user_name` varchar(60) NOT NULL,
  `user_sex` char(1) NOT NULL,
  `user_bdate` varchar(10) NOT NULL COMMENT 'for jj-mm-yyyy',
  `user_regdate` bigint(20) unsigned NOT NULL,
  `user_picid` varchar(50) NOT NULL DEFAULT '0_0',
  `user_cc2` char(2) NOT NULL,
  `user_valid` tinyint(1) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;