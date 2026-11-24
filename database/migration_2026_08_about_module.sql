-- =====================================================================
-- About SABNAHIS module (2026-08)
--
-- Adds a content-managed "About SABNAHIS" public page. All content is
-- editable from the Administrator dashboard (admin/about_content.php,
-- about_core_values.php, about_facilities.php, about_gallery.php,
-- about_downloads.php) - nothing here requires touching source code.
--
-- Run this file in phpMyAdmin (or `mysql -u root sabnahis_enrollment <
-- migration_2026_08_about_module.sql`) against the existing
-- sabnahis_enrollment database. Safe to run once; re-running will fail
-- on the CREATE TABLE statements since IF NOT EXISTS is used below.
-- =====================================================================

-- about_school: singleton row (id always = 1) holding every "one per
-- site" content block - Hero Banner, About School, History, Vision,
-- Mission, Principal's Message, Contact Info, Google Map, Social Media.
CREATE TABLE IF NOT EXISTS about_school (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,

    -- Section 1: Hero Banner
    banner_image VARCHAR(255) NULL,
    logo_image VARCHAR(255) NULL,
    school_name VARCHAR(150) NULL,
    school_motto VARCHAR(255) NULL,
    short_description VARCHAR(500) NULL,

    -- Section 2: About the School (rich text HTML)
    about_title VARCHAR(150) NULL,
    about_description MEDIUMTEXT NULL,

    -- Section 3: School History
    history_title VARCHAR(150) NULL,
    history_content MEDIUMTEXT NULL,
    history_image VARCHAR(255) NULL,

    -- Sections 4-5: Vision / Mission (rich text HTML)
    vision MEDIUMTEXT NULL,
    mission MEDIUMTEXT NULL,

    -- Section 9: Principal's Message
    principal_name VARCHAR(150) NULL,
    principal_position VARCHAR(150) NULL,
    principal_photo VARCHAR(255) NULL,
    principal_message MEDIUMTEXT NULL,
    principal_signature VARCHAR(255) NULL,

    -- Section 10: Contact Information
    contact_address VARCHAR(255) NULL,
    contact_telephone VARCHAR(50) NULL,
    contact_mobile VARCHAR(50) NULL,
    contact_email VARCHAR(150) NULL,
    contact_office_hours VARCHAR(150) NULL,
    contact_website VARCHAR(150) NULL,

    -- Section 11: Google Map (either a pasted <iframe> embed code or a
    -- plain Google Maps URL; about_map_src() in functions.php extracts
    -- a safe iframe src from either at render time)
    map_embed TEXT NULL,

    -- Section 12: Social Media
    social_facebook VARCHAR(255) NULL,
    social_messenger VARCHAR(255) NULL,
    social_youtube VARCHAR(255) NULL,
    social_instagram VARCHAR(255) NULL,
    social_tiktok VARCHAR(255) NULL,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO about_school (id) VALUES (1);

-- Section 6: Core Values (repeatable - Makadiyos, Makatao, ...)
CREATE TABLE IF NOT EXISTS about_core_values (
    core_value_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    icon VARCHAR(50) NULL,
    title VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Section 7: School Facilities (Library, Science Lab, ...)
CREATE TABLE IF NOT EXISTS about_facilities (
    facility_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    image VARCHAR(255) NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Section 8: Photo Gallery
CREATE TABLE IF NOT EXISTS about_gallery (
    gallery_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image VARCHAR(255) NOT NULL,
    title VARCHAR(150) NULL,
    caption VARCHAR(500) NULL,
    category VARCHAR(100) NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Section 14: Downloadable Files (Student Handbook, Enrollment
-- Guidelines, School Calendar, School Profile) - PDF only.
CREATE TABLE IF NOT EXISTS about_downloads (
    download_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
