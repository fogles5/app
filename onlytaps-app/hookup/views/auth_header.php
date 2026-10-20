<?php
if (!function_exists('ico')) {
    function ico(string $n, string $c = 'size-4 opacity-70'): string {
        static $p = [
            'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'list' => '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4M12 16h4M8 11h.01M8 16h.01"/>',
            'heart' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
            'msg' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
            'gem' => '<path d="M6 3h12l4 6-10 13L2 9Z"/><path d="M11 3 8 9l4 13 4-13-3-6"/><path d="M2 9h20"/>',
            'cog' => '<circle cx="12" cy="12" r="3"/><path d="M12 1v3M12 20v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M1 12h3M20 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
            'out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
            'in' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5M15 12H3"/>',
            'plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
            'pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
            'store' => '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M2 7h20"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/>',
            'menu' => '<path d="M4 12h16M4 6h16M4 18h16"/>',
            'bot' => '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2M20 14h2M15 13v2M9 13v2"/>',
            'down' => '<path d="m6 9 6 6 6-6"/>',
        ];
        return '<svg class="' . $c . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[$n] . '</svg>';
    }
}
// Shared top of the Tailwind-styled auth pages (login/register). Expects optional $auth_title.
$auth_title = $auth_title ?? 'Turnpage';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
$uid = (int)($_SESSION['user_id'] ?? 0);
$uname = (string)($_SESSION['username'] ?? 'Account');
$mi = 'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink/90 transition hover:bg-white/10 hover:text-white';
$link = 'inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-ink/80 transition hover:bg-white/10 hover:text-white';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a0912">
    <title><?php echo htmlspecialchars($auth_title); ?> · Turnpage</title>
    <link rel="icon" href="/assets/images/favicon.png">
    <link rel="stylesheet" href="/assets/css/tw.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/tw.css') ?: 1; ?>">
</head>
<body>
<header class="sticky top-0 z-40 border-b border-line bg-bg/80 backdrop-blur-xl">
    <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-5">
        <a href="/" class="flex items-center gap-2 text-lg font-extrabold tracking-tight text-white hover:text-white">
            <span class="grid size-8 place-items-center rounded-lg text-base" style="background-image: var(--grad)"><?php echo ico('list', 'size-4.5'); ?></span>
            Turnpage
        </a>
        <div class="flex items-center gap-1">
            <a href="/choose-location.php" class="<?php echo $link; ?>"><?php echo ico('pin'); ?> Locations</a>
            <a href="/marketplace.php" class="<?php echo $link; ?> hidden sm:inline-flex"><?php echo ico('store'); ?> Marketplace</a>
            <?php if ($uid): ?>
            <details class="group relative">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-white/10 bg-white/5 py-1.5 pl-1.5 pr-3 text-sm font-medium transition hover:bg-white/10 [&::-webkit-details-marker]:hidden">
                    <span class="grid size-7 place-items-center rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-xs font-bold text-white"><?php echo htmlspecialchars(strtoupper(substr($uname, 0, 1))); ?></span>
                    <span class="max-w-24 truncate"><?php echo htmlspecialchars($uname); ?></span>
                    <?php echo ico('down', 'size-3.5 transition group-open:rotate-180'); ?>
                </summary>
                <div class="menu-pop absolute right-0 z-50 mt-2 w-56 rounded-xl border border-white/10 bg-[#14102a]/95 p-1.5 shadow-2xl backdrop-blur-xl">
                    <a href="/profile.php?id=<?php echo $uid; ?>" class="<?php echo $mi; ?>"><?php echo ico('user'); ?> My profile</a>
                    <a href="/my-listings.php" class="<?php echo $mi; ?>"><?php echo ico('list'); ?> My Ads</a>
                    <a href="/favorites.php" class="<?php echo $mi; ?>"><?php echo ico('heart'); ?> Favorites</a>
                    <a href="/messages.php" class="<?php echo $mi; ?>"><?php echo ico('msg'); ?> Messages</a>
                    <a href="/ai-profiles" class="<?php echo $mi; ?>"><?php echo ico('bot'); ?> AI Profiles</a>
                    <a href="/membership.php" class="<?php echo $mi; ?>"><?php echo ico('gem'); ?> Membership</a>
                    <a href="/edit-profile.php" class="<?php echo $mi; ?>"><?php echo ico('cog'); ?> Edit profile</a>
                    <div class="my-1 border-t border-white/10"></div>
                    <a href="/logout.php" class="<?php echo $mi; ?> text-rose-300"><?php echo ico('out'); ?> Log out</a>
                </div>
            </details>
            <?php else: ?>
            <details class="group relative">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3.5 py-1.5 text-sm font-medium transition hover:bg-white/10 [&::-webkit-details-marker]:hidden">
                    <?php echo ico('menu', 'size-4'); ?> Menu
                </summary>
                <div class="menu-pop absolute right-0 z-50 mt-2 w-56 rounded-xl border border-white/10 bg-[#14102a]/95 p-1.5 shadow-2xl backdrop-blur-xl">
                    <a href="/login.php" class="<?php echo $mi; ?>"><?php echo ico('in'); ?> Log in</a>
                    <a href="/register-tos.php" class="<?php echo $mi; ?>"><?php echo ico('plus'); ?> Sign up</a>
                    <div class="my-1 border-t border-white/10"></div>
                    <a href="/choose-location.php" class="<?php echo $mi; ?>"><?php echo ico('pin'); ?> Locations</a>
                    <a href="/marketplace.php" class="<?php echo $mi; ?>"><?php echo ico('store'); ?> Marketplace</a>
                    <a href="/ai-profiles" class="<?php echo $mi; ?>"><?php echo ico('bot'); ?> AI Profiles</a>
                    <a href="/membership.php" class="<?php echo $mi; ?>"><?php echo ico('gem'); ?> Membership</a>
                </div>
            </details>
            <a href="/register-tos.php" class="btn btn-primary btn-sm ml-1">Sign up</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
<main>
