SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';

CREATE SCHEMA IF NOT EXISTS `eduroam-api` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;
USE `eduroam-api` ;

-- -----------------------------------------------------
-- Table `eduroam-api`.`Site`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `eduroam-api`.`Site` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `name` VARCHAR(255) NOT NULL ,
  `active` TINYINT(1)  NOT NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `eduroam-api`.`Platforms`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `eduroam-api`.`Platforms` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `pkey` VARCHAR(64) NOT NULL ,
  `description` VARCHAR(255) NOT NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `eduroam-api`.`Users`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `eduroam-api`.`Users` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `trust` DOUBLE NOT NULL DEFAULT 0.5 ,
  `platform` INT NOT NULL ,
  `userName` VARCHAR(64) NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `userplatform` (`platform` ASC) ,
  CONSTRAINT `userplatform`
    FOREIGN KEY (`platform` )
    REFERENCES `eduroam-api`.`Platforms` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `eduroam-api`.`SubSites`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `eduroam-api`.`SubSites` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `site` INT NOT NULL ,
  `name` VARCHAR(255) NOT NULL ,
  `address` VARCHAR(255) NOT NULL ,
  `lat` DOUBLE NOT NULL ,
  `lng` DOUBLE NOT NULL ,
  `altitude` DOUBLE NOT NULL ,
  `ssid` VARCHAR(45) NULL ,
  `encryption` VARCHAR(45) NULL ,
  `accesspoints` MEDIUMINT NULL ,
  `active` TINYINT(1)  NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `site` (`site` ASC) ,
  CONSTRAINT `site`
    FOREIGN KEY (`site` )
    REFERENCES `eduroam-api`.`Site` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `eduroam-api`.`Tags`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `eduroam-api`.`Tags` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `tagtime` DATETIME NOT NULL ,
  `lat` DOUBLE NOT NULL ,
  `lng` DOUBLE NOT NULL ,
  `accuracy` DOUBLE NOT NULL ,
  `user` INT NOT NULL ,
  `subsite` INT NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `subsite` (`subsite` ASC) ,
  INDEX `user` (`user` ASC) ,
  CONSTRAINT `subsite`
    FOREIGN KEY (`subsite` )
    REFERENCES `eduroam-api`.`SubSites` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `user`
    FOREIGN KEY (`user` )
    REFERENCES `eduroam-api`.`Users` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `eduroam-api`.`APs`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `eduroam-api`.`APs` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `lat` DOUBLE NOT NULL ,
  `lng` DOUBLE NOT NULL ,
  `subsite` INT NOT NULL ,
  `rating` DOUBLE NOT NULL DEFAULT 0.5 ,
  `active` TINYINT(1)  NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `aptosubsite` (`subsite` ASC) ,
  CONSTRAINT `aptosubsite`
    FOREIGN KEY (`subsite` )
    REFERENCES `eduroam-api`.`SubSites` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `eduroam-api`.`APTags`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `eduroam-api`.`APTags` (
  `AP` INT NOT NULL ,
  `Tag` INT NOT NULL ,
  PRIMARY KEY (`AP`, `Tag`) ,
  INDEX `ap` (`AP` ASC) ,
  INDEX `tag` (`Tag` ASC) ,
  CONSTRAINT `ap`
    FOREIGN KEY (`AP` )
    REFERENCES `eduroam-api`.`APs` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `tag`
    FOREIGN KEY (`Tag` )
    REFERENCES `eduroam-api`.`Tags` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `eduroam-api`.`Info`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `eduroam-api`.`Info` (
  `id` INT NOT NULL ,
  `version` INT NOT NULL DEFAULT 1 ,
  `dateUpdated` DATETIME NOT NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `eduroam-api`.`SiteHistory`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `eduroam-api`.`SiteHistory` (
  `id` BIGINT NOT NULL AUTO_INCREMENT ,
  `site` INT NOT NULL ,
  `operation` INT NOT NULL ,
  `date` DATETIME NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `sitehistory` (`site` ASC) ,
  CONSTRAINT `sitehistory`
    FOREIGN KEY (`site` )
    REFERENCES `eduroam-api`.`Site` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `eduroam-api`.`SubSiteHistory`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `eduroam-api`.`SubSiteHistory` (
  `id` BIGINT NOT NULL AUTO_INCREMENT ,
  `subsite` INT NOT NULL ,
  `operation` INT NOT NULL ,
  `date` DATETIME NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `subsitehistory` (`subsite` ASC) ,
  CONSTRAINT `subsitehistory`
    FOREIGN KEY (`subsite` )
    REFERENCES `eduroam-api`.`SubSites` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;


-- -----------------------------------------------------
-- Table `eduroam-api`.`APHistory`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `eduroam-api`.`APHistory` (
  `id` BIGINT NOT NULL AUTO_INCREMENT ,
  `ap` INT NOT NULL ,
  `operation` INT NOT NULL ,
  `date` DATETIME NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `aphistory` (`ap` ASC) ,
  CONSTRAINT `aphistory`
    FOREIGN KEY (`ap` )
    REFERENCES `eduroam-api`.`APs` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;



SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
