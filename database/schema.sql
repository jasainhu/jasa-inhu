-- ========================================================
-- DATABASE SCHEMA: JASA INHU
-- Marketplace Jasa Lokal Kabupaten Indragiri Hulu, Riau
-- Compatible with MySQL 5.0+ / 8.0+ / MariaDB 10.x+
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS banners;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS service_request_responses;
DROP TABLE IF EXISTS service_requests;
DROP TABLE IF EXISTS service_areas;
DROP TABLE IF EXISTS service_providers;
DROP TABLE IF EXISTS service_categories;
DROP TABLE IF EXISTS profiles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS villages;
DROP TABLE IF EXISTS districts;
DROP TABLE IF EXISTS regencies;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- 1. Table: roles
-- --------------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 2. Table: regencies (Kabupaten)
-- --------------------------------------------------------
CREATE TABLE regencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    province_name VARCHAR(100) NOT NULL DEFAULT 'Riau',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 3. Table: districts (Kecamatan)
-- --------------------------------------------------------
CREATE TABLE districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    regency_id INT NOT NULL,
    code VARCHAR(20) NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_districts_regency (regency_id),
    CONSTRAINT fk_districts_regency FOREIGN KEY (regency_id) REFERENCES regencies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 4. Table: villages (Desa / Kelurahan)
-- --------------------------------------------------------
CREATE TABLE villages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    district_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    postal_code VARCHAR(10) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_villages_district (district_id),
    CONSTRAINT fk_villages_district FOREIGN KEY (district_id) REFERENCES districts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 5. Table: users
-- --------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    email_verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_users_role (role_id),
    KEY idx_users_phone (phone),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 6. Table: profiles
-- --------------------------------------------------------
CREATE TABLE profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    avatar VARCHAR(255) NULL,
    bio TEXT NULL,
    gender VARCHAR(20) NULL,
    birth_date DATE NULL,
    address TEXT NULL,
    district_id INT NULL,
    village_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_profiles_district (district_id),
    KEY idx_profiles_village (village_id),
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_profiles_district FOREIGN KEY (district_id) REFERENCES districts (id) ON DELETE SET NULL,
    CONSTRAINT fk_profiles_village FOREIGN KEY (village_id) REFERENCES villages (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 7. Table: service_categories
-- --------------------------------------------------------
CREATE TABLE service_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    icon VARCHAR(100) DEFAULT 'fa-wrench',
    description VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_categories_active (is_active),
    KEY idx_categories_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 8. Table: service_providers
-- --------------------------------------------------------
CREATE TABLE service_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    primary_category_id INT NOT NULL,
    business_name VARCHAR(150) NOT NULL,
    headline VARCHAR(255) NULL,
    description TEXT NULL,
    experience_years INT DEFAULT 1,
    hourly_rate_min DECIMAL(12,2) DEFAULT 0,
    hourly_rate_max DECIMAL(12,2) DEFAULT 0,
    id_card_number VARCHAR(50) NULL,
    is_verified TINYINT(1) DEFAULT 0,
    rating_avg DECIMAL(3,2) DEFAULT 5.00,
    reviews_count INT DEFAULT 0,
    completed_jobs INT DEFAULT 0,
    address TEXT NULL,
    district_id INT NULL,
    village_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_providers_category (primary_category_id),
    KEY idx_providers_district (district_id),
    KEY idx_providers_verified (is_verified),
    CONSTRAINT fk_providers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_providers_category FOREIGN KEY (primary_category_id) REFERENCES service_categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_providers_district FOREIGN KEY (district_id) REFERENCES districts (id) ON DELETE SET NULL,
    CONSTRAINT fk_providers_village FOREIGN KEY (village_id) REFERENCES villages (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 9. Table: service_areas
-- --------------------------------------------------------
CREATE TABLE service_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    district_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provider_district (provider_id, district_id),
    KEY idx_areas_district (district_id),
    CONSTRAINT fk_areas_provider FOREIGN KEY (provider_id) REFERENCES service_providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_areas_district FOREIGN KEY (district_id) REFERENCES districts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 10. Table: service_requests
-- --------------------------------------------------------
CREATE TABLE service_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    provider_id INT NULL,                 -- NULL = Permintaan terbuka (lelang), Terisi = Pesan langsung ke penyedia tertentu
    district_id INT NOT NULL,
    village_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    budget DECIMAL(12,2) NULL,
    address_detail VARCHAR(255) NULL,
    urgency VARCHAR(30) DEFAULT 'normal', -- normal, urgent, scheduled
    status VARCHAR(30) DEFAULT 'open',    -- open, in_progress, completed, cancelled
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_requests_user (user_id),
    KEY idx_requests_category (category_id),
    KEY idx_requests_provider (provider_id),
    KEY idx_requests_district (district_id),
    KEY idx_requests_status (status),
    CONSTRAINT fk_requests_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_requests_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_requests_provider FOREIGN KEY (provider_id) REFERENCES service_providers (id) ON DELETE SET NULL,
    CONSTRAINT fk_requests_district FOREIGN KEY (district_id) REFERENCES districts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_requests_village FOREIGN KEY (village_id) REFERENCES villages (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 11. Table: service_request_responses
-- --------------------------------------------------------
CREATE TABLE service_request_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    provider_id INT NOT NULL,
    offer_price DECIMAL(12,2) NOT NULL,
    estimated_duration VARCHAR(100) NULL,
    message TEXT NOT NULL,
    status VARCHAR(30) DEFAULT 'pending', -- pending, accepted, rejected, withdrawn
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_responses_request (request_id),
    KEY idx_responses_provider (provider_id),
    KEY idx_responses_status (status),
    CONSTRAINT fk_responses_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_responses_provider FOREIGN KEY (provider_id) REFERENCES service_providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 12. Table: reviews
-- --------------------------------------------------------
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    user_id INT NOT NULL,
    provider_id INT NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reviews_provider (provider_id),
    KEY idx_reviews_user (user_id),
    CONSTRAINT fk_reviews_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_provider FOREIGN KEY (provider_id) REFERENCES service_providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 13. Table: notifications
-- --------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user (user_id, is_read),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 14. Table: reports
-- --------------------------------------------------------
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_user_id INT NOT NULL,
    reported_user_id INT NOT NULL,
    request_id INT NULL,
    reason VARCHAR(200) NOT NULL,
    details TEXT NULL,
    status VARCHAR(30) DEFAULT 'pending', -- pending, reviewed, resolved, dismissed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reports_status (status),
    CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_reports_reported FOREIGN KEY (reported_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_reports_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- 15. Table: banners (Promo & Pengumuman Beranda)
-- --------------------------------------------------------
CREATE TABLE banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    subtitle VARCHAR(255) NULL,
    badge_text VARCHAR(50) NULL DEFAULT 'PENGUMUMAN',
    badge_color VARCHAR(30) NULL DEFAULT '#0d9488',
    image_url VARCHAR(255) NULL,
    link_url VARCHAR(255) NULL,
    button_text VARCHAR(50) NULL DEFAULT 'Lihat Selengkapnya',
    show_overlay TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    KEY idx_banners_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
