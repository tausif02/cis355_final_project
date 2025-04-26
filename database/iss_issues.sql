-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 26, 2025 at 03:57 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cis355`
--

-- --------------------------------------------------------

--
-- Table structure for table `iss_issues`
--

CREATE TABLE `iss_issues` (
  `id` int(11) NOT NULL,
  `short_description` varchar(255) NOT NULL,
  `long_description` text NOT NULL,
  `open_date` date NOT NULL,
  `close_date` date NOT NULL,
  `priority` varchar(255) NOT NULL,
  `org` varchar(255) NOT NULL,
  `project` varchar(255) NOT NULL,
  `per_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `pdf_attachment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `iss_issues`
--

INSERT INTO `iss_issues` (`id`, `short_description`, `long_description`, `open_date`, `close_date`, `priority`, `org`, `project`, `per_id`, `created_by`, `pdf_attachment`) VALUES
(1, 'cs451 solidity', 'The course, cs451, needs to be updated to include blockchain concepts, ethereum network, remix IDE and solidity programming language.', '2025-02-19', '2025-04-11', 'Medium', '', 'csis', 1, 0, NULL),
(2, 'cis355 login screen dfg df g', 'We need to develop the login functionality. We need to verify email before allowing login. So there needs to be a Join link and a verification process', '2025-03-19', '2025-04-22', 'High', '', 'csis', 1, 0, NULL),
(3, 'cis355 issues list screen', 'wdfg jsdlkf jgsldkf jglskdfj g;sldf gjs;ldkf jgl;sdkfj gl;sdkfj g;lsdfk jg;lskdf gjs;ldfk gjsl;dkf jgl;skdfj gls;dkfjg;lsdfkjg;lsdkf jgsl;dfg', '2025-03-19', '0000-00-00', 'Low', 'csisdept', 'csis', 1, 0, NULL),
(92, 'adminassign1', '', '2025-04-25', '0000-00-00', 'Low', '', '', 25, 1, NULL),
(93, 'adminassign2updatedfromnonadmin', '', '2025-04-25', '2025-04-30', 'Medium', '', '', 25, 1, NULL),
(94, 'nonadminassigncheck', '', '2025-04-25', '2025-04-25', 'Low', '', 'sda', 25, 26, NULL),
(106, 'nonadminassign4', '', '2025-04-25', '0000-00-00', 'Low', '', '', 2, 2, NULL),
(114, 'adminassign', '', '2025-04-25', '0000-00-00', 'High', '', '', 3, 25, NULL),
(116, 'dasd', '', '2025-04-25', '0000-00-00', 'High', '', '', 1, 27, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `iss_issues`
--
ALTER TABLE `iss_issues`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `iss_issues`
--
ALTER TABLE `iss_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
