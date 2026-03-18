@props([
  'durationText' => '',
])

<span
  @class([
    'pointer-events-none absolute right-3 top-3 z-10 rounded-full bg-black/15 px-2.5 py-1.5 text-sm font-semibold leading-none text-white backdrop-blur-sm',
    'hidden' => $durationText === '',
  ])
  data-video-duration
>{{ $durationText }}</span>
