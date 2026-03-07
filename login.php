<?php
/**
 * Login page.
 */

require_once __DIR__ . '/core/Auth.php';

// Already logged in - redirect to dashboard
if (Auth::check()) {
    header('Location: /dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM - 로그인</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans KR', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <!-- Logo / Title -->
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">CRM System</h1>
            <p class="text-sm text-gray-500 mt-1">업무 관리 시스템</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-900">로그인</h2>
                <p class="text-sm text-gray-500 mt-1">계정 정보를 입력하여 로그인하세요.</p>
            </div>

            <form id="loginForm" onsubmit="return handleLogin(event)">
                <!-- Error message -->
                <div id="errorMessage" class="hidden mb-4 p-3 rounded-md bg-red-50 border border-red-200">
                    <p class="text-sm text-red-600" id="errorText"></p>
                </div>

                <!-- Username -->
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1.5">아이디</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        required
                        autocomplete="username"
                        placeholder="아이디를 입력하세요"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm shadow-sm placeholder-gray-400
                               focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent
                               transition duration-150"
                    >
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">비밀번호</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="비밀번호를 입력하세요"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm shadow-sm placeholder-gray-400
                               focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent
                               transition duration-150"
                    >
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    id="loginBtn"
                    class="w-full bg-gray-900 text-white py-2 px-4 rounded-md text-sm font-medium
                           hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2
                           transition duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    로그인
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">&copy; 2026 CRM System. All rights reserved.</p>
    </div>

    <script>
        async function handleLogin(e) {
            e.preventDefault();

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            const loginBtn = document.getElementById('loginBtn');

            if (!username || !password) {
                errorDiv.classList.remove('hidden');
                errorText.textContent = '아이디와 비밀번호를 입력해주세요.';
                return false;
            }

            // Disable button
            loginBtn.disabled = true;
            loginBtn.textContent = '로그인 중...';
            errorDiv.classList.add('hidden');

            try {
                const response = await fetch('/api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ username, password }),
                });

                if (!response.ok && response.status >= 500) throw new Error('Server error');
                const data = await response.json();

                if (data.success) {
                    window.location.href = data.redirect || '/dashboard.php';
                } else {
                    errorDiv.classList.remove('hidden');
                    errorText.textContent = data.message || '로그인에 실패했습니다.';
                    loginBtn.disabled = false;
                    loginBtn.textContent = '로그인';
                }
            } catch (err) {
                errorDiv.classList.remove('hidden');
                errorText.textContent = '서버와 통신할 수 없습니다. 다시 시도해주세요.';
                loginBtn.disabled = false;
                loginBtn.textContent = '로그인';
            }

            return false;
        }

        // Focus username field on load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>
