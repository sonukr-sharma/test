<?php
$currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$navigation = [
    ['broadcast.php', 'Broadcast', 'fa-paper-plane'],
    ['withdrawal.php', 'Withdrawals', 'fa-wallet'],
    ['channels.php', 'Channels', 'fa-tower-broadcast'],
    ['settings.php', 'Settings', 'fa-gear'],
    ['account.php', 'Account', 'fa-user-shield'],
];
?>
<div id="sidebar-backdrop" onclick="toggleSidebar()" class="fixed inset-0 z-40 hidden bg-slate-900/50 backdrop-blur-sm transition-opacity lg:hidden" aria-hidden="true"></div>
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex h-screen w-64 shrink-0 -translate-x-full select-none flex-col bg-[#141627] text-slate-300 transition-transform duration-300 ease-in-out lg:sticky lg:top-0 lg:translate-x-0">
  <div class="flex items-center justify-between px-5 py-6">
    <a href="broadcast.php" class="flex items-center gap-3" aria-label="Refer &amp; Earn admin home">
      <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-400 text-lg font-bold text-amber-950 ring-2 ring-amber-200">T</span>
      <span>
        <span class="block text-base font-semibold leading-tight text-white">Refer &amp; Earn</span>
        <span class="block text-xs text-slate-400">Admin Panel</span>
      </span>
    </a>
    <button type="button" onclick="toggleSidebar()" aria-label="Close menu" class="p-1 text-slate-400 hover:text-white lg:hidden">
      <i class="fa-solid fa-xmark text-xl"></i>
    </button>
  </div>

  <nav aria-label="Admin navigation" class="mt-2 flex-1 space-y-1.5 overflow-y-auto px-3 text-sm font-medium">
    <?php foreach ($navigation as [$href, $label, $icon]): ?>
      <?php $active = $currentPage === $href; ?>
      <a href="<?= e($href) ?>"
         <?= $active ? 'aria-current="page"' : '' ?>
         class="flex items-center gap-3.5 rounded-xl px-4 py-3 transition <?= $active ? 'bg-gradient-to-r from-[#6c3cf7] to-[#5a29e4] text-white shadow-lg shadow-indigo-600/30' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
        <i class="fa-solid <?= e($icon) ?> w-5 text-center text-base <?= $active ? 'text-white' : 'text-slate-400' ?>"></i>
        <span><?= e($label) ?></span>
        <?php if ($href === 'broadcast.php'): ?>
          <span class="ml-auto rounded-full bg-white/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-violet-100">Priority</span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="mt-2 border-t border-white/5 px-3 pt-3">
    <div class="px-4 py-2">
      <p class="truncate text-xs font-semibold text-white"><?= e((string) $admin['name']) ?></p>
      <p class="truncate text-[11px] text-slate-400"><?= e((string) $admin['email']) ?></p>
    </div>
    <a href="logout.php" class="flex items-center gap-3.5 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white">
      <i class="fa-solid fa-right-from-bracket w-5 text-center text-base text-slate-400"></i>
      <span>Sign out</span>
    </a>
  </div>
</aside>
<script>
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const isClosed = sidebar.classList.toggle('-translate-x-full');
    backdrop.classList.toggle('hidden', isClosed);
  }
</script>
