-- ══════════════════════════════════════════════════════════════
--  hoteldb — Full Schema (Tables + Functions + Triggers + Procedures)
--  mysql -u root -p < hoteldb.sql
-- ══════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS hoteldb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hoteldb;

-- ──────────────────────────────────────────────
--  TABLES
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

CREATE TABLE IF NOT EXISTS rooms (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_type       VARCHAR(60)      NOT NULL,
    price_per_night DECIMAL(10,2)    NOT NULL,
    max_guests      TINYINT UNSIGNED DEFAULT 2,
    description     VARCHAR(200),
    is_available    TINYINT(1)       DEFAULT 1
) ENGINE=InnoDB;

INSERT IGNORE INTO rooms (room_type, price_per_night, max_guests, description) VALUES
('Deluxe Room',         4500.00, 2, '35 m² · King or Twin · City view · Sleeps 2'),
('Junior Suite',        7200.00, 3, '55 m² · King bed · Garden view · Sitting lounge'),
('Premier Suite',      11500.00, 4, '75 m² · King bed · Skyline view · Walk-in closet'),
('Presidential Suite', 18000.00, 6, '120 m² · Master bedroom · Panoramic · Private butler');

CREATE TABLE IF NOT EXISTS food_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category    VARCHAR(40)  NOT NULL,
    name        VARCHAR(100) NOT NULL,
    price       DECIMAL(8,2) NOT NULL,
    description VARCHAR(200)
) ENGINE=InnoDB;

INSERT IGNORE INTO food_items (category, name, price, description) VALUES
('Breakfast','Filipino Breakfast Set', 350.00,'Sinangag, itlog, longganisa & coffee'),
('Breakfast','Continental Breakfast',  280.00,'Croissant, jam, fresh fruit, OJ'),
('Breakfast','American Breakfast',     420.00,'Pancakes, bacon, eggs, maple syrup'),
('Breakfast','Healthy Granola Bowl',   220.00,'Oats, fresh berries, honey, milk'),
('Lunch','Kare-Kare Platter',          580.00,'Oxtail stew, shrimp paste, puso ng saging'),
('Lunch','Grilled Sea Bass',           650.00,'Lemon herb, capers, seasonal greens'),
('Lunch','Caesar Salad & Sandwich',    390.00,'Romaine, croutons, parmesan, chicken'),
('Lunch','Pasta Aglio e Olio',         440.00,'Garlic, olive oil, chili flakes, parsley'),
('Dinner','Wagyu Beef Steak',         1850.00,'200g A4 wagyu, truffle butter, fries'),
('Dinner','Seafood Platter',          1200.00,'Prawns, mussels, squid, garlic sauce'),
('Dinner','Chicken Inasal',            480.00,'Char-grilled, java rice, atchara'),
('Dinner','Vegetarian Tasting Menu',   690.00,'4-course plant-based dining experience'),
('Desserts & Drinks','Leche Flan',     180.00,'Classic creamy caramel custard'),
('Desserts & Drinks','Mango Float',    210.00,'Layers of cream, graham, fresh mango'),
('Desserts & Drinks','Sparkling Wine Bottle', 1400.00,'Chilled upon check-in, with 2 glasses'),
('Desserts & Drinks','Fresh Fruit Basket',     350.00,'Seasonal tropical fruits, daily refreshed');

