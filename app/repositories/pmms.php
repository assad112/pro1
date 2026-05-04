<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

function create_user(string $name, string $email, string $password, string $role): bool
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = run_query(
        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
        'ssss',
        [$name, $email, $hash, $role]
    );
    return $stmt->affected_rows > 0;
}

function find_user_by_email(string $email): ?array
{
    $stmt = run_query('SELECT * FROM users WHERE email = ? LIMIT 1', 's', [$email]);
    return fetch_one_assoc($stmt);
}

function create_service_request(int $clientId, string $title, string $category, float $budget, string $location, string $dueDate): bool
{
    $stmt = run_query(
        'INSERT INTO service_requests (client_id, title, category, budget, location, due_date) VALUES (?, ?, ?, ?, ?, ?)',
        'issdss',
        [$clientId, $title, $category, $budget, $location, $dueDate]
    );
    return $stmt->affected_rows > 0;
}

function get_open_requests(): array
{
    $stmt = run_query(
        'SELECT r.*, u.name AS client_name
         FROM service_requests r
         JOIN users u ON u.id = r.client_id
         WHERE r.status = "open"
         ORDER BY r.created_at DESC'
    );
    return fetch_all_assoc($stmt);
}

function get_requests_by_client(int $clientId): array
{
    $stmt = run_query(
        'SELECT r.*,
            (SELECT COUNT(*) FROM bids b WHERE b.request_id = r.id) AS bids_count
         FROM service_requests r
         WHERE r.client_id = ?
         ORDER BY r.created_at DESC',
        'i',
        [$clientId]
    );
    return fetch_all_assoc($stmt);
}

function submit_bid(int $requestId, int $providerId, float $price, int $durationDays, string $details): bool
{
    $stmt = run_query(
        'INSERT INTO bids (request_id, provider_id, price, duration_days, details) VALUES (?, ?, ?, ?, ?)',
        'iidis',
        [$requestId, $providerId, $price, $durationDays, $details]
    );
    return $stmt->affected_rows > 0;
}

function get_service_request_status(int $requestId): ?string
{
    $row = fetch_one_assoc(run_query('SELECT status FROM service_requests WHERE id = ? LIMIT 1', 'i', [$requestId]));
    return $row['status'] ?? null;
}

function provider_has_bid(int $requestId, int $providerId): bool
{
    $row = fetch_one_assoc(
        run_query(
            'SELECT id FROM bids WHERE request_id = ? AND provider_id = ? LIMIT 1',
            'ii',
            [$requestId, $providerId]
        )
    );
    return $row !== null;
}

function get_bids_for_request(int $requestId): array
{
    $stmt = run_query(
        'SELECT b.*, u.name AS provider_name,
            (SELECT ROUND(AVG(r.rating), 2) FROM reviews r WHERE r.provider_id = b.provider_id) AS rating_avg
         FROM bids b
         JOIN users u ON u.id = b.provider_id
         WHERE b.request_id = ?
         ORDER BY b.price ASC, b.created_at ASC',
        'i',
        [$requestId]
    );
    return fetch_all_assoc($stmt);
}

function get_bids_by_provider(int $providerId): array
{
    $stmt = run_query(
        'SELECT b.*, r.title AS request_title, r.status AS request_status
         FROM bids b
         JOIN service_requests r ON r.id = b.request_id
         WHERE b.provider_id = ?
         ORDER BY b.created_at DESC',
        'i',
        [$providerId]
    );
    return fetch_all_assoc($stmt);
}

function get_request_for_client(int $requestId, int $clientId): ?array
{
    $stmt = run_query('SELECT * FROM service_requests WHERE id = ? AND client_id = ? LIMIT 1', 'ii', [$requestId, $clientId]);
    return fetch_one_assoc($stmt);
}

function get_bid_for_request(int $bidId, int $requestId): ?array
{
    $stmt = run_query('SELECT * FROM bids WHERE id = ? AND request_id = ? LIMIT 1', 'ii', [$bidId, $requestId]);
    return fetch_one_assoc($stmt);
}

