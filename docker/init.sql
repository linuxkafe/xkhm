-- Xkhm Database Initialization
-- This creates the basic structure for testing

-- Create tables if they don't exist (simplified schema for testing)
CREATE TABLE IF NOT EXISTS `tblclients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `companyname` varchar(100) DEFAULT NULL,
  `credit` decimal(10,2) DEFAULT 0.00,
  `paymentids` text DEFAULT NULL,
  `status` enum('Inactive','Active','Closed','DueFax','Cancelled','Fraud') NOT NULL DEFAULT 'Active',
  `clientgroupid` int(10) unsigned DEFAULT NULL,
  `datecreated` date DEFAULT NULL,
  `lastlogin` datetime DEFAULT NULL,
  `phonenum` varchar(50) DEFAULT NULL,
  `address1` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbladmins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `language` varchar(25) DEFAULT 'english',
  `lastlogin` datetime DEFAULT NULL,
  `disabled` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblinvoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL,
  `datecreated` date NOT NULL,
  `duedate` date NOT NULL,
  `datepaid` date DEFAULT NULL,
  `status` enum('Unpaid','Paid','Cancelled','Refunded','Collections','Credit') NOT NULL DEFAULT 'Unpaid',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `taxrate` decimal(5,2) DEFAULT 0.00,
  `currency` int(10) unsigned DEFAULT 1,
  `note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblinvoiceitems` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoiceid` int(10) unsigned NOT NULL,
  `type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `taxed` tinyint(1) NOT NULL DEFAULT 0,
  `duedate` date DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `invoiceid` (`invoiceid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbldomains` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned DEFAULT NULL,
  `orderid` int(10) unsigned DEFAULT NULL,
  `productid` int(10) unsigned DEFAULT NULL,
  `domain` varchar(255) NOT NULL,
  `period` varchar(10) DEFAULT '1 Year',
  `firstpaymentamount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `recurringamount` decimal(10,2) DEFAULT 0.00,
  `registrationdate` date DEFAULT NULL,
  `expirydate` date DEFAULT NULL,
  `nextduedate` date DEFAULT NULL,
  `status` enum('Active','Suspended','Terminated','Cancelled','Fraud','Pending','TransferredAway','TransferredIn') NOT NULL DEFAULT 'Active',
  `dateadded` datetime DEFAULT current_timestamp(),
  `dns1` varchar(100) DEFAULT NULL,
  `dns2` varchar(100) DEFAULT NULL,
  `registrar` varchar(100) DEFAULT NULL,
  `datefirstactive` date DEFAULT NULL,
  `datepaid` date DEFAULT NULL,
  `nextinvoicedate` date DEFAULT NULL,
  `initial期` int(11) DEFAULT NULL,
  `idnumber` varchar(50) DEFAULT NULL,
  `subsequent期` int(11) DEFAULT NULL,
  `auto Renew` tinyint(1) DEFAULT 1,
  `currency` int(10) unsigned DEFAULT 1,
  `seonofollow` tinyint(1) DEFAULT 1,
  `seotitle` varchar(255) DEFAULT NULL,
  `pausenotes` text DEFAULT NULL,
  `lasttransferdate` date DEFAULT NULL,
  `ownership_last_renewed_year` int(11) DEFAULT NULL,
  `dns_management` tinyint(1) DEFAULT NULL,
  `email_forwarding` tinyint(1) DEFAULT NULL,
  `whois_privacy` tinyint(1) DEFAULT NULL,
  `whois_privacy_last_renewed_year` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain` (`domain`),
  KEY `userid` (`userid`),
  KEY `orderid` (`orderid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbldomainpricing` (
  `extension` varchar(50) NOT NULL,
  `qsetupprice` decimal(10,2) DEFAULT NULL,
  `msetupprice` decimal(10,2) DEFAULT NULL,
  `ssetupprice` decimal(10,2) DEFAULT NULL,
  `asetupprice` decimal(10,2) DEFAULT NULL,
  `bsetupprice` decimal(10,2) DEFAULT NULL,
  `6msetupprice` decimal(10,2) DEFAULT NULL,
  `renprice` decimal(10,2) DEFAULT NULL,
  `transferprice` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`extension`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblorders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL,
  `date` date NOT NULL,
  `paymentmethod` varchar(50) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `status` enum('New','Pending','Active','Completed','Cancelled','Fraud','Chargeback') NOT NULL DEFAULT 'New',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `currency` int(10) unsigned DEFAULT 1,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblaffiliates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `refcode` varchar(50) NOT NULL,
  `commission` decimal(5,2) DEFAULT 10.00,
  `balance` decimal(10,2) DEFAULT 0.00,
  `unpaidbalance` decimal(10,2) DEFAULT 0.00,
  `datecreated` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `refcode` (`refcode`),
  UNIQUE KEY `userid` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblaffiliatesclients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `affiliateid` int(10) unsigned NOT NULL,
  `clientid` int(10) unsigned NOT NULL,
  `date` date DEFAULT NULL,
  `commission` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `affiliateid_clientid` (`affiliateid`, `clientid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbltickets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userid` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('Open','Closed','Answered','In Progress') NOT NULL DEFAULT 'Open',
  `priority` enum('Low','Medium','High','Urgent') DEFAULT 'Medium',
  `deptid` int(10) unsigned DEFAULT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `lastreply` datetime DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblticketreplies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tid` int(10) unsigned NOT NULL,
  `userid` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `adminreply` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `tid` (`tid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblannouncements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `published` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `published` (`published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblknowledgebasecats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sortorder` int(10) unsigned DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblknowledgebase` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `catid` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `published` tinyint(1) DEFAULT 1,
  `counter` int(10) unsigned DEFAULT 0,
  `lastupdated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `catid` (`catid`),
  KEY `published` (`published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data
INSERT INTO `tblclients` (`firstname`, `lastname`, `email`, `password`, `status`, `datecreated`) VALUES
('Test', 'User', 'test@example.com', '5f4dcc3b5aa765d61d8327deb882cf99', 'Active', CURDATE());

INSERT INTO `tbladmins` (`username`, `password`, `fullname`, `email`) VALUES
('admin', '0192023a7bbd73250516f069df18b500', 'Administrator', 'admin@linuxkafe.com');

-- Sample client (password: "password", md5 hash)
-- Client login: test@example.com / password

INSERT INTO `tbldomainpricing` (`extension`, `msetupprice`, `renprice`) VALUES
('.com', 9.99, 14.99),
('.net', 11.99, 15.99),
('.org', 9.99, 13.99),
('.com.br', 24.99, 34.99),
('.net.br', 24.99, 34.99);

INSERT INTO `tblannouncements` (`title`, `message`, `published`, `date`) VALUES
('Welcome to LINUXKAFE', 'Thank you for choosing our services. We are committed to providing excellent support.', 1, NOW()),
('Server Maintenance', 'Scheduled maintenance on weekends from 2am to 6am BRT.', 1, NOW());

INSERT INTO `tblknowledgebasecats` (`title`, `description`, `sortorder`) VALUES
('Getting Started', 'Learn the basics', 1),
('Billing', 'Payment and invoice questions', 2),
('Technical', 'Technical support articles', 3);

INSERT INTO `tblknowledgebase` (`catid`, `title`, `description`, `content`, `published`) VALUES
(1, 'How to get started', 'Basic getting started guide', 'Welcome to our service! Here is how you can get started...', 1),
(2, 'Understanding invoices', 'Invoice billing questions', 'Invoices are generated automatically for all services...', 1);
