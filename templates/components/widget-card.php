<?php
/**
 * Dashboard widget card component.
 *
 * @param string $title   Card title
 * @param string $value   Main display value
 * @param string $icon    SVG icon HTML or icon name
 * @param string $change  Change indicator text (e.g., "+12%", "-5건")
 * @param array  $options Optional settings:
 *   - 'color'   => string  Accent color: 'blue','green','red','yellow','purple' (default 'blue')
 *   - 'link'    => string  Link URL for card click
 *   - 'subtitle'=> string  Subtitle text
 */
function renderWidgetCard(string $title, string $value, string $icon = '', string $change = '', array $options = []): string
{
    $color    = $options['color'] ?? 'blue';
    $link     = $options['link'] ?? '';
    $subtitle = $options['subtitle'] ?? '';

    $colorMap = [
        'blue'   => ['bg' => 'bg-blue-50',   'text' => 'text-blue-600',   'icon' => 'bg-blue-100 text-blue-600'],
        'green'  => ['bg' => 'bg-green-50',  'text' => 'text-green-600',  'icon' => 'bg-green-100 text-green-600'],
        'red'    => ['bg' => 'bg-red-50',    'text' => 'text-red-600',    'icon' => 'bg-red-100 text-red-600'],
        'yellow' => ['bg' => 'bg-amber-50',  'text' => 'text-amber-600',  'icon' => 'bg-amber-100 text-amber-600'],
        'purple' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'icon' => 'bg-purple-100 text-purple-600'],
    ];

    $colors = $colorMap[$color] ?? $colorMap['blue'];

    // Determine change direction
    $changeColor = 'text-gray-500';
    $changeIcon = '';
    if ($change) {
        if (strpos($change, '+') === 0 || strpos($change, '▲') !== false) {
            $changeColor = 'text-green-600';
            $changeIcon = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>';
        } elseif (strpos($change, '-') === 0 || strpos($change, '▼') !== false) {
            $changeColor = 'text-red-600';
            $changeIcon = '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>';
        }
    }

    $tag = $link ? 'a' : 'div';
    $linkAttr = $link ? 'href="' . htmlspecialchars($link) . '"' : '';

    ob_start();
    ?>
    <<?= $tag ?> <?= $linkAttr ?>
       class="widget-card bg-white rounded-lg border border-gray-200 shadow-sm p-5 <?= $link ? 'hover:shadow-md hover:border-gray-300 transition-all cursor-pointer' : '' ?>">
        <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-500 truncate"><?= htmlspecialchars($title) ?></p>
                <p class="mt-2 text-2xl font-bold text-gray-900"><?= htmlspecialchars($value) ?></p>
                <?php if ($subtitle): ?>
                <p class="mt-1 text-xs text-gray-400"><?= htmlspecialchars($subtitle) ?></p>
                <?php endif; ?>
                <?php if ($change): ?>
                <div class="mt-2 flex items-center gap-1 <?= $changeColor ?>">
                    <?= $changeIcon ?>
                    <span class="text-xs font-medium"><?= htmlspecialchars($change) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($icon): ?>
            <div class="flex-shrink-0 ml-4">
                <div class="w-10 h-10 rounded-lg <?= $colors['icon'] ?> flex items-center justify-center">
                    <?= $icon ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </<?= $tag ?>>
    <?php
    return ob_get_clean();
}
