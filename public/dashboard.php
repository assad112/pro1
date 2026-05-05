<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
require_login();
$user = current_user();

function provider_dashboard_percentage(float $value, float $total): int
{
    if ($total <= 0) {
        return 0;
    }
    return (int) min(100, max(0, round(($value / $total) * 100)));
}

function provider_dashboard_sparkline_points(array $rows, string $valueKey): string
{
    if (count($rows) === 0) {
        return '10,72 190,72';
    }

    $values = array_map(static fn (array $row): float => (float) $row[$valueKey], $rows);
    $max = max($values);
    $max = $max > 0 ? $max : 1;
    $count = max(1, count($values) - 1);
    $points = [];

    foreach ($values as $index => $value) {
        $x = 10 + (($index / $count) * 180);
        $y = 78 - (($value / $max) * 58);
        $points[] = round($x, 1) . ',' . round($y, 1);
    }

    if (count($points) === 1) {
        $points[] = '190,' . explode(',', $points[0])[1];
    }

    return implode(' ', $points);
}

function provider_dashboard_max_value(array $rows, string $valueKey): float
{
    $values = array_map(static fn (array $row): float => (float) ($row[$valueKey] ?? 0), $rows);
    if (count($values) === 0) {
        return 1.0;
    }
    return max(1.0, max($values));
}

