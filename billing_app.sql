-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 04, 2025 at 06:40 AM
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
-- Database: `billing_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `company_name` varchar(150) NOT NULL,
  `legal_name` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(150) DEFAULT NULL,
  `gst_number` varchar(20) DEFAULT NULL,
  `pan_number` varchar(20) DEFAULT NULL,
  `cin_number` varchar(30) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `state` varchar(80) DEFAULT NULL,
  `pincode` varchar(20) DEFAULT NULL,
  `country` varchar(80) DEFAULT NULL,
  `invoice_prefix` varchar(20) DEFAULT NULL,
  `next_invoice_no` int(11) DEFAULT 1,
  `tax_type` varchar(10) DEFAULT 'CGST',
  `tax_percent` decimal(5,2) DEFAULT 0.00,
  `show_delivery` tinyint(1) DEFAULT 1,
  `bank_name` varchar(120) DEFAULT NULL,
  `bank_holder` varchar(120) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `branch` varchar(150) DEFAULT NULL,
  `upi_id` varchar(120) DEFAULT NULL,
  `invoice_terms` text DEFAULT NULL,
  `footer_notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_settings`
--

INSERT INTO `company_settings` (`id`, `company_name`, `legal_name`, `phone`, `email`, `website`, `gst_number`, `pan_number`, `cin_number`, `logo_path`, `address`, `city`, `state`, `pincode`, `country`, `invoice_prefix`, `next_invoice_no`, `tax_type`, `tax_percent`, `show_delivery`, `bank_name`, `bank_holder`, `bank_account`, `ifsc_code`, `branch`, `upi_id`, `invoice_terms`, `footer_notes`, `updated_at`) VALUES
(1, 'TECHINTA', 'TECHINTA', '6380338626', 'ajay@techinta.com', 'www.techinta.com', '', 'ENVPA0309E', '', 'uploads/logo_1764823675.png', '7/10 , Techinta It Training Centre , Ruby school road,', 'Saravanampatti,Coimbatore', 'Tamil Nadu', '641035', 'India', 'TN', 101, 'CGST', 0.00, 1, 'SBI', 'AJAY A', '12345678910', 'SBIN000ENSA', 'CBE', '6380338626@oksbi', 'THANKS FOR VISITING', 'LETS GROW TOGETHER', '2025-12-04 04:48:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Main Admin', 'admin@example.com', '$2y$10$bJYIfDU4IPM7yfkoT/L9qOGY3/VzaskGgmtUoOnh7qtLkyfZ2Sck2', 'admin', 1, '2025-12-04 03:36:20', '2025-12-04 03:36:20'),
(2, 'AJAY A', 'ajay@techinta.com', '$2y$10$zF0z0JO0.MJ2HMR/qHlbEOpQLnpWFMHBKkU5vWEuy68fw58wEIF1G', 'staff', 1, '2025-12-04 03:38:25', '2025-12-04 03:38:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;



CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30),
    email VARCHAR(150),
    address TEXT,
    city VARCHAR(80),
    state VARCHAR(80),
    pincode VARCHAR(20),
    gst_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
