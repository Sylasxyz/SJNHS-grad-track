-- Railway import from XAMPP export (graduate_tracking.sql)
-- HeidiSQL: select Railway database (MYSQLDATABASE), then File -> Run SQL file.

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS user_messages;
DROP TABLE IF EXISTS announcement_recipients;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS graduate_profiles;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS user_accounts;
DROP TABLE IF EXISTS User_Messages;
DROP TABLE IF EXISTS Announcement_Recipients;
DROP TABLE IF EXISTS Announcements;
DROP TABLE IF EXISTS Graduate_Profiles;
DROP TABLE IF EXISTS Password_Resets;
DROP TABLE IF EXISTS Login_Attempts;
DROP TABLE IF EXISTS Reports;
DROP TABLE IF EXISTS User_Accounts;
SET FOREIGN_KEY_CHECKS = 1;
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 10, 2026 at 11:04 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `graduate_tracking`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `Announcement_ID` int(10) UNSIGNED NOT NULL,
  `Author_ID` int(10) UNSIGNED DEFAULT NULL,
  `Title` varchar(150) NOT NULL,
  `Message` text NOT NULL,
  `Image_Path` varchar(255) DEFAULT NULL,
  `Audience` enum('Everyone','Specific') NOT NULL DEFAULT 'Everyone',
  `Date_Posted` timestamp NOT NULL DEFAULT current_timestamp(),
  `Is_Archived` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`Announcement_ID`, `Author_ID`, `Title`, `Message`, `Image_Path`, `Audience`, `Date_Posted`, `Is_Archived`) VALUES
(1, 3, 'TEST', 'ASFafafa', NULL, 'Specific', '2026-08-14 01:02:12', 0),
(2, 3, 'SPECIFIC', 'asdawa', NULL, 'Specific', '2026-08-23 03:18:05', 0),
(3, 3, 'AAAA', 'ASFAFAFA', NULL, 'Specific', '2026-08-23 03:21:15', 0),
(4, 3, 'AFSAFAF', 'SFAFASFAFAF', NULL, 'Specific', '2026-08-23 14:04:34', 0);

-- --------------------------------------------------------

--
-- Table structure for table `announcement_recipients`
--

