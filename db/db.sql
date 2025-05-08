CREATE DATABASE volunteer_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE volunteer_system;

-- Volunteers table
CREATE TABLE volunteers (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(100) NOT NULL,
                            email VARCHAR(100) UNIQUE NOT NULL,
                            password VARCHAR(255) NOT NULL,
                            phone VARCHAR(20),
                            avatar VARCHAR(255),
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Organizers table
CREATE TABLE organizers (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(100) NOT NULL,
                            email VARCHAR(100) UNIQUE NOT NULL,
                            password VARCHAR(255) NOT NULL,
                            organization VARCHAR(100),
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Events table
CREATE TABLE events (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(100) NOT NULL,
                        description TEXT,
                        event_date DATETIME NOT NULL,
                        max_participants INT NOT NULL,
                        organizer_id INT,
                        status ENUM('active', 'completed', 'recurring') DEFAULT 'active',
                        hours INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (organizer_id) REFERENCES organizers(id)
);

-- Event registrations
CREATE TABLE registrations (
                               id INT AUTO_INCREMENT PRIMARY KEY,
                               volunteer_id INT,
                               event_id INT,
                               registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                               FOREIGN KEY (volunteer_id) REFERENCES volunteers(id),
                               FOREIGN KEY (event_id) REFERENCES events(id),
                               UNIQUE (volunteer_id, event_id)
);

-- Activity reports
CREATE TABLE reports (
                         id INT AUTO_INCREMENT PRIMARY KEY,
                         volunteer_id INT,
                         event_id INT,
                         tasks_completed INT DEFAULT 0,
                         rating DECIMAL(3,1) DEFAULT 0.0,
                         hours INT DEFAULT 0,
                         comments TEXT,
                         reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                         FOREIGN KEY (volunteer_id) REFERENCES volunteers(id),
                         FOREIGN KEY (event_id) REFERENCES events(id)
);

-- Subscriptions (volunteers follow organizers)
CREATE TABLE subscriptions (
                               id INT AUTO_INCREMENT PRIMARY KEY,
                               volunteer_id INT,
                               organizer_id INT,
                               created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                               FOREIGN KEY (volunteer_id) REFERENCES volunteers(id),
                               FOREIGN KEY (organizer_id) REFERENCES organizers(id),
                               UNIQUE (volunteer_id, organizer_id)
);

-- News (organizer posts)
CREATE TABLE news (
                      id INT AUTO_INCREMENT PRIMARY KEY,
                      organizer_id INT,
                      title VARCHAR(100) NOT NULL,
                      content TEXT NOT NULL,
                      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                      FOREIGN KEY (organizer_id) REFERENCES organizers(id)
);

-- Achievements
CREATE TABLE achievements (
                              id INT AUTO_INCREMENT PRIMARY KEY,
                              user_id INT,
                              user_type ENUM('volunteer', 'organizer') NOT NULL,
                              title VARCHAR(100) NOT NULL,
                              description TEXT,
                              awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);