CREATE TABLE IF NOT EXISTS reservations (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_number       VARCHAR(20)  NOT NULL UNIQUE,
    guest_id         INT UNSIGNED NOT NULL,
    room_id          INT UNSIGNED NOT NULL,
    check_in         DATE         NOT NULL,
    check_out        DATE         NOT NULL,
    nights           TINYINT UNSIGNED NOT NULL DEFAULT 1,
    num_guests       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    bed_preference   VARCHAR(40),
    floor_preference VARCHAR(20),
    special_notes    TEXT,
    room_total       DECIMAL(10,2) NOT NULL DEFAULT 0,
    food_total       DECIMAL(10,2) NOT NULL DEFAULT 0,
    grand_total      DECIMAL(10,2) NOT NULL DEFAULT 0,
    status           ENUM('pending','confirmed','checked_in','checked_out','cancelled') DEFAULT 'confirmed',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE RESTRICT,
    FOREIGN KEY (room_id)  REFERENCES rooms(id)  ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservation_foods (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    food_item_id   INT UNSIGNED NOT NULL,
    quantity       TINYINT UNSIGNED DEFAULT 1,
    unit_price     DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (food_item_id)   REFERENCES food_items(id)   ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservation_logs (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT UNSIGNED NOT NULL,
    action         VARCHAR(60)  NOT NULL,
    old_status     VARCHAR(30),
    new_status     VARCHAR(30),
    notes          TEXT,
    logged_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ──────────────────────────────────────────────
--  FUNCTIONS + TRIGGERS + PROCEDURES
-- ──────────────────────────────────────────────

DELIMITER $$

-- ── fn_nights_count ────────────────────────────
CREATE FUNCTION fn_nights_count(p_check_in DATE, p_check_out DATE)
RETURNS INT DETERMINISTIC NO SQL
BEGIN
    RETURN GREATEST(0, DATEDIFF(p_check_out, p_check_in));
END$$

-- ── fn_room_total ──────────────────────────────
CREATE FUNCTION fn_room_total(p_room_type VARCHAR(60), p_check_in DATE, p_check_out DATE)
RETURNS DECIMAL(10,2) DETERMINISTIC READS SQL DATA
BEGIN
    DECLARE v_price  DECIMAL(10,2) DEFAULT 0;
    DECLARE v_nights INT           DEFAULT 0;
    SELECT price_per_night INTO v_price FROM rooms WHERE room_type = p_room_type LIMIT 1;
    SET v_nights = DATEDIFF(p_check_out, p_check_in);
    IF v_nights <= 0 THEN RETURN 0; END IF;
    RETURN v_price * v_nights;
END$$

-- ── fn_food_total ──────────────────────────────
CREATE FUNCTION fn_food_total(p_ref VARCHAR(20))
RETURNS DECIMAL(10,2) DETERMINISTIC READS SQL DATA
BEGIN
    DECLARE v_total DECIMAL(10,2) DEFAULT 0;
    SELECT COALESCE(SUM(rf.quantity * rf.unit_price), 0) INTO v_total
    FROM   reservation_foods rf
    JOIN   reservations r ON r.id = rf.reservation_id
    WHERE  r.ref_number = p_ref;
    RETURN v_total;
END$$

-- ── fn_grand_total ─────────────────────────────
CREATE FUNCTION fn_grand_total(p_ref VARCHAR(20))
RETURNS DECIMAL(10,2) DETERMINISTIC READS SQL DATA
BEGIN
    DECLARE v_room DECIMAL(10,2) DEFAULT 0;
    SELECT fn_room_total(rm.room_type, r.check_in, r.check_out) INTO v_room
    FROM   reservations r JOIN rooms rm ON rm.id = r.room_id
    WHERE  r.ref_number = p_ref LIMIT 1;
    RETURN v_room + fn_food_total(p_ref);
END$$

-- ── fn_total_revenue_all ───────────────────────
CREATE FUNCTION fn_total_revenue_all()
RETURNS DECIMAL(12,2) DETERMINISTIC READS SQL DATA
BEGIN
    DECLARE v_total DECIMAL(12,2) DEFAULT 0;
    SELECT COALESCE(SUM(grand_total), 0) INTO v_total
    FROM   reservations WHERE status NOT IN ('cancelled');
    RETURN v_total;
END$$

-- ── fn_total_revenue_by_room ───────────────────
CREATE FUNCTION fn_total_revenue_by_room(p_room_type VARCHAR(60))
RETURNS DECIMAL(12,2) DETERMINISTIC READS SQL DATA
BEGIN
    DECLARE v_total DECIMAL(12,2) DEFAULT 0;
    SELECT COALESCE(SUM(r.grand_total), 0) INTO v_total
    FROM   reservations r JOIN rooms rm ON rm.id = r.room_id
    WHERE  rm.room_type = p_room_type AND r.status NOT IN ('cancelled');
    RETURN v_total;
END$$

-- ── fn_total_revenue_by_guest ──────────────────
CREATE FUNCTION fn_total_revenue_by_guest(p_email VARCHAR(160))
RETURNS DECIMAL(12,2) DETERMINISTIC READS SQL DATA
BEGIN
    DECLARE v_total DECIMAL(12,2) DEFAULT 0;
    SELECT COALESCE(SUM(r.grand_total), 0) INTO v_total
    FROM   reservations r JOIN guests g ON g.id = r.guest_id
    WHERE  g.email = p_email AND r.status NOT IN ('cancelled');
    RETURN v_total;
END$$

-- ── TRIGGER: auto ref_number ───────────────────
CREATE TRIGGER trg_reservation_ref_before_insert
BEFORE INSERT ON reservations FOR EACH ROW
BEGIN
    IF NEW.ref_number IS NULL OR NEW.ref_number = '' THEN
        SET NEW.ref_number = CONCAT('GMP-', YEAR(NOW()), '-', LPAD(FLOOR(RAND() * 99999), 5, '0'));
    END IF;
END$$

-- ── TRIGGER: log status changes ────────────────
CREATE TRIGGER trg_reservation_log_status_change
AFTER UPDATE ON reservations FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO reservation_logs (reservation_id, action, old_status, new_status, notes)
        VALUES (NEW.id, 'STATUS_CHANGE', OLD.status, NEW.status,
                CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END$$

-- ── TRIGGER: log on insert ─────────────────────
CREATE TRIGGER trg_reservation_log_on_insert
AFTER INSERT ON reservations FOR EACH ROW
BEGIN
    INSERT INTO reservation_logs (reservation_id, action, new_status, notes)
    VALUES (NEW.id, 'CREATED', NEW.status, CONCAT('Reservation created. Ref: ', NEW.ref_number));
END$$

-- ── TRIGGER: recalc grand_total on update ──────
CREATE TRIGGER trg_reservation_recalc_total
BEFORE UPDATE ON reservations FOR EACH ROW
BEGIN
    SET NEW.grand_total = NEW.room_total + NEW.food_total;
END$$

-- ── TRIGGER: prevent double booking (INSERT) ───
CREATE TRIGGER trg_prevent_double_booking_insert
BEFORE INSERT ON reservations FOR EACH ROW
BEGIN
    DECLARE v_count  INT          DEFAULT 0;
    DECLARE v_ref    VARCHAR(20)  DEFAULT '';
    DECLARE v_cin    DATE;
    DECLARE v_cout   DATE;
    DECLARE v_msg    VARCHAR(255) DEFAULT '';

    SELECT COUNT(*), COALESCE(MIN(ref_number), '')
    INTO   v_count, v_ref
    FROM   reservations
    WHERE  room_id    =  NEW.room_id
      AND  status    NOT IN ('cancelled')
      AND  check_in  <   NEW.check_out
      AND  check_out >   NEW.check_in;

    IF v_count > 0 THEN
        SELECT check_in, check_out INTO v_cin, v_cout
        FROM   reservations WHERE ref_number = v_ref LIMIT 1;
        SET v_msg = CONCAT('DOUBLE_BOOKING: Room reserved from ',
                           CAST(v_cin AS CHAR), ' to ', CAST(v_cout AS CHAR),
                           '. Ref: ', v_ref);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;
END$$

-- ── TRIGGER: prevent double booking (UPDATE) ───
CREATE TRIGGER trg_prevent_double_booking_update
BEFORE UPDATE ON reservations FOR EACH ROW
BEGIN
    DECLARE v_count INT          DEFAULT 0;
    DECLARE v_ref   VARCHAR(20)  DEFAULT '';
    DECLARE v_msg   VARCHAR(255) DEFAULT '';

    IF NEW.status != 'cancelled'
    AND (NEW.room_id != OLD.room_id OR NEW.check_in != OLD.check_in OR NEW.check_out != OLD.check_out)
    THEN
        SELECT COUNT(*), COALESCE(MIN(ref_number), '')
        INTO   v_count, v_ref
        FROM   reservations
        WHERE  room_id    =  NEW.room_id
          AND  id        !=  OLD.id
          AND  status    NOT IN ('cancelled')
          AND  check_in  <   NEW.check_out
          AND  check_out >   NEW.check_in;

        IF v_count > 0 THEN
            SET v_msg = CONCAT('DOUBLE_BOOKING: Cannot update — room already booked. Ref: ', v_ref);
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
    END IF;
END$$

-- ── sp_create_reservation ──────────────────────
CREATE PROCEDURE sp_create_reservation(
    IN  p_first_name  VARCHAR(80),   IN  p_last_name   VARCHAR(80),
    IN  p_email       VARCHAR(160),  IN  p_phone       VARCHAR(30),
    IN  p_nationality VARCHAR(60),   IN  p_id_number   VARCHAR(60),
    IN  p_room_type   VARCHAR(60),   IN  p_check_in    DATE,
    IN  p_check_out   DATE,          IN  p_num_guests  TINYINT UNSIGNED,
    IN  p_bed_pref    VARCHAR(40),   IN  p_floor_pref  VARCHAR(20),
    IN  p_notes       TEXT,          IN  p_food_names  TEXT,
    OUT p_ref_number  VARCHAR(20)
)
BEGIN
    DECLARE v_guest_id       INT UNSIGNED;
    DECLARE v_room_id        INT UNSIGNED;
    DECLARE v_room_price     DECIMAL(10,2);
    DECLARE v_nights         INT;
    DECLARE v_room_total     DECIMAL(10,2);
    DECLARE v_reservation_id INT UNSIGNED;
    DECLARE v_food_id        INT UNSIGNED;
    DECLARE v_food_price     DECIMAL(8,2);
    DECLARE v_food_item      VARCHAR(100);
    DECLARE v_pos            INT;
    DECLARE v_remaining      TEXT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

    START TRANSACTION;

    -- Upsert guest
    INSERT INTO guests (first_name, last_name, email, phone, nationality, id_number)
    VALUES (p_first_name, p_last_name, p_email, p_phone, p_nationality, p_id_number)
    ON DUPLICATE KEY UPDATE
        first_name = VALUES(first_name), last_name  = VALUES(last_name),
        phone      = VALUES(phone),      nationality = VALUES(nationality),
        id_number  = VALUES(id_number);

    SELECT id INTO v_guest_id FROM guests WHERE email = p_email LIMIT 1;
    SELECT id, price_per_night INTO v_room_id, v_room_price
    FROM   rooms WHERE room_type = p_room_type LIMIT 1;

    SET v_nights     = fn_nights_count(p_check_in, p_check_out);
    SET v_room_total = fn_room_total(p_room_type, p_check_in, p_check_out);

    -- Insert reservation (triggers fire here)
    INSERT INTO reservations
        (ref_number, guest_id, room_id, check_in, check_out, nights,
         num_guests, bed_preference, floor_preference, special_notes,
         room_total, food_total, grand_total, status)
    VALUES ('', v_guest_id, v_room_id, p_check_in, p_check_out, v_nights,
            p_num_guests, p_bed_pref, p_floor_pref, p_notes,
            v_room_total, 0, v_room_total, 'confirmed');

    SET v_reservation_id = LAST_INSERT_ID();

    -- Parse food names and insert line items
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
                INSERT INTO reservation_foods (reservation_id, food_item_id, quantity, unit_price)
                VALUES (v_reservation_id, v_food_id, 1, v_food_price);
            END IF;
        END IF;
    END WHILE;

    -- Final totals via functions
    SELECT ref_number INTO p_ref_number FROM reservations WHERE id = v_reservation_id;
    UPDATE reservations
    SET    food_total  = fn_food_total(p_ref_number),
           grand_total = fn_grand_total(p_ref_number)
    WHERE  id = v_reservation_id;

    COMMIT;
END$$

-- ── sp_get_reservation ─────────────────────────
CREATE PROCEDURE sp_get_reservation(IN p_ref VARCHAR(20))
BEGIN
    SELECT r.ref_number, r.status, r.check_in, r.check_out, r.nights,
           r.num_guests, r.bed_preference, r.floor_preference, r.special_notes,
           r.room_total, r.food_total, r.grand_total, r.created_at,
           g.first_name, g.last_name, g.email, g.phone, g.nationality,
           rm.room_type, rm.price_per_night
    FROM   reservations r
    JOIN   guests g  ON g.id  = r.guest_id
    JOIN   rooms  rm ON rm.id = r.room_id
    WHERE  r.ref_number = p_ref;

    SELECT fi.category, fi.name, rf.quantity, rf.unit_price,
           (rf.quantity * rf.unit_price) AS line_total
    FROM   reservation_foods rf
    JOIN   food_items fi  ON fi.id  = rf.food_item_id
    JOIN   reservations r ON r.id   = rf.reservation_id
    WHERE  r.ref_number = p_ref;
END$$

-- ── sp_cancel_reservation ──────────────────────
CREATE PROCEDURE sp_cancel_reservation(IN p_ref VARCHAR(20), OUT p_result VARCHAR(60))
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