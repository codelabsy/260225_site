<?php
/**
 * Reusable table component.
 *
 * @param array  $headers  Array of header labels (or assoc array with 'label', 'class', 'width')
 * @param array  $rows     Array of row arrays (each row is array of cell values)
 * @param array  $options  Optional settings:
 *   - 'checkbox'  => bool   Enable row checkboxes
 *   - 'id'        => string Table id attribute
 *   - 'class'     => string Additional CSS classes
 *   - 'empty_msg' => string Message when no rows
 *   - 'striped'   => bool   Striped rows (default true)
 *   - 'hover'     => bool   Hover highlight (default true)
 *   - 'compact'   => bool   Compact padding
 */
function renderTable(array $headers, array $rows, array $options = []): string
{
    $tableId    = $options['id'] ?? ('table-' . uniqid());
    $extraClass = $options['class'] ?? '';
    $checkbox   = $options['checkbox'] ?? false;
    $raw        = $options['raw'] ?? false;
    $emptyMsg   = $options['empty_msg'] ?? '데이터가 없습니다.';
    $striped    = $options['striped'] ?? true;
    $hover      = $options['hover'] ?? true;
    $compact    = $options['compact'] ?? false;

    $cellPad = $compact ? 'px-3 py-2 text-xs' : 'px-4 py-3 text-sm';

    ob_start();
    ?>
    <div class="table-container bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden <?= $extraClass ?>">
        <div class="overflow-x-auto">
            <table id="<?= htmlspecialchars($tableId) ?>" class="w-full text-left">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <?php if ($checkbox): ?>
                        <th class="w-10 px-3 py-3">
                            <input type="checkbox" class="table-check-all rounded border-gray-300 text-blue-600 focus:ring-blue-500" data-table="<?= htmlspecialchars($tableId) ?>">
                        </th>
                        <?php endif; ?>
                        <?php foreach ($headers as $header): ?>
                        <?php
                            $label = is_array($header) ? ($header['label'] ?? '') : $header;
                            $thClass = is_array($header) ? ($header['class'] ?? '') : '';
                            $thWidth = is_array($header) ? ($header['width'] ?? '') : '';
                        ?>
                        <th class="<?= $cellPad ?> font-semibold text-gray-600 whitespace-nowrap <?= $thClass ?>"
                            <?= $thWidth ? 'style="width:' . htmlspecialchars($thWidth) . '"' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?= count($headers) + ($checkbox ? 1 : 0) ?>" class="px-4 py-12 text-center text-gray-400 text-sm">
                            <?= htmlspecialchars($emptyMsg) ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($rows as $rowIdx => $row): ?>
                    <tr class="<?= $striped && $rowIdx % 2 === 1 ? 'bg-gray-50/50' : 'bg-white' ?> <?= $hover ? 'hover:bg-blue-50/50 transition-colors' : '' ?>">
                        <?php if ($checkbox): ?>
                        <td class="w-10 px-3 py-3">
                            <input type="checkbox" class="table-check-row rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                   value="<?= htmlspecialchars($row['_id'] ?? $rowIdx) ?>"
                                   data-table="<?= htmlspecialchars($tableId) ?>">
                        </td>
                        <?php endif; ?>
                        <?php
                        $cells = $row;
                        unset($cells['_id']);
                        foreach (array_values($cells) as $cell):
                        ?>
                        <td class="<?= $cellPad ?> text-gray-700">
                            <?= $raw ? $cell : htmlspecialchars((string)$cell) ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