CREATE TABLE `announcement_recipients` (
  `Announcement_ID` int(10) UNSIGNED NOT NULL,
  `User_ID` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcement_recipients`
--

INSERT INTO `announcement_recipients` (`Announcement_ID`, `User_ID`) VALUES
(1, 2),
(2, 2),
(3, 2);

-- --------------------------------------------------------

--
-- Table structure for table `graduate_profiles`
--

CREATE TABLE `graduate_profiles` (
  `Profile_ID` int(10) UNSIGNED NOT NULL,
  `User_ID` int(10) UNSIGNED NOT NULL,
  `Full_Name` varchar(150) NOT NULL,
  `Course` varchar(120) DEFAULT NULL,
  `Batch_Year` int(11) NOT NULL,
  `Employment_Status` enum('Employed','Unemployed','Self-Employed','Further Studies') NOT NULL DEFAULT 'Unemployed',
  `Company_School_Name` varchar(200) DEFAULT NULL,
  `Further_Studies_Program` varchar(200) DEFAULT NULL,
  `Contact_Info` varchar(200) DEFAULT NULL,
  `Profile_Picture` varchar(255) DEFAULT NULL,
  `Is_Verified` tinyint(1) NOT NULL DEFAULT 0,
  `Verified_By` int(10) UNSIGNED DEFAULT NULL,
  `Verified_At` timestamp NULL DEFAULT NULL,
  `Last_Updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `graduate_profiles`
--

INSERT INTO `graduate_profiles` (`Profile_ID`, `User_ID`, `Full_Name`, `Course`, `Batch_Year`, `Employment_Status`, `Company_School_Name`, `Further_Studies_Program`, `Contact_Info`, `Profile_Picture`, `Is_Verified`, `Verified_By`, `Verified_At`, `Last_Updated`) VALUES
(1, 2, 'Justine Arcenias', 'TVL', 2001, 'Further Studies', 'Nueva Ecija University Of Science And Technology', NULL, '09102790535', 'uploads/profiles/u2_1786669394.png', 1, 3, '2026-08-24 03:38:50', '2026-08-24 06:13:32');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `Attempt_ID` int(10) UNSIGNED NOT NULL,
  `Username` varchar(50) NOT NULL,
  `IP_Address` varchar(45) NOT NULL,
  `Was_Successful` tinyint(1) NOT NULL DEFAULT 0,
  `Attempted_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

-- (login_attempts data omitted for Railway)
-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `Reset_ID` int(10) UNSIGNED NOT NULL,
  `User_ID` int(10) UNSIGNED NOT NULL,
  `Token_Hash` char(64) NOT NULL,
  `Expires_At` datetime NOT NULL,
  `Used_At` timestamp NULL DEFAULT NULL,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

-- (password_resets data omitted for Railway)
-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `Report_ID` int(10) UNSIGNED NOT NULL,
  `Report_Name` varchar(150) NOT NULL,
  `Generated_Date` timestamp NOT NULL DEFAULT current_timestamp(),
  `Summary_JSON` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`Summary_JSON`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_accounts`
--

CREATE TABLE `user_accounts` (
  `User_ID` int(10) UNSIGNED NOT NULL,
  `First_Name` varchar(80) NOT NULL,
  `Last_Name` varchar(80) NOT NULL,
  `Middle_Initial` varchar(2) DEFAULT NULL,
  `Name_Suffix` varchar(10) DEFAULT NULL,
  `Username` varchar(50) NOT NULL,
  `Email` varchar(150) NOT NULL,
  `Student_No` varchar(60) DEFAULT NULL,
  `Employee_ID` varchar(60) DEFAULT NULL,
  `Password` varchar(255) NOT NULL,
  `Role` enum('Admin','Instructor','Student','Principal') NOT NULL DEFAULT 'Student',
  `Status` enum('Pending','Active','Inactive','Archived') NOT NULL DEFAULT 'Pending',
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_accounts`
--

INSERT INTO `user_accounts` (`User_ID`, `First_Name`, `Last_Name`, `Middle_Initial`, `Name_Suffix`, `Username`, `Email`, `Student_No`, `Employee_ID`, `Password`, `Role`, `Status`, `Created_At`) VALUES
(2, 'Justine', 'Arcenias', NULL, NULL, 'Justine', 'jarcenias8@gmail.com', NULL, NULL, '$2y$10$rt1KJFN5kAWzoi5Yjb5uuOwbPjJ0YpaH7ddsLFLKoalBEFzu.OjCW', 'Student', 'Active', '2026-08-03 05:53:29'),
(3, 'SJNHS', 'ADMIN', NULL, NULL, 'Admin1', 'sjhnsadmin@edu.com', NULL, NULL, '$2y$10$MorgSJ3FeS/WZ1fvpPmeiuS4t8wAvLM7GWX88eP27sga7eJ8rKyey', 'Admin', 'Active', '2026-08-11 23:29:45'),
(5, 'Jandred', 'Silvino', NULL, NULL, 'Dred', 'silvino@gmail.com', NULL, NULL, '$2y$10$gQMp5bPB67BCwG0m3dVGxu2ltSalN.SoyqQTVPxEvFundktmXmP0W', 'Instructor', 'Active', '2026-08-13 07:40:15'),
(17, 'Imelda', 'Sison', NULL, NULL, '012345', 'donalduck@gmail.com', NULL, '012345', '$2y$10$gIqq64SD.wozeigxApFwqeWPFo4Nkq3U6oscpb8Q7M9q6pQEMP/Sa', 'Principal', 'Active', '2026-09-10 12:41:24'),
(25, 'Red', 'Orian', NULL, NULL, '01793', 'kboy3111@gmail.com', '01793', NULL, '$2y$10$Pcy5fy2BvFAT0L4ykzHdmuV2rIuTKySMNqEC1tq0F56ZR3gobNld.', 'Student', 'Pending', '2026-10-10 05:13:44'),
(26, 'Brandon', 'Dela Cruz', NULL, NULL, '01379', 'arceniasjustine0808@gmail.com', '01379', NULL, '$2y$10$JB1JAijFaqbZH2jafYBaCOs6BqikRFbdnFur5lRd502sn/M/C/PzS', 'Student', 'Pending', '2026-10-10 05:26:47');

-- --------------------------------------------------------

--
-- Table structure for table `user_messages`
--

CREATE TABLE `user_messages` (
  `Message_ID` int(10) UNSIGNED NOT NULL,
  `Sender_ID` int(10) UNSIGNED NOT NULL,
  `Recipient_ID` int(10) UNSIGNED NOT NULL,
  `Message_Text` text NOT NULL,
  `Sent_At` timestamp NOT NULL DEFAULT current_timestamp(),
  `Is_Read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_messages`
--

INSERT INTO `user_messages` (`Message_ID`, `Sender_ID`, `Recipient_ID`, `Message_Text`, `Sent_At`, `Is_Read`) VALUES
(1, 3, 2, 'yg', '2026-09-09 14:46:03', 1),
(2, 2, 3, 'TH', '2026-09-09 14:47:50', 1),
(3, 2, 3, 'TTG', '2026-09-09 14:54:47', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`Announcement_ID`),
  ADD KEY `idx_announcements_date_posted` (`Date_Posted`),
  ADD KEY `fk_announcements_author` (`Author_ID`),
  ADD KEY `idx_announcements_archived` (`Is_Archived`);

--
-- Indexes for table `announcement_recipients`
--
ALTER TABLE `announcement_recipients`
  ADD PRIMARY KEY (`Announcement_ID`,`User_ID`),
  ADD KEY `idx_announcement_recipients_user` (`User_ID`);

--
-- Indexes for table `graduate_profiles`
--
ALTER TABLE `graduate_profiles`
  ADD PRIMARY KEY (`Profile_ID`),
  ADD UNIQUE KEY `uq_graduate_profiles_user_id` (`User_ID`),
  ADD KEY `idx_graduate_profiles_batch_year` (`Batch_Year`),
  ADD KEY `idx_graduate_profiles_employment_status` (`Employment_Status`),
  ADD KEY `idx_graduate_profiles_is_verified` (`Is_Verified`),
  ADD KEY `fk_graduate_profiles_verified_by` (`Verified_By`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`Attempt_ID`),
  ADD KEY `idx_login_attempts_user_time` (`Username`,`Attempted_At`),
  ADD KEY `idx_login_attempts_ip_time` (`IP_Address`,`Attempted_At`),
  ADD KEY `idx_login_attempts_failed_time` (`Was_Successful`,`Attempted_At`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`Reset_ID`),
  ADD UNIQUE KEY `uq_password_resets_token_hash` (`Token_Hash`),
  ADD KEY `idx_password_resets_user_id` (`User_ID`),
  ADD KEY `idx_password_resets_expires_at` (`Expires_At`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`Report_ID`),
  ADD KEY `idx_reports_generated_date` (`Generated_Date`);

--
-- Indexes for table `user_accounts`
--
ALTER TABLE `user_accounts`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `uq_user_accounts_username` (`Username`),
  ADD UNIQUE KEY `uq_user_accounts_email` (`Email`),
  ADD UNIQUE KEY `uq_user_accounts_student_no` (`Student_No`),
  ADD UNIQUE KEY `uq_user_accounts_employee_id` (`Employee_ID`);

--
-- Indexes for table `user_messages`
--
ALTER TABLE `user_messages`
  ADD PRIMARY KEY (`Message_ID`),
  ADD KEY `idx_user_messages_sender` (`Sender_ID`),
  ADD KEY `idx_user_messages_recipient` (`Recipient_ID`),
  ADD KEY `idx_user_messages_sent_at` (`Sent_At`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `Announcement_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `graduate_profiles`
--
ALTER TABLE `graduate_profiles`
  MODIFY `Profile_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `Attempt_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `Reset_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `Report_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_accounts`
--
ALTER TABLE `user_accounts`
  MODIFY `User_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `user_messages`
--
ALTER TABLE `user_messages`
  MODIFY `Message_ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_author` FOREIGN KEY (`Author_ID`) REFERENCES `user_accounts` (`User_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `announcement_recipients`
--
ALTER TABLE `announcement_recipients`
  ADD CONSTRAINT `fk_announcement_recipients_announcement` FOREIGN KEY (`Announcement_ID`) REFERENCES `announcements` (`Announcement_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_announcement_recipients_user` FOREIGN KEY (`User_ID`) REFERENCES `user_accounts` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `graduate_profiles`
--
ALTER TABLE `graduate_profiles`
  ADD CONSTRAINT `fk_graduate_profiles_user` FOREIGN KEY (`User_ID`) REFERENCES `user_accounts` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_graduate_profiles_verified_by` FOREIGN KEY (`Verified_By`) REFERENCES `user_accounts` (`User_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`User_ID`) REFERENCES `user_accounts` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_messages`
--
ALTER TABLE `user_messages`
  ADD CONSTRAINT `fk_user_messages_recipient` FOREIGN KEY (`Recipient_ID`) REFERENCES `user_accounts` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_messages_sender` FOREIGN KEY (`Sender_ID`) REFERENCES `user_accounts` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Match PHP table names (Linux MySQL)
SET FOREIGN_KEY_CHECKS = 0;
RENAME TABLE user_accounts TO User_Accounts;
RENAME TABLE announcements TO Announcements;
RENAME TABLE announcement_recipients TO Announcement_Recipients;
RENAME TABLE graduate_profiles TO Graduate_Profiles;
RENAME TABLE login_attempts TO Login_Attempts;
RENAME TABLE password_resets TO Password_Resets;
RENAME TABLE reports TO Reports;
RENAME TABLE user_messages TO User_Messages;
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;