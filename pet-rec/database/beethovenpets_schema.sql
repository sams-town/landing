-- ============================================================
-- BeethovenPets Pet Relocation Concierge — Database Schema
-- Ready for phpMyAdmin / MySQL Import
-- Default Admin: admin@beethovenpets.com / admin123
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

-- --------------------------------------------------------
-- 1. Admins Table (admin panel users)
-- --------------------------------------------------------
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','manager','operator') NOT NULL DEFAULT 'manager',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin: admin@beethovenpets.com / admin123
INSERT INTO `admins` (`full_name`, `email`, `password_hash`, `role`) VALUES
('BeethovenPets Administrator', 'admin@beethovenpets.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- --------------------------------------------------------
-- 2. Customers Table
-- --------------------------------------------------------
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `country` varchar(60) DEFAULT 'Indonesia',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email_idx` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Pets Table (1 customer → many pets)
-- --------------------------------------------------------
CREATE TABLE `pets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `species` enum('dog','cat','other') NOT NULL DEFAULT 'dog',
  `breed` varchar(100) DEFAULT NULL,
  `age` varchar(30) DEFAULT NULL,
  `weight_kg` decimal(6,2) NOT NULL DEFAULT 15.00,
  `size_category` enum('small','medium','large','giant','brachycephalic') NOT NULL DEFAULT 'medium',
  `microchip` varchar(60) DEFAULT NULL,
  `vaccine_status` enum('up_to_date','pending_expired','incomplete') DEFAULT 'up_to_date',
  `health_notes` text DEFAULT NULL,
  `crate_dimensions` varchar(80) DEFAULT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id_idx` (`customer_id`),
  CONSTRAINT `pets_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Routes Table (available routes with base pricing)
-- --------------------------------------------------------
CREATE TABLE `routes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `origin_city` varchar(100) NOT NULL,
  `origin_code` varchar(6) DEFAULT NULL,
  `destination_city` varchar(100) NOT NULL,
  `destination_code` varchar(6) DEFAULT NULL,
  `type` enum('domestic_air','domestic_land','international','combined') NOT NULL,
  `base_price_idr` decimal(12,2) NOT NULL DEFAULT 3500000.00,
  `est_duration_days` int(3) NOT NULL DEFAULT 1,
  `crate_required` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `routes` (`origin_city`, `origin_code`, `destination_city`, `destination_code`, `type`, `base_price_idr`, `est_duration_days`, `notes`) VALUES
('Jakarta', 'CGK', 'Surabaya', 'SUB', 'domestic_air', 3200000.00, 1, 'Same-day via Garuda / Lion Air Pet Cargo'),
('Jakarta', 'CGK', 'Denpasar Bali', 'DPS', 'domestic_air', 3800000.00, 1, '2 flights available daily'),
('Jakarta', 'CGK', 'Medan', 'KNO', 'domestic_air', 3500000.00, 1, 'Direct flight cargo'),
('Jakarta', 'CGK', 'Singapore', 'SIN', 'international', 12500000.00, 2, 'IATA approved agent, includes import permit'),
('Jakarta', 'CGK', 'Kuala Lumpur', 'KUL', 'international', 11200000.00, 2, 'Vet-endorsed health cert required'),
('Jakarta', 'CGK', 'Tokyo Narita', 'NRT', 'international', 24800000.00, 5, 'Japan 180-day rabies wait period applies'),
('Surabaya', 'SUB', 'Jakarta', 'CGK', 'domestic_air', 3100000.00, 1, 'Return cargo available'),
('Bandung', 'BDO', 'Jakarta', 'CGK', 'domestic_land', 1800000.00, 1, 'Premium climate-controlled van + handler');

-- --------------------------------------------------------
-- 5. Pricing Add-ons (door-to-door, docs, boarding)
-- --------------------------------------------------------
CREATE TABLE `addons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL UNIQUE,
  `name` varchar(120) NOT NULL,
  `description` text NOT NULL,
  `price_idr` decimal(10,2) NOT NULL DEFAULT 0.00,
  `icon` varchar(40) DEFAULT 'star',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `addons` (`code`, `name`, `description`, `price_idr`, `icon`) VALUES
('door_to_door', 'Door-to-Door Concierge', 'Private pick-up from home to airport and from airport to destination address', 1200000.00, 'home'),
('vet_documents', 'Full Vet Documentation', 'Health certificate, rabies titre, import/export permits, microchip scanning', 1800000.00, 'file'),
('transit_boarding', 'Transit Boarding Care', 'Premium 24/7 pet hotel during transit or waiting period (per 24h)', 950000.00, 'bed'),
('crate_rental', 'IATA Travel Crate Rental', 'Approved airline crate, absorbent pad, water bottle, ID tag', 650000.00, 'box'),
('live_tracking', 'Live Video Updates', 'Real-time video updates + GPS tracking link during entire journey', 450000.00, 'video'),
('pet_chaperone', 'Dedicated Chaperone', 'A human travel companion flies with your pet (international only)', 7500000.00, 'user');

-- --------------------------------------------------------
-- 6. Bookings Table (core table — estimates + confirmed bookings)
-- --------------------------------------------------------
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_code` varchar(20) NOT NULL UNIQUE,
  `customer_id` int(11) NOT NULL,
  `pet_id` int(11) DEFAULT NULL,
  `route_id` int(11) DEFAULT NULL,
  `origin_city` varchar(100) NOT NULL,
  `destination_city` varchar(100) NOT NULL,
  `travel_type` enum('domestic_air','domestic_land','international','combined') NOT NULL DEFAULT 'domestic_air',
  `travel_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `pet_type_booking` enum('small','medium','large','giant','brachycephalic') NOT NULL DEFAULT 'medium',
  `pet_weight_kg` decimal(6,2) NOT NULL DEFAULT 15.00,
  `crate_included` tinyint(1) NOT NULL DEFAULT 0,
  `addon_door` tinyint(1) NOT NULL DEFAULT 0,
  `addon_docs` tinyint(1) NOT NULL DEFAULT 0,
  `addon_stay` tinyint(1) NOT NULL DEFAULT 0,
  `addon_custom` text DEFAULT NULL,
  `price_estimated_idr` decimal(14,2) NOT NULL DEFAULT 0.00,
  `price_final_idr` decimal(14,2) DEFAULT NULL,
  `paid_amount_idr` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','deposit','paid','refunded') NOT NULL DEFAULT 'unpaid',
  `status` enum('inquiry','confirmed','preparing','in_transit','arrived','delivered','cancelled','completed') NOT NULL DEFAULT 'inquiry',
  `special_instructions` text DEFAULT NULL,
  `assigned_handler` varchar(120) DEFAULT NULL,
  `flight_details` text DEFAULT NULL,
  `customer_notes` text DEFAULT NULL,
  `private_admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_code_idx` (`booking_code`),
  KEY `customer_idx` (`customer_id`),
  KEY `status_idx` (`status`),
  KEY `travel_date_idx` (`travel_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample Seeded Bookings
INSERT INTO `bookings` (`booking_code`, `customer_id`, `route_id`, `origin_city`, `destination_city`, `travel_type`, `travel_date`, `pet_type_booking`, `pet_weight_kg`, `addon_door`, `addon_docs`, `addon_stay`, `price_estimated_idr`, `price_final_idr`, `paid_amount_idr`, `payment_status`, `status`, `special_instructions`, `assigned_handler`) VALUES
('PV-20261005-001', 1, 1, 'Jakarta', 'Surabaya', 'domestic_air', '2026-10-20', 'large', 28.50, 1, 1, 0, 6200000.00, 6150000.00, 6150000.00, 'paid', 'confirmed', 'Anjing takut dengan orang baru. Mohon panggil dengan nama "Max".', 'Budi Santoso'),
('PV-20261004-002', 2, 4, 'Jakarta', 'Singapore', 'international', '2026-10-28', 'medium', 14.20, 1, 1, 1, 16450000.00, 16450000.00, 8225000.00, 'deposit', 'preparing', 'French Bulldog — brachycephalic, ANA-approved crate required', 'Dewi Lestari'),
('PV-20261002-003', 3, 8, 'Bandung', 'Jakarta', 'domestic_land', '2026-10-08', 'medium', 12.00, 1, 0, 0, 3000000.00, 3000000.00, 3000000.00, 'paid', 'in_transit', 'Pick up at 7AM sharp, Villa Dago', 'Agus Kurniawan'),
('PV-20260928-004', 4, 2, 'Jakarta', 'Denpasar Bali', 'domestic_air', '2026-09-30', 'small', 6.80, 1, 1, 0, 5600000.00, 5600000.00, 5600000.00, 'paid', 'delivered', 'Golden puppy, 5 months old — fragile', 'Wulan Anggraini');

-- --------------------------------------------------------
-- 7. Customer seed data
-- --------------------------------------------------------
INSERT INTO `customers` (`full_name`, `email`, `phone`, `address`, `city`, `country`, `notes`) VALUES
('Budi Pratama', 'budi.pratama@email.com', '+62 812 1111 2222', 'Jl. Sudirman No. 123, Apt 45B', 'Jakarta Selatan', 'Indonesia', 'Frequent traveler, business owner'),
('Sarah Wijaya', 'sarah.wijaya@email.com', '+62 816 9988 7766', 'Marina Bay Residences', 'Jakarta Pusat', 'Indonesia', 'Moving to Singapore for work'),
('Andi Saputra', 'andi.saputra@email.com', '+62 821 3344 5566', 'Jl. Dago Resort 22', 'Bandung', 'Indonesia', 'Transferring to Jakarta HQ'),
('Dina Permata', 'dina.permata@email.com', '+62 813 7788 9900', 'Kemang Village T-12', 'Jakarta Selatan', 'Indonesia', 'Premium customer, multiple bookings');

-- --------------------------------------------------------
-- 8. Pets seed data
-- --------------------------------------------------------
INSERT INTO `pets` (`customer_id`, `name`, `species`, `breed`, `age`, `weight_kg`, `size_category`, `vaccine_status`, `health_notes`) VALUES
(1, 'Max', 'dog', 'Golden Retriever', '3 tahun', 28.50, 'large', 'up_to_date', 'Sehat, alergi ayam'),
(2, 'Mochi', 'dog', 'French Bulldog', '2 tahun', 14.20, 'brachycephalic', 'up_to_date', 'Stress jika cuaca panas — butuh AC ekstra'),
(3, 'Luna', 'dog', 'Cocker Spaniel', '4 tahun', 12.00, 'medium', 'up_to_date', 'Sehat, sangat ramah'),
(4, 'Cooper', 'dog', 'Toy Poodle', '5 bulan', 6.80, 'small', 'pending_expired', 'Puppy — vaksinasi booster 1 minggu lagi');

-- --------------------------------------------------------
-- 9. Tracking Logs (timeline per booking)
-- --------------------------------------------------------
CREATE TABLE `tracking_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `status` enum('inquiry','confirmed','preparing','picked_up','origin_airport','in_flight','transit','destination_airport','out_for_delivery','delivered','completed','cancelled') NOT NULL DEFAULT 'inquiry',
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(180) DEFAULT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `created_by_admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_idx` (`booking_id`),
  CONSTRAINT `tracking_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tracking_logs` (`booking_id`, `status`, `title`, `description`, `location`) VALUES
(1, 'inquiry', 'Estimasi Diterima', 'Customer request via website — rute Jakarta → Surabaya anjing Golden 28kg, include dokumen & door-to-door', 'BeethovenPets HQ, Jakarta'),
(1, 'confirmed', 'Booking Dikonfirmasi', 'Pembayaran lunas Rp 6.150.000 diterima. Jadwal penerbangan 20 Okt GA622.', 'BeethovenPets HQ, Jakarta'),
(2, 'inquiry', 'Request Relokasi Internasional', 'Jakarta → Singapore, French Bulldog 14kg, full package + transit', 'BeethovenPets HQ'),
(2, 'preparing', 'Dokumen Sedang Diproses', 'Health certificate & AVA Singapore import permit — progress 70%', 'Partner Vet, Jakarta Selatan'),
(3, 'inquiry', 'Booking Darat Bandung-Jakarta', 'Door-to-door service, van ber-AC', 'BeethovenPets HQ'),
(3, 'confirmed', 'Jadwal Disetujui', 'Tanggal 8 Okt, jam 07.00 WIB pick-up di Villa Dago', 'Bandung'),
(3, 'in_transit', 'Sedang Dalam Perjalanan', 'Agus Kurniawan dengan mobil XXXXX, on schedule, ETA 13.30 WIB', 'Tol Cipularang KM 45'),
(4, 'inquiry', 'Puppy Relocation', 'Transfer ke Denpasar Bali, Poodle puppy', 'BeethovenPets HQ'),
(4, 'delivered', 'Sampai Tujuan!', 'Diterima dengan selamat oleh keluarga di Ubud, Bali.', 'Ubud, Bali');

-- --------------------------------------------------------
-- 10. Site Settings
-- --------------------------------------------------------
CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(60) NOT NULL UNIQUE,
  `setting_value` text,
  `setting_group` enum('general','pricing','contact','payment','social') NOT NULL DEFAULT 'general',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('site_name', 'BeethovenPets — Premium Pet Relocation', 'general'),
('whatsapp_number', '+62 812-1234-5678', 'contact'),
('wechat_id', 'BeethovenPets_ID', 'contact'),
('telegram_username', 'beethovenpets', 'contact'),
('contact_email', 'hello@beethovenpets.com', 'contact'),
('office_address', 'Jl. TB Simatupang, Cilandak, Jakarta Selatan 12430', 'contact'),
('price_weight_multiplier_small', '1.0', 'pricing'),
('price_weight_multiplier_medium', '1.4', 'pricing'),
('price_weight_multiplier_large', '1.9', 'pricing'),
('price_weight_multiplier_giant', '2.6', 'pricing'),
('price_weight_multiplier_brachy', '1.8', 'pricing'),
('social_instagram', 'https://instagram.com/beethovenpets', 'social'),
('social_tiktok', 'https://tiktok.com/@beethovenpets', 'social'),
('social_facebook', 'https://facebook.com/beethovenpets', 'social'),
('social_youtube', 'https://youtube.com/@beethovenpets', 'social'),
('social_linkedin', 'https://linkedin.com/company/beethovenpets', 'social'),
('social_twitter', 'https://x.com/beethovenpets', 'social');

COMMIT;