function dashboard_growth_percent(float $current, float $previous): float
{
    if ($previous <= 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

$providerAnalytics = $user['role'] === ROLE_PROVIDER ? provider_analytics((int) $user['id']) : null;
$providerOpenRequests = $user['role'] === ROLE_PROVIDER ? get_open_requests(true) : [];
$providerBids = $user['role'] === ROLE_PROVIDER ? get_bids_by_provider((int) $user['id'], true) : [];
$providerConversations = $user['role'] === ROLE_PROVIDER ? get_user_conversations((int) $user['id'], true) : [];
$providerReviews = $user['role'] === ROLE_PROVIDER ? get_provider_reviews((int) $user['id'], true) : [];
$providerPromotions = $user['role'] === ROLE_PROVIDER ? get_provider_promotions((int) $user['id'], true) : [];

$adminCounts = $user['role'] === ROLE_ADMIN ? admin_counts() : [];
$adminAnalytics = $user['role'] === ROLE_ADMIN ? admin_sales_analytics() : null;
$adminUsers = $user['role'] === ROLE_ADMIN ? admin_latest_users() : [];
$adminRequests = $user['role'] === ROLE_ADMIN ? admin_latest_requests() : [];
$adminBids = $user['role'] === ROLE_ADMIN ? admin_latest_bids() : [];

require __DIR__ . '/_header.php';
?>
<section class="card <?= $user['role'] === ROLE_ADMIN ? 'dashboard-command' : '' ?>">
  <?php if ($user['role'] === ROLE_ADMIN): ?>
    <p class="eyebrow">PMMS Control Center</p>
    <h1>Operations Dashboard</h1>
    <p class="dashboard-lead">Welcome <?= e($user['name']) ?>. Monitor customers, promotions, transactions, and reports from one workspace.</p>
  <?php else: ?>
    <h1><?= e(t('dashboard.welcome', ['name' => $user['name']])) ?></h1>
    <p><?= e(t('dashboard.role', ['role' => role_label($user['role'])])) ?></p>
  <?php endif; ?>
  <div class="grid">
    <?php if ($user['role'] === ROLE_PROVIDER): ?>
      <a class="tile provider-tab is-active" href="#provider-requests" data-provider-tab="provider-requests"><?= e(t('dashboard.manage_requests')) ?></a>
      <a class="tile provider-tab" href="#provider-bids" data-provider-tab="provider-bids"><?= e(t('nav.bids')) ?></a>
      <a class="tile provider-tab" href="#provider-messages" data-provider-tab="provider-messages"><?= e(t('nav.messages')) ?></a>
      <a class="tile provider-tab" href="#provider-reviews" data-provider-tab="provider-reviews"><?= e(t('nav.reviews')) ?></a>
      <a class="tile provider-tab" href="#provider-marketing" data-provider-tab="provider-marketing"><?= e(t('dashboard.marketing_discounts')) ?></a>
    <?php elseif ($user['role'] !== ROLE_ADMIN): ?>
      <a class="tile" href="/requests.php"><?= e(t('dashboard.manage_requests')) ?></a>
      <a class="tile" href="/bids.php"><?= e(t('nav.bids')) ?></a>
      <a class="tile" href="/messages.php"><?= e(t('nav.messages')) ?></a>
      <a class="tile" href="/reviews.php"><?= e(t('nav.reviews')) ?></a>
    <?php endif; ?>
    <?php if ($user['role'] === ROLE_ADMIN): ?>
      <a class="tile" href="/admin.php#customers"><i class="bi bi-people-fill"></i> Customers</a>
      <a class="tile" href="/admin_promotions.php"><i class="bi bi-gift-fill"></i> Promotions</a>
      <a class="tile" href="/admin.php#transactions"><i class="bi bi-cash-stack"></i> Transactions</a>
      <a class="tile" href="/admin_analytics.php"><i class="bi bi-bar-chart-fill"></i> Reports</a>
    <?php endif; ?>
  </div>
</section>

<?php if ($user['role'] === ROLE_ADMIN && $adminAnalytics !== null): ?>
  <?php
    $adminOverview = $adminAnalytics['overview'];
    $adminMonthlyRevenue = $adminAnalytics['monthly_revenue'];
    $adminStatusRows = $adminAnalytics['status_distribution'];
    $adminTopProviders = $adminAnalytics['top_providers'];
    $adminTotalBids = (float) ($adminOverview['total_bids'] ?? 0);
    $adminAcceptedBids = (float) ($adminOverview['accepted_bids'] ?? 0);
    $adminConversionPercent = provider_dashboard_percentage($adminAcceptedBids, $adminTotalBids);
    $adminGrowth = dashboard_growth_percent((float) ($adminOverview['current_month_revenue'] ?? 0), (float) ($adminOverview['previous_month_revenue'] ?? 0));
    $maxAdminStatus = provider_dashboard_max_value($adminStatusRows, 'total');
  ?>
  <div class="stats admin-stats dashboard-kpis">
    <a class="stat" href="/admin.php#customers"><i class="bi bi-people"></i><span>Customers</span><strong><?= (int) ($adminCounts['users'] ?? 0) ?></strong></a>
    <a class="stat" href="/admin.php"><i class="bi bi-list-check"></i><span>Requests</span><strong><?= (int) ($adminCounts['requests'] ?? 0) ?></strong></a>
    <a class="stat" href="/admin.php"><i class="bi bi-cash-coin"></i><span>Bids</span><strong><?= (int) ($adminCounts['bids'] ?? 0) ?></strong></a>
    <a class="stat" href="/admin.php#transactions"><i class="bi bi-receipt"></i><span>Transactions</span><strong><?= (int) ($adminCounts['transactions'] ?? 0) ?></strong></a>
  </div>

  <section class="sales-monitor card">
    <div class="section-heading">
      <div>
        <p class="eyebrow">Live Overview</p>
        <h2>Business Snapshot</h2>
      </div>
      <a class="admin-action" href="/admin_analytics.php">Open Reports</a>
    </div>

    <div class="monitor-grid">
      <article class="metric-card has-tooltip" data-tooltip-title="Sales Revenue" data-tooltip-value="<?= e(number_format((float) ($adminOverview['total_revenue'] ?? 0), 2)) ?>">
        <div class="metric-title"><i class="bi bi-currency-dollar"></i>Sales Revenue</div>
        <strong class="revenue-number"><?= e(number_format((float) ($adminOverview['total_revenue'] ?? 0), 2)) ?></strong>
        <span class="muted">Total discounts <?= e(number_format((float) ($adminOverview['total_discount'] ?? 0), 2)) ?></span>
        <div class="growth <?= $adminGrowth >= 0 ? 'positive' : 'negative' ?>">
          <i class="bi <?= $adminGrowth >= 0 ? 'bi-arrow-up' : 'bi-arrow-down' ?>"></i>
          <?= e((string) abs($adminGrowth)) ?>%
        </div>
      </article>

      <article class="metric-card has-tooltip" data-tooltip-title="Bid Conversion" data-tooltip-value="<?= $adminConversionPercent ?>%">
        <div class="metric-title"><i class="bi bi-check2-circle"></i>Bid Conversion</div>
        <div class="gauge" style="--goal: <?= e((string) round($adminConversionPercent / 2, 1)) ?>%;">
          <span><?= $adminConversionPercent ?>%</span>
        </div>
        <div class="metric-footer">
          <strong><?= (int) $adminAcceptedBids ?> / <?= (int) $adminTotalBids ?></strong>
          <span>accepted bids</span>
        </div>
      </article>

      <article class="metric-card trend-card has-tooltip" data-tooltip-title="Revenue Trend" data-tooltip-value="<?= e(count($adminMonthlyRevenue) > 0 ? number_format((float) end($adminMonthlyRevenue)['revenue'], 2) : '0.00') ?>">
        <div class="metric-title"><i class="bi bi-graph-up-arrow"></i>Revenue Trend</div>
        <svg class="sparkline" viewBox="0 0 200 90" role="img" aria-label="Revenue Trend">
          <path d="M10 78 H190" />
          <polyline points="<?= e(provider_dashboard_sparkline_points($adminMonthlyRevenue, 'revenue')) ?>" />
        </svg>
        <div class="month-labels">
          <?php foreach ($adminMonthlyRevenue as $month): ?>
            <span><?= e($month['month_label']) ?></span>
          <?php endforeach; ?>
        </div>
      </article>
    </div>
  </section>

  <div class="admin-grid">
    <section class="card admin-panel">
      <div class="section-heading">
        <h2>Latest Customers</h2>
        <a class="admin-action" href="/admin.php#customers">View</a>
      </div>
      <div class="table-wrap">
        <table>
          <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th></tr>
          <?php foreach (array_slice($adminUsers, 0, 5) as $adminUser): ?>
            <tr>
              <td><?= (int) $adminUser['id'] ?></td>
              <td><?= e($adminUser['name']) ?></td>
              <td><?= e($adminUser['email']) ?></td>
              <td><span class="badge role-<?= e($adminUser['role']) ?>"><?= e(role_label($adminUser['role'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </section>

    <section class="card admin-panel">
      <div class="section-heading">
        <h2>Operations Status</h2>
        <a class="admin-action" href="/admin_analytics.php">Reports</a>
      </div>
      <?php foreach ($adminStatusRows as $statusRow): ?>
        <?php $statusPercent = provider_dashboard_percentage((float) $statusRow['total'], (float) $maxAdminStatus); ?>
        <div class="bar-row">
          <span><?= e(status_label($statusRow['status'])) ?></span>
          <div class="wide-bar"><span style="width: <?= $statusPercent ?>%;"></span></div>
          <strong><?= (int) $statusRow['total'] ?></strong>
        </div>
      <?php endforeach; ?>
      <?php if (count($adminStatusRows) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
    </section>
  </div>

  <div class="admin-grid">
    <section class="card admin-panel">
      <div class="section-heading">
        <h2>Latest Requests</h2>
        <a class="admin-action" href="/admin.php">View</a>
      </div>
      <div class="table-wrap">
        <table>
          <tr><th>#</th><th>Request</th><th>Client</th><th>Status</th><th>Budget</th></tr>
          <?php foreach (array_slice($adminRequests, 0, 5) as $request): ?>
            <tr>
              <td><?= (int) $request['id'] ?></td>
              <td><?= e($request['title']) ?></td>
              <td><?= e($request['client_name']) ?></td>
              <td><span class="badge status-<?= e($request['status']) ?>"><?= e(status_label($request['status'])) ?></span></td>
              <td><?= e(number_format((float) $request['budget'], 2)) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </section>

    <section class="card admin-panel">
      <div class="section-heading">
        <h2>Latest Bids</h2>
        <a class="admin-action" href="/admin.php">View</a>
      </div>
      <div class="table-wrap">
        <table>
          <tr><th>#</th><th>Request</th><th>Provider</th><th>Price</th><th>Status</th></tr>
          <?php foreach (array_slice($adminBids, 0, 5) as $bid): ?>
            <tr>
              <td><?= (int) $bid['id'] ?></td>
              <td><?= e($bid['title']) ?></td>
              <td><?= e($bid['provider_name']) ?></td>
              <td><?= e(number_format((float) $bid['price'], 2)) ?></td>
              <td><span class="badge status-<?= e($bid['status']) ?>"><?= e(status_label($bid['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </section>
  </div>
<?php endif; ?>

<?php if ($user['role'] === ROLE_PROVIDER && $providerAnalytics !== null): ?>
  <?php
    $providerOverview = $providerAnalytics['overview'];
    $providerMonthlyRevenue = $providerAnalytics['monthly_revenue'];
    $providerBidStatus = $providerAnalytics['bid_status'];
    $providerCategories = $providerAnalytics['category_performance'];
    $totalProviderBids = (float) ($providerOverview['total_bids'] ?? 0);
    $acceptedProviderBids = (float) ($providerOverview['accepted_bids'] ?? 0);
    $providerAcceptancePercent = provider_dashboard_percentage($acceptedProviderBids, $totalProviderBids);
    $maxProviderStatus = provider_dashboard_max_value($providerBidStatus, 'total');
    $maxProviderCategoryRevenue = provider_dashboard_max_value($providerCategories, 'revenue');
  ?>
  <section class="sales-monitor card provider-monitor">
    <div class="section-heading">
      <div>
        <p class="eyebrow">Provider</p>
        <h2><?= e(t('provider.chart_title')) ?></h2>
      </div>
      <span class="muted"><?= e(t('provider.chart_subtitle')) ?></span>
    </div>

    <div class="monitor-grid">
      <article class="metric-card">
        <div class="metric-title"><i class="bi bi-check2-circle"></i><?= e(t('provider.acceptance_rate')) ?></div>
        <div class="gauge" style="--goal: <?= e((string) round($providerAcceptancePercent / 2, 1)) ?>%;">
          <span><?= $providerAcceptancePercent ?>%</span>
        </div>
        <div class="metric-footer">
          <strong><?= (int) $acceptedProviderBids ?> / <?= (int) $totalProviderBids ?></strong>
          <span><?= e(t('provider.accepted_from_bids')) ?></span>
        </div>
      </article>

      <article class="metric-card trend-card">
        <div class="metric-title"><i class="bi bi-graph-up-arrow"></i><?= e(t('provider.revenue_chart')) ?></div>
        <svg class="sparkline" viewBox="0 0 200 90" role="img" aria-label="<?= e(t('provider.revenue_chart')) ?>">
          <path d="M10 78 H190" />
          <polyline points="<?= e(provider_dashboard_sparkline_points($providerMonthlyRevenue, 'revenue')) ?>" />
        </svg>
        <div class="month-labels">
          <?php foreach ($providerMonthlyRevenue as $month): ?>
            <span><?= e($month['month_label']) ?></span>
          <?php endforeach; ?>
        </div>
        <?php if (count($providerMonthlyRevenue) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
      </article>

      <article class="metric-card">
        <div class="metric-title"><i class="bi bi-currency-dollar"></i><?= e(t('provider.total_revenue')) ?></div>
        <strong class="revenue-number"><?= e(number_format((float) ($providerOverview['total_revenue'] ?? 0), 2)) ?></strong>
        <span class="muted"><?= e(t('provider.total_discount')) ?> <?= e(number_format((float) ($providerOverview['total_discount'] ?? 0), 2)) ?></span>
        <div class="mini-list">
          <span><?= e(t('provider.average_bid')) ?></span>
          <b><?= e(number_format((float) ($providerOverview['average_bid'] ?? 0), 2)) ?></b>
        </div>
      </article>

      <article class="metric-card">
        <div class="metric-title"><i class="bi bi-star"></i><?= e(t('provider.rating')) ?></div>
        <strong class="metric-number"><?= e(number_format((float) ($providerOverview['average_rating'] ?? 0), 2)) ?></strong>
        <div class="mini-list">
          <span><?= e(t('provider.reviews_count')) ?></span>
          <b><?= (int) ($providerOverview['reviews_count'] ?? 0) ?></b>
        </div>
        <div class="mini-list">
          <span><?= e(t('provider.active_promotions')) ?></span>
          <b><?= (int) ($providerOverview['active_promotions'] ?? 0) ?></b>
        </div>
      </article>

      <article class="metric-card status-card">
        <div class="metric-title"><i class="bi bi-kanban"></i><?= e(t('provider.bid_status_chart')) ?></div>
        <?php foreach ($providerBidStatus as $statusRow): ?>
          <?php $statusPercent = provider_dashboard_percentage((float) $statusRow['total'], (float) $maxProviderStatus); ?>
          <div class="bar-row">
            <span><?= e(status_label($statusRow['status'])) ?></span>
            <div class="wide-bar"><span style="width: <?= $statusPercent ?>%;"></span></div>
            <strong><?= (int) $statusRow['total'] ?></strong>
          </div>
        <?php endforeach; ?>
        <?php if (count($providerBidStatus) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
      </article>

      <article class="metric-card">
        <div class="metric-title"><i class="bi bi-tags"></i><?= e(t('provider.category_performance')) ?></div>
        <?php foreach ($providerCategories as $category): ?>
          <?php $categoryPercent = provider_dashboard_percentage((float) $category['revenue'], (float) $maxProviderCategoryRevenue); ?>
          <div class="leader-row">
            <span><?= e($category['category']) ?></span>
            <strong><?= e(number_format((float) $category['revenue'], 2)) ?></strong>
            <div class="progress"><span style="width: <?= $categoryPercent ?>%;"></span></div>
          </div>
        <?php endforeach; ?>
        <?php if (count($providerCategories) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
      </article>
    </div>
  </section>

  <div class="provider-panel-stage" id="provider-panel-stage">
  <section id="provider-requests" class="card dashboard-section provider-tab-panel is-active">
    <div class="section-heading">
      <h2><?= e(t('dashboard.manage_requests')) ?></h2>
      <a class="admin-action" href="/requests.php"><?= e(t('messages.open')) ?></a>
    </div>
    <div class="table-wrap">
      <table>
        <tr>
          <th>#</th>
          <th><?= e(t('requests.request_title')) ?></th>
          <th><?= e(t('requests.client')) ?></th>
          <th><?= e(t('requests.budget')) ?></th>
          <th><?= e(t('requests.due_date')) ?></th>
        </tr>
        <?php foreach (array_slice($providerOpenRequests, 0, 5) as $request): ?>
          <tr>
            <td><?= (int) $request['id'] ?></td>
            <td><?= e($request['title']) ?></td>
            <td><?= e($request['client_name']) ?></td>
            <td><?= e(number_format((float) $request['budget'], 2)) ?></td>
            <td><?= e($request['due_date']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php if (count($providerOpenRequests) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
  </section>

  <section id="provider-bids" class="card dashboard-section provider-tab-panel">
    <div class="section-heading">
      <h2><?= e(t('nav.bids')) ?></h2>
      <a class="admin-action" href="/bids.php"><?= e(t('messages.open')) ?></a>
    </div>
    <div class="table-wrap">
      <table>
        <tr>
          <th>#</th>
          <th><?= e(t('bids.request_title')) ?></th>
          <th><?= e(t('bids.price')) ?></th>
          <th><?= e(t('bids.duration_days')) ?></th>
          <th><?= e(t('bids.status')) ?></th>
        </tr>
        <?php foreach (array_slice($providerBids, 0, 5) as $bid): ?>
          <tr>
            <td><?= (int) $bid['id'] ?></td>
            <td><?= e($bid['request_title']) ?></td>
            <td><?= e(number_format((float) $bid['price'], 2)) ?></td>
            <td><?= (int) $bid['duration_days'] ?> <?= e(t('bids.days')) ?></td>
            <td><span class="badge status-<?= e($bid['status']) ?>"><?= e(status_label($bid['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php if (count($providerBids) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
  </section>

  <section id="provider-messages" class="card dashboard-section provider-tab-panel">
    <div class="section-heading">
      <h2><?= e(t('nav.messages')) ?></h2>
      <a class="admin-action" href="/messages.php"><?= e(t('messages.open')) ?></a>
    </div>
    <div class="table-wrap">
      <table>
        <tr>
          <th>#</th>
          <th><?= e(t('messages.request')) ?></th>
          <th><?= e(t('requests.client')) ?></th>
          <th></th>
        </tr>
        <?php foreach (array_slice($providerConversations, 0, 5) as $conversation): ?>
          <tr>
            <td><?= (int) $conversation['id'] ?></td>
            <td><?= e($conversation['title']) ?></td>
            <td><?= e($conversation['client_name']) ?></td>
            <td><a href="/messages.php?conversation_id=<?= (int) $conversation['id'] ?>"><?= e(t('messages.open')) ?></a></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php if (count($providerConversations) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
  </section>

  <section id="provider-reviews" class="card dashboard-section provider-tab-panel">
    <div class="section-heading">
      <h2><?= e(t('nav.reviews')) ?></h2>
      <a class="admin-action" href="/reviews.php"><?= e(t('messages.open')) ?></a>
    </div>
    <div class="table-wrap">
      <table>
        <tr>
          <th><?= e(t('messages.request')) ?></th>
          <th><?= e(t('requests.client')) ?></th>
          <th><?= e(t('bids.rating')) ?></th>
          <th><?= e(t('reviews.comment')) ?></th>
        </tr>
        <?php foreach (array_slice($providerReviews, 0, 5) as $review): ?>
          <tr>
            <td><?= e($review['title']) ?></td>
            <td><?= e($review['client_name']) ?></td>
            <td><?= (int) $review['rating'] ?>/5</td>
            <td><?= e($review['comment']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php if (count($providerReviews) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
  </section>

  <section id="provider-marketing" class="card dashboard-section provider-tab-panel">
    <div class="section-heading">
      <h2><?= e(t('dashboard.marketing_discounts')) ?></h2>
      <a class="admin-action" href="/marketing.php"><?= e(t('messages.open')) ?></a>
    </div>
    <div class="table-wrap">
      <table>
        <tr>
          <th>#</th>
          <th><?= e(t('marketing.offer_title')) ?></th>
          <th><?= e(t('marketing.type')) ?></th>
          <th><?= e(t('marketing.value')) ?></th>
          <th><?= e(t('bids.status')) ?></th>
        </tr>
        <?php foreach (array_slice($providerPromotions, 0, 5) as $promotion): ?>
          <tr>
            <td><?= (int) $promotion['id'] ?></td>
            <td><?= e($promotion['title']) ?></td>
            <td><?= e($promotion['discount_type'] === 'percent' ? t('marketing.percent') : t('marketing.fixed')) ?></td>
            <td><?= e((string) $promotion['discount_value'] . ($promotion['discount_type'] === 'percent' ? '%' : '')) ?></td>
            <td><?= (int) $promotion['is_active'] === 1 ? e(t('marketing.active')) : e(t('marketing.stopped')) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php if (count($providerPromotions) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
  </section>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