function get_active_promotion_for_provider(int $providerId): ?array
{
    $stmt = run_query(
        'SELECT * FROM promotions
         WHERE provider_id = ?
           AND is_active = 1
           AND CURDATE() BETWEEN start_date AND end_date
         ORDER BY created_at DESC
         LIMIT 1',
        'i',
        [$providerId]
    );
    return fetch_one_assoc($stmt);
}

function accept_bid_and_start_request(int $requestId, int $bidId, int $providerId, float $price): bool
{
    db()->begin_transaction();
    try {
        run_query('UPDATE bids SET status = "rejected" WHERE request_id = ?', 'i', [$requestId]);
        run_query('UPDATE bids SET status = "accepted" WHERE id = ? AND request_id = ?', 'ii', [$bidId, $requestId]);
        run_query(
            'UPDATE service_requests
             SET status = "in_progress", selected_provider_id = ?, selected_bid_id = ?
             WHERE id = ?',
            'iii',
            [$providerId, $bidId, $requestId]
        );

        run_query(
            'INSERT INTO conversations (request_id, client_id, provider_id)
             SELECT id, client_id, selected_provider_id
             FROM service_requests
             WHERE id = ?',
            'i',
            [$requestId]
        );

        $discountAmount = 0.0;
        $promo = get_active_promotion_for_provider($providerId);
        if ($promo) {
            $value = (float) $promo['discount_value'];
            if ($promo['discount_type'] === 'percent') {
                $discountAmount = round($price * ($value / 100), 2);
            } else {
                $discountAmount = min($value, $price);
            }
        }
        $finalPrice = max($price - $discountAmount, 0);

        run_query(
            'INSERT INTO transactions (request_id, original_price, discount_amount, final_price) VALUES (?, ?, ?, ?)',
            'iddd',
            [$requestId, $price, $discountAmount, $finalPrice]
        );
        db()->commit();
        return true;
    } catch (Throwable $e) {
        db()->rollback();
        return false;
    }
}

function complete_request(int $requestId, int $clientId): bool
{
    $stmt = run_query(
        'UPDATE service_requests SET status = "completed" WHERE id = ? AND client_id = ? AND status = "in_progress"',
        'ii',
        [$requestId, $clientId]
    );
    return $stmt->affected_rows > 0;
}

function get_user_conversations(int $userId): array
{
    $stmt = run_query(
        'SELECT c.*, r.title,
            cu.name AS client_name,
            pu.name AS provider_name
         FROM conversations c
         JOIN service_requests r ON r.id = c.request_id
         JOIN users cu ON cu.id = c.client_id
         JOIN users pu ON pu.id = c.provider_id
         WHERE c.client_id = ? OR c.provider_id = ?
         ORDER BY c.created_at DESC',
        'ii',
        [$userId, $userId]
    );
    return fetch_all_assoc($stmt);
}

function get_conversation_for_user(int $conversationId, int $userId): ?array
{
    $stmt = run_query(
        'SELECT * FROM conversations WHERE id = ? AND (client_id = ? OR provider_id = ?) LIMIT 1',
        'iii',
        [$conversationId, $userId, $userId]
    );
    return fetch_one_assoc($stmt);
}

function get_messages(int $conversationId): array
{
    $stmt = run_query(
        'SELECT m.*, u.name AS sender_name
         FROM messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.conversation_id = ?
         ORDER BY m.sent_at ASC',
        'i',
        [$conversationId]
    );
    return fetch_all_assoc($stmt);
}

function send_message(int $conversationId, int $senderId, string $body): bool
{
    $stmt = run_query(
        'INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)',
        'iis',
        [$conversationId, $senderId, $body]
    );
    return $stmt->affected_rows > 0;
}

function get_completed_requests_for_review(int $clientId): array
{
    $stmt = run_query(
        'SELECT r.id, r.title, r.selected_provider_id, u.name AS provider_name
         FROM service_requests r
         JOIN users u ON u.id = r.selected_provider_id
         LEFT JOIN reviews rv ON rv.request_id = r.id
         WHERE r.client_id = ?
           AND r.status = "completed"
           AND rv.id IS NULL
         ORDER BY r.created_at DESC',
        'i',
        [$clientId]
    );
    return fetch_all_assoc($stmt);
}

