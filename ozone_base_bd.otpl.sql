-- oz_db_name
-- oz_main_site_api_key
-- oz_main_site_url
-- oz_main_site_desc

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `<% $.oz_db_name %>`
--
CREATE DATABASE IF NOT EXISTS `<% $.oz_db_name %>` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `<% $.oz_db_name %>`;

-- --------------------------------------------------------

--
-- Table structure for table `oz_clients`
--

DROP TABLE IF EXISTS `oz_clients`;
CREATE TABLE IF NOT EXISTS `oz_clients` (
  `client_clid` varchar(35) NOT NULL,
  `client_userid` bigint(20) DEFAULT NULL,
  `client_url` varchar(255) NOT NULL,
  `client_valid` tinyint(1) NOT NULL,
  `client_pkey` text,
  `client_about` text,
  `client_geoloc` text,
  PRIMARY KEY (`client_clid`),
  UNIQUE KEY `client_clid` (`client_clid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `oz_clients`
--

INSERT INTO `oz_clients` (`client_clid`, `client_userid`, `client_url`, `client_valid`, `client_pkey`, `client_about`, `client_geoloc`) VALUES
('<% $.oz_main_site_api_key %>', NULL, '<% $.oz_main_site_url %>', 1, NULL, '<% $.oz_main_site_desc %>', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `oz_clients_users`
--

DROP TABLE IF EXISTS `oz_clients_users`;
CREATE TABLE IF NOT EXISTS `oz_clients_users` (
  `client_clid` char(35) NOT NULL,
  `client_userid` bigint(20) NOT NULL,
  `client_sid` char(32) NOT NULL,
  `client_token` char(32) NOT NULL,
  `client_last_check` bigint(20) NOT NULL,
  PRIMARY KEY (`client_clid`,`client_userid`),
  UNIQUE KEY `client_apikey` (`client_clid`,`client_userid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `oz_sessions`
--

DROP TABLE IF EXISTS `oz_sessions`;
CREATE TABLE IF NOT EXISTS `oz_sessions` (
  `sess_sid` varchar(32) NOT NULL,
  `sess_data` text,
  `sess_expire` bigint(20) NOT NULL,
  PRIMARY KEY (`sess_sid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

DROP TABLE IF EXISTS `oz_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oz_users` (
  `user_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_phone` varchar(30),
  `user_email` varchar(255),
  `user_pass` varchar(255) NOT NULL,
  `user_name` varchar(60) NOT NULL,
  `user_sexe` char(1) NOT NULL,
  `user_bdate` varchar(10) NOT NULL COMMENT 'for jj-mm-yyyy',
  `user_regdate` bigint(20) unsigned NOT NULL,
  `user_picid` varchar(50) NOT NULL DEFAULT '0_0',
  `user_cc2` char(2) NOT NULL,
  `user_valid` tinyint(1) NOT NULL,
  PRIMARY KEY (`user_id`)
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oz_countries`
--

LOCK TABLES `oz_countries` WRITE;
/*!40000 ALTER TABLE `oz_countries` DISABLE KEYS */;
INSERT INTO `oz_countries` VALUES (1,'BJ','+229','Benin','Bénin',1),(2,'TG','+228','Togo','Togo',0);
/*!40000 ALTER TABLE `oz_countries` ENABLE KEYS */;
UNLOCK TABLES;

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

-- --------------------------------------------------------

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
