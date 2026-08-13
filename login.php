<?php
ob_start();
require_once 'includes/auth.php';
require_no_auth('index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Survey Pacific — Work Journey</title>
<link rel="icon" type="image/png" href="image.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Nunito:wght@700&display=swap">
<link rel="stylesheet" href="assets/app.css">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
<style>
  * { font-family: 'Inter', system-ui, sans-serif; box-sizing: border-box; }
  [x-cloak] { display: none !important; }
  @keyframes spFadeUp { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }
  @keyframes ping { 75%,100% { transform:scale(2); opacity:0; } }
  @keyframes spin { to { transform:rotate(360deg); } }
  .anim-fade-up      { animation: spFadeUp 0.6s ease both; }
  .anim-fade-up-late { animation: spFadeUp 0.6s ease 0.08s both; }
  .animate-ping      { animation: ping 1.5s cubic-bezier(0,0,0.2,1) infinite; }
  .animate-spin      { animation: spin 1s linear infinite; }
  @media (max-width:767px){
    input:not([type=checkbox]):not([type=radio]),select,textarea{font-size:16px!important}
  }
  :root{
    --sp-page-bg: linear-gradient(160deg,#EEF3FB 0%,#E4EBF7 45%,#DDE6F5 100%);
    --sp-grid-line: rgba(7,29,43,0.035);
    --sp-navy:#071D2B; --sp-slate:#344054; --sp-gray:#667085;
    --sp-muted:#8FA3BF; --sp-footer:#98A9C4; --sp-placeholder:#B8CCED;
    --sp-back-text:#475467; --sp-back-bg:rgba(255,255,255,0.6); --sp-back-border:rgba(255,255,255,0.9);
    --sp-feature-bg:rgba(255,255,255,0.65); --sp-feature-border:rgba(255,255,255,0.9);
    --sp-card-bg:#ffffff; --sp-card-border:rgba(255,255,255,0.9); --sp-footer-border:rgba(7,29,43,0.08);
  }
  body.dark-mode{
    --sp-page-bg: linear-gradient(160deg,#0B0F14 0%,#0D1420 45%,#0A0E16 100%);
    --sp-grid-line: rgba(255,255,255,0.04);
    --sp-navy:#E9ECF1; --sp-slate:#C6CDD8; --sp-gray:#93A0B4;
    --sp-muted:#7C8CA6; --sp-footer:#5B6B85; --sp-placeholder:#4A5568;
    --sp-back-text:#C6CDD8; --sp-back-bg:rgba(255,255,255,0.06); --sp-back-border:rgba(255,255,255,0.12);
    --sp-feature-bg:rgba(255,255,255,0.04); --sp-feature-border:rgba(255,255,255,0.08);
    --sp-card-bg:#151B23; --sp-card-border:rgba(255,255,255,0.08); --sp-footer-border:rgba(255,255,255,0.08);
  }
  body.dark-mode .bg-\[\#F0FDF4\]{background:#0F2B20;}
  body.dark-mode .bg-\[\#FFF1F0\]{background:#34191A;}
  body.dark-mode .border-\[\#BBF7D0\]{border-color:#1F4A38;}
  body.dark-mode .border-\[\#FECDCA\]{border-color:#5C2323;}
  body.dark-mode input{background:#151B23;color:#E9ECF1;border-color:#2A323D!important;}
  body.dark-mode .placeholder\:text-\[\#B8CCED\]::placeholder{color:#4A5568;}
</style>
</head>
<body class="relative min-h-screen flex flex-col overflow-y-auto"
      style="font-family:'Inter',system-ui,sans-serif;background:var(--sp-page-bg)"
      x-data="loginApp()" :class="darkMode?'dark-mode':''">

<!-- Ambient background decoration -->
<div aria-hidden class="pointer-events-none absolute inset-0 overflow-hidden">
  <div class="absolute rounded-full" style="width:620px;height:620px;top:-260px;right:-160px;background:radial-gradient(circle,rgba(18,104,243,0.16) 0%,rgba(18,104,243,0) 70%)"></div>
  <div class="absolute rounded-full" style="width:520px;height:520px;bottom:-240px;left:-140px;background:radial-gradient(circle,rgba(89,37,220,0.10) 0%,rgba(89,37,220,0) 70%)"></div>
  <div class="absolute inset-0" style="opacity:0.5;background-image:linear-gradient(var(--sp-grid-line) 1px,transparent 1px),linear-gradient(90deg,var(--sp-grid-line) 1px,transparent 1px);background-size:44px 44px;mask-image:radial-gradient(ellipse 80% 60% at 50% 40%,#000 40%,transparent 100%);-webkit-mask-image:radial-gradient(ellipse 80% 60% at 50% 40%,#000 40%,transparent 100%)"></div>
</div>

<!-- Top navbar -->
<header class="relative z-10 flex items-center justify-between px-4 sm:px-8 md:px-14" style="height:64px">
  <div class="flex items-center">
    <svg class="w-[160px] sm:w-[210px]" height="38" viewBox="0 0 300 52" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="26" cy="26" r="22" stroke="#1D3461" stroke-width="3.2"/>
      <circle cx="26" cy="26" r="9" fill="#1D3461"/>
      <text x="58" y="31" font-family="'Nunito','Inter',system-ui,sans-serif" font-size="28" font-weight="700" fill="#1D3461" letter-spacing="-0.3">Survey Pacific</text>
      <text x="59" y="46" font-family="'Inter',system-ui,sans-serif" font-size="10.5" font-weight="400" fill="#1D3461" letter-spacing="1.4">Global Research Execution Partner</text>
    </svg>
  </div>
  <div class="flex items-center gap-2.5">
    <button type="button" @click="toggleDarkMode()"
      class="flex items-center gap-1.5 text-[11px] font-bold px-3 py-2 rounded-full transition-all"
      style="color:var(--sp-back-text);background:var(--sp-back-bg);border:1px solid var(--sp-back-border);box-shadow:0 1px 3px rgba(7,29,43,0.05)"
      :title="darkMode?'Switch to light mode':'Switch to dark mode'">
      <svg x-show="!darkMode" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="#F5A623" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      <svg x-show="darkMode" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="#A9C6FF" stroke="none"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      <span x-text="darkMode?'Dark':'Light'"></span>
    </button>
    <a href="#" class="flex items-center gap-1.5 text-[12px] font-semibold px-3.5 py-2 rounded-full transition-all hover:text-[#1268F3]"
       style="color:var(--sp-back-text);background:var(--sp-back-bg);border:1px solid var(--sp-back-border);box-shadow:0 1px 3px rgba(7,29,43,0.05)">
      Back to Website <span>→</span>
    </a>
  </div>
</header>

<!-- Main content -->
<main class="relative z-10 flex-1 flex flex-col lg:flex-row items-center justify-center gap-8 lg:gap-16 px-4 sm:px-8 md:px-14 pt-6 sm:pt-10 pb-8 max-w-[1200px] w-full mx-auto">

  <!-- Left — hero copy -->
  <div class="flex-1 flex flex-col justify-center anim-fade-up" style="max-width:560px">
    <div class="inline-flex items-center gap-2 self-start rounded-full px-3 py-1.5 mb-6"
         style="background:rgba(18,104,243,0.10);border:1px solid rgba(18,104,243,0.18)">
      <span class="relative flex h-1.5 w-1.5">
        <span class="absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping" style="background:#1268F3"></span>
        <span class="relative inline-flex rounded-full h-1.5 w-1.5" style="background:#1268F3"></span>
      </span>
      <span class="text-[11px] font-bold uppercase tracking-[0.12em]" style="color:#1268F3">Internal Work System</span>
    </div>

    <h1 class="font-extrabold leading-[1.1] tracking-tight mb-5" style="font-size:clamp(32px,3.8vw,48px);color:var(--sp-navy)">
      Manage every inquiry<br>from first contact to
      <span style="background:linear-gradient(120deg,#1268F3 0%,#5925DC 100%);-webkit-background-clip:text;background-clip:text;color:transparent"> payment.</span>
    </h1>
    <p class="text-[14px] leading-[1.72] mb-8" style="color:var(--sp-gray);max-width:420px">
      A secure workspace for Survey Pacific employees to create inquiries, assign tasks, track client stages, manage follow-ups and review the complete work journey.
    </p>


    <!-- Feature cards -->
    <div class="hidden lg:grid grid-cols-3 gap-4" style="max-width:520px">
      <div class="rounded-[12px] p-4 transition-all hover:-translate-y-0.5 hover:shadow-lg"
           style="background:var(--sp-feature-bg);border:1px solid var(--sp-feature-border);box-shadow:0 1px 3px rgba(7,29,43,0.04)">
        <div class="w-8 h-8 rounded-[8px] flex items-center justify-center mb-3" style="background:rgba(18,104,243,0.10);color:#1268F3">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="3" width="16" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M1 7h16" stroke="currentColor" stroke-width="1.5"/><path d="M5 11h3M5 13h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </div>
        <div class="text-[12px] font-bold mb-1" style="color:var(--sp-navy)">Inquiry Tracking</div>
        <div class="text-[11px] leading-[1.55]" style="color:var(--sp-muted)">Log every client inquiry from the first contact, with full requirement details.</div>
      </div>
      <div class="rounded-[12px] p-4 transition-all hover:-translate-y-0.5 hover:shadow-lg"
           style="background:var(--sp-feature-bg);border:1px solid var(--sp-feature-border);box-shadow:0 1px 3px rgba(7,29,43,0.04)">
        <div class="w-8 h-8 rounded-[8px] flex items-center justify-center mb-3" style="background:rgba(18,104,243,0.10);color:#1268F3">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="6" cy="5" r="2.5" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="13" r="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M8.5 5h3a2 2 0 0 1 2 2v3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        </div>
        <div class="text-[12px] font-bold mb-1" style="color:var(--sp-navy)">Task Handover</div>
        <div class="text-[11px] leading-[1.55]" style="color:var(--sp-muted)">Assign workflow steps to team members with due dates and instructions.</div>
      </div>
      <div class="rounded-[12px] p-4 transition-all hover:-translate-y-0.5 hover:shadow-lg"
           style="background:var(--sp-feature-bg);border:1px solid var(--sp-feature-border);box-shadow:0 1px 3px rgba(7,29,43,0.04)">
        <div class="w-8 h-8 rounded-[8px] flex items-center justify-center mb-3" style="background:rgba(18,104,243,0.10);color:#1268F3">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 14l3-4 3 2 3-5 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="1" y="1" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/></svg>
        </div>
        <div class="text-[12px] font-bold mb-1" style="color:var(--sp-navy)">Complete Journey</div>
        <div class="text-[11px] leading-[1.55]" style="color:var(--sp-muted)">Track from proposal to payment with stage history and analytics.</div>
      </div>
    </div>
  </div>

  <!-- Right — auth card -->
  <div class="w-full lg:w-auto shrink-0 anim-fade-up-late" style="width:100%;max-width:404px">
    <div class="relative rounded-[18px] px-7 pt-8 pb-7 overflow-hidden"
         style="background:var(--sp-card-bg);box-shadow:0 24px 60px -12px rgba(7,29,43,0.22),0 2px 6px rgba(7,29,43,0.06);border:1px solid var(--sp-card-border)">
      <!-- Accent top bar -->
      <div class="absolute top-0 left-0 right-0 h-1" style="background:linear-gradient(90deg,#1268F3 0%,#5925DC 100%)"></div>

      <!-- Brand emblem -->
      <div class="flex items-center justify-center mb-5">
        <svg width="160" height="42" viewBox="0 0 300 52" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="26" cy="26" r="22" stroke="#1D3461" stroke-width="3.2"/>
          <circle cx="26" cy="26" r="9" fill="#1D3461"/>
          <text x="58" y="31" font-family="'Nunito','Inter',system-ui,sans-serif" font-size="28" font-weight="700" fill="#1D3461" letter-spacing="-0.3">Survey Pacific</text>
          <text x="59" y="46" font-family="'Inter',system-ui,sans-serif" font-size="10.5" font-weight="400" fill="#1D3461" letter-spacing="1.4">Global Research Execution Partner</text>
        </svg>
      </div>

      <!-- Card header -->
      <div class="mb-6 text-center">
        <h2 class="text-[21px] font-bold mb-1" style="color:var(--sp-navy)"
            x-text="mode==='login' ? 'Employee Login' : 'Request Access'"></h2>
        <p class="text-[12px]" style="color:var(--sp-muted)"
           x-text="mode==='login' ? 'Survey Pacific employees can sign in below.' : 'Submit a request — an admin will review and approve.'"></p>
      </div>

      <!-- Notice -->
      <div x-show="notice" x-cloak class="flex items-start gap-2 text-[12px] bg-[#F0FDF4] border border-[#BBF7D0] rounded-[8px] px-3 py-2.5 mb-4" style="color:#16803C">
        <svg class="w-3.5 h-3.5 mt-px shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span x-text="notice"></span>
      </div>

      <!-- LOGIN FORM -->
      <form x-show="mode==='login'" @submit.prevent="submitLogin" class="space-y-4">
        <div>
          <label class="block text-[11px] font-bold mb-1.5" style="color:var(--sp-slate)">Employee Email</label>
          <input type="email" x-model="email" placeholder="name@surveypacific.in" autocomplete="email" required
                 class="w-full text-[13px] border border-[#E4E7EC] rounded-[10px] px-3.5 py-3 focus:outline-none focus:border-[#1268F3] placeholder:text-[#B8CCED] bg-white transition-colors" />
        </div>
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="text-[11px] font-bold" style="color:var(--sp-slate)">Password</label>
            <button type="button" @click="notice='Contact your Master Admin at mahendra@surveypacific.com to reset your password.';error=''"
                    class="text-[11px] font-semibold transition-colors hover:opacity-80" style="color:#1268F3">Forgot Password?</button>
          </div>
          <div class="relative">
            <input :type="showPw ? 'text' : 'password'" x-model="password" placeholder="••••••••" autocomplete="current-password" required
                   class="w-full text-[13px] border border-[#E4E7EC] rounded-[10px] px-3.5 py-3 pr-11 focus:outline-none focus:border-[#1268F3] placeholder:text-[#B8CCED] bg-white transition-colors" />
            <button type="button" @click="showPw=!showPw" class="absolute right-3.5 top-1/2 -translate-y-1/2 transition-colors" style="color:var(--sp-placeholder)">
              <svg x-show="!showPw" class="w-[15px] h-[15px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg x-show="showPw"  class="w-[15px] h-[15px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
            </button>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" id="rem" class="w-3.5 h-3.5 cursor-pointer accent-[#1268F3]" />
          <label for="rem" class="text-[12px] cursor-pointer" style="color:var(--sp-gray)">Remember me</label>
        </div>
        <div x-show="error" x-cloak class="flex items-center gap-2 text-[12px] bg-[#FFF1F0] border border-[#FECDCA] rounded-[8px] px-3 py-2.5" style="color:#B42318">
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span x-text="error"></span>
        </div>
        <button type="submit" :disabled="loading"
                class="w-full text-[13px] font-bold text-white rounded-[10px] transition-all disabled:opacity-60 flex items-center justify-center gap-2 hover:brightness-105"
                style="height:46px;background:linear-gradient(135deg,#1268F3 0%,#0B4FD1 100%);box-shadow:0 6px 16px rgba(18,104,243,0.32)">
          <template x-if="loading">
            <span class="flex items-center gap-2">
              <span class="w-4 h-4 border-2 rounded-full animate-spin shrink-0" style="border-color:rgba(255,255,255,0.3);border-top-color:white"></span>
              Signing in…
            </span>
          </template>
          <template x-if="!loading"><span>Sign In</span></template>
        </button>
        <p class="text-center text-[12px]" style="color:var(--sp-muted)">
          New employee?
          <button type="button" @click="mode='signup';error='';notice='';password='';confirm=''"
                  class="font-semibold transition-colors hover:opacity-80" style="color:#1268F3">Request access</button>
        </p>
      </form>

      <!-- SIGNUP FORM -->
      <form x-show="mode==='signup'" @submit.prevent="submitSignup" class="space-y-3.5">
        <div>
          <label class="block text-[11px] font-bold mb-1.5" style="color:var(--sp-slate)">Full Name</label>
          <input type="text" x-model="name" placeholder="Your full name" autocomplete="name" required
                 class="w-full text-[13px] border border-[#E4E7EC] rounded-[10px] px-3.5 py-3 focus:outline-none focus:border-[#1268F3] placeholder:text-[#B8CCED] bg-white transition-colors" />
        </div>
        <div>
          <label class="block text-[11px] font-bold mb-1.5" style="color:var(--sp-slate)">Work Email</label>
          <input type="email" x-model="email" placeholder="name@surveypacific.in" autocomplete="email" required
                 class="w-full text-[13px] border border-[#E4E7EC] rounded-[10px] px-3.5 py-3 focus:outline-none focus:border-[#1268F3] placeholder:text-[#B8CCED] bg-white transition-colors" />
        </div>
        <div>
          <label class="block text-[11px] font-bold mb-1.5" style="color:var(--sp-slate)">Password</label>
          <div class="relative">
            <input :type="showPw ? 'text' : 'password'" x-model="password" placeholder="Minimum 6 characters" required
                   class="w-full text-[13px] border border-[#E4E7EC] rounded-[10px] px-3.5 py-3 pr-11 focus:outline-none focus:border-[#1268F3] placeholder:text-[#B8CCED] bg-white transition-colors" />
            <button type="button" @click="showPw=!showPw" class="absolute right-3.5 top-1/2 -translate-y-1/2 transition-colors" style="color:var(--sp-placeholder)">
              <svg x-show="!showPw" class="w-[15px] h-[15px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg x-show="showPw"  class="w-[15px] h-[15px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
            </button>
          </div>
        </div>
        <div>
          <label class="block text-[11px] font-bold mb-1.5" style="color:var(--sp-slate)">Confirm Password</label>
          <input :type="showPw ? 'text' : 'password'" x-model="confirm" placeholder="Re-enter password" required
                 class="w-full text-[13px] border border-[#E4E7EC] rounded-[10px] px-3.5 py-3 focus:outline-none focus:border-[#1268F3] placeholder:text-[#B8CCED] bg-white transition-colors" />
        </div>
        <div x-show="error" x-cloak class="flex items-center gap-2 text-[12px] bg-[#FFF1F0] border border-[#FECDCA] rounded-[8px] px-3 py-2.5" style="color:#B42318">
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span x-text="error"></span>
        </div>
        <button type="submit" :disabled="loading"
                class="w-full text-[13px] font-bold text-white rounded-[10px] transition-all disabled:opacity-60 flex items-center justify-center gap-2 hover:brightness-105"
                style="height:46px;background:linear-gradient(135deg,#1268F3 0%,#0B4FD1 100%);box-shadow:0 6px 16px rgba(18,104,243,0.32)">
          <template x-if="loading">
            <span class="flex items-center gap-2">
              <span class="w-4 h-4 border-2 rounded-full animate-spin shrink-0" style="border-color:rgba(255,255,255,0.3);border-top-color:white"></span>
              Submitting…
            </span>
          </template>
          <template x-if="!loading"><span>Submit Request</span></template>
        </button>
        <p class="text-center text-[12px]" style="color:var(--sp-muted)">
          <button type="button" @click="mode='login';error='';notice='';password='';confirm=''"
                  class="font-semibold inline-flex items-center gap-1 transition-colors hover:opacity-80" style="color:#1268F3">
            ← Back to sign in
          </button>
        </p>
      </form>

    </div>
  </div>
</main>

<!-- Footer -->
<footer class="relative z-10 flex items-center justify-between px-4 sm:px-8 md:px-14 py-4 border-t" style="border-color:var(--sp-footer-border)">
  <span class="text-[11px]" style="color:var(--sp-footer)">© <?= date('Y') ?> Survey Pacific. All rights reserved.</span>
  <span class="text-[11px] font-medium" style="color:var(--sp-footer)">surveypacific.com</span>
</footer>

<script>
function loginApp() {
  return {
    mode: 'login',
    name: '', email: '', password: '', confirm: '',
    showPw: false, loading: false,
    error: '', notice: '',
    darkMode: localStorage.getItem('sp_theme')==='dark',
    toggleDarkMode(){ this.darkMode=!this.darkMode; localStorage.setItem('sp_theme', this.darkMode?'dark':'light'); },

    async submitLogin() {
      this.error = ''; this.notice = ''; this.loading = true;
      try {
        const res  = await fetch('api/login.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ email:this.email, password:this.password }) });
        const data = await res.json();
        if (data.ok) { window.location.href = 'index.php'; }
        else         { this.error = data.message; }
      } catch(e) { this.error = 'Connection error. Please try again.'; }
      this.loading = false;
    },

    async submitSignup() {
      this.error = ''; this.notice = '';
      if (!this.name.trim() || !this.email.trim() || !this.password) { this.error = 'Please fill in all fields.'; return; }
      if (this.password.length < 6) { this.error = 'Password must be at least 6 characters.'; return; }
      if (this.password !== this.confirm) { this.error = 'Passwords do not match.'; return; }
      this.loading = true;
      try {
        const res  = await fetch('api/signup.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ name:this.name, email:this.email, password:this.password }) });
        const data = await res.json();
        if (data.ok) { this.notice = data.message; this.mode = 'login'; this.password = ''; this.confirm = ''; this.name = ''; }
        else         { this.error = data.message; }
      } catch(e) { this.error = 'Connection error. Please try again.'; }
      this.loading = false;
    },
  }
}
</script>
</body>
</html>
