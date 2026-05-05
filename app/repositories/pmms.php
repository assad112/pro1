<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

function real_user_filter(string $alias): string
{
    return $alias . ".email NOT LIKE '%@pmms.local'";
}

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

function get_open_requests(bool $realUsersOnly = false): array
{
    $where = 'r.status = "open"';
    if ($realUsersOnly) {
        $where .= ' AND ' . real_user_filter('u');
    }

    $stmt = run_query(
        'SELECT r.*, u.name AS client_name
         FROM service_requests r
         JOIN users u ON u.id = r.client_id
         WHERE ' . $where . '
         ORDER BY r.created_at DESC'
    );
    return fetch_all_assoc($stmt);
}

function get_requests_by_client(int $clientId, bool $realUsersOnly = false): array
{
    $where = 'r.client_id = ?';
    $join = '';
    if ($realUsersOnly) {
        $join = ' JOIN users client ON client.id = r.client_id';
        $where .= ' AND ' . real_user_filter('client');
    }

    $stmt = run_query(
        'SELECT r.*,
            (SELECT COUNT(*) FROM bids b WHERE b.request_id = r.id) AS bids_count
         FROM service_requests r
         ' . $join . '
         WHERE ' . $where . '
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

function get_bids_for_request(int $requestId, bool $realUsersOnly = false): array
{
    $where = 'b.request_id = ?';
    $joins = '';
    if ($realUsersOnly) {
        $joins = ' JOIN service_requests sr ON sr.id = b.request_id
                   JOIN users client ON client.id = sr.client_id';
        $where .= ' AND ' . real_user_filter('u') . ' AND ' . real_user_filter('client');
    }

    $stmt = run_query(
        'SELECT b.*, u.name AS provider_name,
            (SELECT ROUND(AVG(r.rating), 2) FROM reviews r WHERE r.provider_id = b.provider_id) AS rating_avg
         FROM bids b
         JOIN users u ON u.id = b.provider_id
         ' . $joins . '
         WHERE ' . $where . '
         ORDER BY b.price ASC, b.created_at ASC',
        'i',
        [$requestId]
    );
    return fetch_all_assoc($stmt);
}

function get_bids_by_provider(int $providerId, bool $realUsersOnly = false): array
{
    $where = 'b.provider_id = ?';
    $joins = '';
    if ($realUsersOnly) {
        $joins = ' JOIN users provider ON provider.id = b.provider_id
                   JOIN users client ON client.id = r.client_id';
        $where .= ' AND ' . real_user_filter('provider') . ' AND ' . real_user_filter('client');
    }

    $stmt = run_query(
        'SELECT b.*, r.title AS request_title, r.status AS request_status
         FROM bids b
         JOIN service_requests r ON r.id = b.request_id
         ' . $joins . '
         WHERE ' . $where . '
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

function get_user_conversations(int $userId, bool $realUsersOnly = false): array
{
    $where = '(c.client_id = ? OR c.provider_id = ?)';
    if ($realUsersOnly) {
        $where .= ' AND ' . real_user_filter('cu') . ' AND ' . real_user_filter('pu');
    }

    $stmt = run_query(
        'SELECT c.*, r.title,
            cu.name AS client_name,
            pu.name AS provider_name
         FROM conversations c
         JOIN service_requests r ON r.id = c.request_id
         JOIN users cu ON cu.id = c.client_id
         JOIN users pu ON pu.id = c.provider_id
         WHERE ' . $where . '
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

function get_completed_requests_for_review(int $clientId, bool $realUsersOnly = false): array
{
    $where = 'r.client_id = ?
           AND r.status = "completed"
           AND rv.id IS NULL';
    if ($realUsersOnly) {
        $where .= ' AND ' . real_user_filter('client') . ' AND ' . real_user_filter('u');
    }

    $stmt = run_query(
        'SELECT r.id, r.title, r.selected_provider_id, u.name AS provider_name
         FROM service_requests r
         JOIN users client ON client.id = r.client_id
         JOIN users u ON u.id = r.selected_provider_id
         LEFT JOIN reviews rv ON rv.request_id = r.id
         WHERE ' . $where . '
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

function get_provider_reviews(int $providerId, bool $realUsersOnly = false): array
{
    $where = 'r.provider_id = ?';
    if ($realUsersOnly) {
        $where .= ' AND ' . real_user_filter('provider') . ' AND ' . real_user_filter('u');
    }

    $stmt = run_query(
        'SELECT r.*, u.name AS client_name, sr.title
         FROM reviews r
         JOIN users u ON u.id = r.client_id
         JOIN users provider ON provider.id = r.provider_id
         JOIN service_requests sr ON sr.id = r.request_id
         WHERE ' . $where . '
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

function get_provider_promotions(int $providerId, bool $realUsersOnly = false): array
{
    $where = 'p.provider_id = ?';
    $join = '';
    if ($realUsersOnly) {
        $join = ' JOIN users provider ON provider.id = p.provider_id';
        $where .= ' AND ' . real_user_filter('provider');
    }

    $stmt = run_query(
        'SELECT p.* FROM promotions p
         ' . $join . '
         WHERE ' . $where . '
         ORDER BY p.created_at DESC',
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

function provider_analytics(int $providerId): array
{
    // Provider dashboard is scoped to the signed-in provider only.
    $realProvider = real_user_filter('provider');
    $realClient = real_user_filter('client');

    $bidSummary = fetch_one_assoc(
        run_query(
            "SELECT
                COUNT(*) AS total_bids,
                COALESCE(SUM(b.status = 'accepted'), 0) AS accepted_bids,
                COALESCE(SUM(b.status = 'pending'), 0) AS pending_bids,
                COALESCE(SUM(b.status = 'rejected'), 0) AS rejected_bids,
                COALESCE(AVG(b.price), 0) AS average_bid
             FROM bids b
             JOIN users provider ON provider.id = b.provider_id
             JOIN service_requests r ON r.id = b.request_id
             JOIN users client ON client.id = r.client_id
             WHERE b.provider_id = ?
               AND {$realProvider}
               AND {$realClient}",
            'i',
            [$providerId]
        )
    ) ?: [];

    $revenueSummary = fetch_one_assoc(
        run_query(
            "SELECT
                COUNT(t.id) AS sales_count,
                COALESCE(SUM(t.final_price), 0) AS total_revenue,
                COALESCE(SUM(t.discount_amount), 0) AS total_discount
             FROM service_requests r
             JOIN users provider ON provider.id = r.selected_provider_id
             JOIN users client ON client.id = r.client_id
             LEFT JOIN transactions t ON t.request_id = r.id
             WHERE r.selected_provider_id = ?
               AND {$realProvider}
               AND {$realClient}",
            'i',
            [$providerId]
        )
    ) ?: [];

    $reviewSummary = fetch_one_assoc(
        run_query(
            "SELECT COUNT(*) AS reviews_count, COALESCE(ROUND(AVG(rating), 2), 0) AS average_rating
             FROM reviews rv
             JOIN users provider ON provider.id = rv.provider_id
             JOIN users client ON client.id = rv.client_id
             WHERE rv.provider_id = ?
               AND {$realProvider}
               AND {$realClient}",
            'i',
            [$providerId]
        )
    ) ?: [];

    $promotionSummary = fetch_one_assoc(
        run_query(
            "SELECT
                COUNT(*) AS promotions_count,
                COALESCE(SUM(is_active = 1 AND CURDATE() BETWEEN start_date AND end_date), 0) AS active_promotions
             FROM promotions p
             JOIN users provider ON provider.id = p.provider_id
             WHERE p.provider_id = ?
               AND {$realProvider}",
            'i',
            [$providerId]
        )
    ) ?: [];

    $monthlyRevenue = fetch_all_assoc(
        run_query(
            "SELECT
                DATE_FORMAT(t.created_at, '%Y-%m') AS month_key,
                DATE_FORMAT(t.created_at, '%b') AS month_label,
                COALESCE(SUM(t.final_price), 0) AS revenue
             FROM transactions t
             JOIN service_requests r ON r.id = t.request_id
             JOIN users provider ON provider.id = r.selected_provider_id
             JOIN users client ON client.id = r.client_id
             WHERE r.selected_provider_id = ?
               AND {$realProvider}
               AND {$realClient}
               AND t.created_at >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
             GROUP BY DATE_FORMAT(t.created_at, '%Y-%m'), DATE_FORMAT(t.created_at, '%b')
             ORDER BY month_key",
            'i',
            [$providerId]
        )
    );

    $bidStatus = fetch_all_assoc(
        run_query(
            "SELECT b.status, COUNT(*) AS total
             FROM bids b
             JOIN users provider ON provider.id = b.provider_id
             JOIN service_requests r ON r.id = b.request_id
             JOIN users client ON client.id = r.client_id
             WHERE b.provider_id = ?
               AND {$realProvider}
               AND {$realClient}
             GROUP BY b.status
             ORDER BY total DESC",
            'i',
            [$providerId]
        )
    );

    $categoryPerformance = fetch_all_assoc(
        run_query(
            "SELECT
                r.category,
                COUNT(*) AS bids_count,
                COALESCE(SUM(b.status = 'accepted'), 0) AS accepted_count,
                COALESCE(SUM(t.final_price), 0) AS revenue
             FROM bids b
             JOIN service_requests r ON r.id = b.request_id
             JOIN users provider ON provider.id = b.provider_id
             JOIN users client ON client.id = r.client_id
             LEFT JOIN transactions t ON t.request_id = r.id AND r.selected_provider_id = b.provider_id
             WHERE b.provider_id = ?
               AND {$realProvider}
               AND {$realClient}
             GROUP BY r.category
             ORDER BY revenue DESC, accepted_count DESC, bids_count DESC
             LIMIT 5",
            'i',
            [$providerId]
        )
    );

    return [
        'overview' => array_merge($bidSummary, $revenueSummary, $reviewSummary, $promotionSummary),
        'monthly_revenue' => $monthlyRevenue,
        'bid_status' => $bidStatus,
        'category_performance' => $categoryPerformance,
    ];
}

function admin_counts(): array
{
    $data = [];
    $realUser = real_user_filter('u');
    $realClient = real_user_filter('client');
    $realProvider = real_user_filter('provider');

    $data['users'] = (int) (fetch_one_assoc(run_query("SELECT COUNT(*) AS c FROM users u WHERE {$realUser}"))['c'] ?? 0);
    $data['requests'] = (int) (fetch_one_assoc(run_query(
        "SELECT COUNT(*) AS c
         FROM service_requests r
         JOIN users client ON client.id = r.client_id
         LEFT JOIN users provider ON provider.id = r.selected_provider_id
         WHERE {$realClient}
           AND (provider.id IS NULL OR {$realProvider})"
    ))['c'] ?? 0);
    $data['bids'] = (int) (fetch_one_assoc(run_query(
        "SELECT COUNT(*) AS c
         FROM bids b
         JOIN users provider ON provider.id = b.provider_id
         JOIN service_requests r ON r.id = b.request_id
         JOIN users client ON client.id = r.client_id
         WHERE {$realClient}
           AND {$realProvider}"
    ))['c'] ?? 0);
    $data['transactions'] = (int) (fetch_one_assoc(run_query(
        "SELECT COUNT(*) AS c
         FROM transactions t
         JOIN service_requests r ON r.id = t.request_id
         JOIN users client ON client.id = r.client_id
         JOIN users provider ON provider.id = r.selected_provider_id
         WHERE {$realClient}
           AND {$realProvider}"
    ))['c'] ?? 0);
    return $data;
}

function admin_sales_analytics(): array
{
    // Admin analytics aggregates all real users and skips seeded demo accounts.
    $realProvider = real_user_filter('provider');
    $realClient = real_user_filter('client');

    $overview = fetch_one_assoc(
        run_query(
            "SELECT
                COALESCE(SUM(t.final_price), 0) AS total_revenue,
                COALESCE(SUM(t.original_price), 0) AS target_revenue,
                COALESCE(SUM(t.discount_amount), 0) AS total_discount,
                COUNT(*) AS sales_count,
                COALESCE(SUM(CASE WHEN t.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN t.final_price ELSE 0 END), 0) AS current_month_revenue,
                COALESCE(SUM(CASE
                    WHEN t.created_at >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
                     AND t.created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')
                    THEN t.final_price ELSE 0 END), 0) AS previous_month_revenue
             FROM transactions t
             JOIN service_requests r ON r.id = t.request_id
             JOIN users client ON client.id = r.client_id
             JOIN users provider ON provider.id = r.selected_provider_id
             WHERE {$realClient}
               AND {$realProvider}"
        )
    ) ?: [];

    $requestSummary = fetch_one_assoc(
        run_query(
            "SELECT
                COUNT(*) AS total_requests,
                COALESCE(SUM(r.status = 'completed'), 0) AS completed_requests,
                COALESCE(SUM(r.status = 'in_progress'), 0) AS active_requests,
                COALESCE(SUM(r.status = 'open'), 0) AS open_requests,
                COALESCE(SUM(r.status = 'cancelled'), 0) AS cancelled_requests
             FROM service_requests r
             JOIN users client ON client.id = r.client_id
             LEFT JOIN users provider ON provider.id = r.selected_provider_id
             WHERE {$realClient}
               AND (provider.id IS NULL OR {$realProvider})"
        )
    ) ?: [];

    $bidSummary = fetch_one_assoc(
        run_query(
            "SELECT
                COUNT(*) AS total_bids,
                COALESCE(SUM(b.status = 'accepted'), 0) AS accepted_bids,
                COALESCE(SUM(b.status = 'pending'), 0) AS pending_bids,
                COALESCE(AVG(b.price), 0) AS average_bid
             FROM bids b
             JOIN users provider ON provider.id = b.provider_id
             JOIN service_requests r ON r.id = b.request_id
             JOIN users client ON client.id = r.client_id
             WHERE {$realClient}
               AND {$realProvider}"
        )
    ) ?: [];

    $promotionSummary = fetch_one_assoc(
        run_query(
            "SELECT COUNT(*) AS active_promotions
             FROM promotions p
             JOIN users provider ON provider.id = p.provider_id
             WHERE p.is_active = 1
               AND CURDATE() BETWEEN p.start_date AND p.end_date
               AND {$realProvider}"
        )
    ) ?: [];

    $monthlyRevenue = fetch_all_assoc(
        run_query(
            "SELECT
                DATE_FORMAT(t.created_at, '%Y-%m') AS month_key,
                DATE_FORMAT(t.created_at, '%b') AS month_label,
                COALESCE(SUM(t.final_price), 0) AS revenue
             FROM transactions t
             JOIN service_requests r ON r.id = t.request_id
             JOIN users client ON client.id = r.client_id
             JOIN users provider ON provider.id = r.selected_provider_id
             WHERE t.created_at >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
               AND {$realClient}
               AND {$realProvider}
             GROUP BY DATE_FORMAT(t.created_at, '%Y-%m'), DATE_FORMAT(t.created_at, '%b')
             ORDER BY month_key"
        )
    );

    $statusDistribution = fetch_all_assoc(
        run_query(
            "SELECT r.status, COUNT(*) AS total
             FROM service_requests r
             JOIN users client ON client.id = r.client_id
             LEFT JOIN users provider ON provider.id = r.selected_provider_id
             WHERE {$realClient}
               AND (provider.id IS NULL OR {$realProvider})
             GROUP BY r.status
             ORDER BY total DESC"
        )
    );

    $categorySales = fetch_all_assoc(
        run_query(
            "SELECT
                r.category,
                COUNT(*) AS requests_count,
                COALESCE(SUM(t.final_price), 0) AS revenue
             FROM service_requests r
             JOIN users client ON client.id = r.client_id
             LEFT JOIN transactions t ON t.request_id = r.id
             LEFT JOIN users provider ON provider.id = r.selected_provider_id
             WHERE {$realClient}
               AND (provider.id IS NULL OR {$realProvider})
             GROUP BY r.category
             ORDER BY revenue DESC, requests_count DESC
             LIMIT 5"
        )
    );

    $topProviders = fetch_all_assoc(
        run_query(
            "SELECT
                provider.name,
                COUNT(*) AS accepted_sales,
                COALESCE(SUM(t.final_price), 0) AS revenue
             FROM service_requests r
             JOIN users provider ON provider.id = r.selected_provider_id
             JOIN users client ON client.id = r.client_id
             LEFT JOIN transactions t ON t.request_id = r.id
             WHERE r.selected_provider_id IS NOT NULL
               AND {$realClient}
               AND {$realProvider}
             GROUP BY provider.id, provider.name
             ORDER BY revenue DESC, accepted_sales DESC
             LIMIT 4"
        )
    );

    return [
        'overview' => array_merge($overview, $requestSummary, $bidSummary, $promotionSummary),
        'monthly_revenue' => $monthlyRevenue,
        'status_distribution' => $statusDistribution,
        'category_sales' => $categorySales,
        'top_providers' => $topProviders,
    ];
}

function admin_latest_users(): array
{
    return fetch_all_assoc(run_query(
        'SELECT id, name, email, role, created_at
         FROM users u
         WHERE ' . real_user_filter('u') . '
         ORDER BY id DESC LIMIT 10'
    ));
}

function admin_latest_requests(): array
{
    return fetch_all_assoc(
        run_query(
            'SELECT r.id, r.title, r.status, r.budget, u.name AS client_name, r.created_at
             FROM service_requests r
             JOIN users u ON u.id = r.client_id
             LEFT JOIN users provider ON provider.id = r.selected_provider_id
             WHERE ' . real_user_filter('u') . '
               AND (provider.id IS NULL OR ' . real_user_filter('provider') . ')
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
             WHERE ' . real_user_filter('client') . '
               AND ' . real_user_filter('provider') . '
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
             JOIN users client ON client.id = r.client_id
             JOIN users provider ON provider.id = r.selected_provider_id
             WHERE ' . real_user_filter('client') . '
               AND ' . real_user_filter('provider') . '
             ORDER BY t.id DESC LIMIT 10'
        )
    );
}

function admin_latest_promotions(): array
{
    return fetch_all_assoc(
        run_query(
            'SELECT p.*, provider.name AS provider_name
             FROM promotions p
             JOIN users provider ON provider.id = p.provider_id
             WHERE ' . real_user_filter('provider') . '
             ORDER BY p.id DESC LIMIT 50'
        )
    );
}
