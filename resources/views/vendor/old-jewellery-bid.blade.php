@extends('layouts.vendor-minimal')

@section('meta_title', $invitation->request->request_number.' | Vendor Bid | Estele')

@section('content')

  @php
    $bidRequest = $invitation->request;
    $isOpen = $invitation->response_status === 'pending'
        && $bidRequest->status === 'bidding_active'
        && now()->lessThan($invitation->expires_at);
    $videoUrl = $bidRequest->hasMedia('video')
        ? \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'old-jewellery.vendor.video',
            now()->addMinutes(30),
            ['invitation' => $invitation->id],
        )
        : null;
  @endphp

  @if (session('success'))
    <div class="mb-4 rounded-lg border border-line bg-green-50 p-3 text-[13px] text-green-700">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="mb-4 rounded-lg border border-salebadge bg-red-50 p-3 text-[13px] text-salebadge">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div class="mb-4 rounded-lg border border-salebadge bg-red-50 p-3 text-[13px] text-salebadge">
      <ul class="list-disc space-y-1 pl-4">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <h1 class="mb-1 text-[18px] uppercase tracking-[0.4px] text-heading">{{ $bidRequest->request_number }}</h1>
  <p class="mb-4 text-[12px] text-muted">Bidding closes {{ $bidRequest->bidding_end_at?->format('d M Y, h:i A') }}</p>

  <div class="mb-5 rounded-lg border border-line p-4" x-data="{ lightbox: null }">
    @if ($bidRequest->getFirstMediaUrl('image', 'thumb'))
      <img
        style="cursor:zoom-in;"
        class="mb-3 max-h-56 w-full rounded-lg object-cover"
        src="{{ $bidRequest->getFirstMediaUrl('image', 'thumb') }}"
        alt="Jewellery photo"
        x-on:click="lightbox = 'image'"
      >
    @endif

    @if ($videoUrl)
      <div style="position:relative; cursor:pointer;" class="mb-3" x-on:click="lightbox = 'video'">
        <video style="width:100%; border-radius:8px; pointer-events:none;" preload="metadata" muted playsinline>
          <source src="{{ $videoUrl }}#t=0.1">
        </video>
        <span style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; border-radius:8px; background:rgba(0,0,0,.35);">
          <svg viewBox="0 0 24 24" fill="#fff" style="width:40px; height:40px; opacity:.9;"><path d="M8 5v14l11-7z"/></svg>
        </span>
      </div>
    @endif

    @if ($bidRequest->description)
      <p class="text-[13px] text-muted">{{ $bidRequest->description }}</p>
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

        @if ($bidRequest->getFirstMediaUrl('image', 'thumb'))
          <div x-show="lightbox === 'image'" x-data="{ scale: 1 }" style="display:flex; flex-direction:column; align-items:center; gap:12px;">
            <img
              :style="'transform: scale(' + scale + '); max-height: 85vh; max-width: 90vw; object-fit: contain; border-radius: 8px; transition: transform 0.2s;'"
              src="{{ $bidRequest->getFirstMediaUrl('image') ?: $bidRequest->getFirstMediaUrl('image', 'thumb') }}"
              alt="Jewellery photo"
            >
            <div style="display:flex; align-items:center; gap:8px;">
              <button type="button" x-on:click="scale = Math.max(0.5, scale - 0.25)" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center; border:0; border-radius:9999px; background:rgba(255,255,255,.15); color:#fff; font-size:18px; font-weight:bold; cursor:pointer;">&minus;</button>
              <button type="button" x-on:click="scale = 1" style="border:0; border-radius:9999px; background:rgba(255,255,255,.15); padding:4px 12px; font-size:12px; color:#fff; cursor:pointer;">Reset</button>
              <button type="button" x-on:click="scale = Math.min(3, scale + 0.25)" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center; border:0; border-radius:9999px; background:rgba(255,255,255,.15); color:#fff; font-size:18px; font-weight:bold; cursor:pointer;">+</button>
            </div>
          </div>
        @endif

        @if ($videoUrl)
          <video
            x-show="lightbox === 'video'"
            x-ref="lbPlayer"
            controls
            autoplay
            playsinline
            controlsList="nodownload"
            style="max-height:85vh; max-width:90vw; border-radius:8px; background:#000;"
          >
            <source src="{{ $videoUrl }}">
          </video>
        @endif
      </div>
    </template>
  </div>

  @if ($isOpen)
    <form class="mb-5 rounded-lg border border-line p-4" action="{{ route('old-jewellery.vendor.accept', request()->route('token')) }}" method="post">
      @csrf
      <label class="mb-1.5 block text-[12px] font-medium uppercase tracking-[0.3px] text-heading" for="amount">Your Bid (₹)</label>
      <input class="mb-3 w-full rounded-lg border border-line p-3 text-[14px]" type="number" id="amount" name="amount" min="1" max="1000000" step="0.01" required>
      <button class="inline-flex w-full items-center justify-center gap-2 border border-accent bg-accent px-6 py-2.5 text-[12px] font-medium uppercase tracking-[0.5px] text-white transition-colors hover:border-accent-dark hover:bg-accent-dark" type="submit">
        Submit Bid
      </button>
    </form>

    <details class="group rounded-lg border border-line p-4">
      <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-[12px] font-medium uppercase tracking-[0.3px] text-muted [&::-webkit-details-marker]:hidden">
        Can't offer on this one?
        <svg class="h-4 w-4 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
      </summary>
      <form class="mt-3" action="{{ route('old-jewellery.vendor.decline', request()->route('token')) }}" method="post">
        @csrf
        <textarea class="mb-3 w-full rounded-lg border border-line p-3 text-[13px]" name="reason" rows="3" maxlength="1000" placeholder="Reason (optional)"></textarea>
        <button class="inline-flex w-full items-center justify-center gap-2 border border-line px-6 py-2.5 text-[12px] font-medium uppercase tracking-[0.5px] text-heading transition-colors hover:border-salebadge hover:text-salebadge" type="submit">
          Decline
        </button>
      </form>
    </details>
  @elseif ($invitation->response_status === 'accepted')
    <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-[13px] text-green-700">
      You submitted a bid on {{ $invitation->responded_at?->format('d M Y, h:i A') }}.
    </div>
  @elseif ($invitation->response_status === 'declined')
    <div class="rounded-lg border border-line p-4 text-[13px] text-muted">
      You declined this request{{ $invitation->decline_reason ? ' — "'.$invitation->decline_reason.'"' : '' }}.
    </div>
  @else
    <div class="rounded-lg border border-line p-4 text-[13px] text-muted">
      This bidding window has closed.
    </div>
  @endif

@endsection
