<?php
/**
 * Modal component.
 *
 * @param string $id      Modal unique identifier
 * @param string $title   Modal title
 * @param string $content Modal body HTML content
 * @param array  $options Optional settings:
 *   - 'size'    => string  'sm', 'md', 'lg', 'xl' (default 'md')
 *   - 'footer'  => string  Footer HTML content
 *   - 'static'  => bool    Prevent closing on backdrop click
 */
function renderModal(string $id, string $title, string $content, array $options = []): string
{
    $size = $options['size'] ?? 'md';
    $footer = $options['footer'] ?? '';
    $static = $options['static'] ?? false;

    $sizeClasses = [
        'sm' => 'max-w-md',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    ];
    $maxWidth = $sizeClasses[$size] ?? $sizeClasses['md'];

    ob_start();
    ?>
    <div id="<?= htmlspecialchars($id) ?>"
         class="modal-overlay hidden fixed inset-0 z-[100] overflow-y-auto"
         <?= $static ? 'data-static="true"' : '' ?>>
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <!-- Backdrop -->
            <div class="modal-backdrop fixed inset-0 bg-black/50 transition-opacity"
                 <?= !$static ? 'onclick="closeModal(\'' . htmlspecialchars($id) . '\')"' : '' ?>></div>

            <!-- Modal Content -->
            <div class="modal-content relative bg-white rounded-lg shadow-xl <?= $maxWidth ?> w-full transform transition-all">
                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($title) ?></h3>
                    <button type="button" onclick="closeModal('<?= htmlspecialchars($id) ?>')"
                            class="text-gray-400 hover:text-gray-600 transition-colors rounded-lg p-1 hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="px-6 py-4">
                    <?= $content ?>
                </div>

                <?php if ($footer): ?>
                <!-- Footer -->
                <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                    <?= $footer ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
