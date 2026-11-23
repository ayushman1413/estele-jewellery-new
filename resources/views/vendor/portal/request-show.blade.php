@extends('layouts.vendor')

@section('meta_title', $request->request_number.' | Vendor Portal | Estele')

@section('content')

  @php
    $isOpen = $invitation->response_status === 'pending'
        && $request->status === 'bidding_active'
        && now()->lessThan($invitation->expires_at);
  @endphp

  <a href="{{ route('vendor.dashboard') }}" class="mb-4 inline-flex items-center gap-1.5 text-[12px] font-medium text-muted hover:text-accent">
    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
    Back to dashboard
  </a>

  <h1 class="mb-1 font-display text-[20px] uppercase tracking-[0.5px] text-heading">{{ $request->request_number }}</h1>
  <p class="mb-6 text-[13px] text-muted">Bidding closes {{ $request->bidding_end_at?->formatIst('d M Y, h:i A') }}</p>

  <div class="mb-5 rounded-xl border border-line bg-white p-4 sm:p-5" x-data="{ lightbox: null }">
    @if ($request->hasMedia('image'))
      <img
        style="cursor:zoom-in;"
        class="mb-3 max-h-72 w-full rounded-lg object-cover"
        src="{{ route('vendor.requests.image', $request) }}"
        alt="Jewellery photo"
        x-on:click="lightbox = 'image'"
      >
    @endif

    @if ($request->hasMedia('video'))
      <div style="position:relative; cursor:pointer;" class="mb-3" x-on:click="lightbox = 'video'">
        <video style="width:100%; border-radius:8px; pointer-events:none;" preload="metadata" muted playsinline>
          <source src="{{ route('vendor.requests.video', $request) }}#t=0.1">
        </video>
        <span style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; border-radius:8px; background:rgba(0,0,0,.35);">
          <svg viewBox="0 0 24 24" fill="#fff" style="width:40px; height:40px; opacity:.9;"><path d="M8 5v14l11-7z"/></svg>
        </span>
      </div>
    @endif

    @if ($request->description)
      <p class="text-[13px] leading-relaxed text-muted">{{ $request->description }}</p>
    @endif

    <template x-teleport="body">
      <div
        x-show="lightbox"
        x-cloak
        x-transition.opacity
        style="position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.92); padding:16px;"
        x-on:click.self="lightbox = null"
        x-on:keydown.escape.window="lightbox = null"
      >
        <button
          type="button"
          x-on:click="lightbox = null"
          style="position:absolute; top:16px; right:16px; width:40px; height:40px; display:flex; align-items:center; justify-content:center; border:0; border-radius:9999px; background:rgba(255,255,255,.12); color:#fff; cursor:pointer;"
          aria-label="Close"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:22px; height:22px;"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>

        @if ($request->hasMedia('image'))
          <div x-show="lightbox === 'image'" x-data="{ scale: 1 }" style="display:flex; flex-direction:column; align-items:center; gap:12px;">
            <img
              :style="'transform: scale(' + scale + '); max-height: 85vh; max-width: 90vw; object-fit: contain; border-radius: 8px; transition: transform 0.2s;'"
              src="{{ route('vendor.requests.image', $request) }}"
              alt="Jewellery photo"
            >
            <div style="display:flex; align-items:center; gap:8px;">
              <button type="button" x-on:click="scale = Math.max(0.5, scale - 0.25)" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center; border:0; border-radius:9999px; background:rgba(255,255,255,.15); color:#fff; font-size:18px; font-weight:bold; cursor:pointer;">&minus;</button>
              <button type="button" x-on:click="scale = 1" style="border:0; border-radius:9999px; background:rgba(255,255,255,.15); padding:4px 12px; font-size:12px; color:#fff; cursor:pointer;">Reset</button>
              <button type="button" x-on:click="scale = Math.min(3, scale + 0.25)" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center; border:0; border-radius:9999px; background:rgba(255,255,255,.15); color:#fff; font-size:18px; font-weight:bold; cursor:pointer;">+</button>
            </div>
          </div>
        @endif

        @if ($request->hasMedia('video'))
          <video
            x-show="lightbox === 'video'"
            x-ref="lbPlayer"
            controls
            autoplay
            playsinline
            controlsList="nodownload"
            style="max-height:85vh; max-width:90vw; border-radius:8px; background:#000;"
          >
            <source src="{{ route('vendor.requests.video', $request) }}">
          </video>
        @endif
      </div>
    </template>
  </div>

  @if ($isOpen)
    <div class="mb-5 rounded-xl border border-line bg-white p-4 sm:p-5">
      <h2 class="mb-3 text-[13px] font-medium uppercase tracking-[0.3px] text-heading">Place your bid</h2>
      <form action="{{ route('vendor.requests.bid', $request) }}" method="post" class="flex flex-col gap-3 sm:flex-row">
        @csrf
        <input class="h-12 w-full rounded-lg sm:flex-1 border border-line-strong px-4 text-[14px] outline-none focus:border-accent focus:ring-1 focus:ring-accent" type="number" name="amount" min="1" max="1000000" step="0.01" placeholder="Your bid (₹)" required>
        <button class="h-12 shrink-0 rounded-lg bg-accent px-6 text-[12.5px] font-semibold uppercase tracking-[0.5px] text-white transition-colors hover:bg-accent-dark" type="submit">
          Submit Bid
        </button>
      </form>
    </div>

    <details class="group rounded-xl border border-line bg-white p-4 sm:p-5">
      <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-[12.5px] font-medium uppercase tracking-[0.3px] text-muted [&::-webkit-details-marker]:hidden">
        Can't offer on this one?
        <svg class="h-4 w-4 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
      </summary>
      <form class="mt-3" action="{{ route('vendor.requests.decline', $request) }}" method="post">
        @csrf
        <textarea class="mb-3 w-full rounded-lg border border-line p-3 text-[13px]" name="reason" rows="3" maxlength="1000" placeholder="Reason (optional)"></textarea>
        <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-line px-6 py-2.5 text-[12px] font-medium uppercase tracking-[0.5px] text-heading transition-colors hover:border-salebadge hover:text-salebadge sm:w-auto" type="submit">
          Decline
        </button>
      </form>
    </details>
  @elseif ($invitation->response_status === 'accepted')
    <div class="rounded-xl border border-line bg-pinksoft p-4 text-[13px] text-heading">
      You submitted a bid on {{ $invitation->responded_at?->formatIst('d M Y, h:i A') }}.
    </div>
  @elseif ($invitation->response_status === 'declined')
    <div class="rounded-xl border border-line bg-white p-4 text-[13px] text-muted">
      You declined this request{{ $invitation->decline_reason ? ' — "'.$invitation->decline_reason.'"' : '' }}.
    </div>
  @else
    <div class="rounded-xl border border-line bg-white p-4 text-[13px] text-muted">
      This bidding window has closed.
    </div>
  @endif

@endsection
