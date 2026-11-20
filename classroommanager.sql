-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 20, 2026 at 04:05 PM
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
-- Database: `classroommanager`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Opće vijesti'),
(2, 'Promjena rasporeda'),
(3, 'Predmetne vijesti');

-- --------------------------------------------------------

--
-- Table structure for table `classrooms`
--

CREATE TABLE `classrooms` (
  `id` int(11) NOT NULL,
  `room_code` varchar(30) NOT NULL,
  `capacity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `classrooms`
--

INSERT INTO `classrooms` (`id`, `room_code`, `capacity`) VALUES
(1, 'A101', 50),
(2, 'A102', 35),
(3, 'B202', 60),
(4, 'C105', 30);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `professor_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image` varchar(255) DEFAULT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `subject_id`, `professor_id`, `title`, `message`, `created_at`, `image`, `category_id`) VALUES
(19, 2, 3, 'Predavanje otkazano', 'Predavanje iz predmeta Programming u učionici A101 na datum 19.11.2026 20:25 je otkazano.', '2026-11-18 16:33:38', NULL, 2),
(21, 1, 3, 'Nova rezervacija', 'Kreirana je nova rezervacija za predmet \"Mathematics\", učionica: A101, početak: 18.11.2026 17:46.\nasd', '2026-11-18 16:46:24', NULL, 2),
(22, 2, 3, 'Promjena rezervacije', 'Došlo je do promjena u rezervaciji:\n- Predmet: Mathematics → Programming\n- Učionica: A101 → B202\n- Početak: 18.11.2026 17:46\n- Završetak: 18.11.2026 20:46\n- Napomene: asd → asdaaaa', '2026-11-18 16:56:03', NULL, 2),
(25, 4, 3, 'Promjena rezervacije', 'Došlo je do promjena u rezervaciji:\n- Predmet: Web Design\n- Učionica: A101\n- Početak: 19.11.2026 07:30 → 19.11.2026 08:30\n- Završetak: 19.11.2026 08:30 → 19.11.2026 09:30\n- Napomene: asd', '2026-11-18 17:03:37', NULL, 2),
(26, 3, 3, 'Nova vijest', 'Ayoo', '2026-11-19 22:29:56', 'uploads/announcements/21ebe659bf217f1f206eababfcf95907.jpg', 2),
(27, 3, 3, 'Nova rezervacija', 'Kreirana je nova rezervacija za predmet \"Databases\", učionica: A101, početak: 24.11.2026 09:50.\nNapomene: Novi sat', '2026-11-19 22:51:27', NULL, 2),
(28, NULL, 3, 'asd', 'asd', '2026-11-19 22:53:32', 'uploads/announcements/e85bb0c3cd797897afecb5f9e80e0088.jpg', 1),
(29, 2, 3, 'asasdasdasd', 'asdasdasd', '2026-11-19 22:54:34', 'uploads/announcements/1e21fb8a44f7ed219d7dbe1e1cbce06f.jpg', 3),
(30, 5, 4, 'Nova rezervacija', 'Kreirana je nova rezervacija za predmet \"Linux Basics\", učionica: B202, početak: 17.11.2026 12:00.\nNapomene: ', '2026-11-20 13:58:15', NULL, 2),
(31, 5, 4, 'Promjena rezervacije', 'Došlo je do promjena u rezervaciji:\n- Predmet: Linux Basics\n- Učionica: B202\n- Početak: 24.11.2026 12:00\n- Završetak: 24.11.2026 15:00\n- Napomene: Bez napomena → Grupe za labose', '2026-11-20 14:04:17', NULL, 2),
(32, 5, 4, 'Predavanje otkazano', 'Predavanje iz predmeta Linux Basics u učionici B202 na datum 24.11.2026 12:00 je otkazano.', '2026-11-20 14:05:04', NULL, 2),
(33, NULL, 4, 'Dodan je Rich Text editor', '<h1>Ovo je heading 1</h1><p><strong>Ovo bold</strong></p><h2>Ovo je heading 2</h2><p><em>Ovo italic</em></p><p><u>Ovo underline</u></p><p><s>Ovo je line kroz</s></p><p><br></p><p>Lista:</p><ol><li>lista 1</li><li>lista 2</li><li>lista 3</li></ol><p><br></p><p>Bulletsi:</p><ul><li>bullet 1</li><li>bullet 2</li><li>bullet 3</li></ul><p><br></p><blockquote>ovo je quote</blockquote><p><br></p><p><a href=\"https://github.com/sHrv7/ClassRoomManager\">ovo je veza</a></p><p><br></p><blockquote><strong><em><s><u>ovo je sve</u></s></em></strong></blockquote><p><br></p><p>a ovo normal text</p>', '2026-11-20 14:18:43', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `classroom_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `classroom_id`, `subject_id`, `user_id`, `starts_at`, `ends_at`, `notes`) VALUES
(1, 1, 3, 1, '2026-11-18 10:00:00', '2026-11-18 11:00:00', 'Predavanje'),
(2, 4, 1, 3, '2026-11-20 12:15:00', '2026-11-20 13:30:00', 'Obavezno predavanje'),
(4, 1, 1, 3, '2026-11-18 11:30:00', '2026-11-18 12:45:00', 'Kolokvij'),
(8, 1, 4, 3, '2026-11-19 08:30:00', '2026-11-19 09:30:00', 'asd'),
(9, 3, 2, 3, '2026-11-20 13:30:00', '2026-11-20 14:15:00', 'asdaaaa'),
(10, 1, 3, 3, '2026-11-24 09:50:00', '2026-11-24 10:50:00', 'Novi sat');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `name`) VALUES
(1, 'Mathematics'),
(2, 'Programming'),
(3, 'Databases'),
(4, 'Web Design'),
(5, 'Linux Basics');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `Username` varchar(150) NOT NULL,
  `Email` varchar(150) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Role` varchar(50) NOT NULL DEFAULT 'Student',
  `Active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `Username`, `Email`, `Password`, `Role`, `Active`) VALUES
