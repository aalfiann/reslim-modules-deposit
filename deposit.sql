SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for deposit_balance
-- ----------------------------
DROP TABLE IF EXISTS `deposit_balance`;
CREATE TABLE `deposit_balance` (
  `DepositID` varchar(50) NOT NULL,
  `Balance` decimal(10,2) NOT NULL,
  PRIMARY KEY (`DepositID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ----------------------------
-- Table structure for deposit_history
-- ----------------------------
DROP TABLE IF EXISTS `deposit_history`;
CREATE TABLE `deposit_history` (
  `ReferenceID` varchar(50) NOT NULL,
  `Balance` decimal(10,2) NOT NULL,
  PRIMARY KEY (`ReferenceID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ----------------------------
-- Table structure for deposit_mutation
-- ----------------------------
DROP TABLE IF EXISTS `deposit_mutation`;
CREATE TABLE `deposit_mutation` (
  `DepositID` varchar(50) NOT NULL,
  `Created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `ReferenceID` varchar(50) NOT NULL,
  `Description` varchar(255) NOT NULL,
  `Task` varchar(2) NOT NULL,
  `Mutation` decimal(10,2) NOT NULL,
  `Created_by` varchar(50) NOT NULL,
  PRIMARY KEY (`ReferenceID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
SET FOREIGN_KEY_CHECKS=1;
