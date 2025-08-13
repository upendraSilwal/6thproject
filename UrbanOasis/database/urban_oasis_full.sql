-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 30, 2025 at 07:22 PM
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
-- Database: `urban_oasis`
--
CREATE DATABASE IF NOT EXISTS `urban_oasis` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `urban_oasis`;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `created_at`) VALUES
(1, 'admin', 'admin@urbanoasis.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'Admin', 'User', '2025-06-29 15:24:42');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `created_at`) VALUES
(1, 'Alice Johnson', 'alice@example.com', 'General Inquiry', 'I would like to know more about your services and how to list my property.', '2025-06-29 15:24:42'),
(2, 'Bob Williams', 'bob@example.com', 'Property Viewing', 'I am interested in viewing some properties. Can you help me schedule appointments?', '2025-06-29 15:24:42'),
(3, 'Carol Davis', 'carol@example.com', 'Pricing Information', 'What are your commission rates for property sales and rentals?', '2025-06-29 15:24:42'),
(4, 'Edward Miller', 'edward@example.com', 'Partnership Inquiry', 'I am a real estate agent and would like to discuss partnership opportunities.', '2025-06-29 15:24:42'),
(5, 'Fiona Garcia', 'fiona@example.com', 'Website Feedback', 'Great website! I found it very user-friendly and informative.', '2025-06-29 15:24:42');

-- --------------------------------------------------------

--
-- Table structure for table `credit_transactions`
--

CREATE TABLE `credit_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('purchase','usage','refund','bonus') NOT NULL,
  `credits` int(11) NOT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `property_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `credit_transactions`
--
INSERT INTO `credit_transactions` (`id`, `user_id`, `transaction_type`, `credits`, `amount_paid`, `payment_method`, `description`, `property_id`, `created_at`) VALUES
(1, 1, 'purchase', 5, '99.00', 'esewa', 'Basic Package - 5 credits for 1 listing', NULL, '2025-07-30 14:00:00'),
(2, 2, 'purchase', 20, '399.00', 'esewa', 'Pro Package - 20 credits for 4 listings', NULL, '2025-07-30 14:01:00'),
(3, 3, 'purchase', 25, '999.00', 'esewa', 'Elite Package - 25 credits for 5 listings (+5 bonus)', NULL, '2025-07-30 14:02:00'),
(4, 4, 'usage', 5, NULL, NULL, 'Used 1 free listing for property #1, then 5 credits', 1, '2025-07-30 14:03:00');


-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `property_type` enum('house','apartment','room','land','commercial') NOT NULL,
  `listing_type` enum('sale','rent') NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `location` varchar(200) NOT NULL,
  `city` varchar(100) NOT NULL,
  `bedrooms` int(11) DEFAULT NULL,
  `bathrooms` int(11) DEFAULT NULL,
  `area_sqft` decimal(10,2) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `parking_spaces` int(11) DEFAULT 0,
  `furnished` tinyint(1) DEFAULT 0,
  `available_from` date DEFAULT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `properties`
--
INSERT INTO `properties` (`id`, `user_id`, `title`, `description`, `property_type`, `listing_type`, `price`, `location`, `city`, `bedrooms`, `bathrooms`, `area_sqft`, `image_url`, `parking_spaces`, `furnished`, `available_from`, `contact_phone`, `contact_email`, `approval_status`, `approved_by`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 4, 'Modern Apartment in Kathmandu', 'A beautiful modern apartment with all amenities.', 'apartment', 'rent', '50000.00', 'Baneshwor', 'Kathmandu', 3, 2, '1200.00', 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=500&q=80', 1, 1, '2025-08-01', '9800000004', 'sarah@example.com', 'approved', 1, '2025-07-30 10:00:00', '2025-07-30 09:00:00', '2025-07-30 10:00:00');


-- --------------------------------------------------------

--
-- Table structure for table `property_features`
--

CREATE TABLE `property_features` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `feature_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_features`
--
INSERT INTO `property_features` (`id`, `property_id`, `feature_name`) VALUES
(1, 1, 'Internet'),
(2, 1, 'Parking'),
(3, 1, 'Air Conditioning'),
(4, 1, 'Security');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `listing_credits` int(11) NOT NULL DEFAULT 0,
  `free_listings_used` int(11) NOT NULL DEFAULT 0,
  `total_listings_created` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `first_name`, `last_name`, `phone`, `address`, `city`, `listing_credits`, `free_listings_used`, `total_listings_created`, `created_at`, `updated_at`) VALUES
(1, 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Doe', '9851234567', 'Kathmandu-15', 'Kathmandu', 5, 1, 1, '2025-06-29 15:24:42', '2025-07-30 14:00:00'),
(2, 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane', 'Smith', '9851234568', 'Pokhara-10', 'Pokhara', 20, 0, 0, '2025-06-29 15:24:42', '2025-07-30 14:01:00'),
(3, 'mike@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike', 'Wilson', '9851234569', 'Lalitpur-5', 'Lalitpur', 25, 0, 0, '2025-06-29 15:24:42', '2025-07-30 14:02:00'),
(4, 'sarah@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah', 'Brown', '9851234570', 'Bhaktapur-8', 'Bhaktapur', 0, 1, 1, '2025-06-29 15:24:42', '2025-07-30 14:03:00'),
(5, 'david@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'David', 'Jones', '9851234571', 'Chitwan-7', 'Chitwan', 0, 0, 0, '2025-06-29 15:24:42', '2025-06-29 15:24:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `credit_transactions`
--
ALTER TABLE `credit_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_credit_transactions_user_id` (`user_id`),
  ADD KEY `idx_credit_transactions_type` (`transaction_type`),
  ADD KEY `idx_credit_transactions_created_at` (`created_at`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_properties_user_id` (`user_id`),
  ADD KEY `idx_properties_approval_status` (`approval_status`),
  ADD KEY `idx_properties_city` (`city`),
  ADD KEY `idx_properties_property_type` (`property_type`),
  ADD KEY `idx_properties_listing_type` (`listing_type`);

--
-- Indexes for table `property_features`
--
ALTER TABLE `property_features`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_property_features_property_id` (`property_id`);

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
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `credit_transactions`
--
ALTER TABLE `credit_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `property_features`
--
ALTER TABLE `property_features`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `credit_transactions`
--
ALTER TABLE `credit_transactions`
  ADD CONSTRAINT `credit_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `credit_transactions_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_features`
--
ALTER TABLE `property_features`
  ADD CONSTRAINT `property_features_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