(1, 'Hrvoje Sic', 'hrvoje.sic@gmail.com', '$2y$10$O6opMF/DeyuPP7kq7CtZou5kqJ.XdxFfn85.snWEgn/4HltBoOIke', 'Admin', 1),
(2, 'test korisnik', 'test@gmail.com', '$2y$10$nKJnEliKZ9N7YDozFRUhXe7k6XxPEBfVoBu501f1VdvIkKpRX9o4O', 'Student', 1),
(3, 'Gvido Maskalan', 'gvido.maskalan@gmail.com', '$2y$10$1VzDwnRLFMMkYnCb7mEEkOZBuKfPoZavMjPwlb/P9L8qkbLSeR4Cu', 'Professor', 1),
(4, 'Test Profesor', 'test.profesor@gmail.com', '$2y$10$MQCfZcsmIuXRea29kcJCjO3InFj/FhQO8IOVGQy.z0E/TRLkZoaxe', 'Professor', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_subjects`
--

CREATE TABLE `user_subjects` (
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_subjects`
--

INSERT INTO `user_subjects` (`user_id`, `subject_id`) VALUES
(1, 2),
(2, 1),
(2, 3),
(3, 1),
(3, 2),
(3, 3),
(3, 4),
(4, 5);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `classrooms`
--
ALTER TABLE `classrooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `professor_id` (`professor_id`),
  ADD KEY `fk_notifications_category` (`category_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `classroom_id` (`classroom_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`Username`),
  ADD UNIQUE KEY `uq_users_email` (`Email`);

--
-- Indexes for table `user_subjects`
--
ALTER TABLE `user_subjects`
  ADD PRIMARY KEY (`user_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `classrooms`
--
ALTER TABLE `classrooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`professor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`),
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `reservations_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_subjects`
--
ALTER TABLE `user_subjects`
  ADD CONSTRAINT `user_subjects_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
