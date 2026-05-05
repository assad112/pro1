<?php
declare(strict_types=1);
require_once __DIR__ . '/_init.php';
require_role([ROLE_ADMIN]);

$analytics = admin_sales_analytics();
$overview = $analytics['overview'];
$monthlyRevenue = $analytics['monthly_revenue'];
$statusDistribution = $analytics['status_distribution'];
$categorySales = $analytics['category_sales'];
$topProviders = $analytics['top_providers'];

function admin_percentage(float $value, float $total): int
{
    if ($total <= 0) {
        return 0;
    }
    return (int) min(100, max(0, round(($value / $total) * 100)));
}

function admin_growth_percent(float $current, float $previous): float
{
    if ($previous <= 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

function admin_sparkline_points(array $rows, string $valueKey): string
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

function admin_max_value(array $rows, string $valueKey): float
{
    $values = array_map(static fn (array $row): float => (float) ($row[$valueKey] ?? 0), $rows);
    if (count($values) === 0) {
        return 1.0;
    }
    return max(1.0, max($values));
}

$totalRevenue = (float) ($overview['total_revenue'] ?? 0);
$targetRevenue = (float) ($overview['target_revenue'] ?? 0);
$targetRevenue = $targetRevenue > 0 ? $targetRevenue : max($totalRevenue, 1);
$goalPercent = admin_percentage($totalRevenue, $targetRevenue);
$growthPercent = admin_growth_percent((float) ($overview['current_month_revenue'] ?? 0), (float) ($overview['previous_month_revenue'] ?? 0));
$conversionPercent = admin_percentage((float) ($overview['accepted_bids'] ?? 0), (float) ($overview['total_bids'] ?? 0));
$maxCategoryRevenue = admin_max_value($categorySales, 'revenue');
$maxStatusTotal = admin_max_value($statusDistribution, 'total');

require __DIR__ . '/_header.php';
?>
<section class="admin-page">
  <div class="admin-hero card">
    <div>
      <p class="eyebrow">Analytics</p>
      <h1><?= e(t('admin.sales_monitor')) ?></h1>
      <p><?= e(t('admin.live_from_data')) ?></p>
    </div>
    <a class="admin-action" href="/admin.php"><?= e(t('nav.admin')) ?></a>
  </div>

  <section class="sales-monitor card">
    <div class="section-heading">
      <div>
        <p class="eyebrow">PMMS</p>
        <h2><?= e(t('admin.sales_monitor')) ?></h2>
      </div>
      <span class="muted"><?= e(t('admin.live_from_data')) ?></span>
    </div>

    <div class="monitor-grid">
      <article class="metric-card goal-card">
        <div class="metric-title"><i class="bi bi-bullseye"></i><?= e(t('admin.goal_this_year')) ?></div>
        <div class="gauge" style="--goal: <?= e((string) round($goalPercent / 2, 1)) ?>%;">
          <span><?= $goalPercent ?>%</span>
        </div>
        <div class="metric-footer">
          <strong><?= e(number_format($totalRevenue, 2)) ?></strong>
          <span><?= e(t('admin.target')) ?> <?= e(number_format($targetRevenue, 2)) ?></span>
        </div>
      </article>

      <article class="metric-card trend-card">
        <div class="metric-title"><i class="bi bi-graph-up-arrow"></i><?= e(t('admin.revenue_trend')) ?></div>
        <svg class="sparkline" viewBox="0 0 200 90" role="img" aria-label="<?= e(t('admin.revenue_trend')) ?>">
          <path d="M10 78 H190" />
          <polyline points="<?= e(admin_sparkline_points($monthlyRevenue, 'revenue')) ?>" />
        </svg>
        <div class="month-labels">
          <?php foreach ($monthlyRevenue as $month): ?>
            <span><?= e($month['month_label']) ?></span>
          <?php endforeach; ?>
        </div>
        <?php if (count($monthlyRevenue) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
      </article>

      <article class="metric-card sales-card">
        <div class="metric-title"><i class="bi bi-bag-check"></i><?= e(t('admin.sales_count')) ?></div>
        <strong class="metric-number"><?= (int) ($overview['sales_count'] ?? 0) ?></strong>
        <div class="mini-list">
          <span><?= e(t('admin.conversion_rate')) ?></span>
          <b><?= $conversionPercent ?>%</b>
        </div>
        <div class="progress"><span style="width: <?= $conversionPercent ?>%;"></span></div>
      </article>

      <article class="metric-card top-card">
        <div class="metric-title"><i class="bi bi-trophy"></i><?= e(t('admin.top_performing')) ?></div>
        <?php foreach ($topProviders as $provider): ?>
          <?php $providerPercent = admin_percentage((float) $provider['revenue'], $targetRevenue); ?>
          <div class="leader-row">
            <span><?= e($provider['name']) ?></span>
            <strong><?= e(number_format((float) $provider['revenue'], 2)) ?></strong>
            <div class="progress"><span style="width: <?= $providerPercent ?>%;"></span></div>
          </div>
        <?php endforeach; ?>
        <?php if (count($topProviders) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
      </article>

      <article class="metric-card status-card">
        <div class="metric-title"><i class="bi bi-gear-wide-connected"></i><?= e(t('admin.operations_status')) ?></div>
        <?php foreach ($statusDistribution as $statusRow): ?>
          <?php $statusPercent = admin_percentage((float) $statusRow['total'], (float) $maxStatusTotal); ?>
          <div class="bar-row">
            <span><?= e(status_label($statusRow['status'])) ?></span>
            <div class="wide-bar"><span style="width: <?= $statusPercent ?>%;"></span></div>
            <strong><?= (int) $statusRow['total'] ?></strong>
          </div>
        <?php endforeach; ?>
        <?php if (count($statusDistribution) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
      </article>

      <article class="metric-card revenue-card">
        <div class="metric-title"><i class="bi bi-currency-dollar"></i><?= e(t('admin.sales_revenue')) ?></div>
        <strong class="revenue-number"><?= e(number_format($totalRevenue, 2)) ?></strong>
        <span class="muted"><?= e(t('admin.total_discount')) ?> <?= e(number_format((float) ($overview['total_discount'] ?? 0), 2)) ?></span>
        <div class="growth <?= $growthPercent >= 0 ? 'positive' : 'negative' ?>">
          <i class="bi <?= $growthPercent >= 0 ? 'bi-arrow-up' : 'bi-arrow-down' ?>"></i>
          <?= e((string) abs($growthPercent)) ?>%
        </div>
      </article>
    </div>

    <div class="category-strip">
      <?php foreach ($categorySales as $category): ?>
        <?php $categoryPercent = admin_percentage((float) $category['revenue'], (float) $maxCategoryRevenue); ?>
        <div>
          <span><?= e($category['category']) ?></span>
          <strong><?= e(number_format((float) $category['revenue'], 2)) ?></strong>
          <div class="progress"><span style="width: <?= $categoryPercent ?>%;"></span></div>
        </div>
      <?php endforeach; ?>
      <?php if (count($categorySales) === 0): ?><p class="empty-state"><?= e(t('admin.empty')) ?></p><?php endif; ?>
    </div>
  </section>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
