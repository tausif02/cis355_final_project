-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 26, 2025 at 03:58 AM
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
-- Table structure for table `iss_comments`
--

CREATE TABLE `iss_comments` (
  `id` int(11) NOT NULL,
  `per_id` int(11) NOT NULL,
  `iss_id` int(11) NOT NULL,
  `short_comment` varchar(255) NOT NULL,
  `long_comment` text NOT NULL,
  `posted_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `iss_comments`
--

INSERT INTO `iss_comments` (`id`, `per_id`, `iss_id`, `short_comment`, `long_comment`, `posted_date`) VALUES
(278, 25, 3, '21356', '', '2025-04-25 18:25:57'),
(279, 25, 3, '561615', '', '2025-04-25 18:26:08'),
(280, 25, 3, '241224', '', '2025-04-25 18:26:10'),
(282, 25, 3, '69679', '', '2025-04-25 18:26:30'),
(283, 25, 114, 'p', '', '2025-04-25 18:34:55'),
(284, 28, 114, '12', '', '2025-04-25 18:52:10'),
(285, 25, 93, 'test1', '', '2025-04-25 21:49:11'),
(286, 25, 92, 'test', '', '2025-04-25 21:51:29'),
(287, 2, 106, 'test', '', '2025-04-25 21:51:58'),
(288, 2, 3, 'test', '', '2025-04-25 21:52:07'),
(289, 2, 116, 'test', '', '2025-04-25 21:52:13'),
(290, 3, 114, 'test', '', '2025-04-25 21:53:01'),
(291, 3, 116, 'test', '', '2025-04-25 21:53:09'),
(292, 3, 92, 'test', '', '2025-04-25 21:53:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `iss_comments`
--
ALTER TABLE `iss_comments`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `iss_comments`
--
ALTER TABLE `iss_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=293;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
