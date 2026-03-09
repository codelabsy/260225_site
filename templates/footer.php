    </main>

    <!-- Footer Status Bar -->
    <footer class="bg-white border-t border-gray-200 py-3 mt-auto">
        <div class="max-w-screen-2xl mx-auto px-4 flex items-center justify-between text-xs text-gray-500">
            <div class="flex items-center space-x-4">
                <span>ERP+CRM System</span>
                <?php if (isset($currentUser) && $currentUser): ?>
                <span class="text-gray-300">|</span>
                <span>
                    로그인: <?= htmlspecialchars($currentUser['name']) ?>
                    (<?= $isAdmin ? '관리자' : '직원' ?>)
                </span>
                <?php endif; ?>
            </div>
            <div class="hidden sm:block text-gray-400">
                &copy; <?= date('Y') ?> ERP+CRM. All rights reserved.
            </div>
        </div>
    </footer>

    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
