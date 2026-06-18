CREATE TABLE IF NOT EXISTS users (
    id          SERIAL PRIMARY KEY,
    email       VARCHAR(255) UNIQUE NOT NULL,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    password    VARCHAR(255) NOT NULL,
    token       VARCHAR(255) UNIQUE,
    expired     TIMESTAMP,
    is_admin    BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS courts (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS bookings (
    id               SERIAL PRIMARY KEY,
    created_by       INT NOT NULL,
    court_id         INT NOT NULL,
    booking_datetime TIMESTAMP NOT NULL,
    duration_blocks  INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (court_id)   REFERENCES courts(id)
);

CREATE TABLE IF NOT EXISTS booking_participants (
    id         SERIAL PRIMARY KEY,
    booking_id INT NOT NULL,
    user_id    INT NOT NULL,
    CONSTRAINT unique_booking_user UNIQUE (booking_id, user_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (user_id)    REFERENCES users(id)
);
