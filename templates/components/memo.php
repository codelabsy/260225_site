<?php
/**
 * Memo panel component with AJAX save support.
 *
 * @param string $targetType  Target type (company, shopping, place)
 * @param int    $targetId    Target record ID
 * @param array  $memos       Array of existing memos [['id','user_name','content','created_at'],...]
 */
function renderMemoPanel(string $targetType, int $targetId, array $memos = []): string
{
    $panelId = 'memo-panel-' . $targetType . '-' . $targetId;

    ob_start();
    ?>
    <div id="<?= htmlspecialchars($panelId) ?>" class="memo-panel bg-white rounded-lg border border-gray-200 shadow-sm">
        <!-- Memo Input -->
        <div class="p-4 border-b border-gray-100">
            <div class="flex gap-2">
                <textarea id="memo-input-<?= htmlspecialchars($targetType . '-' . $targetId) ?>"
                          class="form-textarea w-full rounded-md border-gray-300 text-sm py-2 px-3 resize-none focus:ring-blue-500 focus:border-blue-500"
                          rows="2"
                          placeholder="메모를 입력하세요..."></textarea>
                <button type="button"
                        class="btn btn-primary self-end text-sm py-2 px-4 whitespace-nowrap flex-shrink-0"
                        onclick="saveMemo('<?= htmlspecialchars($targetType) ?>', <?= $targetId ?>)">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    저장
                </button>
            </div>
        </div>

        <!-- Memo History -->
        <div id="memo-list-<?= htmlspecialchars($targetType . '-' . $targetId) ?>"
             class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
            <?php if (empty($memos)): ?>
            <div class="px-4 py-8 text-center text-gray-400 text-sm memo-empty">
                등록된 메모가 없습니다.
            </div>
            <?php else: ?>
            <?php foreach ($memos as $memo): ?>
            <div class="memo-item px-4 py-3 hover:bg-gray-50/50 transition-colors">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium text-gray-700"><?= htmlspecialchars($memo['user_name'] ?? '') ?></span>
                    <span class="text-xs text-gray-400"><?= htmlspecialchars($memo['created_at'] ?? '') ?></span>
                </div>
                <p class="text-sm text-gray-600 whitespace-pre-wrap"><?= htmlspecialchars($memo['content'] ?? '') ?></p>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function saveMemo(targetType, targetId) {
        const inputEl = document.getElementById('memo-input-' + targetType + '-' + targetId);
        const content = inputEl.value.trim();

        if (!content) {
            showToast('메모 내용을 입력하세요.', 'error');
            return;
        }

        apiRequest('/api/' + (targetType === 'company' ? 'erp' : targetType) + '/memo.php', 'POST', {
            target_type: targetType,
            target_id: targetId,
            content: content
        }).then(data => {
            if (data.success) {
                inputEl.value = '';
                showToast('메모가 저장되었습니다.', 'success');

                // Prepend new memo to list
                const listEl = document.getElementById('memo-list-' + targetType + '-' + targetId);
                const emptyEl = listEl.querySelector('.memo-empty');
                if (emptyEl) emptyEl.remove();

                const memoHtml = `
                    <div class="memo-item px-4 py-3 hover:bg-gray-50/50 transition-colors">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-gray-700">${escapeHtml(data.data.user_name)}</span>
                            <span class="text-xs text-gray-400">${escapeHtml(data.data.created_at)}</span>
                        </div>
                        <p class="text-sm text-gray-600 whitespace-pre-wrap">${escapeHtml(data.data.content)}</p>
                    </div>
                `;
                listEl.insertAdjacentHTML('afterbegin', memoHtml);
            } else {
                showToast(data.message || '메모 저장에 실패했습니다.', 'error');
            }
        }).catch(() => {
            showToast('메모 저장 중 오류가 발생했습니다.', 'error');
        });
    }

    </script>
    <?php
    return ob_get_clean();
}
