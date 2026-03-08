-- ══════════════════════════════════════════════════════════════
--  hoteldb  —  Full Schema
--  Tables + Stored Procedures + Triggers
--  Run this once against your MySQL/MariaDB server:
--    mysql -u root -p hoteldb < schema.sql
-- ══════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS hoteldb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hoteldb;

-- ──────────────────────────────────────────────
--  1. GUESTS
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS guests (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name  VARCHAR(80)  NOT NULL,
    last_name   VARCHAR(80)  NOT NULL,
    email       VARCHAR(160) NOT NULL,
    phone       VARCHAR(30)  NOT NULL,
    nationality VARCHAR(60),
    id_number   VARCHAR(60),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_guest_email (email)
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────
--  2. ROOMS  (master catalogue)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rooms (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_type    VARCHAR(60)    NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL,
    max_guests   TINYINT UNSIGNED DEFAULT 2,
    description  VARCHAR(200),
    is_available TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- Seed room catalogue
INSERT IGNORE INTO rooms (room_type, price_per_night, max_guests, description) VALUES
('Deluxe Room',        4500.00, 2, '35 m² · King or Twin · City view · Sleeps 2'),
('Junior Suite',       7200.00, 3, '55 m² · King bed · Garden view · Sitting lounge'),
('Premier Suite',     11500.00, 4, '75 m² · King bed · Skyline view · Walk-in closet'),
('Presidential Suite',18000.00, 6, '120 m² · Master bedroom · Panoramic · Private butler');

-- ──────────────────────────────────────────────
--  3. FOOD ITEMS  (master catalogue)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS food_items (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(40) NOT NULL,
    name     VARCHAR(100) NOT NULL,
    price    DECIMAL(8,2) NOT NULL,
    description VARCHAR(200)
) ENGINE=InnoDB;

INSERT IGNORE INTO food_items (category, name, price, description) VALUES
('Breakfast','Filipino Breakfast Set', 350.00,'Sinangag, itlog, longganisa & coffee'),
('Breakfast','Continental Breakfast',  280.00,'Croissant, jam, fresh fruit, OJ'),
('Breakfast','American Breakfast',     420.00,'Pancakes, bacon, eggs, maple syrup'),
('Breakfast','Healthy Granola Bowl',   220.00,'Oats, fresh berries, honey, milk'),
('Lunch',   'Kare-Kare Platter',       580.00,'Oxtail stew, shrimp paste, puso ng saging'),
('Lunch',   'Grilled Sea Bass',        650.00,'Lemon herb, capers, seasonal greens'),
('Lunch',   'Caesar Salad & Sandwich', 390.00,'Romaine, croutons, parmesan, chicken'),
('Lunch',   'Pasta Aglio e Olio',      440.00,'Garlic, olive oil, chili flakes, parsley'),
('Dinner',  'Wagyu Beef Steak',       1850.00,'200g A4 wagyu, truffle butter, fries'),
('Dinner',  'Seafood Platter',        1200.00,'Prawns, mussels, squid, garlic sauce'),
('Dinner',  'Chicken Inasal',          480.00,'Char-grilled, java rice, atchara'),
('Dinner',  'Vegetarian Tasting Menu', 690.00,'4-course plant-based dining experience'),
('Desserts & Drinks','Leche Flan',     180.00,'Classic creamy caramel custard'),
('Desserts & Drinks','Mango Float',    210.00,'Layers of cream, graham, fresh mango'),
('Desserts & Drinks','Sparkling Wine Bottle',1400.00,'Chilled upon check-in, with 2 glasses'),
('Desserts & Drinks','Fresh Fruit Basket',   350.00,'Seasonal tropical fruits, daily refreshed');

-- ──────────────────────────────────────────────
--  4. RESERVATIONS
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reservations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_number      VARCHAR(20)  NOT NULL UNIQUE,
    guest_id        INT UNSIGNED NOT NULL,
    room_id         INT UNSIGNED NOT NULL,
    check_in        DATE         NOT NULL,
    check_out       DATE         NOT NULL,
    nights          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    num_guests      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    bed_preference  VARCHAR(40),
    floor_preference VARCHAR(20),
    special_notes   TEXT,
    room_total      DECIMAL(10,2) NOT NULL DEFAULT 0,
    food_total      DECIMAL(10,2) NOT NULL DEFAULT 0,
    grand_total     DECIMAL(10,2) NOT NULL DEFAULT 0,
    status          ENUM('pending','confirmed','checked_in','checked_out','cancelled') DEFAULT 'confirmed',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE RESTRICT,
    FOREIGN KEY (room_id)  REFERENCES rooms(id)  ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────
--  5. RESERVATION FOOD ORDERS  (line items)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reservation_foods (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    food_item_id   INT UNSIGNED NOT NULL,
    quantity       TINYINT UNSIGNED DEFAULT 1,
    unit_price     DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (food_item_id)   REFERENCES food_items(id)   ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────
--  6. RESERVATION LOGS  (audit trail)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reservation_logs (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    action         VARCHAR(60)  NOT NULL,
    old_status     VARCHAR(30),
    new_status     VARCHAR(30),
    notes          TEXT,
    logged_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ══════════════════════════════════════════════
--  TRIGGERS
-- ══════════════════════════════════════════════

DELIMITER $$

-- ① Auto-generate ref_number before INSERT
CREATE TRIGGER trg_reservation_ref_before_insert
BEFORE INSERT ON reservations
FOR EACH ROW
BEGIN
    IF NEW.ref_number IS NULL OR NEW.ref_number = '' THEN
        SET NEW.ref_number = CONCAT(
            'GMP-',
            YEAR(NOW()),
            '-',
            LPAD(FLOOR(RAND() * 99999), 5, '0')
        );
    END IF;
END$$

-- ② Log every status change on reservations
CREATE TRIGGER trg_reservation_log_status_change
AFTER UPDATE ON reservations
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO reservation_logs (reservation_id, action, old_status, new_status, notes)
        VALUES (NEW.id, 'STATUS_CHANGE', OLD.status, NEW.status,
                CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END$$

-- ③ Log when a reservation is first created
CREATE TRIGGER trg_reservation_log_on_insert
AFTER INSERT ON reservations
FOR EACH ROW
BEGIN
    INSERT INTO reservation_logs (reservation_id, action, new_status, notes)
    VALUES (NEW.id, 'CREATED', NEW.status,
            CONCAT('Reservation created. Ref: ', NEW.ref_number));
END$$

-- ④ Recalculate grand_total when food_total is updated
CREATE TRIGGER trg_reservation_recalc_total
BEFORE UPDATE ON reservations
FOR EACH ROW
BEGIN
    SET NEW.grand_total = NEW.room_total + NEW.food_total;
END$$

DELIMITER ;


-- ══════════════════════════════════════════════
--  STORED PROCEDURES
-- ══════════════════════════════════════════════

DELIMITER $$

-- ─────────────────────────────────────────────
-- sp_create_reservation
--   Wraps guest upsert + reservation insert
--   + food order lines in a single transaction.
--
--   Params:
--     p_first_name, p_last_name, p_email, p_phone,
--     p_nationality, p_id_number,
--     p_room_type, p_check_in, p_check_out,
--     p_num_guests, p_bed_pref, p_floor_pref, p_notes,
--     p_food_names  — comma-separated food item names
--
--   OUT p_ref_number  — generated ref returned to PHP
-- ─────────────────────────────────────────────
CREATE PROCEDURE sp_create_reservation(
    IN  p_first_name  VARCHAR(80),
    IN  p_last_name   VARCHAR(80),
    IN  p_email       VARCHAR(160),
    IN  p_phone       VARCHAR(30),
    IN  p_nationality VARCHAR(60),
    IN  p_id_number   VARCHAR(60),
    IN  p_room_type   VARCHAR(60),
    IN  p_check_in    DATE,
    IN  p_check_out   DATE,
    IN  p_num_guests  TINYINT UNSIGNED,
    IN  p_bed_pref    VARCHAR(40),
    IN  p_floor_pref  VARCHAR(20),
    IN  p_notes       TEXT,
    IN  p_food_names  TEXT,          -- e.g. 'Leche Flan,Wagyu Beef Steak'
    OUT p_ref_number  VARCHAR(20)
)
BEGIN
    DECLARE v_guest_id      INT UNSIGNED;
    DECLARE v_room_id       INT UNSIGNED;
    DECLARE v_room_price    DECIMAL(10,2);
    DECLARE v_nights        TINYINT UNSIGNED;
    DECLARE v_room_total    DECIMAL(10,2);
    DECLARE v_food_total    DECIMAL(10,2) DEFAULT 0;
    DECLARE v_reservation_id INT UNSIGNED;
    DECLARE v_food_id       INT UNSIGNED;
    DECLARE v_food_price    DECIMAL(8,2);
    DECLARE v_food_item     VARCHAR(100);
    DECLARE v_pos           INT;
    DECLARE v_remaining     TEXT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- 1. Upsert guest
    INSERT INTO guests (first_name, last_name, email, phone, nationality, id_number)
    VALUES (p_first_name, p_last_name, p_email, p_phone, p_nationality, p_id_number)
    ON DUPLICATE KEY UPDATE
        first_name  = VALUES(first_name),
        last_name   = VALUES(last_name),
        phone       = VALUES(phone),
        nationality = VALUES(nationality),
        id_number   = VALUES(id_number);

    SELECT id INTO v_guest_id FROM guests WHERE email = p_email LIMIT 1;

    -- 2. Get room
    SELECT id, price_per_night
    INTO   v_room_id, v_room_price
    FROM   rooms
    WHERE  room_type = p_room_type
    LIMIT  1;

    -- 3. Calculate nights & room total
    SET v_nights     = DATEDIFF(p_check_out, p_check_in);
    SET v_room_total = v_room_price * v_nights;

    -- 4. Insert reservation (ref_number filled by trigger)
    INSERT INTO reservations
        (ref_number, guest_id, room_id, check_in, check_out, nights,
         num_guests, bed_preference, floor_preference, special_notes,
         room_total, food_total, grand_total, status)
    VALUES
        ('', v_guest_id, v_room_id, p_check_in, p_check_out, v_nights,
         p_num_guests, p_bed_pref, p_floor_pref, p_notes,
         v_room_total, 0, v_room_total, 'confirmed');

    SET v_reservation_id = LAST_INSERT_ID();

    -- 5. Parse comma-separated food names & insert line items
    SET v_remaining = p_food_names;
    WHILE LENGTH(TRIM(v_remaining)) > 0 DO
        SET v_pos = LOCATE(',', v_remaining);
        IF v_pos > 0 THEN
            SET v_food_item  = TRIM(SUBSTRING(v_remaining, 1, v_pos - 1));
            SET v_remaining  = TRIM(SUBSTRING(v_remaining, v_pos + 1));
        ELSE
            SET v_food_item = TRIM(v_remaining);
            SET v_remaining = '';
        END IF;

        IF LENGTH(v_food_item) > 0 THEN
            SELECT id, price INTO v_food_id, v_food_price
            FROM   food_items WHERE name = v_food_item LIMIT 1;

            IF v_food_id IS NOT NULL THEN
                INSERT INTO reservation_foods (reservation_id, food_item_id, quantity, unit_price)
                VALUES (v_reservation_id, v_food_id, 1, v_food_price);
                SET v_food_total = v_food_total + v_food_price;
            END IF;
        END IF;
    END WHILE;

    -- 6. Update food & grand totals
    UPDATE reservations
    SET    food_total  = v_food_total,
           grand_total = v_room_total + v_food_total
    WHERE  id = v_reservation_id;

    -- 7. Return ref number
    SELECT ref_number INTO p_ref_number
    FROM   reservations WHERE id = v_reservation_id;

    COMMIT;
END$$


-- ─────────────────────────────────────────────
-- sp_get_reservation
--   Returns full reservation details by ref.
-- ─────────────────────────────────────────────
CREATE PROCEDURE sp_get_reservation(IN p_ref VARCHAR(20))
BEGIN
    SELECT
        r.ref_number,
        r.status,
        r.check_in,
        r.check_out,
        r.nights,
        r.num_guests,
        r.bed_preference,
        r.floor_preference,
        r.special_notes,
        r.room_total,
        r.food_total,
        r.grand_total,
        r.created_at,
        g.first_name,
        g.last_name,
        g.email,
        g.phone,
        g.nationality,
        rm.room_type,
        rm.price_per_night
    FROM reservations r
    JOIN guests g  ON g.id  = r.guest_id
    JOIN rooms  rm ON rm.id = r.room_id
    WHERE r.ref_number = p_ref;

    -- Food orders for this reservation
    SELECT fi.category, fi.name, rf.quantity, rf.unit_price,
           (rf.quantity * rf.unit_price) AS line_total
    FROM   reservation_foods rf
    JOIN   food_items fi ON fi.id = rf.food_item_id
    JOIN   reservations res ON res.id = rf.reservation_id
    WHERE  res.ref_number = p_ref;
END$$


-- ─────────────────────────────────────────────
-- sp_cancel_reservation
--   Safely cancels a reservation by ref number.
-- ─────────────────────────────────────────────
CREATE PROCEDURE sp_cancel_reservation(
    IN  p_ref    VARCHAR(20),
    OUT p_result VARCHAR(60)
)
BEGIN
    DECLARE v_id     INT UNSIGNED;
    DECLARE v_status VARCHAR(30);

    SELECT id, status INTO v_id, v_status
    FROM   reservations WHERE ref_number = p_ref LIMIT 1;

    IF v_id IS NULL THEN
        SET p_result = 'NOT_FOUND';
    ELSEIF v_status IN ('checked_in','checked_out') THEN
        SET p_result = 'CANNOT_CANCEL';
    ELSE
        UPDATE reservations SET status = 'cancelled' WHERE id = v_id;
        SET p_result = 'CANCELLED';
    END IF;
END$$

DELIMITER ;

-- ══════════════════════════════════════════════════════════════
--  anti_double_booking.sql  (fixed — no CONCAT inside SIGNAL)
--  mysql -u root -p hoteldb < anti_double_booking.sql
-- ══════════════════════════════════════════════════════════════

USE hoteldb;

DELIMITER $$

-- ══════════════════════════════════════════════════════════════
--  TRIGGER 1: BEFORE INSERT — block if room already booked
-- ══════════════════════════════════════════════════════════════
DROP TRIGGER IF EXISTS trg_prevent_double_booking_insert$$

CREATE TRIGGER trg_prevent_double_booking_insert
BEFORE INSERT ON reservations
FOR EACH ROW
BEGIN
    DECLARE v_conflict_count INT     DEFAULT 0;
    DECLARE v_conflict_ref   VARCHAR(20) DEFAULT '';
    DECLARE v_conflict_in    DATE;
    DECLARE v_conflict_out   DATE;
    DECLARE v_msg            VARCHAR(255) DEFAULT '';

    -- Find any active overlapping reservation for the same room
    SELECT COUNT(*), COALESCE(MIN(ref_number), '')
    INTO   v_conflict_count, v_conflict_ref
    FROM   reservations
    WHERE  room_id    =  NEW.room_id
      AND  status    NOT IN ('cancelled')
      AND  check_in  <  NEW.check_out
      AND  check_out >  NEW.check_in;

    IF v_conflict_count > 0 THEN
        -- Fetch conflicting dates into declared variables first
        SELECT check_in, check_out
        INTO   v_conflict_in, v_conflict_out
        FROM   reservations
        WHERE  ref_number = v_conflict_ref
        LIMIT  1;

        -- Build the message string into a variable
        -- SIGNAL SET MESSAGE_TEXT does NOT allow CONCAT() directly
        SET v_msg = CONCAT(
            'DOUBLE_BOOKING: Room already reserved from ',
            CAST(v_conflict_in  AS CHAR), ' to ',
            CAST(v_conflict_out AS CHAR),
            '. Ref: ', v_conflict_ref
        );

        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;
END$$


-- ══════════════════════════════════════════════════════════════
--  TRIGGER 2: BEFORE UPDATE — block date / room changes that
--             would clash with existing bookings
-- ══════════════════════════════════════════════════════════════
DROP TRIGGER IF EXISTS trg_prevent_double_booking_update$$

CREATE TRIGGER trg_prevent_double_booking_update
BEFORE UPDATE ON reservations
FOR EACH ROW
BEGIN
    DECLARE v_conflict_count INT DEFAULT 0;
    DECLARE v_conflict_ref   VARCHAR(20) DEFAULT '';
    DECLARE v_msg            VARCHAR(255) DEFAULT '';

    -- Only run when dates / room actually change and row is not being cancelled
    IF NEW.status != 'cancelled'
    AND (
        NEW.room_id   != OLD.room_id   OR
        NEW.check_in  != OLD.check_in  OR
        NEW.check_out != OLD.check_out
    )
    THEN
        SELECT COUNT(*), COALESCE(MIN(ref_number), '')
        INTO   v_conflict_count, v_conflict_ref
        FROM   reservations
        WHERE  room_id    =  NEW.room_id
          AND  id        !=  OLD.id
          AND  status    NOT IN ('cancelled')
          AND  check_in  <   NEW.check_out
          AND  check_out >   NEW.check_in;

        IF v_conflict_count > 0 THEN
            SET v_msg = CONCAT(
                'DOUBLE_BOOKING: Cannot update — room already booked. Ref: ',
                v_conflict_ref
            );
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
    END IF;
END$$

DELIMITER ;


-- ══════════════════════════════════════════════════════════════
--  REPLACE sp_create_reservation
--  (EXIT HANDLER resignals the DOUBLE_BOOKING error to PHP)
-- ══════════════════════════════════════════════════════════════

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_create_reservation$$

CREATE PROCEDURE sp_create_reservation(
    IN  p_first_name  VARCHAR(80),
    IN  p_last_name   VARCHAR(80),
    IN  p_email       VARCHAR(160),
    IN  p_phone       VARCHAR(30),
    IN  p_nationality VARCHAR(60),
    IN  p_id_number   VARCHAR(60),
    IN  p_room_type   VARCHAR(60),
    IN  p_check_in    DATE,
    IN  p_check_out   DATE,
    IN  p_num_guests  TINYINT UNSIGNED,
    IN  p_bed_pref    VARCHAR(40),
    IN  p_floor_pref  VARCHAR(20),
    IN  p_notes       TEXT,
    IN  p_food_names  TEXT,
    OUT p_ref_number  VARCHAR(20)
)
BEGIN
    DECLARE v_guest_id       INT UNSIGNED;
    DECLARE v_room_id        INT UNSIGNED;
    DECLARE v_room_price     DECIMAL(10,2);
    DECLARE v_nights         TINYINT UNSIGNED;
    DECLARE v_room_total     DECIMAL(10,2);
    DECLARE v_food_total     DECIMAL(10,2) DEFAULT 0;
    DECLARE v_reservation_id INT UNSIGNED;
    DECLARE v_food_id        INT UNSIGNED;
    DECLARE v_food_price     DECIMAL(8,2);
    DECLARE v_food_item      VARCHAR(100);
    DECLARE v_pos            INT;
    DECLARE v_remaining      TEXT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- 1. Upsert guest
    INSERT INTO guests (first_name, last_name, email, phone, nationality, id_number)
    VALUES (p_first_name, p_last_name, p_email, p_phone, p_nationality, p_id_number)
    ON DUPLICATE KEY UPDATE
        first_name  = VALUES(first_name),
        last_name   = VALUES(last_name),
        phone       = VALUES(phone),
        nationality = VALUES(nationality),
        id_number   = VALUES(id_number);

    SELECT id INTO v_guest_id FROM guests WHERE email = p_email LIMIT 1;

    -- 2. Get room
    SELECT id, price_per_night
    INTO   v_room_id, v_room_price
    FROM   rooms WHERE room_type = p_room_type LIMIT 1;

    -- 3. Nights & totals
    SET v_nights     = DATEDIFF(p_check_out, p_check_in);
    SET v_room_total = v_room_price * v_nights;

    -- 4. Insert reservation — double-booking trigger fires here
    INSERT INTO reservations
        (ref_number, guest_id, room_id, check_in, check_out, nights,
         num_guests, bed_preference, floor_preference, special_notes,
         room_total, food_total, grand_total, status)
    VALUES
        ('', v_guest_id, v_room_id, p_check_in, p_check_out, v_nights,
         p_num_guests, p_bed_pref, p_floor_pref, p_notes,
         v_room_total, 0, v_room_total, 'confirmed');

    SET v_reservation_id = LAST_INSERT_ID();

    -- 5. Food line items
    SET v_remaining = p_food_names;
    WHILE LENGTH(TRIM(v_remaining)) > 0 DO
        SET v_pos = LOCATE(',', v_remaining);
        IF v_pos > 0 THEN
            SET v_food_item = TRIM(SUBSTRING(v_remaining, 1, v_pos - 1));
            SET v_remaining = TRIM(SUBSTRING(v_remaining, v_pos + 1));
        ELSE
            SET v_food_item = TRIM(v_remaining);
            SET v_remaining = '';
        END IF;

        IF LENGTH(v_food_item) > 0 THEN
            SELECT id, price INTO v_food_id, v_food_price
            FROM   food_items WHERE name = v_food_item LIMIT 1;

            IF v_food_id IS NOT NULL THEN
                INSERT INTO reservation_foods
                    (reservation_id, food_item_id, quantity, unit_price)
                VALUES (v_reservation_id, v_food_id, 1, v_food_price);
                SET v_food_total = v_food_total + v_food_price;
            END IF;
        END IF;
    END WHILE;

    -- 6. Update totals
    UPDATE reservations
    SET    food_total  = v_food_total,
           grand_total = v_room_total + v_food_total
    WHERE  id = v_reservation_id;

    -- 7. Return ref
    SELECT ref_number INTO p_ref_number
    FROM   reservations WHERE id = v_reservation_id;

    COMMIT;
END$$

DELIMITER ;