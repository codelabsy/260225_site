<?php
/**
 * Shopping DB upload page (standalone).
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';

Permission::requireLogin();

$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();

// Get upload histories
$db = Database::getInstance();
$uploadHistories = $db->fetchAll(
    'SELECT uh.*, u.name AS user_name
     FROM upload_histories uh
     LEFT JOIN users u ON u.id = uh.user_id
     ORDER BY uh.created_at DESC
     LIMIT 50'
);

$currentPage = 'shopping';
$pageTitle = '쇼핑DB 업로드';
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-gray-900">엑셀(CSV) 업로드</h2>
        <p class="text-sm text-gray-500 mt-1">쇼핑 DB에 CSV 파일을 업로드합니다.</p>
    </div>

    <!-- Upload Card -->
    <div class="card mb-6">
        <div class="card-body">
            <!-- Drag & Drop Area -->
            <div id="upload-dropzone-page" class="border-2 border-dashed border-gray-300 rounded-lg p-12 text-center hover:border-blue-400 transition-colors cursor-pointer"
                 onclick="document.getElementById('upload-file-input-page').click()">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                <p class="text-gray-600 mb-2">CSV 파일을 여기에 드래그하거나 클릭하여 선택</p>
                <p class="text-sm text-gray-400">CSV 형식만 지원됩니다. 첫 번째 행은 헤더로 사용됩니다.</p>
                <p class="text-xs text-gray-400 mt-2">필수 컬럼: 연락처(전화번호) / 선택: 상호명, 담당자명(이름), 기타1~3</p>
                <input type="file" id="upload-file-input-page" accept=".csv" class="hidden">
            </div>

            <!-- Selected file -->
            <div id="upload-file-info-page" class="hidden mt-4 p-3 bg-gray-50 rounded-md flex items-center justify-between">
                <span class="text-sm text-gray-700" id="upload-file-name-page"></span>
                <button type="button" onclick="clearUploadFilePage()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Progress -->
            <div id="upload-progress-page" class="hidden mt-4">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <div class="spinner"></div>
                    <span>업로드 중...</span>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                    <div id="upload-progress-bar-page" class="bg-blue-600 h-2 rounded-full transition-all" style="width: 0%"></div>
                </div>
            </div>

            <!-- Result -->
            <div id="upload-result-page" class="hidden mt-4"></div>

            <div class="mt-4 flex justify-end">
                <button type="button" id="btn-do-upload-page" class="btn btn-primary" onclick="doUploadPage()">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    업로드
                </button>
            </div>
        </div>
    </div>

    <!-- Upload History -->
    <div class="card">
        <div class="card-header">
            <h3>업로드 이력</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600">파일명</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600">업로더</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600">전체</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600">성공</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600">중복</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600">업로드일시</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($uploadHistories)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">업로드 이력이 없습니다.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($uploadHistories as $uh): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($uh['file_name']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($uh['user_name'] ?? '') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= number_format($uh['total_count']) ?></td>
                        <td class="px-4 py-3 text-sm text-green-600 font-medium"><?= number_format($uh['success_count']) ?></td>
                        <td class="px-4 py-3 text-sm text-red-600 font-medium"><?= number_format($uh['duplicate_count']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-500"><?= htmlspecialchars($uh['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let selectedFilePage = null;

// Drag and drop
const dropzonePage = document.getElementById('upload-dropzone-page');
const fileInputPage = document.getElementById('upload-file-input-page');

['dragenter', 'dragover'].forEach(evt => {
    dropzonePage.addEventListener(evt, (e) => {
        e.preventDefault();
        dropzonePage.classList.add('border-blue-400', 'bg-blue-50');
    });
});

['dragleave', 'drop'].forEach(evt => {
    dropzonePage.addEventListener(evt, (e) => {
        e.preventDefault();
        dropzonePage.classList.remove('border-blue-400', 'bg-blue-50');
    });
});

dropzonePage.addEventListener('drop', (e) => {
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFileSelectPage(files[0]);
    }
});

fileInputPage.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        handleFileSelectPage(e.target.files[0]);
    }
});

function handleFileSelectPage(file) {
    if (!file.name.toLowerCase().endsWith('.csv')) {
        showToast('CSV 파일만 업로드 가능합니다.', 'error');
        return;
    }
    selectedFilePage = file;
    document.getElementById('upload-file-name-page').textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + 'KB)';
    document.getElementById('upload-file-info-page').classList.remove('hidden');
}

function clearUploadFilePage() {
    selectedFilePage = null;
    fileInputPage.value = '';
    document.getElementById('upload-file-info-page').classList.add('hidden');
}

async function doUploadPage() {
    if (!selectedFilePage) {
        showToast('파일을 선택해주세요.', 'error');
        return;
    }

    const progressEl = document.getElementById('upload-progress-page');
    const resultEl = document.getElementById('upload-result-page');
    const btn = document.getElementById('btn-do-upload-page');

    progressEl.classList.remove('hidden');
    resultEl.classList.add('hidden');
    btn.disabled = true;

    const formData = new FormData();
    formData.append('file', selectedFilePage);

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch('/api/shopping/upload.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-Token': csrfToken || ''
            },
            body: formData,
        });

        if (!response.ok) throw new Error('HTTP ' + response.status);
        const data = await response.json();
        progressEl.classList.add('hidden');

        if (data.success) {
            const d = data.data;
            let html = '<div class="p-4 rounded-lg bg-green-50 border border-green-200">';
            html += '<h4 class="text-sm font-semibold text-green-800 mb-2">업로드 완료</h4>';
            html += '<div class="grid grid-cols-3 gap-4 text-center">';
            html += '<div><p class="text-2xl font-bold text-gray-800">' + d.total + '</p><p class="text-xs text-gray-500">전체</p></div>';
            html += '<div><p class="text-2xl font-bold text-green-600">' + d.success_count + '</p><p class="text-xs text-gray-500">성공</p></div>';
            html += '<div><p class="text-2xl font-bold text-red-600">' + d.duplicate_count + '</p><p class="text-xs text-gray-500">중복</p></div>';
            html += '</div>';

            if (d.duplicates && d.duplicates.length > 0) {
                html += '<div class="mt-3 max-h-40 overflow-y-auto">';
                html += '<p class="text-xs font-medium text-gray-600 mb-1">중복 목록:</p>';
                html += '<div class="space-y-1">';
                d.duplicates.forEach(dup => {
                    html += '<div class="text-xs text-gray-500 bg-white p-2 rounded">' +
                            escapeHtml(dup.company_name || '-') + ' / ' +
                            escapeHtml(dup.phone) + '</div>';
                });
                html += '</div></div>';
            }

            html += '</div>';
            resultEl.innerHTML = html;
            resultEl.classList.remove('hidden');
            showToast('업로드가 완료되었습니다.', 'success');

            // Reload after 2s
            setTimeout(() => { location.reload(); }, 2000);
        } else {
            showToast(data.message || '업로드에 실패했습니다.', 'error');
        }
    } catch (err) {
        progressEl.classList.add('hidden');
        showToast('업로드 중 오류가 발생했습니다.', 'error');
    }

    btn.disabled = false;
}
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