function add_review(int $requestId, int $clientId, int $providerId, int $rating, string $comment): bool
{
    $stmt = run_query(
        'INSERT INTO reviews (request_id, client_id, provider_id, rating, comment)
         SELECT r.id, r.client_id, r.selected_provider_id, ?, ?
         FROM service_requests r
         LEFT JOIN reviews rv ON rv.request_id = r.id
         WHERE r.id = ?
           AND r.client_id = ?
           AND r.selected_provider_id = ?
           AND r.status = "completed"
           AND rv.id IS NULL',
        'isiii',
        [$rating, $comment, $requestId, $clientId, $providerId]
    );
    return $stmt->affected_rows > 0;
}

function get_provider_reviews(int $providerId): array
{
    $stmt = run_query(
        'SELECT r.*, u.name AS client_name, sr.title
         FROM reviews r
         JOIN users u ON u.id = r.client_id
         JOIN service_requests sr ON sr.id = r.request_id
         WHERE r.provider_id = ?
         ORDER BY r.created_at DESC',
        'i',
        [$providerId]
    );
    return fetch_all_assoc($stmt);
}

function create_promotion(int $providerId, string $title, string $type, float $value, string $startDate, string $endDate): bool
{
    $stmt = run_query(
        'INSERT INTO promotions (provider_id, title, discount_type, discount_value, start_date, end_date, is_active)
         VALUES (?, ?, ?, ?, ?, ?, 1)',
        'issdss',
        [$providerId, $title, $type, $value, $startDate, $endDate]
    );
    return $stmt->affected_rows > 0;
}

function get_provider_promotions(int $providerId): array
{
    $stmt = run_query(
        'SELECT * FROM promotions WHERE provider_id = ? ORDER BY created_at DESC',
        'i',
        [$providerId]
    );
    return fetch_all_assoc($stmt);
}

function toggle_promotion(int $promotionId, int $providerId, int $active): bool
{
    $stmt = run_query(
        'UPDATE promotions SET is_active = ? WHERE id = ? AND provider_id = ?',
        'iii',
        [$active, $promotionId, $providerId]
    );
    return $stmt->affected_rows > 0;
}

function admin_counts(): array
{
    $data = [];
    $data['users'] = (int) (fetch_one_assoc(run_query('SELECT COUNT(*) AS c FROM users'))['c'] ?? 0);
    $data['requests'] = (int) (fetch_one_assoc(run_query('SELECT COUNT(*) AS c FROM service_requests'))['c'] ?? 0);
    $data['bids'] = (int) (fetch_one_assoc(run_query('SELECT COUNT(*) AS c FROM bids'))['c'] ?? 0);
    $data['transactions'] = (int) (fetch_one_assoc(run_query('SELECT COUNT(*) AS c FROM transactions'))['c'] ?? 0);
    return $data;
}

function admin_latest_users(): array
{
    return fetch_all_assoc(run_query('SELECT id, name, email, role, created_at FROM users ORDER BY id DESC LIMIT 10'));
}

function admin_latest_requests(): array
{
    return fetch_all_assoc(
        run_query(
            'SELECT r.id, r.title, r.status, r.budget, u.name AS client_name, r.created_at
             FROM service_requests r
             JOIN users u ON u.id = r.client_id
             ORDER BY r.id DESC LIMIT 10'
        )
    );
}

function admin_latest_bids(): array
{
    return fetch_all_assoc(
        run_query(
            'SELECT b.id, b.price, b.duration_days, b.status, r.title,
                    client.name AS client_name, provider.name AS provider_name
             FROM bids b
             JOIN service_requests r ON r.id = b.request_id
             JOIN users client ON client.id = r.client_id
             JOIN users provider ON provider.id = b.provider_id
             ORDER BY b.id DESC LIMIT 10'
        )
    );
}

function admin_latest_transactions(): array
{
    return fetch_all_assoc(
        run_query(
            'SELECT t.*, r.title
             FROM transactions t
             JOIN service_requests r ON r.id = t.request_id
             ORDER BY t.id DESC LIMIT 10'
        )
    );
}
