USE pmms;
SET NAMES utf8mb4;

INSERT INTO users (name, email, password_hash, role) VALUES
('Admin User', 'admin@pmms.local', '$2y$10$Ui/d08GLH4Weikwm9bMqjObsqFJTUg.Dxn1geLiHDg3yk/5N69LdW', 'admin'),
('Client User', 'client@pmms.local', '$2y$10$Ui/d08GLH4Weikwm9bMqjObsqFJTUg.Dxn1geLiHDg3yk/5N69LdW', 'client'),
('Provider User', 'provider@pmms.local', '$2y$10$Ui/d08GLH4Weikwm9bMqjObsqFJTUg.Dxn1geLiHDg3yk/5N69LdW', 'provider');

SET @client_id = (SELECT id FROM users WHERE email = 'client@pmms.local');
SET @provider_id = (SELECT id FROM users WHERE email = 'provider@pmms.local');

INSERT INTO service_requests (client_id, title, category, budget, location, due_date, status) VALUES
(@client_id, 'تصميم شعار لمتجر', 'تصميم', 500.00, 'سلطنة عمان', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'open'),
(@client_id, 'تصميم موقع تعريفي', 'برمجة', 900.00, 'مسقط', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'in_progress'),
(@client_id, 'تنظيف مكتب شهري', 'تنظيف', 300.00, 'صلالة', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'completed');

SET @open_request_id = (SELECT id FROM service_requests WHERE title = 'تصميم شعار لمتجر' ORDER BY id LIMIT 1);
SET @active_request_id = (SELECT id FROM service_requests WHERE title = 'تصميم موقع تعريفي' ORDER BY id LIMIT 1);
SET @completed_request_id = (SELECT id FROM service_requests WHERE title = 'تنظيف مكتب شهري' ORDER BY id LIMIT 1);

INSERT INTO bids (request_id, provider_id, price, duration_days, details, status) VALUES
(@open_request_id, @provider_id, 450.00, 5, 'سأقدم ثلاثة نماذج للشعار مع تعديلات مفتوحة.', 'pending'),
(@active_request_id, @provider_id, 900.00, 10, 'تصميم موقع متجاوب مع لوحة بسيطة لإدارة المحتوى.', 'accepted'),
(@completed_request_id, @provider_id, 280.00, 2, 'تنظيف شامل للمكتب مع تعقيم الأسطح.', 'accepted');

SET @active_bid_id = (SELECT id FROM bids WHERE request_id = @active_request_id AND provider_id = @provider_id LIMIT 1);
SET @completed_bid_id = (SELECT id FROM bids WHERE request_id = @completed_request_id AND provider_id = @provider_id LIMIT 1);

UPDATE service_requests
SET selected_provider_id = @provider_id, selected_bid_id = @active_bid_id
WHERE id = @active_request_id;

UPDATE service_requests
SET selected_provider_id = @provider_id, selected_bid_id = @completed_bid_id
WHERE id = @completed_request_id;

INSERT INTO promotions (provider_id, title, discount_type, discount_value, start_date, end_date, is_active)
VALUES (@provider_id, 'خصم ترحيبي 20%', 'percent', 20.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1);

INSERT INTO conversations (request_id, client_id, provider_id) VALUES
(@active_request_id, @client_id, @provider_id),
(@completed_request_id, @client_id, @provider_id);

SET @active_conversation_id = (SELECT id FROM conversations WHERE request_id = @active_request_id LIMIT 1);

INSERT INTO messages (conversation_id, sender_id, body) VALUES
(@active_conversation_id, @client_id, 'أهلاً، متى تبدأ تنفيذ الموقع؟'),
(@active_conversation_id, @provider_id, 'أبدأ اليوم وأرسل أول نسخة خلال ثلاثة أيام.');

INSERT INTO transactions (request_id, original_price, discount_amount, final_price) VALUES
(@active_request_id, 900.00, 180.00, 720.00),
(@completed_request_id, 280.00, 56.00, 224.00);

INSERT INTO reviews (request_id, client_id, provider_id, rating, comment)
VALUES (@completed_request_id, @client_id, @provider_id, 5, 'خدمة ممتازة وسريعة.');
