<?php

return function ($pdo){
    $pdo->exec("CREATE TABLE IF NOT EXISTS event_gallery (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(255) NOT NULL,
            image VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id)
                REFERENCES events(event_id)
                ON DELETE CASCADE
        );
    ");
};