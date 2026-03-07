<?php
/**
 * Filter component.
 *
 * @param array $options Optional settings:
 *   - 'action'       => string  Form action URL (default current page)
 *   - 'method'       => string  'GET' or 'POST' (default 'GET')
 *   - 'date_filter'  => bool    Show date range filter (default true)
 *   - 'status_filter'=> array   Status options for dropdown (empty = hide)
 *   - 'assignee_filter' => array  Assignee list [['id'=>..,'name'=>..]] (empty = hide, admin only)
 *   - 'search'       => bool    Show search input (default true)
 *   - 'search_placeholder' => string Search placeholder text
 *   - 'extra_filters'=> string  Additional filter HTML
 *   - 'values'       => array   Current filter values
 */
function renderFilter(array $options = []): string
{
    $action     = $options['action'] ?? '';
    $method     = strtoupper($options['method'] ?? 'GET');
    $dateFilter = $options['date_filter'] ?? true;
    $statuses   = $options['status_filter'] ?? [];
    $assignees  = $options['assignee_filter'] ?? [];
    $showSearch = $options['search'] ?? true;
    $searchPlaceholder = $options['search_placeholder'] ?? '검색어를 입력하세요...';
    $extraFilters = $options['extra_filters'] ?? '';
    $values     = $options['values'] ?? [];

    $isAdmin = Auth::isAdmin();

    ob_start();
    ?>
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 mb-4">
        <form action="<?= htmlspecialchars($action) ?>" method="<?= $method ?>" class="filter-form">
            <div class="flex flex-wrap items-end gap-3">
                <?php if ($dateFilter): ?>
                <!-- Date Range Filter -->
                <div class="flex items-center gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">시작일</label>
                        <input type="date" name="date_from"
                               value="<?= htmlspecialchars($values['date_from'] ?? '') ?>"
                               class="form-input rounded-md border-gray-300 text-sm py-1.5 px-3 w-36 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <span class="text-gray-400 mt-5">~</span>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">종료일</label>
                        <input type="date" name="date_to"
                               value="<?= htmlspecialchars($values['date_to'] ?? '') ?>"
                               class="form-input rounded-md border-gray-300 text-sm py-1.5 px-3 w-36 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($statuses)): ?>
                <!-- Status Filter -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">상태</label>
                    <select name="status" class="form-select rounded-md border-gray-300 text-sm py-1.5 pl-3 pr-8 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">전체</option>
                        <?php foreach ($statuses as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>"
                                <?= ($values['status'] ?? '') === $status ? 'selected' : '' ?>>
                            <?= htmlspecialchars($status) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($isAdmin && !empty($assignees)): ?>
                <!-- Assignee Filter (Admin only) -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">담당자</label>
                    <select name="assignee" class="form-select rounded-md border-gray-300 text-sm py-1.5 pl-3 pr-8 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">전체</option>
                        <?php foreach ($assignees as $assignee): ?>
                        <option value="<?= htmlspecialchars($assignee['id']) ?>"
                                <?= ($values['assignee'] ?? '') == $assignee['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($assignee['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($showSearch): ?>
                <!-- Search Input -->
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-500 mb-1">검색</label>
                    <input type="text" name="search"
                           value="<?= htmlspecialchars($values['search'] ?? '') ?>"
                           placeholder="<?= htmlspecialchars($searchPlaceholder) ?>"
                           class="form-input rounded-md border-gray-300 text-sm py-1.5 px-3 w-full focus:ring-blue-500 focus:border-blue-500">
                </div>
                <?php endif; ?>

                <?= $extraFilters ?>

                <!-- Apply Button -->
                <div>
                    <label class="block text-xs font-medium text-transparent mb-1">적용</label>
                    <button type="submit" class="btn btn-primary text-sm py-1.5 px-4">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        적용
                    </button>
                </div>

                <!-- Reset -->
                <div>
                    <label class="block text-xs font-medium text-transparent mb-1">초기화</label>
                    <a href="<?= htmlspecialchars($action ?: strtok($_SERVER['REQUEST_URI'], '?')) ?>"
                       class="btn btn-outline text-sm py-1.5 px-3 inline-flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        초기화
                    </a>
                </div>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
