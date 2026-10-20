<?php
session_start();
require_once 'config/database.php';
require_once 'classes/CSRF.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

$page_title = 'Messages';
include 'views/header.php';
?>
<link rel="stylesheet" href="/assets/css/messages.css?v=1">
<div class="chat-app" id="chat-app" data-csrf="<?php echo htmlspecialchars(CSRF::getToken()); ?>">
    <div class="conn-banner" id="conn-banner" hidden>Reconnecting…</div>

    <aside class="chat-side">
        <div class="chat-side-head">
            <h1>Messages</h1>
            <input type="search" id="conv-search" placeholder="Search conversations" autocomplete="off">
        </div>
        <div class="conv-list" id="conv-list"></div>
        <div class="conv-empty" id="conv-empty" hidden></div>
    </aside>

    <section class="chat-main">
        <div class="thread-placeholder" id="thread-placeholder">
            <div class="ph-icon">💬</div>
            <h2>Your messages</h2>
            <p>Select a conversation to start chatting in real time.</p>
        </div>

        <div class="thread" id="thread" hidden>
            <header class="thread-head">
                <button type="button" class="icon-btn thread-back" id="thread-back" aria-label="Back">‹</button>
                <div class="avatar avatar-md" id="peer-avatar"></div>
                <div class="peer-info">
                    <a class="peer-name" id="peer-name" href="#"></a>
                    <span class="peer-status" id="peer-status"></span>
                </div>
                <div class="peer-menu-wrap">
                    <button type="button" class="icon-btn" id="peer-menu-btn" aria-label="More">⋯</button>
                    <div class="peer-menu" id="peer-menu" hidden>
                        <a id="peer-profile" href="#">View profile</a>
                        <a id="peer-report" href="/report-abuse.php">Report</a>
                        <button type="button" id="peer-block">Block user</button>
                    </div>
                </div>
            </header>

            <div class="msg-scroll" id="msg-scroll">
                <button type="button" class="load-more" id="load-more" hidden>Load earlier messages</button>
                <div class="msg-list" id="msg-list"></div>
            </div>

            <form class="composer" id="composer" autocomplete="off">
                <textarea id="composer-input" rows="1" maxlength="2100" placeholder="Write a message…"></textarea>
                <span class="composer-count" id="composer-count"></span>
                <button type="submit" class="send-btn" aria-label="Send">➤</button>
            </form>
        </div>
    </section>
</div>
<script>window.__ON_MESSAGES__ = true;</script>
<script src="/assets/js/realtime.js?v=2"></script>
<script src="/assets/js/chat.js?v=2"></script>
<?php $rt_loaded = true; include 'views/footer.php'; ?>
