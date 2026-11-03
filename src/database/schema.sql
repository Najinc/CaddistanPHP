-- Schéma de la base Caddistan
SET NAMES utf8mb4;

CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    first_name    VARCHAR(100) NOT NULL,
    last_name     VARCHAR(100) NOT NULL,
    role          ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id       INT UNSIGNED NOT NULL,
    name              VARCHAR(150) NOT NULL,
    slug              VARCHAR(170) NOT NULL UNIQUE,
    short_description VARCHAR(255) NOT NULL DEFAULT '',
    description       TEXT NOT NULL,
    price_cents       INT UNSIGNED NOT NULL,
    stock             INT UNSIGNED NOT NULL DEFAULT 0,
    image             VARCHAR(255) NULL,
    datasheet         VARCHAR(255) NULL,
    is_active         TINYINT(1) NOT NULL DEFAULT 1,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id),
    INDEX idx_products_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    status           ENUM('pending', 'paid', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'paid',
    total_cents      INT UNSIGNED NOT NULL,
    shipping_name    VARCHAR(200) NOT NULL,
    shipping_address VARCHAR(255) NOT NULL,
    shipping_zip     VARCHAR(10) NOT NULL,
    shipping_city    VARCHAR(100) NOT NULL,
    shipping_phone   VARCHAR(20) NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id         INT UNSIGNED NOT NULL,
    product_id       INT UNSIGNED NULL,
    product_name     VARCHAR(150) NOT NULL,
    unit_price_cents INT UNSIGNED NOT NULL,
    quantity         INT UNSIGNED NOT NULL,
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    rating     TINYINT UNSIGNED NOT NULL,
    comment    TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
