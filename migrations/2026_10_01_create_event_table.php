<?php

return function ($pdo){
    $pdo->exec("CREATE TABLE IF NOT EXISTS events (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_id VARCHAR(255) NOT NULL UNIQUE,
        user_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        short_description VARCHAR(255),
        category VARCHAR(255),
        cover_image VARCHAR(255),
        event_date DATE NOT NULL,
        event_time TIME NOT NULL,
        event_location VARCHAR(255),
        event_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
};