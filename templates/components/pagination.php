<?php
/**
 * Pagination component.
 *
 * @param int    $currentPage  Current page number (1-based)
 * @param int    $totalPages   Total number of pages
 * @param string $baseUrl      Base URL for page links (will append ?page= or &page=)
 * @param int    $pageSize     Current page size
 */
function renderPagination(int $currentPage, int $totalPages, string $baseUrl, int $pageSize = 50): string
{
    if ($totalPages <= 0) {
        $totalPages = 1;
    }

    $separator = strpos($baseUrl, '?') !== false ? '&' : '?';

    ob_start();
    ?>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 mt-4 px-1">
        <!-- Page Size Selector -->
        <div class="flex items-center space-x-2 text-sm text-gray-600">
            <label for="page-size">표시개수:</label>
            <select id="page-size" class="form-select rounded-md border-gray-300 text-sm py-1.5 pl-3 pr-8 focus:ring-blue-500 focus:border-blue-500"
                    onchange="changePageSize(this.value)">
                <?php foreach (PAGE_SIZES as $size): ?>
                <option value="<?= $size ?>" <?= $size === $pageSize ? 'selected' : '' ?>><?= $size ?>개</option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Pagination Links -->
        <nav class="flex items-center space-x-1">
            <?php
            // Previous button
            $prevDisabled = $currentPage <= 1;
            $prevUrl = $baseUrl . $separator . 'page=' . ($currentPage - 1) . '&size=' . $pageSize;
            ?>
            <a href="<?= $prevDisabled ? '#' : htmlspecialchars($prevUrl) ?>"
               class="pagination-btn <?= $prevDisabled ? 'pagination-btn-disabled' : '' ?>"
               <?= $prevDisabled ? 'aria-disabled="true"' : '' ?>>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>

            <?php
            // Page numbers with ellipsis
            $range = 2;
            $startPage = max(1, $currentPage - $range);
            $endPage = min($totalPages, $currentPage + $range);

            if ($startPage > 1):
            ?>
                <a href="<?= htmlspecialchars($baseUrl . $separator . 'page=1&size=' . $pageSize) ?>"
                   class="pagination-btn">1</a>
                <?php if ($startPage > 2): ?>
                <span class="pagination-ellipsis">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="<?= htmlspecialchars($baseUrl . $separator . 'page=' . $i . '&size=' . $pageSize) ?>"
               class="pagination-btn <?= $i === $currentPage ? 'pagination-btn-active' : '' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                <span class="pagination-ellipsis">...</span>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($baseUrl . $separator . 'page=' . $totalPages . '&size=' . $pageSize) ?>"
                   class="pagination-btn"><?= $totalPages ?></a>
            <?php endif; ?>

            <?php
            // Next button
            $nextDisabled = $currentPage >= $totalPages;
            $nextUrl = $baseUrl . $separator . 'page=' . ($currentPage + 1) . '&size=' . $pageSize;
            ?>
            <a href="<?= $nextDisabled ? '#' : htmlspecialchars($nextUrl) ?>"
               class="pagination-btn <?= $nextDisabled ? 'pagination-btn-disabled' : '' ?>"
               <?= $nextDisabled ? 'aria-disabled="true"' : '' ?>>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </nav>

        <!-- Page Info -->
        <div class="text-sm text-gray-500">
            <?= $currentPage ?> / <?= $totalPages ?> 페이지
        </div>
    </div>

    <script>
    function changePageSize(size) {
        const url = new URL(window.location.href);
        url.searchParams.set('size', size);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }
    </script>
    <?php
    return ob_get_clean();
}
